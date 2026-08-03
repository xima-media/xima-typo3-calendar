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
        $expression = $this->subject->buildExpression($this->queriedTables(), $queryBuilder->expr());

        $queryBuilder->select('e.uid')->from(self::TABLE_EVENT, 'e')->orderBy('e.uid');
        if ((string)$expression !== '') {
            $queryBuilder->andWhere($expression);
        }

        return array_map('intval', $queryBuilder->executeQuery()->fetchFirstColumn());
    }

    private function givenFrontendRequest(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = $this->request(SystemEnvironmentBuilder::REQUESTTYPE_FE);
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
