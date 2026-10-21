<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\CheckboxCalendarFilter;
use Xima\XimaTypo3Calendar\Domain\Model\Dto\CheckboxCalendarFilterItem;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Event\ApplyCalendarFilterEvent;
use Xima\XimaTypo3Calendar\Event\BuildCalendarEntriesQueryEvent;
use Xima\XimaTypo3Calendar\Event\CalendarEntrySerializationEvent;
use Xima\XimaTypo3Calendar\Event\ConfigureCalendarFilterEvent;

final readonly class CalendarFilterStatus implements CalendarFilterListenerInterface
{
    #[AsEventListener(
        identifier: 'xima-typo3-calendar/configure-status-filter',
        event: ConfigureCalendarFilterEvent::class,
        method: 'build',
    )]
    public function build(ConfigureCalendarFilterEvent $event): void
    {
        $filter = new CheckboxCalendarFilter(
            identifier: 'statuses',
            title: LocalizationUtility::translate(
                'filter.status.title',
                'xima_typo3_calendar',
            ) ?? 'Status',
        );

        $statuses = [
            EventStatus::DRAFT,
            EventStatus::REVIEW,
            EventStatus::REJECTED,
            EventStatus::LIVE,
        ];

        foreach ($statuses as $status) {
            $filter->addItem(
                new CheckboxCalendarFilterItem(
                    label: LocalizationUtility::translate(
                        'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/RecordTypes/event/labels.xlf:status.items.'
                        . $status->value
                        . '.label',
                        'xima_typo3_calendar',
                    ) ?? (string)$status->value,
                    value: $status->value,
                    appearance: match ($status) {
                        EventStatus::DRAFT => 'hatched',
                        EventStatus::REVIEW => 'dashed',
                        EventStatus::REJECTED => 'outlined',
                        default => '',
                    },
                ),
            );
        }

        $filter->addItem(
            new CheckboxCalendarFilterItem(
                label: LocalizationUtility::translate(
                    'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_mod_calendar.xlf:filter.canceled.label',
                    'xima_typo3_calendar',
                ) ?? 'Canceled',
                value: 'canceled',
                marker: LocalizationUtility::translate(
                    'LLL:EXT:xima_typo3_calendar/Resources/Private/Language/locallang_mod_calendar.xlf:filter.canceled.marker',
                    'xima_typo3_calendar',
                ) ?? '[C]',
            ),
        );

        $event->addFilter($filter);
    }

    #[AsEventListener(
        identifier: 'xima-typo3-calendar/build-query-status',
        event: BuildCalendarEntriesQueryEvent::class,
        method: 'buildQuery',
    )]
    public function buildQuery(
        BuildCalendarEntriesQueryEvent $event,
    ): void {
        $event->getQueryBuilder()->addSelectLiteral(
            'v.status AS event_status',
        );
    }

    #[AsEventListener(
        identifier: 'xima-typo3-calendar/apply-status-filter',
        event: ApplyCalendarFilterEvent::class,
        method: 'apply',
    )]
    public function apply(ApplyCalendarFilterEvent $event): void
    {
        $filters = $event->getFilters();
        $statuses = $filters['filters']['statuses']
            ?? $filters['statuses']
            ?? [];

        if (!is_array($statuses) || $statuses === []) {
            return;
        }

        $queryBuilder = $event->getQueryBuilder();

        $eventStatuses = array_values(array_filter(
            $statuses,
            static fn (mixed $status): bool => is_int($status) || is_numeric($status),
        ));

        $includeCanceled = in_array('canceled', $statuses, true);

        if ($eventStatuses !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'v.status',
                    $queryBuilder->createNamedParameter(
                        array_map('intval', $eventStatuses),
                        Connection::PARAM_INT_ARRAY,
                    )
                )
            );
        }

        if ($includeCanceled) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'e.canceled',
                    $queryBuilder->createNamedParameter(
                        1,
                        Connection::PARAM_INT,
                    )
                )
            );
        }
    }

    #[AsEventListener(
        identifier: 'xima-typo3-calendar/serialize-event-status',
        event: CalendarEntrySerializationEvent::class,
        method: 'serialize',
    )]
    public function serialize(
        CalendarEntrySerializationEvent $event,
    ): void {
        $status = $event->getSourceRow()['event_status'] ?? null;
        $statusValue = is_int($status) || is_numeric($status)
            ? (int)$status
            : null;

        $event->addExtendedProperty(
            'eventStatus',
            $statusValue,
        );

        if ($statusValue === null) {
            return;
        }

        $event->addFilterValue(
            'statuses',
            $statusValue,
        );
    }
}
