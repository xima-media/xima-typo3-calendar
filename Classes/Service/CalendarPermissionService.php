<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Central place for the calendar-specific backend permissions.
 *
 * The permissions are registered as custom permission options in ext_tables.php
 * (`$GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions']`) and can be granted
 * per backend group in the "Access Lists" of a group. Administrators implicitly
 * pass every check.
 */
final class CalendarPermissionService
{
    public const PERMISSION_GROUP = 'tx_ximatypo3calendar_permissions';

    /** Grants publishing events (setting the LIVE status). */
    public const PERMISSION_PUBLISH_LIVE_EVENTS = 'publish_live_events';

    /** Grants seeing every draft/review/rejected event, not only the own ones. */
    public const PERMISSION_VIEW_ALL_EVENTS = 'view_all_events';

    public function canPublishLiveEvents(?BackendUserAuthentication $backendUser = null): bool
    {
        return $this->checkCustomOption(self::PERMISSION_PUBLISH_LIVE_EVENTS, $backendUser);
    }

    public function canViewAllEvents(?BackendUserAuthentication $backendUser = null): bool
    {
        return $this->checkCustomOption(self::PERMISSION_VIEW_ALL_EVENTS, $backendUser);
    }

    private function checkCustomOption(string $option, ?BackendUserAuthentication $backendUser): bool
    {
        $backendUser ??= $this->getBackendUser();
        if (!$backendUser instanceof BackendUserAuthentication) {
            return false;
        }

        // BackendUserAuthentication::check() returns true for administrators.
        return $backendUser->check('custom_options', self::PERMISSION_GROUP . ':' . $option);
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        return $backendUser instanceof BackendUserAuthentication ? $backendUser : null;
    }
}
