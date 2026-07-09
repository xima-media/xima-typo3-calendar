<?php

namespace Xima\XimaTypo3Calendar\Database;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EnforceableQueryRestrictionInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;

/**
 * Enforced query restriction for the entry (appointment) table.
 *
 * Registered globally via {@see TYPO3_CONF_VARS['DB']['additionalQueryRestrictions']} in ext_localconf.php,
 * so it is appended to *every* query touching `tx_ximatypo3calendar_domain_model_entry` automatically.
 *
 * An entry inherits the visibility of its parent event. Because the event table is usually
 * LEFT JOINed, TYPO3 moves the event-side restriction into the ON clause and drops the event
 * from the WHERE-clause table list, so the join alias is not reliably available here. The
 * restriction therefore checks the parent event through a correlated `EXISTS` subquery instead:
 *   - anonymous visitors only see entries whose event has status LIVE;
 *   - a logged-in frontend user additionally sees entries of events they own;
 *   - CLI and backend requests are never restricted.
 *
 * Per-record-type opt-out: record types listed in the extension setting
 * `restrictions.unrestrictedRecordTypes` (comma-separated) are exempt. An entry stays visible
 * when *either* its own record type *or* its parent event's record type is unrestricted. This
 * keeps entries of externally managed event record types (e.g. a "private-event") visible
 * regardless of the parent event's status/owner.
 *
 * @see EventRestriction the sibling restriction for the event table, which shares the same setting
 */
#[Autoconfigure(public: true)]
class EntryRestriction implements QueryRestrictionInterface, EnforceableQueryRestrictionInterface
{
    public function __construct(
        protected Context $context,
        protected ConnectionPool $connectionPool,
        protected ExtensionConfiguration $extensionConfiguration,
        protected CalendarPermissionService $permissionService,
    ) {
    }

    public function isEnforced(): bool
    {
        return true;
    }

    public function buildExpression(array $queriedTables, ExpressionBuilder $expressionBuilder): CompositeExpression
    {
        if (!in_array('tx_ximatypo3calendar_domain_model_entry', $queriedTables, true)) {
            return $expressionBuilder->and();
        }

        // In CLI context, we do not restrict entries
        if (isset($GLOBALS['BE_USER']) && $GLOBALS['BE_USER'] instanceof CommandLineUserAuthentication) {
            return $expressionBuilder->and();
        }

        // In rare cases (like routeEnhancer resolving) no global request is available
        $request = $this->getRequest();
        if (!$request instanceof ServerRequestInterface) {
            return $expressionBuilder->and();
        }

        $applicationType = ApplicationType::fromRequest($request);

        if ($applicationType->isFrontend()) {
            // The event table is LEFT JOINed, so TYPO3 moves its restrictions to the ON clause
            // and excludes it from $queriedTables for the WHERE clause. Use a correlated subquery
            // to check the event status/owner without relying on the join alias being available here.
            $entryAlias = array_search('tx_ximatypo3calendar_domain_model_entry', $queriedTables, true);
            $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');
            $user = $this->getFrontendUserAuthentication();

            $unrestrictedRecordTypes = $this->getUnrestrictedRecordTypes();
            $quotedRecordTypes = array_map(static fn (string $type): string => $qb->quote($type), $unrestrictedRecordTypes);

            // The parent event is exempt from the restriction when its record type is unrestricted
            $eventClause = $user && $user->getUserId()
                ? '(e.status = ' . $qb->quote(EventStatus::LIVE->value) . ' OR e.owner = ' . (int)$user->getUserId() . ')'
                : 'e.status = ' . $qb->quote(EventStatus::LIVE->value);
            if ($quotedRecordTypes !== []) {
                $eventClause = '(' . $eventClause . ' OR e.record_type IN (' . implode(', ', $quotedRecordTypes) . '))';
            }

            $orConditions = [
                'EXISTS (SELECT 1 FROM tx_ximatypo3calendar_domain_model_event e WHERE e.uid = ' . $entryAlias . '.event AND e.deleted = 0 AND ' . $eventClause . ')',
            ];

            // Entries of configured record types are exempt from the visibility restriction
            if ($quotedRecordTypes !== []) {
                $orConditions[] = $expressionBuilder->in($entryAlias . '.record_type', $quotedRecordTypes);
            }

            return $expressionBuilder->and($expressionBuilder->or(...$orConditions));
        }

        if ($applicationType->isBackend()) {
            $backendUser = $GLOBALS['BE_USER'] ?? null;
            if (!$backendUser instanceof BackendUserAuthentication) {
                return $expressionBuilder->and();
            }

            // Users allowed to see all events (and administrators) are unrestricted.
            if ($this->permissionService->canViewAllEvents($backendUser)) {
                return $expressionBuilder->and();
            }

            $entryAlias = array_search('tx_ximatypo3calendar_domain_model_entry', $queriedTables, true);
            $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');

            $unrestrictedRecordTypes = $this->getUnrestrictedRecordTypes();
            $quotedRecordTypes = array_map(static fn (string $type): string => $qb->quote($type), $unrestrictedRecordTypes);

            // Only entries of live events and events the backend user owns stay visible.
            $eventClause = '(e.status = ' . $qb->quote((string)EventStatus::LIVE->value)
                . ' OR e.owner_be_user = ' . (int)$backendUser->getUserId() . ')';
            if ($quotedRecordTypes !== []) {
                $eventClause = '(' . $eventClause . ' OR e.record_type IN (' . implode(', ', $quotedRecordTypes) . '))';
            }

            $orConditions = [
                'EXISTS (SELECT 1 FROM tx_ximatypo3calendar_domain_model_event e WHERE e.uid = ' . $entryAlias . '.event AND e.deleted = 0 AND ' . $eventClause . ')',
            ];

            // Entries of configured record types are exempt from the visibility restriction
            if ($quotedRecordTypes !== []) {
                $orConditions[] = $expressionBuilder->in($entryAlias . '.record_type', $quotedRecordTypes);
            }

            return $expressionBuilder->and($expressionBuilder->or(...$orConditions));
        }

        return $expressionBuilder->and();
    }

    private function getFrontendUserAuthentication(): ?FrontendUserAuthentication
    {
        return $this->getRequest()?->getAttribute('frontend.user') ?? null;
    }

    /**
     * @return list<string>
     */
    private function getUnrestrictedRecordTypes(): array
    {
        try {
            $configured = $this->extensionConfiguration->get('xima_typo3_calendar', 'restrictions/unrestrictedRecordTypes');
        } catch (ExtensionConfigurationExtensionNotConfiguredException | ExtensionConfigurationPathDoesNotExistException) {
            return [];
        }
        if (!is_string($configured) || $configured === '') {
            return [];
        }

        return array_values(GeneralUtility::trimExplode(',', $configured, true));
    }

    private function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}
