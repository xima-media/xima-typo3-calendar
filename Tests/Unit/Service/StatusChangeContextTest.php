<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\SingletonInterface;
use Xima\XimaTypo3Calendar\Service\StatusChangeContext;

final class StatusChangeContextTest extends TestCase
{
    private StatusChangeContext $subject;

    protected function setUp(): void
    {
        $this->subject = new StatusChangeContext();
    }

    /**
     * A status change that does not originate from the backend status modal must
     * keep the previous behaviour of always notifying the owner.
     */
    #[Test]
    public function notifiesTheOwnerByDefault(): void
    {
        self::assertTrue($this->subject->shouldNotifyOwner());
    }

    #[Test]
    public function ownerNotificationCanBeSuppressed(): void
    {
        $this->subject->setNotifyOwner(false);

        self::assertFalse($this->subject->shouldNotifyOwner());
    }

    #[Test]
    public function ownerNotificationCanBeReEnabled(): void
    {
        $this->subject->setNotifyOwner(false);
        $this->subject->setNotifyOwner(true);

        self::assertTrue($this->subject->shouldNotifyOwner());
    }

    #[Test]
    public function isNotFromBackendUntilABackendUserIsRecorded(): void
    {
        self::assertFalse($this->subject->isFromBackend());
        self::assertNull($this->subject->getBackendUser());
    }

    #[Test]
    public function recordingABackendUserMarksTheChangeAsBackendInitiated(): void
    {
        $backendUser = ['uid' => 7, 'name' => 'Editor', 'email' => 'editor@example.com'];

        $this->subject->setBackendUser($backendUser);

        self::assertTrue($this->subject->isFromBackend());
        self::assertSame($backendUser, $this->subject->getBackendUser());
    }

    /**
     * Calling the setter is itself the backend marker: the modal endpoint may not
     * be able to resolve a user record but the change still came from the backend.
     */
    #[Test]
    public function recordingANullBackendUserStillMarksTheChangeAsBackendInitiated(): void
    {
        $this->subject->setBackendUser(null);

        self::assertTrue($this->subject->isFromBackend());
        self::assertNull($this->subject->getBackendUser());
    }

    #[Test]
    public function backendUserNameIsEmptyWithoutAUser(): void
    {
        self::assertSame('', $this->subject->getBackendUserName());
    }

    #[Test]
    public function backendUserNameIsTrimmed(): void
    {
        $this->subject->setBackendUser(['uid' => 1, 'name' => "  Jane Doe \n", 'email' => 'jane@example.com']);

        self::assertSame('Jane Doe', $this->subject->getBackendUserName());
    }

    /**
     * The context is resolved once per request and shared between the status modal
     * endpoint and the workflow notification listener — it only works as a
     * SingletonInterface implementation.
     */
    #[Test]
    public function isASingleton(): void
    {
        self::assertInstanceOf(SingletonInterface::class, $this->subject);
    }
}
