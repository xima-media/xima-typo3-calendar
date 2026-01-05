<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener\T3api;

use Psr\Http\Message\ServerRequestInterface;
use SourceBroker\T3api\Event\BeforeOperationAccessGrantedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Context\Context;

#[AsEventListener(
    identifier: 'xima-typo3-calendar/t3api/before-operation-access-granted',
)]
readonly class BeforeOperationAccessGrantedEventListener
{
    public function __construct(
        protected Context $context,
    ) {
    }

    public function __invoke(BeforeOperationAccessGrantedEvent $event): void
    {
        $currentUserId = $this->context->getPropertyFromAspect('frontend.user', 'id');
        if ($currentUserId !== null) {
            $event->setExpressionLanguageVariable('currentUserId', $currentUserId);
        }

        $request = $this->getRequest()->getUri();
        $event->setExpressionLanguageVariable('currentUserEventTarget', false);
    }

    private function getRequest(): ?ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'] ?? null;
    }
}
