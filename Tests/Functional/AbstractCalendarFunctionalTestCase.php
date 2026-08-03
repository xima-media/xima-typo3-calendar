<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional;

use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\EnforceableQueryRestrictionInterface;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Xima\XimaTypo3Calendar\Database\EntryRestriction;
use Xima\XimaTypo3Calendar\Database\EventRestriction;

/**
 * Shared base for the calendar functional tests.
 *
 * `xima_typo3_recordlist` is declared as a hard requirement in composer.json, so the
 * package manager refuses to activate xima_typo3_calendar without it — it must be
 * linked into the test instance even though its own surface is not exercised here.
 *
 * The calendar tables themselves are not declared in ext_tables.sql (only a few
 * column overrides are); they are derived from the TCA in Configuration/TCA by the
 * schema analyzer, so no additional SQL is needed.
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
     * Reads a single record with every restriction removed.
     *
     * `removeAll()` keeps restrictions implementing
     * {@see EnforceableQueryRestrictionInterface}, and both calendar restrictions
     * are enforced — they have to be dropped by type or they would filter the
     * very rows a test wants to assert on.
     *
     * @return array<string, mixed>|null
     */
    protected function fetchRawRecord(string $table, int $uid): ?array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $this->removeAllRestrictions($queryBuilder);

        $row = $queryBuilder
            ->select('*')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \TYPO3\CMS\Core\Database\Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();

        return is_array($row) ? $row : null;
    }

    /**
     * Drops every restriction from $queryBuilder, including the enforced calendar
     * ones that `removeAll()` deliberately keeps.
     */
    protected function removeAllRestrictions(QueryBuilder $queryBuilder): void
    {
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->removeByType(EventRestriction::class)
            ->removeByType(EntryRestriction::class);
    }
}
