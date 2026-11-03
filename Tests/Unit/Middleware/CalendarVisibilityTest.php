<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use Xima\XimaTypo3Calendar\Middleware\CalendarVisibility;
use Xima\XimaTypo3Calendar\Service\CalendarVisibilityService;

final class CalendarVisibilityTest extends TestCase
{
    private ?ServerRequestInterface $handledRequest = null;

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_REQUEST']);
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{string, array{scope: string, resource: string, item: bool}}>
     */
    public static function apiRequestDataProvider(): iterable
    {
        yield 'public collection' => [
            'https://example.com/api/events',
            ['scope' => CalendarVisibilityService::SCOPE_PUBLIC, 'resource' => 'events', 'item' => false],
        ];
        yield 'public item' => [
            'https://example.com/api/events/12',
            ['scope' => CalendarVisibilityService::SCOPE_PUBLIC, 'resource' => 'events', 'item' => true],
        ];
        yield 'internal collection' => [
            'https://example.com/api/appointments',
            ['scope' => CalendarVisibilityService::SCOPE_INTERNAL, 'resource' => 'appointments', 'item' => false],
        ];
        yield 'collection below a language prefix' => [
            'https://example.com/de/api/events',
            ['scope' => CalendarVisibilityService::SCOPE_PUBLIC, 'resource' => 'events', 'item' => false],
        ];
    }

    /**
     * @param array{scope: string, resource: string, item: bool} $expectedContext
     */
    #[Test]
    #[DataProvider('apiRequestDataProvider')]
    public function marksApiRequestsWithTheirVisibilityContext(string $uri, array $expectedContext): void
    {
        $this->process($this->request($uri, '/de/'));

        self::assertSame($expectedContext, $this->handledRequest?->getAttribute(CalendarVisibilityService::REQUEST_ATTRIBUTE));
        self::assertSame($this->handledRequest, $GLOBALS['TYPO3_REQUEST'] ?? null);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonApiRequestDataProvider(): iterable
    {
        yield 'regular page' => ['https://example.com/events', '/api/'];
        yield 'api prefix without resource' => ['https://example.com/api/', '/api/'];
        yield 'no api prefix configured' => ['https://example.com/api/events', ''];
    }

    #[Test]
    #[DataProvider('nonApiRequestDataProvider')]
    public function leavesOtherRequestsUntouched(string $uri, string $apiPrefix): void
    {
        $this->process($this->request($uri, '/de/', $apiPrefix));

        self::assertNull($this->handledRequest?->getAttribute(CalendarVisibilityService::REQUEST_ATTRIBUTE));
    }

    #[Test]
    public function leavesRequestsWithoutSiteUntouched(): void
    {
        $this->process(new ServerRequest('https://example.com/api/events'));

        self::assertNull($this->handledRequest?->getAttribute(CalendarVisibilityService::REQUEST_ATTRIBUTE));
    }

    private function request(string $uri, string $germanBase, string $apiPrefix = '/api/'): ServerRequestInterface
    {
        $site = new Site(
            'main',
            1,
            [
                'base' => 'https://example.com/',
                'languages' => [
                    ['languageId' => 0, 'title' => 'English', 'base' => '/', 'locale' => 'en_US.UTF-8'],
                    ['languageId' => 1, 'title' => 'German', 'base' => $germanBase, 'locale' => 'de_DE.UTF-8'],
                ],
            ],
            SiteSettings::createFromSettingsTree([
                'tca_api' => ['apiPrefix' => $apiPrefix],
                'xima_typo3_calendar' => ['internalApiResources' => 'appointments'],
            ]),
        );
        $language = str_starts_with((string)parse_url($uri, PHP_URL_PATH), $germanBase)
            ? $site->getLanguageById(1)
            : $site->getDefaultLanguage();

        return (new ServerRequest($uri))
            ->withAttribute('site', $site)
            ->withAttribute('language', $language);
    }

    private function process(ServerRequestInterface $request): void
    {
        $handler = new class($this->captureHandledRequest(...)) implements RequestHandlerInterface {
            public function __construct(private readonly \Closure $capture)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                ($this->capture)($request);

                return new Response();
            }
        };

        (new CalendarVisibility())->process($request, $handler);
    }

    private function captureHandledRequest(ServerRequestInterface $request): void
    {
        $this->handledRequest = $request;
    }
}
