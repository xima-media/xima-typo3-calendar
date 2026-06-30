<?php

namespace Xima\XimaTypo3Calendar\Database;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
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
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;

/**
 * Enforced query restriction for the event table.
 *
 * Registered globally via {@see TYPO3_CONF_VARS['DB']['additionalQueryRestrictions']} in ext_localconf.php,
 * so it is appended to *every* query touching `tx_ximatypo3calendar_domain_model_event` automatically.
 *
 * The restriction limits which events are visible in the frontend:
 *   - anonymous visitors only see events with status LIVE;
 *   - a logged-in frontend user additionally sees the events they own;
 *   - CLI and backend requests are never restricted.
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

        $applicationType = ApplicationType::fromRequest($this->getRequest());

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

            // Events of configured record types are exempt from the visibility restriction
            $unrestrictedRecordTypes = $this->getUnrestrictedRecordTypes();
            if ($unrestrictedRecordTypes !== []) {
                $orConditions[] = $expressionBuilder->in(
                    $eventAlias . '.record_type',
                    array_map(static fn (string $type): string => $qb->quote($type), $unrestrictedRecordTypes)
                );
            }

            return $expressionBuilder->and($expressionBuilder->or(...$orConditions));
        }

        // @TODO: Check if the user has access to the event module
        if ($applicationType->isBackend()) {
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

        return array_values(array_filter(array_map('trim', explode(',', $configured)), static fn (string $type): bool => $type !== ''));
    }

    private function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
