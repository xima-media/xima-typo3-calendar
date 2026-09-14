<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Indexer;

/**
 * Adds the event indexer to the type selector of a ke_search indexer configuration.
 *
 * Registered on the `registerIndexerConfiguration` hook. The class carries no ke_search parent
 * on purpose, so the indexer type constant stays readable without loading EventIndexer.
 */
class EventIndexerConfiguration
{
    public const INDEXER_TYPE = 'ximatypo3calendar_event';

    /**
     * @param array<string, mixed> $params
     */
    public function registerIndexerConfiguration(array &$params, mixed $pObj): void
    {
        $params['items'][] = [
            'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_be.xlf:indexer.event.title',
            self::INDEXER_TYPE,
            'tx-ximatypo3calendar-event',
        ];
    }
}
