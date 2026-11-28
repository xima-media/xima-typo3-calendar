<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\ViewHelpers\Backend;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\EventWorkflowPolicy;

/**
 * Returns the status modal options of an event: its current status plus every status the
 * EventWorkflowPolicy lets the current backend user move it to. A single item means no
 * transition is allowed.
 *
 *     <f:variable name="statusItems" value="{xtc:backend.eventStatusItems(event: item)}" />
 */
final class EventStatusItemsViewHelper extends AbstractViewHelper
{
    private const LABELS = 'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:status.items.';

    public function initializeArguments(): void
    {
        $this->registerArgument('event', 'array', 'The raw event row', true);
    }

    /**
     * @return list<array{value: int, label: string, description: string}>
     */
    public function render(): array
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return [];
        }

        $event = (array)$this->arguments['event'];
        $policy = GeneralUtility::makeInstance(EventWorkflowPolicy::class);
        $offered = [$policy->statusOf($event), ...$policy->allowedTargetStatuses($backendUser, $event)];

        $items = [];
        foreach (EventStatus::cases() as $status) {
            if (in_array($status, $offered, true)) {
                $items[] = [
                    'value' => $status->value,
                    'label' => $this->translate($status->value . '.label'),
                    'description' => $this->translate($status->value . '.description'),
                ];
            }
        }

        return $items;
    }

    private function translate(string $key): string
    {
        $languageService = $GLOBALS['LANG'] ?? null;

        return $languageService instanceof LanguageService ? $languageService->sL(self::LABELS . $key) : '';
    }
}
