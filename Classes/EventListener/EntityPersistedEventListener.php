<?php

namespace Xima\XimaTypo3Calendar\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use SourceBroker\T3api\Routing\Enhancer\ResourceEnhancer;
use SourceBroker\T3api\Service\RouteService;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Event\Persistence\EntityPersistedEvent;
use Xima\XimaTypo3Calendar\Domain\Model\Api\DraftStatus;
use Xima\XimaTypo3Calendar\Domain\Model\Api\EventAppointment;

#[AsEventListener(
    identifier: 'xima-typo3-calendar/sync-event-status',
)]
class EntityPersistedEventListener
{
    public function __invoke(EntityPersistedEvent $event): void
    {
        $object = $event->getObject();
        if (!$object instanceof EventAppointment || $object->getStatus() !== DraftStatus::DRAFT) {
            return;
        }

        if (!$this->isEventReviewPatchViaT3Api()) {
            return;
        }

        // Note: Update table name if draft table structure changes
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_event');
        $queryBuilder->update('tx_ximatypo3calendar_domain_model_event')
            ->set('status', 1)
            ->where($queryBuilder->expr()->eq('uid', $object->getUid()))
            ->executeStatement();
    }

    private function isEventReviewPatchViaT3Api(): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface || $request->getMethod() !== 'PATCH') {
            return false;
        }

        if (!RouteService::routeHasT3ApiResourceEnhancerQueryParam($request)) {
            return false;
        }

        $requestedPath = $request->getQueryParams()[ResourceEnhancer::PARAMETER_NAME] ?? '';
        return str_starts_with($requestedPath, 'events/') && str_ends_with($requestedPath, '/review');
    }
}
