<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Database;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use Xima\XimaTypo3Calendar\Database\EventRestriction;
use Xima\XimaTypo3Calendar\Service\CalendarPermissionService;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * The restriction is registered globally in ext_localconf.php and is therefore
 * appended to every query touching the event table. It is exercised here
 * directly against a real ExpressionBuilder so the produced SQL fragment can be
 * asserted without having to stand up a frontend request cycle.
 */
final class EventRestrictionTest extends AbstractCalendarFunctionalTestCase
{
    private EventRestriction $subject;

    private ExpressionBuilder $expressionBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');

        $connectionPool = $this->get(ConnectionPool::class);
        $this->subject = new EventRestriction(
            $this->get(Context::class),
            $connectionPool,
            $this->get(ExtensionConfiguration::class),
            new CalendarPermissionService(),
        );
        $this->expressionBuilder = $connectionPool
            ->getQueryBuilderForTable(self::TABLE_EVENT)
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

    /**
     * Being enforced means the restriction survives `removeAll()` — that is the
     * whole point of the interface, and it is the reason ad-hoc queries in
     * consuming code cannot accidentally expose non-live events. Callers that
     * genuinely need the raw rows have to drop it by type.
     */
    #[Test]
    public function theRestrictionSurvivesRemoveAllOnAQueryBuilder(): void
    {
        $this->givenFrontendRequest();
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_EVENT);
        $queryBuilder->getRestrictions()->removeAll();

        $uids = array_map('intval', $queryBuilder
            ->select('uid')
            ->from(self::TABLE_EVENT)
            ->orderBy('uid')
            ->executeQuery()
            ->fetchFirstColumn());

