<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Xima\XimaTypo3Calendar\Service\CalendarVisibilityService;

final class CalendarVisibility implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site) {
            return $handler->handle($request);
        }

        $settings = $site->getSettings();
        $apiPrefix = rtrim('/' . ltrim((string)$settings->get('tca_api.apiPrefix', ''), '/'), '/');
        if ($apiPrefix === '') {
            return $handler->handle($request);
        }

        $requestPrefix = $this->resolveRequestPrefix($request, $apiPrefix);
        if ($requestPrefix === null) {
            return $handler->handle($request);
        }

        $segments = explode('/', trim(substr($request->getUri()->getPath(), strlen($requestPrefix)), '/'));
        $resource = $segments[0];
        if ($resource === '') {
            return $handler->handle($request);
        }

        $internalResources = GeneralUtility::trimExplode(
            ',',
            (string)$settings->get('xima_typo3_calendar.internalApiResources', ''),
            true,
        );
        $context = [
            'scope' => in_array($resource, $internalResources, true)
                ? CalendarVisibilityService::SCOPE_INTERNAL
                : CalendarVisibilityService::SCOPE_PUBLIC,
            'resource' => $resource,
            'item' => isset($segments[1]) && $segments[1] !== '' && ctype_digit($segments[1]),
        ];

        $request = $request->withAttribute(CalendarVisibilityService::REQUEST_ATTRIBUTE, $context);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $handler->handle($request);
    }

    private function resolveRequestPrefix(ServerRequestInterface $request, string $apiPrefix): ?string
    {
        $prefixes = [$apiPrefix];
        $language = $request->getAttribute('language');
        if ($language instanceof SiteLanguage) {
            $languageBasePath = rtrim($language->getBase()->getPath(), '/');
            if ($languageBasePath !== '') {
                $prefixes[] = $languageBasePath . $apiPrefix;
            }
        }

        usort($prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $path = $request->getUri()->getPath();
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $prefix;
            }
        }

        return null;
    }
}
