<?php

namespace Xima\XimaTypo3Calendar\Database;

use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EnforceableQueryRestrictionInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Api\EventStatus;

class EntryRestriction implements QueryRestrictionInterface, EnforceableQueryRestrictionInterface
{
    public function isEnforced(): bool
    {
        return true;
    }

    public function buildExpression(array $queriedTables, ExpressionBuilder $expressionBuilder): CompositeExpression
    {
        if (!in_array('tx_ximatypo3calendar_domain_model_entry', $queriedTables, true)) {
            return $expressionBuilder->and();
        }

        // CLI and backend contexts see all entries
        if (isset($GLOBALS['BE_USER']) && $GLOBALS['BE_USER'] instanceof CommandLineUserAuthentication) {
            return $expressionBuilder->and();
        }

        // @TODO: Check if the user has access to the event module
        if (isset($GLOBALS['BE_USER'])) {
            return $expressionBuilder->and();
        }

        // The event table is LEFT JOINed, so TYPO3 moves its restrictions to the ON clause
        // and excludes it from $queriedTables for the WHERE clause. Use a correlated subquery
        // to check the event status without relying on the join alias being available here.
        $entryAlias = array_search('tx_ximatypo3calendar_domain_model_entry', $queriedTables, true);
        $qb = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');
        // eq() quotes the first argument as a column identifier, so build the subquery comparison as a raw string
        return $expressionBuilder->and(
            '(SELECT status FROM tx_ximatypo3calendar_domain_model_event WHERE uid = ' . $entryAlias . '.event AND deleted = 0) = ' . $qb->quote(EventStatus::LIVE->value)
        );
    }
}
