<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Xima\XimaTypo3Calendar\Service\NotificationRecipientResolver;
use Xima\XimaTypo3Calendar\Tests\Functional\AbstractCalendarFunctionalTestCase;

/**
 * Event 1 is assigned to category 1 (Workshops) by the fixture. Backend user 11
 * subscribed to category 1, user 12 to category 9, and user 10 restricted
 * nothing — which is the distinction the category filter has to get right.
 */
final class NotificationRecipientResolverTest extends AbstractCalendarFunctionalTestCase
{
    private NotificationRecipientResolver $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/calendar.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/notifications.csv');

        $this->subject = new NotificationRecipientResolver($this->get(ConnectionPool::class));
    }

    #[Test]
    public function returnsSubscribersWithoutACategoryRestriction(): void
    {
        $emails = $this->subscribedEmails(1);

        self::assertContains('sam@example.com', $emails);
    }

    #[Test]
    public function returnsSubscribersWhoseCategoryMatchesTheEvent(): void
    {
        $emails = $this->subscribedEmails(1);

        self::assertContains('cat-one@example.com', $emails);
    }

    #[Test]
    public function excludesSubscribersWhoseCategoriesDoNotIntersectTheEvent(): void
    {
        $emails = $this->subscribedEmails(1);

        self::assertNotContains('cat-nine@example.com', $emails);
    }

    #[Test]
    public function excludesUsersWhoDidNotEnableThePreference(): void
    {
        $emails = $this->subscribedEmails(1);

        self::assertNotContains('nina@example.com', $emails);
    }

    #[Test]
    public function readsTheRequestedPreferenceFieldOnly(): void
    {
        $emails = $this->subscribedEmails(1, NotificationRecipientResolver::PREFERENCE_LIVE);

        self::assertSame(['nina@example.com'], $emails);
    }

    #[Test]
    public function skipsUsersWithoutAnEmailAddress(): void
    {
        $emails = $this->subscribedEmails(1);

        self::assertNotContains('', $emails);
        self::assertCount(count(array_unique($emails)), $emails);
    }

    #[Test]
    public function fallsBackToTheUsernameWhenTheRealNameIsEmpty(): void
    {
        $recipients = $this->subject->getSubscribedBackendRecipients(1, NotificationRecipientResolver::PREFERENCE_REVIEW);
        $byEmail = array_column($recipients, 'name', 'email');

        self::assertSame('username_only', $byEmail['username-only@example.com']);
    }

    #[Test]
    public function marksSubscribedRecipientsAsBackendUsers(): void
    {
        $recipients = $this->subject->getSubscribedBackendRecipients(1, NotificationRecipientResolver::PREFERENCE_REVIEW);

        foreach ($recipients as $recipient) {
            self::assertTrue($recipient['isBackendUser']);
        }
    }

    /**
     * An event without categories places no restriction on subscribers who
     * limited themselves to categories — they simply cannot intersect, and are
     * therefore dropped.
     */
    #[Test]
    public function categoryRestrictedSubscribersAreDroppedForAnUncategorizedEvent(): void
    {
        $emails = $this->subscribedEmails(2);

        self::assertContains('sam@example.com', $emails);
        self::assertNotContains('cat-one@example.com', $emails);
        self::assertNotContains('cat-nine@example.com', $emails);
    }

    #[Test]
    public function resolvesAFrontendUserOwner(): void
    {
        $recipient = $this->subject->getOwnerRecipient(1);

        self::assertSame('fred@example.com', $recipient['email']);
        self::assertSame('Fred Frontend', $recipient['name']);
        self::assertFalse($recipient['isBackendUser']);
    }

    #[Test]
    public function fallsBackToTheFrontendUsernameWhenNoRealNameIsSet(): void
    {
        $recipient = $this->subject->getOwnerRecipient(3);

        self::assertSame('fe_username_only', $recipient['name']);
    }

    #[Test]
    public function returnsNoOwnerForAFrontendUserWithoutAnEmail(): void
    {
        self::assertNull($this->subject->getOwnerRecipient(2));
    }

    #[Test]
    public function returnsNoOwnerForAnUnknownOrUnsetUid(): void
    {
        self::assertNull($this->subject->getOwnerRecipient(0));
        self::assertNull($this->subject->getOwnerRecipient(-1));
        self::assertNull($this->subject->getOwnerRecipient(9999));
    }

    #[Test]
    public function resolvesABackendUserOwner(): void
    {
        $recipient = $this->subject->getBackendOwnerRecipient(16);

        self::assertSame('bea@example.com', $recipient['email']);
        self::assertSame('Bea Owner', $recipient['name']);
        self::assertTrue($recipient['isBackendUser']);
    }

    #[Test]
    public function collectsBothTheFrontendAndTheBackendOwner(): void
    {
        $recipients = $this->subject->getOwnerRecipients(['owner' => 1, 'owner_be_user' => 16]);

        self::assertSame(
            ['fred@example.com', 'bea@example.com'],
            array_column($recipients, 'email')
        );
    }

    /**
     * Suppresses the self-notification a backend user would otherwise receive for
     * changing the status of their own event.
     */
    #[Test]
    public function excludesTheActingBackendUserFromTheOwnerRecipients(): void
    {
        $recipients = $this->subject->getOwnerRecipients(['owner' => 1, 'owner_be_user' => 16], 16);

        self::assertSame(['fred@example.com'], array_column($recipients, 'email'));
    }

    #[Test]
    public function doesNotExcludeADifferentBackendUser(): void
    {
        $recipients = $this->subject->getOwnerRecipients(['owner' => 0, 'owner_be_user' => 16], 10);

        self::assertSame(['bea@example.com'], array_column($recipients, 'email'));
    }

    #[Test]
    public function returnsNoOwnersForAnUnownedEvent(): void
    {
        self::assertSame([], $this->subject->getOwnerRecipients([]));
    }

    /**
     * @return list<string>
     */
    private function subscribedEmails(int $eventUid, string $preference = NotificationRecipientResolver::PREFERENCE_REVIEW): array
    {
        return array_column($this->subject->getSubscribedBackendRecipients($eventUid, $preference), 'email');
    }
}
