<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use Xima\XimaTypo3Calendar\Service\CalendarVisibilityService;

final class CalendarVisibilityServiceTest extends \PHPUnit\Framework\TestCase
{
    private CalendarVisibilityService $subject;

    protected function setUp(): void
    {
        $this->subject = new CalendarVisibilityService();
    }

    #[Test]
    public function regularFrontendRequestsKeepPreviewSupport(): void
    {
        $request = new ServerRequest('https://example.com/');

        self::assertTrue($this->subject->allowsPreview($request));
        self::assertFalse($this->subject->isInternalApiRequest($request));
    }

    #[Test]
    public function publicApiCollectionsDoNotKeepPreviewOwnership(): void
    {
        $request = $this->requestWithContext(
            CalendarVisibilityService::SCOPE_PUBLIC,
            'public-appointments',
            false,
        );

        self::assertTrue($this->subject->isPublicApiCollection($request));
        self::assertFalse($this->subject->allowsPreview($request));
    }

    #[Test]
    public function publicApiItemsKeepPreviewSupport(): void
    {
        $request = $this->requestWithContext(
            CalendarVisibilityService::SCOPE_PUBLIC,
            'public-events',
            true,
        );

        self::assertFalse($this->subject->isPublicApiCollection($request));
        self::assertTrue($this->subject->allowsPreview($request));
    }

    #[Test]
    public function internalApiResourcesAllowAllEventStatuses(): void
    {
        $request = $this->requestWithContext(
            CalendarVisibilityService::SCOPE_INTERNAL,
            'appointments',
            false,
        );

        self::assertTrue($this->subject->isInternalApiRequest($request));
        self::assertFalse($this->subject->isPublicApiCollection($request));
    }

    private function requestWithContext(string $scope, string $resource, bool $item): ServerRequest
    {
        return (new ServerRequest('https://example.com/'))
            ->withAttribute(CalendarVisibilityService::REQUEST_ATTRIBUTE, [
                'scope' => $scope,
                'resource' => $resource,
                'item' => $item,
            ]);
    }
}
