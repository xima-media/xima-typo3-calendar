<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use Xima\XimaTypo3Calendar\Service\EventSearchService;

final class CalendarEventSearchController
{
    public function __construct(
        private readonly EventSearchService $eventSearchService,
    ) {
    }

    public function searchAction(ServerRequestInterface $request): ResponseInterface
    {
        $searchTerm = $request->getQueryParams()['q'] ?? '';

        return new JsonResponse([
            'results' => is_string($searchTerm) ? $this->eventSearchService->search($searchTerm) : [],
        ]);
    }
}
