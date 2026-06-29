<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use Xima\XimaTypo3Calendar\Domain\Repository\CalendarRepository;
use Xima\XimaTypo3Calendar\Domain\Repository\EntryRepository;
use Xima\XimaTypo3Calendar\Serializer\VkurkoCalendarSerializer;

class CalendarController extends ActionController
{
    public function __construct(
        protected ConnectionPool $connectionPool,
        protected IconFactory $iconFactory,
        protected PageRenderer $pageRenderer,
        protected UriBuilder $backendUriBuilder,
        protected ContainerInterface $container,
        protected ModuleTemplateFactory $moduleTemplateFactory,
        protected ResourceFactory $resourceFactory,
        protected CalendarRepository $calendarRepository,
        protected EntryRepository $entryRepository
    ) {
    }

    public function processRequest(RequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $ajaxUrl = (string)$this->backendUriBuilder->buildUriFromRoute('ajax_xima_calendar_events');

        $this->pageRenderer->loadJavaScriptModule('@xima/xima-typo3-calendar/calendar.js');

        $moduleTemplate->assignMultiple([
            'ajaxUrl' => $ajaxUrl,
        ]);

        return $moduleTemplate->renderResponse('Backend/Calendar');
    }

    public function eventsAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();

        $start = $params['start'] ?? null;
        $end = $params['end'] ?? null;
        $startDatetime = $start ? (new \DateTime($start))->getTimestamp() : 0;
        $endDatetime = $end ? (new \DateTime($end))->getTimestamp() : (new \DateTime('9999-12-31 23:59:59'))->getTimestamp();

        $calendarUids = array_map('intval', (array)($params['calendars'] ?? []));

        $rows = $this->entryRepository->getBackendCalendarEntries($startDatetime, $endDatetime, $calendarUids);

        $events = VkurkoCalendarSerializer::serializeBackendEntries($rows);

        return new JsonResponse($events);
    }
}
