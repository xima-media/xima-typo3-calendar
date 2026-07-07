<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Sends the calendar workflow notification emails.
 *
 * Kept separate from the event listener so the mailing concern (building a
 * FluidEmail, per-recipient delivery, transport error handling) lives in one
 * place and can be reused. Every recipient receives their own message and a
 * failing send never propagates out — a broken address or transport must not
 * abort the DataHandler save that triggered the notification.
 */
final readonly class NotificationMailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<int, array{email: string, name: string}> $recipients
     * @param array<string, mixed> $assignments
     */
    public function sendToRecipients(string $template, array $recipients, array $assignments): void
    {
        $request = $this->getRequest();

        foreach ($recipients as $recipient) {
            try {
                $email = GeneralUtility::makeInstance(FluidEmail::class)
                    ->format(FluidEmail::FORMAT_HTML)
                    ->setTemplate($template)
                    ->assignMultiple($assignments)
                    ->addTo(new Address($recipient['email'], $recipient['name']));

                if ($request instanceof ServerRequestInterface) {
                    $email->setRequest($request);
                }

                $this->mailer->send($email);
            } catch (\Throwable $exception) {
                $this->logger->error('Failed to send calendar workflow notification', [
                    'template' => $template,
                    'recipient' => $recipient['email'],
                    'exception' => $exception,
                ]);
            }
        }
    }

    private function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}
