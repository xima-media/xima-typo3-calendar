<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Routing\Aspect;

use TYPO3\CMS\Core\Routing\Aspect\PersistedPatternMapper;

/**
 * Maps an event to `<uid>-<slug>` and back.
 *
 * `PersistedPatternMapper` composes the route segment from the record without checking it
 * against its own pattern, so an event with an empty slug would produce a dangling `123-`.
 * Discarding such a value lets the router fall through to the slug-less route instead.
 */
final class EventPathMapper extends PersistedPatternMapper
{
    public function generate(string $value): ?string
    {
        $result = parent::generate($value);
        if ($result === null || !preg_match('#' . $this->routeFieldPattern . '#', $result)) {
            return null;
        }

        return $result;
    }
}
