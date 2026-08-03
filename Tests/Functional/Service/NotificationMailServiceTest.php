<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use Xima\XimaTypo3Calendar\Service\NotificationMailService;
use Xima\XimaTypo3Calendar\Service\StatusChangeContext;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * The service assembles one FluidEmail per recipient and hands it to the mailer.
 *
 * FluidEmail exposes no accessor for its assigned variables, so what the service
 * assigned is asserted where it matters most — the changed-field filtering — by
 * rendering the template body. The remaining label resolution is executed by
 * every test here (a failure in it surfaces as an exception) but not asserted
 * field by field, which would only pin the template markup.
 */
final class NotificationMailServiceTest extends AbstractCalendarFunctionalTestCase
{
    /** @var list<RawMessage> */
    private array $sentMessages = [];

    /** @var list<array{message: string, context: array<string, mixed>}> */
    private array $loggedErrors = [];

    private StatusChangeContext $statusChangeContext;

    private bool $mailerThrows = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sentMessages = [];
        $this->loggedErrors = [];
        $this->mailerThrows = false;
        $this->statusChangeContext = new StatusChangeContext();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    #[Test]
    public function sendsNothingWithoutRecipients(): void
    {
        $this->subject()->sendNotification('EventReviewNotification', [], $this->eventRecord());

        self::assertSame([], $this->sentMessages);
    }

    #[Test]
    public function sendsOneMessagePerRecipient(): void
    {
        $this->subject()->sendNotification(
            'EventReviewNotification',
            [$this->recipient('a@example.com'), $this->recipient('b@example.com')],
            $this->eventRecord()
        );

        self::assertCount(2, $this->sentMessages);
    }

    #[Test]
    public function addressesEachRecipientByEmailAndName(): void
    {
        $this->subject()->sendNotification(
            'EventReviewNotification',
            [$this->recipient('reviewer@example.com', 'Rita Reviewer')],
            $this->eventRecord()
        );

        $to = $this->firstSentEmail()->getTo();
        self::assertCount(1, $to);
        self::assertSame('reviewer@example.com', $to[0]->getAddress());
        self::assertSame('Rita Reviewer', $to[0]->getName());
    }

    /**
     * A recipient whose delivery fails must not take the remaining ones down
     * with it — the workflow notification is best effort.
     */
    #[Test]
    public function keepsSendingAfterAFailedDelivery(): void
    {
        $this->mailerThrows = true;

        $this->subject()->sendNotification(
            'EventReviewNotification',
            [$this->recipient('a@example.com'), $this->recipient('b@example.com')],
            $this->eventRecord()
        );

        self::assertCount(2, $this->loggedErrors);
    }

    #[Test]
    public function logsTheTemplateAndRecipientOfAFailedDelivery(): void
    {
        $this->mailerThrows = true;

        $this->subject()->sendNotification(
            'EventReviewNotification',
            [$this->recipient('broken@example.com')],
            $this->eventRecord()
        );

        self::assertCount(1, $this->loggedErrors);
        self::assertSame('Failed to send calendar workflow notification', $this->loggedErrors[0]['message']);
        self::assertSame('EventReviewNotification', $this->loggedErrors[0]['context']['template']);
        self::assertSame('broken@example.com', $this->loggedErrors[0]['context']['recipient']);
        self::assertArrayHasKey('exception', $this->loggedErrors[0]['context']);
    }

