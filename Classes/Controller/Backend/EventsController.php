<?php

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Doctrine\DBAL\Types\Type;
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
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['appointments']['filter']['partial'] = 'DateTime';

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

    protected function addAdditionalConstraints(): void
    {
        $body = $this->request->getParsedBody();
        if (is_array($body) && !empty($body['filter'])) {
            foreach ($body['filter'] as $field => $data) {
                if ($field === 'appointments' && !empty($data['value'])) {
                    $this->addAppointmentsConstraint($data['value'], $data['expr'] ?? 'eq');
                }
            }
        }
    }

    private function addAppointmentsConstraint(string $value, string $expr): void
    {
        if ($value === '') {
            return;
        }

        foreach ($this->additionalConstraints as $key => $constraint) {
            // remove existing appointment constraints as they do not respect the relation yet
            if (str_contains((string)$constraint, 'appointments')) {
                unset($this->additionalConstraints[$key]);
            }
        }
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_ximatypo3calendar_domain_model_entry');
        $qb->select('event')
            ->distinct()
            ->from('tx_ximatypo3calendar_domain_model_entry')
            ->where(
                $qb->expr()->eq(
                    'record_type',
                    $qb->createNamedParameter('event-appointment')
                )
            );
        match ($expr) {
            'lt' => $qb->andWhere(
                $qb->expr()->lt(
                    'start_date',
                    $qb->createNamedParameter($value, Type::getType('datetime'))
                )
            ),
            'gt' => $qb->andWhere(
                $qb->expr()->gt(
                    'start_date',
                    $qb->createNamedParameter($value, Type::getType('datetime'))
                )
            ),
            'neq' => $qb->andWhere(
                $qb->expr()->neq(
                    'start_date',
                    $qb->createNamedParameter($value, Type::getType('datetime'))
                )
            ),
            default => $qb->andWhere(
                $qb->expr()->and(
                    $qb->expr()->gte(
                        'start_date',
                        $qb->createNamedParameter($value, Type::getType('datetime'))
                    ),
                    $qb->expr()->lt(
                        'start_date',
                        $qb->createNamedParameter(date('Y-m-d H:i:s', strtotime($value . ' +1 day')), Type::getType('datetime'))
                    )
                )
            )
        };

        $uids = $qb->executeQuery()->fetchAllNumeric();
        // prepare for in constraint
        $value = array_map('current', $uids);
        if (empty($value)) {
            $this->additionalConstraints[] = $this->queryBuilder->expr()->eq('t1.uid', 0);
            return;
        }
        $this->additionalConstraints[] = $this->queryBuilder->expr()->in(
            't1.uid',
            $this->queryBuilder->createNamedParameter($value, Connection::PARAM_INT_ARRAY)
        );
    }
}
