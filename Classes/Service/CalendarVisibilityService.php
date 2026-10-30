<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Service;

use Psr\Http\Message\ServerRequestInterface;

final class CalendarVisibilityService
{
    public const REQUEST_ATTRIBUTE = 'xima_typo3_calendar.visibility';

    public const SCOPE_PUBLIC = 'public';

    public const SCOPE_INTERNAL = 'internal';

    /**
     * @return array{scope: string, resource: string, item: bool}|null
     */
    public function getApiContext(ServerRequestInterface $request): ?array
    {
        $context = $request->getAttribute(self::REQUEST_ATTRIBUTE);
        if (!is_array($context) || !isset($context['scope'], $context['resource'])) {
            return null;
        }

        return [
            'scope' => (string)$context['scope'],
            'resource' => (string)$context['resource'],
            'item' => (bool)($context['item'] ?? false),
        ];
    }

    public function isInternalApiRequest(ServerRequestInterface $request): bool
    {
        $context = $this->getApiContext($request);

        return $context !== null && $context['scope'] === self::SCOPE_INTERNAL;
    }

    public function isPublicApiCollection(ServerRequestInterface $request): bool
    {
        $context = $this->getApiContext($request);

        return $context !== null
            && $context['scope'] === self::SCOPE_PUBLIC
            && !$context['item'];
    }

    public function allowsPreview(ServerRequestInterface $request): bool
    {
        $context = $this->getApiContext($request);

        return $context === null || $context['item'];
    }
}
