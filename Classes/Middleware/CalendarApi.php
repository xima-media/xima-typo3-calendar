<?php

namespace Xima\XimaTypo3Calendar\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Xima\XimaTypo3Calendar\Domain\Repository\EntryRepository;
use Xima\XimaTypo3Calendar\Serializer\VkurkoCalendarSerializer;

class CalendarApi implements MiddlewareInterface
{
    public function __construct(protected EntryRepository $entryRepository)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $params = $request->getQueryParams();
        $type = (int)($params['type'] ?? null);
        if ($type !== 1778227923) {
            return $handler->handle($request);
        }

        $GLOBALS['TYPO3_REQUEST'] ??= $request;

        $start = $params['start'] ?? null;
        $end = $params['end'] ?? null;
        $startDatetime = $start ? (new \DateTime($start))->getTimestamp() : 0;
        $endDatetime = $end ? (new \DateTime($end))->getTimestamp() : 99999999999;

        $calendarUids = array_map('intval', (array)($params['calendars'] ?? []));

        $rows = $this->entryRepository->getBackendCalendarEntries($startDatetime, $endDatetime, $calendarUids);

        $events = VkurkoCalendarSerializer::serializeBackendEntries($rows);

        return new JsonResponse($events);
    }
}
