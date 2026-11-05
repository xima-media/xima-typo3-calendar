<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsEventListener(
    identifier: 'xima-typo3-calendar/add-appointment-tab-focus-module',
    event: AfterFormEnginePageInitializedEvent::class,
    method: '__invoke',
)]
final readonly class AddAppointmentTabFocusModule
{
    public function __construct(private PageRenderer $pageRenderer)
    {
    }

    public function __invoke(AfterFormEnginePageInitializedEvent $event): void
    {
        $this->pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction(
            JavaScriptModuleInstruction::create('@xima/xima-typo3-calendar/Event/calendar-appointment-tab-focus.js')
        );
    }
}
