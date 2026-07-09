<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\ViewHelpers\Backend;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;

/**
 * Returns whether the current backend user may publish events (set them LIVE).
 * Used by the record-list status partial to hide the LIVE option in the modal.
 */
final class CanPublishLiveEventsViewHelper extends AbstractViewHelper
{
    public function render(): bool
    {
        return GeneralUtility::makeInstance(CalendarPermissionService::class)->canPublishLiveEvents();
    }
}
