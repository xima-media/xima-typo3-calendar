<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Controller\EditDocumentController;
use TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Page\JavaScriptRenderer;
use TYPO3\CMS\Core\Page\PageRenderer;
use Xima\XimaTypo3Calendar\EventListener\AddAppointmentTabFocusModule;

final class AddAppointmentTabFocusModuleTest extends TestCase
{
    #[Test]
    public function addsTheModuleWhenAnAppointmentWasSelected(): void
    {
        $javaScriptRenderer = $this->createMock(JavaScriptRenderer::class);
        $javaScriptRenderer->expects(self::once())
            ->method('addJavaScriptModuleInstruction')
            ->with(self::callback(
                static fn (JavaScriptModuleInstruction $instruction): bool => $instruction->getName()
                    === '@xima/xima-typo3-calendar/Event/calendar-appointment-tab-focus.js'
            ));
        $pageRenderer = $this->createMock(PageRenderer::class);
        $pageRenderer->method('getJavaScriptRenderer')->willReturn($javaScriptRenderer);

        (new AddAppointmentTabFocusModule($pageRenderer))($this->createEvent(['appointmentUid' => '220']));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function requestsWithoutSelectedAppointment(): iterable
    {
        yield 'missing' => [[]];
        yield 'zero' => [['appointmentUid' => '0']];
        yield 'negative' => [['appointmentUid' => '-3']];
        yield 'non-numeric' => [['appointmentUid' => 'abc']];
        yield 'array' => [['appointmentUid' => ['220']]];
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    #[Test]
    #[DataProvider('requestsWithoutSelectedAppointment')]
    public function skipsTheModuleWithoutSelectedAppointment(array $queryParams): void
    {
        $pageRenderer = $this->createMock(PageRenderer::class);
        $pageRenderer->expects(self::never())->method('getJavaScriptRenderer');

        (new AddAppointmentTabFocusModule($pageRenderer))($this->createEvent($queryParams));
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private function createEvent(array $queryParams): AfterFormEnginePageInitializedEvent
    {
        return new AfterFormEnginePageInitializedEvent(
            $this->createMock(EditDocumentController::class),
            (new ServerRequest('https://example.com/typo3/record/edit'))->withQueryParams($queryParams)
        );
    }
}
