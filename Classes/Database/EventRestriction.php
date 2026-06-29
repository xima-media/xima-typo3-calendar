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
use Xima\XimaTypo3Calendar\Domain\Model\Api\EventStatus;

#[Autoconfigure(public: true)]
class EventRestriction implements QueryRestrictionInterface, EnforceableQueryRestrictionInterface
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
        if (!in_array('tx_ximatypo3calendar_domain_model_event', $queriedTables, true)) {
            return $expressionBuilder->and();
        }

        $applicationType = ApplicationType::fromRequest($this->getRequest());

        // In CLI context, we do not restrict events
        if (isset($GLOBALS['BE_USER']) && $GLOBALS['BE_USER'] instanceof CommandLineUserAuthentication) {
            return $expressionBuilder->and();
        }

        if ($applicationType->isFrontend()) {
            $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_event');
            $user = $this->getFrontendUserAuthentication();
            if ($user && $user->getUserId()) {
                return $expressionBuilder->and(
                    $expressionBuilder->or(
                        $expressionBuilder->eq('status', $qb->quote(EventStatus::LIVE->value)),
                        $expressionBuilder->eq('owner', $user->getUserId())
                    )
                );
            }
            return $expressionBuilder->and($expressionBuilder->eq('status', $qb->quote(EventStatus::LIVE->value)));
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
