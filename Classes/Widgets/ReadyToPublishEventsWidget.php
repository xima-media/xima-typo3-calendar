<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Dashboard\Widgets\ButtonProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\ListDataProviderInterface;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;

class ReadyToPublishEventsWidget implements WidgetInterface, RequestAwareWidgetInterface
{
    private ServerRequestInterface $request;

    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly ListDataProviderInterface $dataProvider,
        private readonly ButtonProviderInterface $buttonProvider,
        private readonly BackendViewFactory $backendViewFactory,
        private readonly array $options = []
    ) {
    }
    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function renderWidgetContent(): string
    {
        $view = $this->backendViewFactory->create($this->request);

        $view->assignMultiple([
            'events' => $this->dataProvider->getItems(),
            'button' => $this->buttonProvider,
            'configuration' => $this->configuration,
            'options' => $this->options,
        ]);

        return $view->render('Widgets/XimaTypo3CalendarEventsWidget');
    }

    /**
     * @inheritDoc
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
