<?php

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\Features;
use Xima\XimaTypo3Recordlist\Controller\AbstractBackendController;
use Xima\XimaTypo3Recordlist\Dto\RecordSource;

class EventsController extends AbstractBackendController
{
    public function __construct(
        private readonly Features $features,
    ) {
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function getRecordPid(): int
    {
        return 0;
    }

    protected function getRecordSources(): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $folders = $qb->select('uid', 'title')
            ->from('pages')
            ->where($qb->expr()->eq('module', $qb->createNamedParameter('events')))
            ->executeQuery()
            ->fetchAllAssociative();

        $recordSources = [];
        foreach ($folders as $folder) {
            $recordSources[] = new RecordSource(
                pid: (int)$folder['uid'],
                includeSubpages: true,
                depth: 1
            );
        }
        return $recordSources;
    }

    public function getTableNames(): array
    {
        $tableNames = [
            'tx_ximatypo3calendar_domain_model_event',
            'tx_ximatypo3calendar_domain_model_organizer',
            'tx_ximatypo3calendar_domain_model_speaker',
            'tx_ximatypo3calendar_domain_model_entry',
            'tx_ximatypo3calendar_domain_model_location',
            'tx_ximatypo3calendar_domain_model_requirement',
            'tx_ximatypo3calendar_domain_model_calendar',
        ];

        if (!$this->features->isFeatureEnabled('ximaTypo3Calendar.requirementsManagement')) {
            $tableNames = array_diff($tableNames, ['tx_ximatypo3calendar_domain_model_requirement']);
        }

        return $tableNames;
    }

    protected function modifyPaginatedRecords(): void
    {
        parent::modifyPaginatedRecords();

        if ($this->getTableName() === 'tx_ximatypo3calendar_domain_model_event') {
            foreach ($this->records as &$record) {
                $record['url'] = '/aktuelles/veranstaltungen/event/' . $record['uid'] . '-slug';
            }
            unset($record);
        }

        if (in_array($this->getTableName(), ['tx_ximatypo3calendar_domain_model_event', 'tx_ximatypo3calendar_domain_model_entry'])) {
            foreach ($this->records as &$record) {
                foreach ($record as $key => &$value) {
                    if (str_starts_with($key, '_')) {
                        foreach ($value as $table => &$relatedRecords) {
                            foreach ($relatedRecords as &$relatedRecord) {
                                $labelField = $GLOBALS['TCA'][$table]['ctrl']['label'] ?? null;
                                if ($labelField) {
                                    $relatedRecord['label'] = BackendUtility::getProcessedValue(
                                        table: $table,
                                        col: $labelField,
                                        value: $relatedRecord['label'],
                                        uid: $relatedRecord['label'],
                                        pid: $this->getRecordPid()
                                    );
                                }
                            }
                            unset($relatedRecord);
                        }
                        unset($relatedRecords);
                    }
                }
                unset($value);
            }
            unset($record);
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
}
