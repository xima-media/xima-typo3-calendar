<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Backend\Tca;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Backend\Tca\EventStatusItemsProcessor;
use Xima\XimaTypo3Calendar\Domain\Model\Enum\EventStatus;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;
use Xima\XimaTypo3Calendar\Service\EventWorkflowPolicy;

/**
 * The processor resolves EventWorkflowPolicy through GeneralUtility and reads
 * $GLOBALS['BE_USER']. A real policy is pushed onto the GeneralUtility instance
 * stack and the backend user's check() result decides the publish permission.
 * The transition table itself is covered by the functional EventWorkflowPolicyTest.
 */
final class EventStatusItemsProcessorTest extends TestCase
{
    private const OWN_USER_UID = 7;

    private EventStatusItemsProcessor $subject;

    /** @var array<string, mixed> */
    private array $globalsBackup = [];

    protected function setUp(): void
    {
        $this->subject = new EventStatusItemsProcessor();
        $this->globalsBackup = ['BE_USER' => $GLOBALS['BE_USER'] ?? null];
        GeneralUtility::addInstance(
            EventWorkflowPolicy::class,
            new EventWorkflowPolicy(new CalendarPermissionService(), self::createStub(ExtensionConfiguration::class))
        );
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
    public function offersAnOwnerWithoutThePublishPermissionOnlyDraftAndReview(): void
    {
        $this->givenPublishPermission(false);
        $params = $this->params(EventStatus::DRAFT->value);

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame([EventStatus::DRAFT->value, EventStatus::REVIEW->value], self::itemValues($params));
    }

    #[Test]
    public function reindexesTheRemainingItemsAfterRemoval(): void
    {
        $this->givenPublishPermission(false);
        $params = $this->params(EventStatus::DRAFT->value);

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame([0, 1], array_keys($params['items']));
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
     * A live event keeps only its own status, so it still renders and cannot be moved.
     */
    #[Test]
    public function offersOnlyTheCurrentStatusOfALiveEvent(): void
    {
        $this->givenPublishPermission(false);
        $params = $this->params(EventStatus::LIVE->value);

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame([EventStatus::LIVE->value], self::itemValues($params));
    }

    #[Test]
    public function keepsTheStatusOfARejectedEventNextToItsTargets(): void
    {
        $this->givenPublishPermission(false);
        $params = $this->params(EventStatus::REJECTED->value);

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame(
            [EventStatus::DRAFT->value, EventStatus::REVIEW->value, EventStatus::REJECTED->value],
            self::itemValues($params)
        );
    }

    #[Test]
    public function offersOnlyTheCurrentStatusOfAForeignEvent(): void
    {
        $this->givenPublishPermission(false);
        $params = $this->params(EventStatus::DRAFT->value, self::OWN_USER_UID + 1);

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame([EventStatus::DRAFT->value], self::itemValues($params));
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

        self::assertSame([EventStatus::LIVE->value], self::itemValues($params));
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
            'row' => ['uid' => 1, 'owner_be_user' => self::OWN_USER_UID, 'status' => EventStatus::DRAFT->value],
        ];

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame([EventStatus::DRAFT->value], self::itemValues($params));
    }

    /**
     * A new record has no status and no owner yet: it starts as the user's own draft.
     */
    #[Test]
    #[DataProvider('missingStatusProvider')]
    public function treatsANewRecordAsTheUsersOwnDraft(mixed $status): void
    {
        $this->givenPublishPermission(false);
        $row = ['uid' => 'NEW123'];
        if ($status !== null) {
            $row['status'] = $status;
        }
        $params = ['items' => $this->items(), 'row' => $row];

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame([EventStatus::DRAFT->value, EventStatus::REVIEW->value], self::itemValues($params));
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
    public function offersOnlyTheCurrentStatusWhenNoBackendUserIsPresent(): void
    {
        unset($GLOBALS['BE_USER']);
        $params = $this->params(EventStatus::DRAFT->value);

        $this->subject->removeUnauthorizedItems($params);

        self::assertSame([EventStatus::DRAFT->value], self::itemValues($params));
    }

    private function givenPublishPermission(bool $granted): void
    {
        $backendUser = self::createStub(BackendUserAuthentication::class);
        $backendUser->user = ['uid' => self::OWN_USER_UID];
        $backendUser->method('check')->willReturnCallback(
            static fn (string $type, string $value): bool => $granted
                && $value === CalendarPermissionService::PERMISSION_GROUP . ':' . CalendarPermissionService::PERMISSION_PUBLISH_LIVE_EVENTS
        );

        $GLOBALS['BE_USER'] = $backendUser;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, row: array<string, mixed>}
     */
    private function params(mixed $currentStatus, int $owner = self::OWN_USER_UID): array
    {
        return [
            'items' => $this->items(),
            'row' => ['uid' => 1, 'owner_be_user' => $owner, 'status' => $currentStatus],
        ];
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
            static function (array $item): int {
                $value = $item['value'] ?? null;
                if ($value === null) {
                    $values = array_values($item);
                    $value = $values[1] ?? -1;
                }

                return (int)$value;
            },
            array_values($params['items'])
        );
    }
}
