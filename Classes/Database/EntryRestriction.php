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
 * Registered globally in ext_localconf.php under
 * `TYPO3_CONF_VARS['DB']['additionalQueryRestrictions']`, so it is appended to *every* query
 * touching `tx_ximatypo3calendar_domain_model_entry` automatically.
 *
 * An entry inherits the visibility of its parent event. Because the event table is usually
 * LEFT JOINed, TYPO3 moves the event-side restriction into the ON clause and drops the event
 * from the WHERE-clause table list, so the join alias is not reliably available here. The
 * restriction therefore checks the parent event through a correlated `EXISTS` subquery instead:
 *   - CLI: everything, never restricted;
 *   - frontend, anonymous: entries whose event has status LIVE;
 *   - frontend, logged-in user: additionally entries of events they own;
 *   - frontend, logged-in backend user: like the backend rules below, so an editor
 *     previewing a draft appointment on the website is not answered with a 404;
 *   - backend with the view_all_events permission (or admin): everything;
 *   - backend without it: entries of LIVE events plus of events they own via owner_be_user.
 *
 * Note the CLI check runs *before* the request lookup here, unlike in EventRestriction, so it
 * applies even when a request happens to be present.
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
            $backendUser = $this->getBackendUserAuthentication($request);
            if ($backendUser instanceof BackendUserAuthentication
                && $this->permissionService->canViewAllEvents($backendUser)
            ) {
                return $expressionBuilder->and();
            }

            $entryAlias = array_search('tx_ximatypo3calendar_domain_model_entry', $queriedTables, true);
            $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');
            $user = $this->getFrontendUserAuthentication();

            $unrestrictedRecordTypes = $this->getUnrestrictedRecordTypes();
            $quotedRecordTypes = array_map(static fn (string $type): string => $qb->quote($type), $unrestrictedRecordTypes);

            // The parent event is exempt from the restriction when its record type is unrestricted
            $ownerClauses = ['e.status = ' . $qb->quote(EventStatus::LIVE->value)];
            if ($user && $user->getUserId()) {
                $ownerClauses[] = 'e.owner = ' . (int)$user->getUserId();
            }
            if ($backendUser instanceof BackendUserAuthentication) {
                $ownerClauses[] = 'e.owner_be_user = ' . (int)$backendUser->getUserId();
            }
            $eventClause = count($ownerClauses) > 1
                ? '(' . implode(' OR ', $ownerClauses) . ')'
                : $ownerClauses[0];
            if ($quotedRecordTypes !== []) {
                $eventClause = '(' . $eventClause . ' OR e.record_type IN (' . implode(', ', $quotedRecordTypes) . '))';
            }

            $orConditions = [
                'EXISTS (SELECT 1 FROM tx_ximatypo3calendar_domain_model_event e WHERE e.uid = ' . $entryAlias . '.event AND e.deleted = 0 AND ' . $eventClause . ')',
            ];

            if ($quotedRecordTypes !== []) {
                $orConditions[] = $expressionBuilder->in($entryAlias . '.record_type', $quotedRecordTypes);
            }

            return $expressionBuilder->and($expressionBuilder->or(...$orConditions));
        }

        if ($applicationType->isBackend()) {
            $backendUser = $this->getBackendUserAuthentication($request);
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

            $eventClause = '(e.status = ' . $qb->quote((string)EventStatus::LIVE->value)
                . ' OR e.owner_be_user = ' . (int)$backendUser->getUserId() . ')';
            if ($quotedRecordTypes !== []) {
                $eventClause = '(' . $eventClause . ' OR e.record_type IN (' . implode(', ', $quotedRecordTypes) . '))';
            }

            $orConditions = [
                'EXISTS (SELECT 1 FROM tx_ximatypo3calendar_domain_model_event e WHERE e.uid = ' . $entryAlias . '.event AND e.deleted = 0 AND ' . $eventClause . ')',
            ];

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
     * The backend user is available in the frontend as well: the frontend
     * BackendUserAuthenticator middleware authenticates an existing backend
     * session and exposes it as the `backend.user` request attribute.
     */
    private function getBackendUserAuthentication(ServerRequestInterface $request): ?BackendUserAuthentication
    {
        $backendUser = $request->getAttribute('backend.user') ?? $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication || !$backendUser->getUserId()) {
            return null;
        }

        return $backendUser;
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
