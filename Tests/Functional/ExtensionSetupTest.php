<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Xima\XimaTypo3Calendar\Backend\FormDataProvider\LiveEventReadOnlyForNonPublisher;
use Xima\XimaTypo3Calendar\Database\EntryRestriction;
use Xima\XimaTypo3Calendar\Database\EventRestriction;
use Xima\XimaTypo3Calendar\Hooks\DataHandlerEventDispatcherHook;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;

/**
 * Guards the wiring in ext_localconf.php / ext_tables.php and the TCA-derived
 * database schema. Everything asserted here is a precondition the other
 * functional tests silently rely on.
 */
final class ExtensionSetupTest extends AbstractCalendarFunctionalTestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function tableProvider(): array
    {
        return [
            'event'               => [self::TABLE_EVENT],
            'entry'               => [self::TABLE_ENTRY],
            'calendar'            => [self::TABLE_CALENDAR],
            'location'            => [self::TABLE_LOCATION],
            'requirement'         => [self::TABLE_REQUIREMENT],
            'requirement booking' => [self::TABLE_REQUIREMENT_BOOKING],
        ];
    }

    /**
     * The tables are not declared in ext_tables.sql — they are derived from the
     * TCA in Configuration/TCA. If that derivation ever breaks, every other
     * functional test fails with a confusing SQL error instead of this one.
     */
    #[Test]
    #[DataProvider('tableProvider')]
    public function tableIsCreatedFromTca(string $table): void
    {
        $schemaManager = $this->getConnectionPool()->getConnectionForTable($table)->createSchemaManager();

        self::assertContains($table, $schemaManager->listTableNames());
    }

    #[Test]
    #[DataProvider('tableProvider')]
    public function tableIsRegisteredInTca(string $table): void
    {
        self::assertArrayHasKey($table, $GLOBALS['TCA']);
    }

    #[Test]
    public function bothQueryRestrictionsAreRegisteredGlobally(): void
    {
        $restrictions = $GLOBALS['TYPO3_CONF_VARS']['DB']['additionalQueryRestrictions'] ?? [];

        self::assertArrayHasKey(EventRestriction::class, $restrictions);
        self::assertArrayHasKey(EntryRestriction::class, $restrictions);
    }

    #[Test]
    public function theQueryRestrictionsAreEnforced(): void
    {
        self::assertTrue($this->get(EventRestriction::class)->isEnforced());
        self::assertTrue($this->get(EntryRestriction::class)->isEnforced());
    }

    #[Test]
    public function theEventDispatcherHookIsRegisteredForBothDatamapAndCmdmap(): void
    {
        $scOptions = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php'];

        self::assertContains(DataHandlerEventDispatcherHook::class, $scOptions['processDatamapClass']);
        self::assertContains(DataHandlerEventDispatcherHook::class, $scOptions['processCmdmapClass']);
    }

    #[Test]
    public function theReadOnlyFormDataProviderIsRegistered(): void
    {
        $formDataGroup = $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['formDataGroup']['tcaDatabaseRecord'];

        self::assertArrayHasKey(LiveEventReadOnlyForNonPublisher::class, $formDataGroup);
    }

    #[Test]
    public function bothCustomPermissionOptionsAreRegistered(): void
    {
        $items = $GLOBALS['TYPO3_CONF_VARS']['BE']['customPermOptions'][CalendarPermissionService::PERMISSION_GROUP]['items'] ?? [];

        self::assertArrayHasKey(CalendarPermissionService::PERMISSION_PUBLISH_LIVE_EVENTS, $items);
        self::assertArrayHasKey(CalendarPermissionService::PERMISSION_VIEW_ALL_EVENTS, $items);
    }

    #[Test]
    public function theNotificationPreferenceColumnsAreAddedToBeUsers(): void
    {
        $columns = $GLOBALS['TCA']['be_users']['columns'];

        self::assertArrayHasKey('tx_ximatypo3calendar_notify_review', $columns);
        self::assertArrayHasKey('tx_ximatypo3calendar_notify_live', $columns);
        self::assertArrayHasKey('tx_ximatypo3calendar_notify_categories', $columns);
    }

    #[Test]
    public function theRequirementsManagementFeatureFlagDefaultsToDisabled(): void
    {
        self::assertFalse($GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['ximaTypo3Calendar.requirementsManagement']);
    }
}
