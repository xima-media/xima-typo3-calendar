<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\CheckboxCalendarFilter;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\CheckboxCalendarFilterItem;
use Xima\XimaTypo3Calendar\Event\ApplyCalendarFilterEvent;
use Xima\XimaTypo3Calendar\Event\BuildCalendarEntriesQueryEvent;
use Xima\XimaTypo3Calendar\Event\CalendarEntrySerializationEvent;
use Xima\XimaTypo3Calendar\Event\ConfigureCalendarFilterEvent;

final readonly class CalendarFilterRecordType implements CalendarFilterListenerInterface
{
    #[AsEventListener(
        identifier: 'xima-typo3-calendar/configure-filter-record-type',
        event: ConfigureCalendarFilterEvent::class,
        method: 'build',
    )]
    public function build(ConfigureCalendarFilterEvent $event): void
    {
        $items = $GLOBALS['TCA']
            ['tx_ximatypo3calendar_domain_model_event']
            ['columns']
            ['record_type']
            ['config']
            ['items']
            ?? [];

        if (!$this->hasRelevantRecordTypes()) {
            return;
        }

        $filter = new CheckboxCalendarFilter(
            identifier: 'recordTypes',
            title: LocalizationUtility::translate(
                'filter.type.title',
                'xima_typo3_calendar',
            ) ?? 'Type',
        );
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $value = (string)($item['value'] ?? '');
            if ($value === '') {
                continue;
            }

            $label = (string)($item['label'] ?? $value);
            $translatedLabel = LocalizationUtility::translate(
                $label,
                'xima_typo3_calendar',
            ) ?? $value;

            $filter->addItem(
                new CheckboxCalendarFilterItem(
                    label: $translatedLabel,
                    value: $value,
                    icon: is_string($item['icon'] ?? null) ? $item['icon'] : null,
                ),
            );
        }

        if ($filter->items !== []) {
            $event->addFilter($filter);
        }
    }

    #[AsEventListener(
        identifier: 'xima-typo3-calendar/build-query-record-type',
        event: BuildCalendarEntriesQueryEvent::class,
        method: 'buildQuery',
    )]
    public function buildQuery(
        BuildCalendarEntriesQueryEvent $event,
    ): void {
        $event->getQueryBuilder()->addSelectLiteral(
            'v.record_type AS record_type',
        );
    }

    #[AsEventListener(
        identifier: 'xima-typo3-calendar/apply-filter-record-type',
        event: ApplyCalendarFilterEvent::class,
        method: 'apply',
    )]
    public function apply(ApplyCalendarFilterEvent $event): void
    {
        $filters = $event->getFilters();

        $recordTypes = $filters['filters']['recordTypes'] ?? [];

        if (!is_array($recordTypes) || $recordTypes === []) {
            return;
        }

        $recordTypes = array_values(array_filter(
            $recordTypes,
            static fn (mixed $recordType): bool => is_string($recordType) && $recordType !== '',
        ));

        if ($recordTypes === []) {
            return;
        }

        $queryBuilder = $event->getQueryBuilder();

        $queryBuilder->andWhere(
            $queryBuilder->expr()->in(
                'v.record_type',
                $queryBuilder->createNamedParameter(
                    $recordTypes,
                    Connection::PARAM_STR_ARRAY
                ),
            ),
        );
    }

    #[AsEventListener(
        identifier: 'xima-typo3-calendar/serialize-event-record-type',
        event: CalendarEntrySerializationEvent::class,
        method: 'serialize',
    )]
    public function serialize(
        CalendarEntrySerializationEvent $event,
    ): void {
        $recordType = $event->getSourceRow()['record_type'] ?? null;
        $recordTypeValue = is_string($recordType) ? $recordType : '';

        $event->addExtendedProperty(
            'recordType',
            $recordTypeValue,
        );

        if (!$this->hasRelevantRecordTypes() || $recordTypeValue === '') {
            return;
        }

        $event->addFilterValue(
            'recordTypes',
            $recordTypeValue,
        );
    }

    private function hasRelevantRecordTypes(): bool
    {
        $items = $GLOBALS['TCA']
            ['tx_ximatypo3calendar_domain_model_event']
            ['columns']
            ['record_type']
            ['config']
            ['items']
            ?? [];

        $recordTypeValues = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $item): string => is_array($item)
                    ? (string)($item['value'] ?? '')
                    : '',
                $items,
            ),
            static fn (string $value): bool => $value !== '',
        )));

        $defaultRecordTypes = ['event', 'event-appointment'];

        return count($recordTypeValues) > 1
            && !(
                count($recordTypeValues) === 2
                && count(array_diff($recordTypeValues, $defaultRecordTypes)) === 0
                && count(array_diff($defaultRecordTypes, $recordTypeValues)) === 0
            );
    }
}
