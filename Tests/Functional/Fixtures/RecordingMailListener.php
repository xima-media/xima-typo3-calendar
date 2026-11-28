<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Functional\Fixtures;

use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\Event\BeforeMailerSentMessageEvent;
use TYPO3\CMS\Core\Mail\FluidEmail;

/**
 * Records the template and recipients of every mail before it reaches the transport.
 *
 * Registered as a shared, public service by the `calendar_test` fixture extension. The
 * template name has no getter on FluidEmail, so it is read from its property.
 */
final class RecordingMailListener
{
    /** @var list<array{template: string, to: list<string>}> */
    private array $mails = [];

    public function __invoke(BeforeMailerSentMessageEvent $event): void
    {
        $message = $event->getMessage();
        if (!$message instanceof FluidEmail) {
            return;
        }

        $this->mails[] = [
            'template' => (string)(new \ReflectionProperty(FluidEmail::class, 'templateName'))->getValue($message),
            'to' => array_map(static fn (Address $address): string => $address->getAddress(), $message->getTo()),
        ];
    }

    public function reset(): void
    {
        $this->mails = [];
    }

    /**
     * @return list<array{template: string, to: list<string>}>
     */
    public function all(): array
    {
        return $this->mails;
    }
}
