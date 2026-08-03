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
 * Enforced query restriction for the event table.
 *
 * Registered globally in ext_localconf.php under
 * `TYPO3_CONF_VARS['DB']['additionalQueryRestrictions']`, so it is appended to *every* query
 * touching `tx_ximatypo3calendar_domain_model_event` automatically.
 *
 * Visible events per context:
 *   - CLI: everything, never restricted;
 *   - frontend, anonymous: status LIVE only;
 *   - frontend, logged-in user: additionally the events they own;
 *   - backend with the view_all_events permission (or admin): everything;
 *   - backend without it: LIVE events plus the events they own via owner_be_user.
 *
 * Per-record-type opt-out: record types listed in the extension setting
 * `restrictions.unrestrictedRecordTypes` (comma-separated) are exempt from the
 * status/owner check and stay visible regardless of their state. This lets other
 * projects introduce their own event record types (e.g. a "private-event" managed
 * by separate access rules) without being filtered out by this restriction.
 *
 * @see EntryRestriction the sibling restriction for the entry (appointment) table
 */
#[Autoconfigure(public: true)]
class EventRestriction implements QueryRestrictionInterface, EnforceableQueryRestrictionInterface
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
        if (!in_array('tx_ximatypo3calendar_domain_model_event', $queriedTables, true)) {
            return $expressionBuilder->and();
        }

        // In rare cases (like routeEnhancer resolving) no global request is available
        $request = $this->getRequest();
        if (!$request instanceof ServerRequestInterface) {
            return $expressionBuilder->and();
        }

        $applicationType = ApplicationType::fromRequest($request);

        // In CLI context, we do not restrict events
        if (isset($GLOBALS['BE_USER']) && $GLOBALS['BE_USER'] instanceof CommandLineUserAuthentication) {
            return $expressionBuilder->and();
        }

        if ($applicationType->isFrontend()) {
            $eventAlias = array_search('tx_ximatypo3calendar_domain_model_event', $queriedTables, true);
            $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_event');
            $user = $this->getFrontendUserAuthentication();

            $orConditions = [
                $expressionBuilder->eq($eventAlias . '.status', $qb->quote(EventStatus::LIVE->value)),
            ];
            if ($user && $user->getUserId()) {
                $orConditions[] = $expressionBuilder->eq($eventAlias . '.owner', $user->getUserId());
            }

            $unrestrictedRecordTypes = $this->getUnrestrictedRecordTypes();
            if ($unrestrictedRecordTypes !== []) {
                $orConditions[] = $expressionBuilder->in(
                    $eventAlias . '.record_type',
                    array_map(static fn (string $type): string => $qb->quote($type), $unrestrictedRecordTypes)
                );
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

            $eventAlias = array_search('tx_ximatypo3calendar_domain_model_event', $queriedTables, true);
            $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_event');

            $orConditions = [
                $expressionBuilder->eq($eventAlias . '.status', $qb->quote((string)EventStatus::LIVE->value)),
                $expressionBuilder->eq($eventAlias . '.owner_be_user', (int)$backendUser->getUserId()),
            ];

            $unrestrictedRecordTypes = $this->getUnrestrictedRecordTypes();
            if ($unrestrictedRecordTypes !== []) {
                $orConditions[] = $expressionBuilder->in(
                    $eventAlias . '.record_type',
                    array_map(static fn (string $type): string => $qb->quote($type), $unrestrictedRecordTypes)
                );
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
