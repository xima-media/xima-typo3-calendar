<?php

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Xima\XimaTypo3Recordlist\Controller\AbstractBackendController;

class EventsController extends AbstractBackendController
{
    public function getRecordPid(): int
    {
        // TODO: Make this configurable via extension configuration or TypoScript
        return 1478;
    }

    public function getTableNames(): array
    {
        return [
            'tx_ximatypo3calendar_domain_model_event',
            'tx_ximatypo3calendar_domain_model_organizer',
            'tx_ximatypo3calendar_domain_model_speaker',
            'tx_ximatypo3calendar_domain_model_entry',
            'tx_ximatypo3calendar_domain_model_location',
            'tx_ximatypo3calendar_domain_model_calendar',
        ];
    }

    protected function modifyPaginatedRecords(): void
    {
        parent::modifyPaginatedRecords();

        if ($this->getTableName() === 'tx_ximatypo3calendar_domain_model_event') {
            foreach ($this->records as &$record) {
                $record['url'] = '/aktuelles/veranstaltungen/event/' . $record['uid'] . '-slug';
            }
        }
    }

    protected function modifyTableConfiguration(): void
    {
        // ============================================
        // tx_ximatypo3calendar_domain_model_event (Event)
        // ============================================
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['title']['defaultPosition'] = 1;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['language']['defaultPosition'] = 2;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['categories']['defaultPosition'] = 3;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['organizer']['defaultPosition'] = 4;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['status']['defaultPosition'] = 5;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['publish_to_website']['defaultPosition'] = 6;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['publish_date']['defaultPosition'] = 7;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['appointments']['defaultPosition'] = 8;

        // ============================================
        // tx_ximatypo3calendar_domain_model_appointment (Appointment)
        // ============================================
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_appointment']['columns']['title']['defaultPosition'] = 1;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_appointment']['columns']['start_date']['defaultPosition'] = 2;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_appointment']['columns']['start_time']['defaultPosition'] = 3;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_appointment']['columns']['end_time']['defaultPosition'] = 4;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_appointment']['columns']['type']['defaultPosition'] = 5;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_appointment']['columns']['location']['defaultPosition'] = 6;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_appointment']['columns']['canceled']['defaultPosition'] = 7;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_appointment']['columns']['requires_registration']['defaultPosition'] = 8;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_appointment']['columns']['speakers']['defaultPosition'] = 9;

        // ============================================
        // tx_ximatypo3calendar_domain_model_organizer (Organizer)
        // ============================================
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_organizer']['columns']['title']['defaultPosition'] = 1;

        // ============================================
        // tx_ximatypo3calendar_domain_model_location (Location)
        // ============================================
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_location']['columns']['name']['defaultPosition'] = 1;

        // ============================================
        // tx_ximatypo3calendar_domain_model_speaker (Speaker)
        // ============================================
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_speaker']['columns']['last_name']['defaultPosition'] = 1;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_speaker']['columns']['title']['defaultPosition'] = 2;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_speaker']['columns']['first_name']['defaultPosition'] = 3;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_speaker']['columns']['department']['defaultPosition'] = 4;
    }
}
