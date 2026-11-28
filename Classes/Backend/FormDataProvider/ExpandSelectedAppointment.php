<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Backend\FormDataProvider;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;

/**
 * Expands only the appointment selected in the calendar when its event is opened.
 */
final class ExpandSelectedAppointment implements FormDataProviderInterface
{
    private const EVENT_TABLE = 'tx_ximatypo3calendar_domain_model_event';
    private const APPOINTMENT_TABLE = 'tx_ximatypo3calendar_domain_model_entry';

    /**
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    public function addData(array $result): array
    {
        $request = $result['request'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return $result;
        }

        $selectedAppointmentUid = $this->resolveSelectedAppointmentUid($request);
        if ($selectedAppointmentUid === null) {
            return $result;
        }

        $tableName = $result['tableName'] ?? '';
        $isInlineChild = !empty($result['isInlineChild']);
        if ($tableName === self::APPOINTMENT_TABLE && $isInlineChild) {
            $result['isInlineChildExpanded'] = (int)($result['databaseRow']['uid'] ?? 0) === $selectedAppointmentUid;
            return $result;
        }

        if ($tableName !== self::EVENT_TABLE || $isInlineChild) {
            return $result;
        }

        $inlineExpandCollapseState = $result['inlineExpandCollapseStateArray'] ?? [];
        if (!is_array($inlineExpandCollapseState)) {
            $inlineExpandCollapseState = [];
        }

        // A calendar click identifies one concrete appointment. Remembered states
        // must not leave another appointment expanded in the form.
        $inlineExpandCollapseState[self::APPOINTMENT_TABLE] = [$selectedAppointmentUid];
        $result['inlineExpandCollapseStateArray'] = $inlineExpandCollapseState;

        return $result;
    }

    private function resolveSelectedAppointmentUid(ServerRequestInterface $request): ?int
    {
        $queryParams = $request->getQueryParams();
        $appointmentUid = $queryParams['appointmentUid'] ?? null;
        $returnUrl = $queryParams['returnUrl'] ?? null;
        if ($appointmentUid === null && is_string($returnUrl)) {
            $returnUrlQuery = parse_url($returnUrl, PHP_URL_QUERY);
            if (is_string($returnUrlQuery)) {
                parse_str($returnUrlQuery, $returnUrlParams);
                $appointmentUid = $returnUrlParams['appointmentUid'] ?? null;
            }
        }

        return is_scalar($appointmentUid) && (int)$appointmentUid > 0
            ? (int)$appointmentUid
            : null;
    }
}
