<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Database;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use Xima\XimaTypo3Calendar\Database\EntryRestriction;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * An entry inherits the visibility of its parent event, but the event table is
 * usually LEFT JOINed — TYPO3 then moves the event-side restriction into the ON
 * clause and drops the event from the WHERE-clause table list, so the join alias
 * is not reliably available. The restriction therefore reaches the parent event
 * through a correlated EXISTS subquery, which is what these tests exercise.
 *
 * The fixture wires entry 1 to the live event 1 and entry 2 to the draft event 2,
 * so an anonymous visitor may only ever see entry 1.
 *
 * @see EventRestrictionTest for the sibling restriction on the event table
 */
final class EntryRestrictionTest extends AbstractCalendarFunctionalTestCase
{
    private EntryRestriction $subject;

    private ExpressionBuilder $expressionBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');

        $connectionPool = $this->get(ConnectionPool::class);
        $this->subject = new EntryRestriction(
            $this->get(Context::class),
            $connectionPool,
            $this->get(ExtensionConfiguration::class),
            new CalendarPermissionService(),
        );
        $this->expressionBuilder = $connectionPool
            ->getQueryBuilderForTable(self::TABLE_ENTRY)
            ->expr();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST'], $GLOBALS['BE_USER']);
        parent::tearDown();
    }

    #[Test]
    public function theRestrictionIsEnforced(): void
    {
        self::assertTrue($this->subject->isEnforced());
    }

    #[Test]
    public function producesNoConditionForQueriesThatDoNotTouchTheEntryTable(): void
    {
        $this->givenFrontendRequest();

        $expression = $this->subject->buildExpression(['pages' => 'pages'], $this->expressionBuilder);

        self::assertSame('', (string)$expression);
    }

    /**
     * The event table alone is not enough — the entry restriction only applies to
     * queries that actually select entries.
     */
    #[Test]
    public function producesNoConditionForQueriesTouchingOnlyTheEventTable(): void
    {
        $this->givenFrontendRequest();

        $expression = $this->subject->buildExpression(['e' => self::TABLE_EVENT], $this->expressionBuilder);

        self::assertSame('', (string)$expression);
    }

    #[Test]
    public function producesNoConditionWithoutAGlobalRequest(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        $expression = $this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertSame('', (string)$expression);
    }

    /**
     * Scheduler tasks and commands keep seeing every record. Unlike the event
     * restriction, the CLI check runs before the request lookup, so it applies
     * even when a request happens to be present.
     */
    #[Test]
    public function producesNoConditionInCliContext(): void
    {
        $GLOBALS['BE_USER'] = new CommandLineUserAuthentication();
        $this->givenFrontendRequest();

        $expression = $this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertSame('', (string)$expression);
    }

    #[Test]
    public function restrictsAnonymousFrontendVisitorsToEntriesOfLiveEvents(): void
    {
        $this->givenFrontendRequest();

        self::assertSame([1], $this->fetchVisibleEntryUids());
    }

    #[Test]
    public function theAnonymousConditionChecksTheParentEventThroughASubquery(): void
    {
        $this->givenFrontendRequest();

        $expression = (string)$this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertStringContainsString('EXISTS (SELECT 1 FROM ' . self::TABLE_EVENT . ' e', $expression);
        self::assertStringContainsString('e.uid = en.event', $expression);
        self::assertStringContainsString('e.deleted = 0', $expression);
        self::assertStringContainsString("e.status = '2'", $expression);
        self::assertStringNotContainsString('e.owner', $expression);
    }

    /**
     * A logged-in frontend user additionally sees the entries of events they own,
     * whatever the status of those events.
     */
    #[Test]
    public function aLoggedInFrontendUserAlsoSeesEntriesOfTheirOwnEvents(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_EVENT)
            ->update(self::TABLE_EVENT, ['owner' => 7], ['uid' => 2]);
        $this->givenFrontendRequest($this->frontendUser(7));

        self::assertSame([1, 2], $this->fetchVisibleEntryUids());
    }

    #[Test]
    public function theOwnerConditionNamesTheFrontendUserUid(): void
    {
        $this->givenFrontendRequest($this->frontendUser(7));

        $expression = (string)$this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertStringContainsString('e.owner = 7', $expression);
    }

    /**
     * A frontend user record without a uid must not widen the restriction.
     */
    #[Test]
    public function anAnonymousFrontendUserObjectDoesNotAddTheOwnerCondition(): void
    {
        $this->givenFrontendRequest(new FrontendUserAuthentication());

        $expression = (string)$this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertStringNotContainsString('e.owner', $expression);
    }

    /**
     * The EXISTS subquery excludes deleted parents explicitly, so an entry whose
     * event was removed disappears even though the entry itself is untouched.
     */
    #[Test]
    public function entriesOfADeletedEventAreHidden(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_EVENT)
            ->update(self::TABLE_EVENT, ['deleted' => 1], ['uid' => 1]);
        $this->givenFrontendRequest();

        self::assertSame([], $this->fetchVisibleEntryUids());
    }

    #[Test]
    public function anEntryWithoutAParentEventIsHidden(): void
    {
        $this->insertEntry(90, 0);
        $this->givenFrontendRequest();

        self::assertSame([1], $this->fetchVisibleEntryUids());
    }

    #[Test]
    public function anEntryPointingAtAMissingEventIsHidden(): void
    {
        $this->insertEntry(91, 9999);
        $this->givenFrontendRequest();

        self::assertSame([1], $this->fetchVisibleEntryUids());
    }

    /**
     * An entry stays visible when its *own* record type is unrestricted, even
     * though its parent event is still a draft.
     */
    #[Test]
    public function entriesOfAnUnrestrictedRecordTypeStayVisible(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_ENTRY)
            ->update(self::TABLE_ENTRY, ['record_type' => 'private-appointment'], ['uid' => 2]);
        $this->givenUnrestrictedRecordTypes('private-appointment');
        $this->givenFrontendRequest();

        self::assertSame([1, 2], $this->fetchVisibleEntryUids());
    }

    /**
     * ...and it also stays visible when the *parent event's* record type is the
     * unrestricted one, which is the case the setting was introduced for.
     */
    #[Test]
    public function entriesOfAnUnrestrictedParentEventRecordTypeStayVisible(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_EVENT)
            ->update(self::TABLE_EVENT, ['record_type' => 'private-event'], ['uid' => 2]);
        $this->givenUnrestrictedRecordTypes('private-event');
        $this->givenFrontendRequest();

        self::assertSame([1, 2], $this->fetchVisibleEntryUids());
    }

    #[Test]
    public function theRecordTypeExemptionAcceptsACommaSeparatedList(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_ENTRY)
            ->update(self::TABLE_ENTRY, ['record_type' => 'private-appointment'], ['uid' => 2]);
        $this->insertEntry(92, 0, 'internal-appointment');
        $this->givenUnrestrictedRecordTypes('private-appointment, internal-appointment');
        $this->givenFrontendRequest();

        self::assertSame([1, 2, 92], $this->fetchVisibleEntryUids());
    }

    #[Test]
    public function anEmptyRecordTypeSettingExemptsNothing(): void
    {
        $this->givenUnrestrictedRecordTypes('');
        $this->givenFrontendRequest();

        self::assertSame([1], $this->fetchVisibleEntryUids());
    }

    #[Test]
    public function producesNoConditionForBackendRequestsWithoutABackendUser(): void
    {
        $this->givenBackendRequest();
        unset($GLOBALS['BE_USER']);

        $expression = $this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertSame('', (string)$expression);
    }

    /**
     * A backend user holding the "view all events" permission — administrators
     * included — is not filtered at all.
     */
    #[Test]
    public function producesNoConditionForABackendUserAllowedToViewAllEvents(): void
    {
        $GLOBALS['BE_USER'] = $this->backendUser(16, canViewAllEvents: true);
        $this->givenBackendRequest();

        $expression = $this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertSame('', (string)$expression);
    }

    #[Test]
    public function aBackendUserWithoutThatPermissionOnlySeesEntriesOfLiveEvents(): void
    {
        $GLOBALS['BE_USER'] = $this->backendUser(16, canViewAllEvents: false);
        $this->givenBackendRequest();

        self::assertSame([1], $this->fetchVisibleEntryUids());
    }

    #[Test]
    public function aBackendUserAlsoSeesEntriesOfTheEventsTheyOwn(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_EVENT)
            ->update(self::TABLE_EVENT, ['owner_be_user' => 16], ['uid' => 2]);
        $GLOBALS['BE_USER'] = $this->backendUser(16, canViewAllEvents: false);
        $this->givenBackendRequest();

        self::assertSame([1, 2], $this->fetchVisibleEntryUids());
    }

    #[Test]
    public function theBackendConditionNamesTheBackendUserUid(): void
    {
        $GLOBALS['BE_USER'] = $this->backendUser(16, canViewAllEvents: false);
        $this->givenBackendRequest();

        $expression = (string)$this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertStringContainsString('e.owner_be_user = 16', $expression);
        self::assertStringContainsString("e.status = '2'", $expression);
    }

    #[Test]
    public function theRecordTypeExemptionAlsoAppliesInTheBackend(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_ENTRY)
            ->update(self::TABLE_ENTRY, ['record_type' => 'private-appointment'], ['uid' => 2]);
        $this->givenUnrestrictedRecordTypes('private-appointment');
        $GLOBALS['BE_USER'] = $this->backendUser(16, canViewAllEvents: false);
        $this->givenBackendRequest();

        self::assertSame([1, 2], $this->fetchVisibleEntryUids());
    }

    /**
     * @return array<string, string>
     */
    private function queriedTables(): array
    {
        return ['en' => self::TABLE_ENTRY];
    }

    /**
     * Runs a query through the restriction and returns the surviving entry uids.
     *
     * @return list<int>
     */
    private function fetchVisibleEntryUids(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_ENTRY);
        // The restriction under test is also registered globally and, being
        // enforced, survives removeAll() — it would otherwise be applied a second
        // time with the real extension configuration.
        $this->removeAllRestrictions($queryBuilder);
        $expression = $this->subject->buildExpression($this->queriedTables(), $queryBuilder->expr());

        $queryBuilder->select('en.uid')->from(self::TABLE_ENTRY, 'en')->orderBy('en.uid');
        if ((string)$expression !== '') {
            $queryBuilder->andWhere($expression);
        }

        return array_map('intval', $queryBuilder->executeQuery()->fetchFirstColumn());
    }

    private function insertEntry(int $uid, int $eventUid, string $recordType = 'event-appointment'): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_ENTRY)->insert(
            self::TABLE_ENTRY,
            [
                'uid' => $uid,
                'pid' => 2,
                'record_type' => $recordType,
                'event' => $eventUid,
                'calendar' => 1,
                'title' => 'Entry ' . $uid,
                'start_date' => 1767225600,
                'end_date' => 1767229200,
                'hidden' => 0,
                'deleted' => 0,
            ]
        );
    }

    private function backendUser(int $uid, bool $canViewAllEvents): BackendUserAuthentication
    {
        $backendUser = self::createStub(BackendUserAuthentication::class);
        $backendUser->method('getUserId')->willReturn($uid);
        $backendUser->method('check')->willReturn($canViewAllEvents);

        return $backendUser;
    }

    private function frontendUser(int $uid): FrontendUserAuthentication
    {
        $user = new FrontendUserAuthentication();
        $user->user = ['uid' => $uid, 'username' => 'fe_user_' . $uid];

        return $user;
    }

    /**
     * Rebuilds the subject against a stubbed extension configuration, because the
     * setting is read on every buildExpression() call through the injected
     * service.
     */
    private function givenUnrestrictedRecordTypes(string $configured): void
    {
        $extensionConfiguration = self::createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($configured);

        $this->subject = new EntryRestriction(
            $this->get(Context::class),
            $this->get(ConnectionPool::class),
            $extensionConfiguration,
            new CalendarPermissionService(),
        );
    }

    private function givenFrontendRequest(?FrontendUserAuthentication $user = null): void
    {
        $request = $this->request(SystemEnvironmentBuilder::REQUESTTYPE_FE);
        if ($user !== null) {
            $request = $request->withAttribute('frontend.user', $user);
        }
        $GLOBALS['TYPO3_REQUEST'] = $request;
    }

    private function givenBackendRequest(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = $this->request(SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    private function request(int $requestType): ServerRequestInterface
    {
        return (new ServerRequest('https://example.com/'))
            ->withAttribute('applicationType', $requestType);
    }
}
