<?php

namespace Xima\XimaTypo3Calendar\Controller\Backend;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Type;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use Xima\XimaTypo3Recordlist\Controller\AbstractBackendController;

class EventsController extends AbstractBackendController
{
    private array $mmTableColumnsCache = [];

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
        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['appointments']['filter']['partial'] = 'DateTime';

        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_event']['columns']['location']['filter']['items'] = $this->getFilterItemsForTable(
            'tx_ximatypo3calendar_domain_model_location'
        );

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

        $this->tableConfiguration['tx_ximatypo3calendar_domain_model_entry']['columns']['event']['filter']['items'] = $this->getFilterItemsForTable(
            'tx_ximatypo3calendar_domain_model_event'
        );

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
                    continue;
                }
                $isRelationField = in_array(
                        $GLOBALS['TCA'][$this->getTableName()]['columns'][$field]['config']['type'] ?? '',
                        ['select', 'group', 'inline']
                    ) &&
                    isset($GLOBALS['TCA'][$this->getTableName()]['columns'][$field]['config']['foreign_table']);
                if ($isRelationField && !empty($data['value'])) {
                    foreach ($this->additionalConstraints as $key => $constraint) {
                        // remove existing relation constraints for the same field to avoid conflicting filters
                        if (str_contains((string)$constraint, $field)) {
                            unset($this->additionalConstraints[$key]);
                        }
                    }

                    $this->addRelationFieldConstraint($field, (string)$data['value'], $data['expr'] ?? 'eq');
                }
            }
        }
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
        unset($record);
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
        unset($record);
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
        unset($record);
    }

    /**
     * @param string $table
     * @return array<int, array<string, string>>
     */
    private function getFilterItemsForTable(string $table): array
    {
        $labelField = $GLOBALS['TCA'][$table]['ctrl']['label'] ?? 'uid';
        $items = [];

        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        try {
            $rows = $qb->select('uid', $labelField)
                ->from($table)
                ->executeQuery()
                ->fetchAllAssociativeIndexed();
        } catch (Exception $e) {
            // In case of an error (e.g. table does not exist), return an empty array
            return [];
        }

        foreach ($rows as $uid => $row) {
            $items[$uid]['label'] = $row[$labelField];
            $items[$uid]['value'] = $uid;
        }

        return $items;
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

    private function addRelationFieldConstraint(string $field, string $value, string $expr = 'eq'): void
    {
        $tableName = $this->getTableName();
        $fieldConfig = $GLOBALS['TCA'][$tableName]['columns'][$field]['config'] ?? [];
        $foreignTable = $fieldConfig['foreign_table'] ?? null;
        if (!is_string($foreignTable) || $foreignTable === '') {
            return;
        }

        $foreignUids = $this->resolveForeignUids($foreignTable, $value);
        if ($foreignUids === []) {
            // No matches: handle based on expression
            if ($expr === 'neq') {
                // NOT IN (empty set) = all records
                return;
            }
            // For 'eq' and others: no match = no results
            $this->additionalConstraints[] = $this->queryBuilder->expr()->eq('t1.uid', 0);
            return;
        }

        if (($fieldConfig['type'] ?? '') === 'inline' && !empty($fieldConfig['foreign_field'])) {
            $localUids = $this->resolveLocalUidsForInlineRelation(
                $foreignTable,
                (string)$fieldConfig['foreign_field'],
                $foreignUids
            );
            $this->addLocalUidConstraint($localUids, $expr);
            return;
        }

        if (!empty($fieldConfig['MM']) && is_string($fieldConfig['MM'])) {
            $localUids = $this->resolveLocalUidsForMmRelation($tableName, $field, $fieldConfig, $foreignUids);
            $this->addLocalUidConstraint($localUids, $expr);
            return;
        }

        $this->addDirectFieldConstraint($field, $foreignUids, $expr);
    }

    private function addDirectFieldConstraint(string $field, array $foreignUids, string $expr): void
    {
        $qualifiedField = 't1.' . $field;

        if ($expr === 'neq') {
            // NOT IN constraint
            $this->additionalConstraints[] = $this->queryBuilder->expr()->notIn(
                $qualifiedField,
                $this->queryBuilder->createNamedParameter($foreignUids, Connection::PARAM_INT_ARRAY)
            );
            return;
        }

        // Default 'eq': IN constraint with fallback for CSV/group fields
        $constraints = [
            $this->queryBuilder->expr()->in(
                $qualifiedField,
                $this->queryBuilder->createNamedParameter($foreignUids, Connection::PARAM_INT_ARRAY)
            ),
        ];

        foreach ($foreignUids as $foreignUid) {
            $constraints[] = sprintf(
                'FIND_IN_SET(%s, %s) > 0',
                $this->queryBuilder->createNamedParameter((string)$foreignUid),
                $qualifiedField
            );
        }

        $this->additionalConstraints[] = $this->queryBuilder->expr()->or(...$constraints);
    }

    /**
     * @return int[]
     */
    private function resolveForeignUids(string $foreignTable, string $value): array
    {
        $parts = array_filter(array_map('trim', explode(',', $value)), static fn(string $part): bool => $part !== '');
        if ($parts !== [] && count(array_filter($parts, static fn(string $part): bool => ctype_digit($part))) === count($parts)) {
            return array_values(array_unique(array_map('intval', $parts)));
        }

        $escapedValue = '%' . $this->connectionPool->getConnectionForTable($foreignTable)->escapeLikeWildcards($value) . '%';
        $qb = $this->connectionPool->getQueryBuilderForTable($foreignTable);

        try {
            $searchFields = $GLOBALS['TCA'][$foreignTable]['ctrl']['searchFields'] ?? '';
            $fields = array_filter(array_map('trim', explode(',', (string)$searchFields)));

            // If no searchFields configured, fall back to label field
            if ($fields === []) {
                $labelField = $GLOBALS['TCA'][$foreignTable]['ctrl']['label'] ?? 'uid';
                $fields = [$labelField];
            }

            // Build OR condition for all search fields
            $constraints = [];
            foreach ($fields as $field) {
                if (is_string($field) && $field !== '') {
                    $constraints[] = $qb->expr()->like(
                        $field,
                        $qb->createNamedParameter($escapedValue)
                    );
                }
            }

            if ($constraints === []) {
                return [];
            }

            $uids = $qb->select('uid')
                ->from($foreignTable)
                ->where($qb->expr()->or(...$constraints))
                ->executeQuery()
                ->fetchFirstColumn();
        } catch (Exception $e) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $uids)));
    }

    /**
     * @param int[] $foreignUids
     * @return int[]
     */
    private function resolveLocalUidsForInlineRelation(string $foreignTable, string $foreignField, array $foreignUids): array
    {
        if ($foreignUids === []) {
            return [];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable($foreignTable);
        try {
            $uids = $qb->select($foreignField)
                ->distinct()
                ->from($foreignTable)
                ->where(
                    $qb->expr()->in(
                        'uid',
                        $qb->createNamedParameter($foreignUids, Connection::PARAM_INT_ARRAY)
                    )
                )
                ->executeQuery()
                ->fetchFirstColumn();
        } catch (Exception $e) {
            return [];
        }

        $uids = array_filter(array_map('intval', $uids), static fn(int $uid): bool => $uid > 0);
        return array_values(array_unique($uids));
    }

    /**
     * @param int[] $foreignUids
     * @return int[]
     */
    private function resolveLocalUidsForMmRelation(string $tableName, string $field, array $fieldConfig, array $foreignUids): array
    {
        if ($foreignUids === []) {
            return [];
        }

        $mmTable = (string)($fieldConfig['MM'] ?? '');
        if ($mmTable === '') {
            return [];
        }

        $isOpposite = !empty($fieldConfig['MM_opposite_field']);
        $localColumn = $isOpposite ? 'uid_foreign' : 'uid_local';
        $foreignColumn = $isOpposite ? 'uid_local' : 'uid_foreign';

        $qb = $this->connectionPool->getQueryBuilderForTable($mmTable);
        try {
            $qb->select($localColumn)
                ->distinct()
                ->from($mmTable)
                ->where(
                    $qb->expr()->in(
                        $foreignColumn,
                        $qb->createNamedParameter($foreignUids, Connection::PARAM_INT_ARRAY)
                    )
                );

            // Restrict shared MM tables to the concrete table/field context when possible.
            if (!$isOpposite && $this->mmTableHasColumn($mmTable, 'tablenames')) {
                $qb->andWhere(
                    $qb->expr()->eq('tablenames', $qb->createNamedParameter($tableName))
                );
            }
            if (!$isOpposite && $this->mmTableHasColumn($mmTable, 'fieldname')) {
                $qb->andWhere(
                    $qb->expr()->eq('fieldname', $qb->createNamedParameter($field))
                );
            }

            $matchFields = $fieldConfig['MM_match_fields'] ?? [];
            if (is_array($matchFields)) {
                foreach ($matchFields as $matchField => $matchValue) {
                    if (!is_string($matchField) || $matchField === '') {
                        continue;
                    }
                    if (!$this->mmTableHasColumn($mmTable, $matchField)) {
                        continue;
                    }
                    if (is_scalar($matchValue) || $matchValue === null) {
                        $qb->andWhere(
                            $qb->expr()->eq(
                                $matchField,
                                $qb->createNamedParameter((string)$matchValue)
                            )
                        );
                    }
                }
            }

            $uids = $qb->executeQuery()->fetchFirstColumn();
        } catch (Exception $e) {
            return [];
        }

        $uids = array_filter(array_map('intval', $uids), static fn(int $uid): bool => $uid > 0);
        return array_values(array_unique($uids));
    }

    private function mmTableHasColumn(string $tableName, string $columnName): bool
    {
        if (!isset($this->mmTableColumnsCache[$tableName])) {
            try {
                $columns = $this->connectionPool
                    ->getConnectionForTable($tableName)
                    ->createSchemaManager()
                    ->listTableColumns($tableName);
            } catch (Exception $e) {
                $this->mmTableColumnsCache[$tableName] = [];
                return false;
            }

            $this->mmTableColumnsCache[$tableName] = array_keys($columns);
        }

        return in_array($columnName, $this->mmTableColumnsCache[$tableName], true);
    }

    /**
     * @param int[] $localUids
     */
    private function addLocalUidConstraint(array $localUids, string $expr = 'eq'): void
    {
        if ($expr === 'neq') {
            // NOT IN constraint
            if ($localUids === []) {
                // NOT IN (empty set) = all records
                return;
            }
            $this->additionalConstraints[] = $this->queryBuilder->expr()->notIn(
                't1.uid',
                $this->queryBuilder->createNamedParameter($localUids, Connection::PARAM_INT_ARRAY)
            );
            return;
        }

        // Default 'eq': IN constraint
        if ($localUids === []) {
            $this->additionalConstraints[] = $this->queryBuilder->expr()->eq('t1.uid', 0);
            return;
        }

        $this->additionalConstraints[] = $this->queryBuilder->expr()->in(
            't1.uid',
            $this->queryBuilder->createNamedParameter($localUids, Connection::PARAM_INT_ARRAY)
        );
    }
}
