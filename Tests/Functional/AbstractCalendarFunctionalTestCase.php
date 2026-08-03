<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional;

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Shared base for the calendar functional tests.
 *
 * `t3api` and `xima_typo3_recordlist` are declared as hard requirements in
 * ext_emconf-equivalent metadata, so the package manager refuses to activate
 * xima_typo3_calendar without them — they must be linked into the test instance
 * even though their own surfaces are not exercised here.
 *
 * The calendar tables are not declared in ext_tables.sql; they are derived from
 * the TCA in Configuration/TCA by the schema analyzer, so no additional SQL is
 * needed.
 */
abstract class AbstractCalendarFunctionalTestCase extends FunctionalTestCase
{
    protected const TABLE_EVENT = 'tx_ximatypo3calendar_domain_model_event';
    protected const TABLE_ENTRY = 'tx_ximatypo3calendar_domain_model_entry';
    protected const TABLE_CALENDAR = 'tx_ximatypo3calendar_domain_model_calendar';
    protected const TABLE_LOCATION = 'tx_ximatypo3calendar_domain_model_location';
    protected const TABLE_REQUIREMENT = 'tx_ximatypo3calendar_domain_model_requirement';
    protected const TABLE_REQUIREMENT_BOOKING = 'tx_ximatypo3calendar_domain_model_requirementbooking';

    protected array $coreExtensionsToLoad = [
        'dashboard',
    ];

    protected array $testExtensionsToLoad = [
        't3api',
        'xima_typo3_recordlist',
        'xima_typo3_calendar',
        // Relative to the docroot the bootstrap chdir()s into, not to the repo root.
        '../Tests/Functional/Fixtures/Extensions/calendar_test',
    ];

    /**
     * The extension configuration must be present in TYPO3_CONF_VARS *before* the
     * DI container is compiled: Configuration/Services.php reads
     * `features/requirementsManagement` while the container is still being built,
     * and at that point ExtensionConfiguration cannot fall back to synchronising
     * from ext_conf_template.txt because the PackageManager singleton does not
     * exist yet. Pre-seeding every path declared in ext_conf_template.txt keeps
     * ExtensionConfiguration on its cheap read-only path.
     *
     * @var array<string, mixed>
     */
    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
            'xima_typo3_calendar' => [
                'notifications' => [
                    'parentCategory' => '',
                ],
                'restrictions' => [
                    'unrestrictedRecordTypes' => '',
                ],
                'features' => [
                    'requirementsManagement' => '0',
                ],
            ],
        ],
    ];

    /**
     * Reads a single record with all restrictions removed — the enforced
     * Event/EntryRestriction would otherwise filter the very rows a test wants to
     * assert on.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchRawRecord(string $table, int $uid): ?array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();

        $row = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }
}
