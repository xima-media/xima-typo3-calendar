<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

final class CalendarPageConfigurationService
{
    private const DEFAULT_START_TIME = '09:00';
    private const DEFAULT_END_TIME = '09:30';
    private const DEFAULT_ALL_DAY = false;
    private const DEFAULT_ENABLE_DRAG = true;
    private const DEFAULT_ENABLE_CLICK = true;

    /** @var array<int, array<string, mixed>> */
    private array $pageTsConfig = [];

    public function isEventPreviewEnabled(RequestInterface $request): bool
    {
        return $this->isEnabled(
            $this->getCalendarPageTsConfig($request)['enableEventPreview'] ?? null,
            false,
        );
    }

    /** @return array{enableDragNewEvent: bool, enableClickNewEvent: bool, defaultStartTime: string, defaultEndTime: string, defaultAllDay: bool} */
    public function getNewEventConfiguration(RequestInterface $request): array
    {
        $config = $this->getCalendarPageTsConfig($request);
        $newEvent = $this->asArray($config['newEvent.'] ?? $config['newEvent'] ?? []);
        $interaction = $this->asArray($newEvent['interaction.'] ?? $newEvent['interaction'] ?? []);
        $defaults = $this->asArray($newEvent['defaults.'] ?? $newEvent['defaults'] ?? []);

        return [
            'enableDragNewEvent' => $this->isEnabled(
                $interaction['enableDrag'] ?? null,
                self::DEFAULT_ENABLE_DRAG,
            ),
            'enableClickNewEvent' => $this->isEnabled(
                $interaction['enableClick'] ?? null,
                self::DEFAULT_ENABLE_CLICK,
            ),
            'defaultStartTime' => $this->getValidTime(
                $defaults['startTime'] ?? null,
                self::DEFAULT_START_TIME,
            ),
            'defaultEndTime' => $this->getValidTime(
                $defaults['endTime'] ?? null,
                self::DEFAULT_END_TIME,
            ),
            'defaultAllDay' => $this->isEnabled(
                $defaults['allDay'] ?? null,
                self::DEFAULT_ALL_DAY,
            ),
        ];
    }

    private function getValidTime(mixed $value, string $default): string
    {
        if (!is_string($value) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return $default;
        }

        return $value;
    }

    private function isEnabled(mixed $value, bool $default): bool
    {
        return (int)($value ?? ($default ? 1 : 0)) === 1;
    }

    /** @return array<string, mixed> */
    private function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** @return array<string, mixed> */
    private function getCalendarPageTsConfig(RequestInterface $request): array
    {
        $pageId = (int)($request->getQueryParams()['id'] ?? 0);
        if (!array_key_exists($pageId, $this->pageTsConfig)) {
            $pageTsConfig = BackendUtility::getPagesTSconfig($pageId);
            $this->pageTsConfig[$pageId] = is_array($pageTsConfig['mod.']['tx_ximatypo3calendar.'] ?? null)
                ? $pageTsConfig['mod.']['tx_ximatypo3calendar.']
                : [];
        }

        return $this->pageTsConfig[$pageId];
    }
}
