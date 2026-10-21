<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;

final class CalendarRecordButtonService
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const ENTRY_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    public function __construct(
        private readonly IconFactory $iconFactory,
        private readonly UriBuilder $backendUriBuilder,
    ) {
    }

    public function addButtons(
        ModuleTemplate $moduleTemplate,
        ServerRequestInterface $request,
        int $pid,
        ?string $eventRecordType,
        bool $canCreateEvent,
        bool $canCreateAppointment,
        string $eventTitle,
        string $appointmentTitle,
    ): void {
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $returnUrl = (string)$request->getUri();

        if ($canCreateEvent) {
            $eventUrl = $this->backendUriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [self::EVENT_TABLE => [$pid => 'new']],
                'defVals' => $eventRecordType === null ? [] : [
                    self::EVENT_TABLE => ['record_type' => $eventRecordType],
                ],
                'returnUrl' => $returnUrl,
            ]);
            $this->addButton($buttonBar, (string)$eventUrl, $eventTitle);
        }

        if ($canCreateAppointment) {
            $appointmentUrl = $this->backendUriBuilder->buildUriFromRoute('record_edit', [
                'edit' => [self::ENTRY_TABLE => [$pid => 'new']],
                'defVals' => [self::ENTRY_TABLE => ['record_type' => 'event-appointment']],
                'returnUrl' => $returnUrl,
            ]);
            $this->addButton($buttonBar, (string)$appointmentUrl, $appointmentTitle);
        }
    }

    private function addButton(ButtonBar $buttonBar, string $url, string $title): void
    {
        $button = $buttonBar->makeLinkButton()
            ->setHref($url)
            ->setTitle($title)
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-plus', IconSize::SMALL));
        $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_RIGHT, 1);
    }
}
