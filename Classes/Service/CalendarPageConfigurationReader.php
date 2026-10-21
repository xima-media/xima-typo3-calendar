<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;

final class CalendarPageConfigurationReader
{
    /** @var array<int, array<string, mixed>> */
    private array $configByPageId = [];

    /** @return array<string, mixed> */
    public function read(RequestInterface|ServerRequestInterface $request): array
    {
        return $this->readForPageId((int)($request->getQueryParams()['id'] ?? 0));
    }

    /** @return array<string, mixed> */
    public function readForPageId(int $pageId): array
    {
        if (!array_key_exists($pageId, $this->configByPageId)) {
            $pageTsConfig = BackendUtility::getPagesTSconfig($pageId);
            $this->configByPageId[$pageId] = is_array($pageTsConfig['mod.']['tx_ximatypo3calendar.'] ?? null)
                ? $pageTsConfig['mod.']['tx_ximatypo3calendar.']
                : [];
        }

        return $this->configByPageId[$pageId];
    }
}