    #[Test]
    public function doesNotLogAnythingOnASuccessfulDelivery(): void
    {
        $this->subject()->sendNotification(
            'EventReviewNotification',
            [$this->recipient('a@example.com')],
            $this->eventRecord()
        );

        self::assertSame([], $this->loggedErrors);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function templateProvider(): array
    {
        return [
            'live'         => ['EventLiveNotification'],
            'review'       => ['EventReviewNotification'],
            'rejected'     => ['EventRejectedNotification'],
            'published'    => ['EventPublishedNotification'],
            'draft'        => ['EventDraftNotification'],
            'review owner' => ['EventReviewOwnerNotification'],
            // Falls back to the shared labels only.
            'unknown'      => ['SomeUnregisteredTemplate'],
        ];
    }

    #[Test]
    #[DataProvider('templateProvider')]
    public function resolvesLabelsForEveryTemplate(string $template): void
    {
        $this->subject()->sendNotification($template, [$this->recipient('a@example.com')], $this->eventRecord());

        self::assertCount(1, $this->sentMessages);
    }

    /**
     * The riskiest assertion in this class: it renders the Fluid body to read
     * back what was assigned. Control fields are noise in a change notification,
     * so they must be filtered; a field the TCA does not describe falls back to
     * its raw name.
     */
    #[Test]
    public function rendersOnlyEditorialChangedFields(): void
    {
        $this->subject()->sendNotification(
            'EventLiveNotification',
            [$this->recipient('a@example.com')],
            $this->eventRecord(),
            ['made_up_field', 'tstamp', 't3ver_wsid', 'status_message', '']
        );

        $html = (string)$this->firstSentEmail()->getHtmlBody(true);

        self::assertStringContainsString('made_up_field', $html);
        self::assertStringNotContainsString('tstamp', $html);
        self::assertStringNotContainsString('t3ver_wsid', $html);
    }

    #[Test]
    public function sendsWithoutAnyChangedFields(): void
    {
        $this->subject()->sendNotification(
            'EventLiveNotification',
            [$this->recipient('a@example.com')],
            $this->eventRecord(),
            []
        );

        self::assertCount(1, $this->sentMessages);
    }

    /**
     * Only recipients with backend access get an edit link. In frontend context
     * the backend UriBuilder is unavailable, so the service falls back to a
     * hand-built absolute path.
     */
    #[Test]
    public function buildsAFrontendFallbackEditUrlForBackendRecipients(): void
    {
        $this->givenFrontendRequest();

        $this->subject()->sendNotification(
            'EventReviewNotification',
            [$this->recipient('editor@example.com', 'Eddie Editor', isBackendUser: true)],
            $this->eventRecord()
        );

        self::assertCount(1, $this->sentMessages);
    }

    #[Test]
    public function buildsABackendEditUrlForBackendRecipients(): void
    {
        $this->subject()->sendNotification(
            'EventReviewNotification',
            [$this->recipient('editor@example.com', 'Eddie Editor', isBackendUser: true)],
            $this->eventRecord()
        );

        self::assertCount(1, $this->sentMessages);
    }

    /**
     * The edit URL is resolved once and reused, so a mixed recipient list still
     * produces exactly one message per recipient.
     */
    #[Test]
    public function handlesAMixedRecipientList(): void
    {
        $this->subject()->sendNotification(
            'EventReviewNotification',
            [
                $this->recipient('owner@example.com'),
                $this->recipient('editor@example.com', 'Eddie Editor', isBackendUser: true),
                $this->recipient('second-editor@example.com', 'Erin Editor', isBackendUser: true),
            ],
            $this->eventRecord()
        );

        self::assertCount(3, $this->sentMessages);
    }

    /**
     * Status changes performed through the backend modal expose the acting user
     * so templates can name who made the change.
     */
    #[Test]
    public function sendsWithAnActingBackendUserInTheContext(): void
    {
        $this->statusChangeContext->setBackendUser([
            'uid' => 1,
            'name' => 'Ada Admin',
            'email' => 'admin@example.com',
        ]);

        $this->subject()->sendNotification(
            'EventReviewNotification',
            [$this->recipient('a@example.com')],
            $this->eventRecord()
        );

        self::assertCount(1, $this->sentMessages);
    }

    #[Test]
    public function sendsForAnEventRecordWithoutUidOrTitle(): void
    {
        $this->subject()->sendNotification(
            'EventReviewNotification',
            [$this->recipient('a@example.com')],
            []
        );

        self::assertCount(1, $this->sentMessages);
    }

    private function subject(): NotificationMailService
    {
        return new NotificationMailService(
            $this->recordingMailer(),
            $this->recordingLogger(),
            $this->get(UriBuilder::class),
            $this->get(LanguageServiceFactory::class),
            $this->statusChangeContext,
        );
    }

    /**
     * The service only ever sends FluidEmail; this narrows the recorded message
     * for the assertions without relying on a PHPStan PHPUnit extension.
     */
    private function firstSentEmail(): FluidEmail
    {
        $message = $this->sentMessages[0] ?? null;
        if (!$message instanceof FluidEmail) {
            self::fail('Expected a FluidEmail to have been handed to the mailer.');
        }

        return $message;
    }

    private function recordingMailer(): MailerInterface
    {
        $mailer = self::createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (RawMessage $message): void {
            if ($this->mailerThrows) {
                $this->sentMessages[] = $message;
                throw new \RuntimeException('transport unavailable', 1785760000);
            }
            $this->sentMessages[] = $message;
        });

        return $mailer;
    }

    private function recordingLogger(): LoggerInterface
    {
        $logger = self::createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function (string|\Stringable $message, array $context = []): void {
            $this->loggedErrors[] = ['message' => (string)$message, 'context' => $context];
        });

        return $logger;
    }

    /**
     * @return array{email: string, name: string, isBackendUser: bool}
     */
    private function recipient(string $email, string $name = 'Recipient', bool $isBackendUser = false): array
    {
        return ['email' => $email, 'name' => $name, 'isBackendUser' => $isBackendUser];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRecord(): array
    {
        return [
            'uid' => 42,
            'title' => 'Annual Conference',
            'status_message' => 'Looks good to me',
        ];
    }

    private function givenFrontendRequest(): void
    {
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
    }
}
