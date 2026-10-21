<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Xima\XimaTypo3Calendar\Service\CalendarFilterService;
use Xima\XimaTypo3Calendar\Service\CalendarPageConfigurationService;

final class CalendarFilterController
{
    private const LANGUAGE_FILE = 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_mod_calendar.xlf:';

    public function __construct(
        private readonly CalendarFilterService $filterService,
        private readonly CalendarPageConfigurationService $pageConfigurationService,
    ) {
    }

    public function optionsAction(ServerRequestInterface $request): ResponseInterface
    {
        $pageId = $this->getPageId($request);

        return new JsonResponse([
            'success' => true,
            'filters' => $this->filterService->getFilters($pageId),
            'filterColors' => $this->pageConfigurationService->getFilterColors($request),
            'state' => $this->filterService->getState($pageId),
            'labels' => [
                'moreActions' => $this->getLanguageLabel('filter.moreActions', 'More actions'),
                'selectAllChildren' => $this->getLanguageLabel(
                    'filter.selectAllChildren',
                    'Select all nested ###name###',
                ),
                'deselectAllChildren' => $this->getLanguageLabel(
                    'filter.deselectAllChildren',
                    'Deselect all nested ###name###',
                ),
            ],
        ]);
    }

    private function getLanguageLabel(string $key, string $fallback): string
    {
        $label = LocalizationUtility::translate(
            self::LANGUAGE_FILE . $key,
            'xima_typo3_calendar',
        );

        return $label ?? $fallback;
    }

    public function saveStateAction(ServerRequestInterface $request): ResponseInterface
    {
        $state = $this->getRequestBody($request);
        if ($state === null) {
            return new JsonResponse(['success' => false], 400);
        }

        $pageId = $this->getPageId($request);
        $this->filterService->saveState($state, $pageId);

        return new JsonResponse(['success' => true]);
    }

    public function saveViewAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->getRequestBody($request);
        if (!is_string($body['view'] ?? null)) {
            return new JsonResponse(['success' => false], 400);
        }

        $pageId = $this->getPageId($request);
        $this->filterService->saveView($body['view'], $pageId);

        return new JsonResponse(['success' => true]);
    }

    private function getPageId(ServerRequestInterface $request): int
    {
        return (int)($request->getQueryParams()['id'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    private function getRequestBody(ServerRequestInterface $request): ?array
    {
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && $parsedBody !== []) {
            return $parsedBody;
        }

        $decodedBody = json_decode((string)$request->getBody(), true);

        return is_array($decodedBody) ? $decodedBody : null;
    }
}
