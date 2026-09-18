<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Indexer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tpwd\KeSearch\Lib\Items;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Indexer\EventIndexerConfiguration;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * ke_search gates its indexer configuration fields on a whitelist of its own indexer types.
 * A field missing from that whitelist does not fail, it simply never renders, which leaves an
 * editor unable to say where the events live and the indexer reading an empty page list.
 */
final class KeSearchIndexerConfigTcaTest extends AbstractCalendarFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'xima_typo3_recordlist',
        'xima_typo3_calendar',
        'ke_search',
        // Relative to the docroot the bootstrap chdir()s into, not to the repo root.
        '../Tests/Functional/Fixtures/Extensions/calendar_test',
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function storageFieldProvider(): array
    {
        return [
            'recursive startingpoints' => ['startingpoints_recursive'],
            'sysfolder' => ['sysfolder'],
        ];
    }

    #[Test]
    #[DataProvider('storageFieldProvider')]
    public function storageFieldIsVisibleForTheEventIndexer(string $field): void
    {
        $displayCond = $GLOBALS['TCA']['tx_kesearch_indexerconfig']['columns'][$field]['displayCond'] ?? '';

        self::assertIsString($displayCond);
        self::assertContains(
            EventIndexerConfiguration::INDEXER_TYPE,
            explode(',', substr($displayCond, strlen('FIELD:type:IN:'))),
        );
    }

    /**
     * The type is not a static TCA item: ke_search collects the registered indexers through an
     * itemsProcFunc when the form is rendered.
     */
    #[Test]
    public function theIndexerTypeIsOfferedInTheTypeSelector(): void
    {
        $params = ['items' => []];
        GeneralUtility::makeInstance(Items::class)->fillIndexerConfig($params, null);

        $values = array_map(static fn (array $item): string => (string)($item[1] ?? ''), $params['items']);

        self::assertContains(EventIndexerConfiguration::INDEXER_TYPE, $values);
    }
}
