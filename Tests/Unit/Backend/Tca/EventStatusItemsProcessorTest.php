<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Backend\Tca;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Backend\Tca\EventStatusItemsProcessor;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;

/**
 * The processor resolves CalendarPermissionService through GeneralUtility, and
 * the service itself reads $GLOBALS['BE_USER']. Both are steered here: a real
 * (non-mockable, final) permission service is pushed onto the GeneralUtility
 * instance stack and the backend user's check() result decides the outcome.
 */
final class EventStatusItemsProcessorTest extends TestCase
{
    private EventStatusItemsProcessor $subject;

    /** @var array<string, mixed> */
    private array $globalsBackup = [];

    protected function setUp(): void
    {
        $this->subject = new EventStatusItemsProcessor();
        $this->globalsBackup = ['BE_USER' => $GLOBALS['BE_USER'] ?? null];
    }

    protected function tearDown(): void
    {
        if ($this->globalsBackup['BE_USER'] === null) {
            unset($GLOBALS['BE_USER']);
        } else {
            $GLOBALS['BE_USER'] = $this->globalsBackup['BE_USER'];
        }
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function removesTheLiveItemForAUserWithoutThePublishPermission(): void
    {
        $this->givenPublishPermission(false);
        $params = $this->params(EventStatus::DRAFT->value);

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame(
            [EventStatus::DRAFT->value, EventStatus::REVIEW->value, EventStatus::REJECTED->value],
            self::itemValues($params)
        );
    }

    #[Test]
    public function reindexesTheRemainingItemsAfterRemoval(): void
    {
        $this->givenPublishPermission(false);
        $params = $this->params(EventStatus::DRAFT->value);

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame([0, 1, 2], array_keys($params['items']));
    }

    #[Test]
    public function keepsEveryItemForAUserWithThePublishPermission(): void
    {
        $this->givenPublishPermission(true);
        $params = $this->params(EventStatus::DRAFT->value);

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame(
            [
                EventStatus::DRAFT->value,
                EventStatus::REVIEW->value,
                EventStatus::LIVE->value,
                EventStatus::REJECTED->value,
            ],
            self::itemValues($params)
        );
    }

    /**
     * An event that is already live keeps the option so its status still renders
     * correctly and is not silently downgraded on save.
     */
    #[Test]
    public function keepsTheLiveItemWhenTheRecordIsAlreadyLive(): void
    {
        $this->givenPublishPermission(false);
        $params = $this->params(EventStatus::LIVE->value);

        $this->subject->removeUnauthorizedItems($params);

        self::assertContains(EventStatus::LIVE->value, self::itemValues($params));
    }

    /**
     * FormEngine hands the current value over as a single-element array for
     * select fields, so the array shape must resolve to the same status.
     */
    #[Test]
    public function acceptsTheCurrentStatusAsASingleElementArray(): void
    {
        $this->givenPublishPermission(false);
        $params = $this->params([EventStatus::LIVE->value]);

        $this->subject->removeUnauthorizedItems($params);

        self::assertContains(EventStatus::LIVE->value, self::itemValues($params));
    }

    /**
     * Legacy TCA item tuples use index 1 for the value instead of the 'value' key.
     */
    #[Test]
    public function removesTheLiveItemFromLegacyIndexedItemTuples(): void
    {
        $this->givenPublishPermission(false);
        $params = [
            'items' => [
                [0 => 'Draft', 1 => EventStatus::DRAFT->value],
                [0 => 'Live', 1 => EventStatus::LIVE->value],
            ],
            'row' => ['status' => EventStatus::DRAFT->value],
        ];

        // The subject reads $item[1], but its own param docblock only describes
        // the modern array<string, mixed> item shape.
        // @phpstan-ignore-next-line argument.type
        $this->subject->removeUnauthorizedItems($params);

        self::assertCount(1, $params['items']);
        self::assertSame([EventStatus::DRAFT->value], self::itemValues($params));
    }

    #[Test]
    #[DataProvider('missingStatusProvider')]
    public function treatsAMissingStatusAsDraftAndStillRemovesLive(mixed $status): void
    {
        $this->givenPublishPermission(false);
        $params = ['items' => $this->items(), 'row' => $status === null ? [] : ['status' => $status]];

        $this->subject->removeUnauthorizedItems($params);

        self::assertNotContains(EventStatus::LIVE->value, self::itemValues($params));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function missingStatusProvider(): array
    {
        return [
            'absent status key' => [null],
            'empty string'      => [''],
            'empty array'       => [[]],
        ];
    }

    /**
     * A request without a backend user must not be treated as permitted.
     */
    #[Test]
    public function removesTheLiveItemWhenNoBackendUserIsPresent(): void
    {
        unset($GLOBALS['BE_USER']);
        GeneralUtility::addInstance(CalendarPermissionService::class, new CalendarPermissionService());
        $params = $this->params(EventStatus::DRAFT->value);

        $this->subject->removeUnauthorizedItems($params);

        self::assertNotContains(EventStatus::LIVE->value, self::itemValues($params));
    }

    private function givenPublishPermission(bool $granted): void
    {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')
            ->with(
                'custom_options',
                CalendarPermissionService::PERMISSION_GROUP . ':' . CalendarPermissionService::PERMISSION_PUBLISH_LIVE_EVENTS
            )
            ->willReturn($granted);

        $GLOBALS['BE_USER'] = $backendUser;
        GeneralUtility::addInstance(CalendarPermissionService::class, new CalendarPermissionService());
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, row: array<string, mixed>}
     */
    private function params(mixed $currentStatus): array
    {
        return ['items' => $this->items(), 'row' => ['status' => $currentStatus]];
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function items(): array
    {
        return [
            ['label' => 'Draft', 'value' => EventStatus::DRAFT->value],
            ['label' => 'Review', 'value' => EventStatus::REVIEW->value],
            ['label' => 'Live', 'value' => EventStatus::LIVE->value],
            ['label' => 'Rejected', 'value' => EventStatus::REJECTED->value],
        ];
    }

    /**
     * Mirrors the value lookup of the subject: modern TCA items carry a `value`
     * key, legacy tuples the value at index 1.
     *
     * @param array{items: array<int, array<array-key, mixed>>} $params
     * @return array<int, int>
     */
    private static function itemValues(array $params): array
    {
        return array_map(
            static fn (array $item): int => (int)($item['value'] ?? $item[1] ?? -1),
            array_values($params['items'])
        );
    }
}
