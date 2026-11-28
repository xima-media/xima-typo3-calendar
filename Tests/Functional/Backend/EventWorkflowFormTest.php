<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * The backend surfaces that only display what the EventWorkflowPolicy allows: the edit form
 * (LiveEventReadOnlyForNonPublisher), the status select (EventStatusItemsProcessor) and the
 * status badge of the record list, which opens the status modal.
 *
 * Users, events and appointments come from Fixtures/workflow-permissions.csv.
 */
final class EventWorkflowFormTest extends AbstractCalendarFunctionalTestCase
{
    private const EDITOR = 2;
    private const PUBLISHER = 3;

    private const OWN_LIVE = 11;
    private const OWN_DRAFT = 12;
    private const FOREIGN_LIVE_APPOINTMENT = 12;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/workflow-permissions.csv');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['BE_USER'], $GLOBALS['LANG'], $GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    public function aLiveEventIsReadOnlyForAnEditorExceptItsAppointments(): void
    {
        $this->actAs(self::EDITOR);

        $columns = $this->compile(self::TABLE_EVENT, self::OWN_LIVE)['processedTca']['columns'];

        self::assertSame(['appointments'], $this->editableColumns($columns));
        self::assertNotEmpty($columns['appointments']['children']);
        foreach ($columns['appointments']['children'] as $child) {
            self::assertSame([], $this->readOnlyColumns($child['processedTca']['columns']));
        }
    }

    #[Test]
    public function anAppointmentOfALiveEventStaysEditableForAnEditor(): void
    {
        $this->actAs(self::EDITOR);

        $columns = $this->compile(self::TABLE_ENTRY, self::FOREIGN_LIVE_APPOINTMENT)['processedTca']['columns'];

        self::assertSame([], $this->readOnlyColumns($columns));
    }

    #[Test]
    public function aLiveEventStaysEditableForAPublisher(): void
    {
        $this->actAs(self::PUBLISHER);

        $columns = $this->compile(self::TABLE_EVENT, self::OWN_LIVE)['processedTca']['columns'];

        self::assertSame([], $this->readOnlyColumns($columns));
    }

    #[Test]
    public function theStatusSelectOffersAnEditorOnlyTheirTransitions(): void
    {
        $this->actAs(self::EDITOR);

        self::assertSame(
            [EventStatus::DRAFT->value, EventStatus::REVIEW->value],
            $this->statusItemValues($this->compile(self::TABLE_EVENT, self::OWN_DRAFT))
        );
        self::assertSame([EventStatus::LIVE->value], $this->statusItemValues($this->compile(self::TABLE_EVENT, self::OWN_LIVE)));
    }

    #[Test]
    public function theStatusBadgeOpensTheModalWithTheAllowedTargetsOnly(): void
    {
        $this->actAs(self::EDITOR);

        $html = $this->renderStatusColumn(self::OWN_DRAFT);

        self::assertStringContainsString('event-status-badge', $html);
        self::assertStringNotContainsString('event-status-badge--readonly', $html);
        self::assertSame([EventStatus::DRAFT->value, EventStatus::REVIEW->value], $this->modalItemValues($html));
    }

    #[Test]
    public function theStatusBadgeIsReadOnlyWhenNoTransitionIsAllowed(): void
    {
        $this->actAs(self::EDITOR);

        $html = $this->renderStatusColumn(self::OWN_LIVE);

        self::assertStringContainsString('event-status-badge--readonly', $html);
        self::assertStringNotContainsString('data-status-items', $html);
    }

    #[Test]
    public function theStatusBadgeOffersAPublisherEveryStatus(): void
    {
        $this->actAs(self::PUBLISHER);

        self::assertSame(
            array_map(static fn (EventStatus $status): int => $status->value, EventStatus::cases()),
            $this->modalItemValues($this->renderStatusColumn(self::OWN_LIVE))
        );
    }

    private function actAs(int $uid): void
    {
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($this->setUpBackendUser($uid));
    }

    /**
     * @return array<string, mixed>
     */
    private function compile(string $table, int $uid): array
    {
        return $this->get(FormDataCompiler::class)->compile(
            ['request' => $this->backendRequest(), 'tableName' => $table, 'vanillaUid' => $uid, 'command' => 'edit'],
            $this->get(TcaDatabaseRecord::class),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $columns
     * @return list<string>
     */
    private function editableColumns(array $columns): array
    {
        return array_keys(array_filter($columns, static fn (array $column): bool => !($column['config']['readOnly'] ?? false)));
    }

    /**
     * Columns the TCA itself already defines read-only (owner, approval date) do not count.
     *
     * @param array<string, array<string, mixed>> $columns
     * @return list<string>
     */
    private function readOnlyColumns(array $columns): array
    {
        $readOnly = [];
        foreach ($columns as $name => $column) {
            if (($column['config']['readOnly'] ?? false) && !in_array($name, ['owner', 'owner_be_user', 'approval_date', 'modified_date'], true)) {
                $readOnly[] = (string)$name;
            }
        }

        return $readOnly;
    }

    /**
     * @param array<string, mixed> $result
     * @return list<int>
     */
    private function statusItemValues(array $result): array
    {
        return array_map(
            static fn (array $item): int => (int)$item['value'],
            array_values($result['processedTca']['columns']['status']['config']['items'])
        );
    }

    private function renderStatusColumn(int $eventUid): string
    {
        $request = $this->backendRequest();
        $GLOBALS['TYPO3_REQUEST'] = $request;
        $view = $this->get(ViewFactoryInterface::class)->create(new ViewFactoryData(
            partialRootPaths: ['EXT:xima_typo3_calendar/Resources/Private/xima_typo3_recordlist/Partials/'],
            templatePathAndFilename: __DIR__ . '/../Fixtures/Templates/EventStatusColumn.html',
            request: $request,
        ));
        $view->assign('item', $this->fetchRawRecord(self::TABLE_EVENT, $eventUid));

        return $view->render();
    }

    /**
     * @return list<int>
     */
    private function modalItemValues(string $html): array
    {
        self::assertSame(1, preg_match('/data-status-items="([^"]*)"/', $html, $matches), $html);
        $items = json_decode(html_entity_decode($matches[1]), true, 512, JSON_THROW_ON_ERROR);

        return array_map(static fn (array $item): int => (int)$item['value'], $items);
    }

    private function backendRequest(): ServerRequest
    {
        return (new ServerRequest('https://example.com/typo3/record/edit'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }
}
