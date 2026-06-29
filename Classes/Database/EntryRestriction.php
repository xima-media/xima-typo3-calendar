<?php

namespace Xima\XimaTypo3Calendar\Database;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EnforceableQueryRestrictionInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;

#[Autoconfigure(public: true)]
class EntryRestriction implements QueryRestrictionInterface, EnforceableQueryRestrictionInterface
{
    public function __construct(protected Context $context, protected ConnectionPool $connectionPool)
    {
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

        $applicationType = ApplicationType::fromRequest($this->getRequest());

        if ($applicationType->isFrontend()) {
            // The event table is LEFT JOINed, so TYPO3 moves its restrictions to the ON clause
            // and excludes it from $queriedTables for the WHERE clause. Use a correlated subquery
            // to check the event status/owner without relying on the join alias being available here.
            $entryAlias = array_search('tx_ximatypo3calendar_domain_model_entry', $queriedTables, true);
            $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');
            $user = $this->getFrontendUserAuthentication();
            if ($user && $user->getUserId()) {
                return $expressionBuilder->and(
                    'EXISTS (SELECT 1 FROM tx_ximatypo3calendar_domain_model_event e WHERE e.uid = ' . $entryAlias . '.event AND e.deleted = 0 AND (e.status = ' . $qb->quote(EventStatus::LIVE->value) . ' OR e.owner = ' . (int)$user->getUserId() . '))'
                );
            }
            return $expressionBuilder->and(
                'EXISTS (SELECT 1 FROM tx_ximatypo3calendar_domain_model_event e WHERE e.uid = ' . $entryAlias . '.event AND e.deleted = 0 AND e.status = ' . $qb->quote(EventStatus::LIVE->value) . ')'
            );
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

    private function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