        self::assertSame([1], $uids);
    }

    #[Test]
    public function producesNoConditionForQueriesThatDoNotTouchTheEventTable(): void
    {
        $this->givenFrontendRequest();

        $expression = $this->subject->buildExpression(['pages' => 'pages'], $this->expressionBuilder);

        self::assertSame('', (string)$expression);
    }

    /**
     * Without a global request — routeEnhancer resolving, some CLI paths — the
     * restriction cannot tell frontend from backend and must not filter anything.
     */
    #[Test]
    public function producesNoConditionWithoutAGlobalRequest(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);

        $expression = $this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertSame('', (string)$expression);
    }

    #[Test]
    public function restrictsAnonymousFrontendVisitorsToLiveEvents(): void
    {
        $this->givenFrontendRequest();

        $expression = (string)$this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        // Identifiers arrive platform-quoted, so compare on the unquoted form.
        $unquoted = str_replace(['`', '"'], '', $expression);
        self::assertStringContainsString('e.status = \'2\'', $unquoted);
        self::assertStringNotContainsString('e.owner', $unquoted);
    }

    #[Test]
    public function anonymousVisitorsOnlySeeLiveEventsInTheDatabase(): void
    {
        $this->givenFrontendRequest();

        self::assertSame([1], $this->fetchVisibleEventUids());
    }

    /**
     * A logged-in frontend user additionally sees the events they own, whatever
     * their status.
     */
    #[Test]
    public function aLoggedInFrontendUserAlsoSeesTheirOwnEvents(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_EVENT)
            ->update(self::TABLE_EVENT, ['owner' => 7], ['uid' => 2]);
        $this->givenFrontendRequest($this->frontendUser(7));

        self::assertSame([1, 2], $this->fetchVisibleEventUids());
    }

    #[Test]
    public function theOwnerConditionNamesTheFrontendUserUid(): void
    {
        $this->givenFrontendRequest($this->frontendUser(7));

        $expression = (string)$this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertStringContainsString('e.owner', str_replace(['`', '"'], '', $expression));
        self::assertStringContainsString('7', $expression);
    }

    /**
     * Record types listed in `restrictions.unrestrictedRecordTypes` are exempt so
     * other projects can introduce event record types governed by their own
     * access rules.
     */
    #[Test]
    public function eventsOfAnUnrestrictedRecordTypeStayVisible(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_EVENT)
            ->update(self::TABLE_EVENT, ['record_type' => 'private-event'], ['uid' => 3]);
        $this->givenUnrestrictedRecordTypes('private-event');
        $this->givenFrontendRequest();

        self::assertSame([1, 3], $this->fetchVisibleEventUids());
    }

    #[Test]
    public function theRecordTypeExemptionAcceptsACommaSeparatedList(): void
    {
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_EVENT)
            ->update(self::TABLE_EVENT, ['record_type' => 'private-event'], ['uid' => 2]);
        $this->getConnectionPool()->getConnectionForTable(self::TABLE_EVENT)
            ->update(self::TABLE_EVENT, ['record_type' => 'internal-event'], ['uid' => 3]);
        $this->givenUnrestrictedRecordTypes('private-event, internal-event');
        $this->givenFrontendRequest();

        self::assertSame([1, 2, 3], $this->fetchVisibleEventUids());
    }

    #[Test]
    public function anEmptyRecordTypeSettingExemptsNothing(): void
    {
        $this->givenUnrestrictedRecordTypes('');
        $this->givenFrontendRequest();

        self::assertSame([1], $this->fetchVisibleEventUids());
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
     * included — must not be filtered at all.
     */
    #[Test]
    public function producesNoConditionForAnAdministrator(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $this->givenBackendRequest();

        $expression = $this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertSame('', (string)$expression);
    }

    #[Test]
    public function anAdministratorSeesEveryEvent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->setUpBackendUser(1);
        $this->givenBackendRequest();

        self::assertSame([1, 2, 3], $this->fetchVisibleEventUids());
    }

    /**
     * The restriction bails out for CLI so scheduler tasks and commands keep
     * seeing every record.
     */
    #[Test]
    public function producesNoConditionInCliContext(): void
    {
        $GLOBALS['BE_USER'] = new \TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication();
        $this->givenFrontendRequest();

        $expression = $this->subject->buildExpression($this->queriedTables(), $this->expressionBuilder);

        self::assertSame('', (string)$expression);
    }

    /**
     * @return array<string, string>
     */
    private function queriedTables(): array
    {
        return ['e' => self::TABLE_EVENT];
    }

    /**
     * Runs a query through the restriction and returns the surviving event uids.
     *
     * @return list<int>
     */
    private function fetchVisibleEventUids(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_EVENT);
        // The restriction under test is also registered globally, so it would be
        // applied a second time — with the real extension configuration rather
        // than the one the test set up. Being enforced, it survives removeAll().
        $this->removeAllRestrictions($queryBuilder);
        $expression = $this->subject->buildExpression($this->queriedTables(), $queryBuilder->expr());

        $queryBuilder->select('e.uid')->from(self::TABLE_EVENT, 'e')->orderBy('e.uid');
        if ((string)$expression !== '') {
            $queryBuilder->andWhere($expression);
        }

        return array_map('intval', $queryBuilder->executeQuery()->fetchFirstColumn());
    }

    private function givenFrontendRequest(?FrontendUserAuthentication $user = null): void
    {
        $request = $this->request(SystemEnvironmentBuilder::REQUESTTYPE_FE);
        if ($user !== null) {
            $request = $request->withAttribute('frontend.user', $user);
        }
        $GLOBALS['TYPO3_REQUEST'] = $request;
    }

    private function frontendUser(int $uid): FrontendUserAuthentication
    {
        $user = new FrontendUserAuthentication();
        $user->user = ['uid' => $uid, 'username' => 'fe_user_' . $uid];

        return $user;
    }

    /**
     * Rebuilds the subject against a stubbed extension configuration, because the
     * setting is read once per buildExpression() call through the injected
     * service.
     */
    private function givenUnrestrictedRecordTypes(string $configured): void
    {
        $extensionConfiguration = self::createStub(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($configured);

        $this->subject = new EventRestriction(
            $this->get(Context::class),
            $this->get(ConnectionPool::class),
            $extensionConfiguration,
            new CalendarPermissionService(),
        );
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
