<?php

namespace Xima\XimaTypo3Calendar\Controller\Frontend;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use Xima\XimaTypo3Calendar\Domain\Repository\EventRepository;

class EventController extends ActionController
{
    protected EventRepository $eventRepository;

    public function __construct(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    public function latestAction(): ResponseInterface
    {
        $maxItems = 3; // Default value

        if ($this->settings['maxItems'] && MathUtility::canBeInterpretedAsInteger($this->settings['maxItems'])) {
            $maxItems = (int)$this->settings['maxItems'] ?? 10;
        }

        $events = $this->eventRepository->findLatest($maxItems);

        $this->view->assign('events', $events);

        $this->addCacheTag();

        return $this->htmlResponse();
    }

    public function listAction(): ResponseInterface
    {
        $maxItems = 10; // Default value

        if (($this->settings['maxItems'] ?? false) && MathUtility::canBeInterpretedAsInteger($this->settings['maxItems'])) {
            $maxItems = (int)$this->settings['maxItems'];
        }

        $events = $this->eventRepository->findPublished($maxItems);

        $header = $this->request->getAttribute('currentContentObject')?->data['header'] ?? '';

        $this->view->assign('header', $header);
        $this->view->assign('events', $events);

        $this->addCacheTag();

        return $this->htmlResponse();
    }

    protected function addCacheTag(): void
    {
        // Add cache tag
        if (!empty($GLOBALS['TSFE']) && is_object($GLOBALS['TSFE'])) {
            static $cacheTagsSet = false;
            $typoScriptFrontendController = $GLOBALS['TSFE'];
            if (!$cacheTagsSet) {
                $typoScriptFrontendController->addCacheTags(['calendar_events']);
                $cacheTagsSet = true;
            }
        }
    }
}
