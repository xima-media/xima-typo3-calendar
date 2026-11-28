<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Tests\Unit\Backend\FormDataProvider;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Xima\XimaTypo3Calendar\Backend\FormDataProvider\ExpandSelectedAppointment;

final class ExpandSelectedAppointmentTest extends TestCase
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const APPOINTMENT_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    #[Test]
    public function replacesRememberedAppointmentExpansionOnTheEvent(): void
    {
        $result = (new ExpandSelectedAppointment())->addData([
            'request' => $this->requestWithAppointmentUid(220),
            'tableName' => self::EVENT_TABLE,
            'isInlineChild' => false,
            'inlineExpandCollapseStateArray' => [self::APPOINTMENT_TABLE => [221]],
        ]);

        self::assertSame([220], $result['inlineExpandCollapseStateArray'][self::APPOINTMENT_TABLE]);
    }

    #[Test]
    public function expandsOnlyTheSelectedAppointment(): void
    {
        $provider = new ExpandSelectedAppointment();
        $request = $this->requestWithAppointmentUid(220);

        $selectedAppointment = $provider->addData([
            'request' => $request,
            'tableName' => self::APPOINTMENT_TABLE,
            'isInlineChild' => true,
            'databaseRow' => ['uid' => 220],
            'isInlineChildExpanded' => false,
        ]);
        $otherAppointment = $provider->addData([
            'request' => $request,
            'tableName' => self::APPOINTMENT_TABLE,
            'isInlineChild' => true,
            'databaseRow' => ['uid' => 221],
            'isInlineChildExpanded' => true,
        ]);

        self::assertTrue($selectedAppointment['isInlineChildExpanded']);
        self::assertFalse($otherAppointment['isInlineChildExpanded']);
    }

    private function requestWithAppointmentUid(int $appointmentUid): ServerRequestInterface
    {
        $request = self::createStub(ServerRequestInterface::class);
        $request->method('getQueryParams')->willReturn(['appointmentUid' => $appointmentUid]);

        return $request;
    }
}
