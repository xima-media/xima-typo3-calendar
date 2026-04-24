<?php

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use Xima\XimaTypo3Recordlist\Controller\AbstractBackendController;

class EventsController extends AbstractBackendController
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function getRecordPid(): int
    {
        $pid = $this->extensionConfiguration
            ->get('xima_typo3_calendar', 'recordPid');
        return $pid !== null ? (int)$pid : 0;
    }

    public function getTableNames(): array
    {
        return [
            'tx_ximatypo3calendar_domain_model_event',
            'tx_ximatypo3calendar_domain_model_organizer',
            'tx_ximatypo3calendar_domain_model_speaker',
            'tx_ximatypo3calendar_domain_model_entry',
            'tx_ximatypo3calendar_domain_model_location',
            'tx_ximatypo3calendar_domain_model_requirement',
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

        $this->addAppointmentsToEvents();
        $this->addEventsToAppointments();
        $this->addLocations();
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
        // tx_ximatypo3calendar_domain_model_entry (Appointment)
        // ============================================
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['title']['defaultPosition'] = 1;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['event']['defaultPosition'] = 2;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['start_date']['defaultPosition'] = 3;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['type']['defaultPosition'] = 4;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['location']['defaultPosition'] = 5;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['canceled']['defaultPosition'] = 6;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['speakers']['defaultPosition'] = 7;

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

        // ============================================
        // tx_ximatypo3calendar_domain_model_requirement (Requirement)
        // ============================================
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_requirement']['columns']['title']['defaultPosition'] = 1;
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_requirement']['columns']['assignee']['defaultPosition'] = 2;
    }

    private function addAppointmentsToEvents(): void
    {
        if ($this->getTableName() !== 'tx_ximatypo3calendar_domain_model_event') {
            return;
        }

        foreach ($this->records as &$record) {
            $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');
            $appointments = $qb->select('*')
                ->from('tx_ximatypo3calendar_domain_model_entry')
                ->where(
                    $qb->expr()->eq('event', $qb->createNamedParameter($record['uid'], Connection::PARAM_INT)),
                    $qb->expr()->eq('record_type', $qb->createNamedParameter('event-appointment'))
                )
                ->orderBy('start_date', 'ASC')
                ->executeQuery()
                ->fetchAllAssociative();

            $record['appointments'] = implode(', ', array_map(static function ($appointment) {
                $date = date_create($appointment['start_date']);
                return $date ? date_format($date, 'd.m.Y H:i') : null;
            }, $appointments));
        }
    }

    private function addEventsToAppointments(): void
    {
        if ($this->getTableName() !== 'tx_ximatypo3calendar_domain_model_entry') {
            return;
        }

        foreach ($this->records as &$record) {
            $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_event');
            $eventTitle = $qb->select('title')
                ->from('tx_ximatypo3calendar_domain_model_event')
                ->where(
                    $qb->expr()->eq('uid', $qb->createNamedParameter($record['event'], Connection::PARAM_INT)),
                )
                ->executeQuery()
                ->fetchOne();

            $record['event'] = $eventTitle ?: '';
        }
    }

    private function addLocations(): void
    {
        if (!in_array($this->getTableName(), ['tx_ximatypo3calendar_domain_model_event', 'tx_ximatypo3calendar_domain_model_entry'])) {
            return;
        }

        foreach ($this->records as &$record) {
            $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_location');
            $locationName = $qb->select('name')
                ->from('tx_ximatypo3calendar_domain_model_location')
                ->where(
                    $qb->expr()->eq('uid', $qb->createNamedParameter($record['location'], Connection::PARAM_INT)),
                )
                ->executeQuery()
                ->fetchOne();

            $record['location'] = $locationName ?: '';
        }
    }
}
