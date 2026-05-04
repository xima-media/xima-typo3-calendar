<?php

declare(strict_types=1);

namespace Xima\XimaTypo3Calendar\Helper;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class RelationHelper
{
    private array $mmTableColumnsCache = [];

    private array $relatedLabelsCache = [];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @param array<int|string, array<string, mixed>> $tableColumns
     * @return array<int, array<string, mixed>>
     */
    public function enrichActiveRelationLabels(string $tableName, array $records, array $tableColumns, int $previewLimit = 3): array
    {
        if ($records === []) {
            return $records;
        }

        $relationColumns = $this->getActiveRelationColumns($tableName, $tableColumns);
        if ($relationColumns === []) {
            return $records;
        }

        foreach ($relationColumns as $columnName => $fieldConfig) {
            $foreignTable = (string)$fieldConfig['foreign_table'];
            $relatedUidsByRecord = [];
            $allRelatedUids = [];

            foreach ($records as $recordIndex => $record) {
                $uids = $this->resolveRelatedUidsForRecordField($record, $tableName, $columnName, $fieldConfig);
                $relatedUidsByRecord[$recordIndex] = $uids;
                foreach ($uids as $uid) {
                    $allRelatedUids[] = $uid;
                }
            }

            $allRelatedUids = array_values(array_unique(array_filter($allRelatedUids, static fn(int $uid): bool => $uid > 0)));
            $labelMap = $this->fetchRelatedLabels($foreignTable, $allRelatedUids);

            foreach ($records as $recordIndex => &$record) {
                $record[$columnName] = $this->formatRelatedLabels($relatedUidsByRecord[$recordIndex] ?? [], $labelMap, $previewLimit);
            }
            unset($record);
        }

        return $records;
    }

    public function buildRelationFieldConstraint(
        QueryBuilder $queryBuilder,
        string $tableName,
        string $field,
        string $value,
        string $expr = 'eq'
    ): mixed {
        $fieldConfig = $GLOBALS['TCA'][$tableName]['columns'][$field]['config'] ?? [];
        $foreignTable = $fieldConfig['foreign_table'] ?? null;
        if (!is_string($foreignTable) || $foreignTable === '') {
            return null;
        }

        $foreignUids = $this->resolveForeignUids($foreignTable, $value);
        if ($foreignUids === []) {
            if ($expr === 'neq') {
                return null;
            }
            return $queryBuilder->expr()->eq('t1.uid', 0);
        }

        if (($fieldConfig['type'] ?? '') === 'inline' && !empty($fieldConfig['foreign_field'])) {
            $localUids = $this->resolveLocalUidsForInlineRelation(
                $foreignTable,
                (string)$fieldConfig['foreign_field'],
                $foreignUids
            );
            return $this->buildLocalUidConstraint($queryBuilder, $localUids, $expr);
        }

        if (!empty($fieldConfig['MM']) && is_string($fieldConfig['MM'])) {
            $localUids = $this->resolveLocalUidsForMmRelation($tableName, $field, $fieldConfig, $foreignUids);
            return $this->buildLocalUidConstraint($queryBuilder, $localUids, $expr);
        }

        return $this->buildDirectFieldConstraint($queryBuilder, $field, $foreignUids, $expr);
    }

    /**
     * @param array<int|string, array<string, mixed>> $tableColumns
     * @return array<string, array<string, mixed>>
     */
    private function getActiveRelationColumns(string $tableName, array $tableColumns): array
    {
        $relationColumns = [];

        foreach ($tableColumns as $column) {
            if (!($column['active'] ?? false) || !isset($column['columnName']) || !is_string($column['columnName'])) {
                continue;
            }

            $columnName = $column['columnName'];
            $fieldConfig = $GLOBALS['TCA'][$tableName]['columns'][$columnName]['config'] ?? [];
            if (!$this->isSupportedRelationFieldConfig($fieldConfig)) {
                continue;
            }

            $relationColumns[$columnName] = $fieldConfig;
        }

        return $relationColumns;
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    private function isSupportedRelationFieldConfig(array $fieldConfig): bool
    {
        $type = $fieldConfig['type'] ?? '';
        $foreignTable = $fieldConfig['foreign_table'] ?? null;

        return in_array($type, ['select', 'group', 'inline'], true)
            && is_string($foreignTable)
            && $foreignTable !== '';
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $fieldConfig
     * @return int[]
     */
    private function resolveRelatedUidsForRecordField(array $record, string $tableName, string $fieldName, array $fieldConfig): array
    {
        $type = (string)($fieldConfig['type'] ?? '');
        $foreignTable = (string)($fieldConfig['foreign_table'] ?? '');
        if ($foreignTable === '') {
            return [];
        }

        if ($type === 'inline') {
            $localUid = (int)($record['uid'] ?? 0);
            if ($localUid <= 0) {
                return [];
            }
            return $this->resolveInlineRelatedUids($tableName, $foreignTable, $fieldConfig, $localUid);
        }

        if (!empty($fieldConfig['MM']) && is_string($fieldConfig['MM'])) {
            $localUid = (int)($record['uid'] ?? 0);
            if ($localUid <= 0) {
                return [];
            }
            return $this->resolveMmRelatedUids($tableName, $fieldName, $fieldConfig, $localUid);
        }

        return $this->extractUidsFromFieldValue($record[$fieldName] ?? null);
    }

    /**
     * @param array<string, mixed> $fieldConfig
     * @return int[]
     */
    private function resolveInlineRelatedUids(string $tableName, string $foreignTable, array $fieldConfig, int $localUid): array
    {
        $foreignField = $fieldConfig['foreign_field'] ?? null;
        if (!is_string($foreignField) || $foreignField === '') {
            return [];
        }

        $qb = $this->connectionPool->getQueryBuilderForTable($foreignTable);
        try {
            $qb->select('uid')
                ->from($foreignTable)
                ->where(
                    $qb->expr()->eq($foreignField, $qb->createNamedParameter($localUid, Connection::PARAM_INT))
                );

            if (!empty($fieldConfig['foreign_table_field']) && is_string($fieldConfig['foreign_table_field'])) {
                $qb->andWhere(
                    $qb->expr()->eq(
                        $fieldConfig['foreign_table_field'],
                        $qb->createNamedParameter($tableName)
                    )
                );
            }

            $foreignMatchFields = $fieldConfig['foreign_match_fields'] ?? [];
            if (is_array($foreignMatchFields)) {
                foreach ($foreignMatchFields as $matchField => $matchValue) {
                    if (!is_string($matchField) || $matchField === '' || (!is_scalar($matchValue) && $matchValue !== null)) {
                        continue;
                    }
                    $qb->andWhere(
                        $qb->expr()->eq($matchField, $qb->createNamedParameter((string)$matchValue))
                    );
                }
            }

            $uids = $qb->executeQuery()->fetchFirstColumn();
        } catch (Exception $e) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $uids), static fn(int $uid): bool => $uid > 0)));
    }

    /**
     * @param array<string, mixed> $fieldConfig
     * @return int[]
     */
    private function resolveMmRelatedUids(string $tableName, string $fieldName, array $fieldConfig, int $localUid): array
    {
        $mmTable = (string)($fieldConfig['MM'] ?? '');
        if ($mmTable === '') {
            return [];
        }

        $isOpposite = !empty($fieldConfig['MM_opposite_field']);
        $localColumn = $isOpposite ? 'uid_foreign' : 'uid_local';
        $foreignColumn = $isOpposite ? 'uid_local' : 'uid_foreign';

        $qb = $this->connectionPool->getQueryBuilderForTable($mmTable);
        try {
            $qb->select($foreignColumn)
                ->distinct()
                ->from($mmTable)
                ->where(
                    $qb->expr()->eq($localColumn, $qb->createNamedParameter($localUid, Connection::PARAM_INT))
                );

            if (!$isOpposite && $this->mmTableHasColumn($mmTable, 'tablenames')) {
                $qb->andWhere($qb->expr()->eq('tablenames', $qb->createNamedParameter($tableName)));
            }
            if (!$isOpposite && $this->mmTableHasColumn($mmTable, 'fieldname')) {
                $qb->andWhere($qb->expr()->eq('fieldname', $qb->createNamedParameter($fieldName)));
            }

            $matchFields = $fieldConfig['MM_match_fields'] ?? [];
            if (is_array($matchFields)) {
                foreach ($matchFields as $matchField => $matchValue) {
                    if (!is_string($matchField) || $matchField === '' || !$this->mmTableHasColumn($mmTable, $matchField)) {
                        continue;
                    }
                    if (!is_scalar($matchValue) && $matchValue !== null) {
                        continue;
                    }
                    $qb->andWhere(
                        $qb->expr()->eq($matchField, $qb->createNamedParameter((string)$matchValue))
                    );
                }
            }

            $uids = $qb->executeQuery()->fetchFirstColumn();
        } catch (Exception $e) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $uids), static fn(int $uid): bool => $uid > 0)));
    }

    /**
     * @return int[]
     */
    private function extractUidsFromFieldValue(mixed $value): array
    {
        if (is_int($value)) {
            return $value > 0 ? [$value] : [];
        }

        if (is_array($value)) {
            $uids = [];
            foreach ($value as $item) {
                foreach ($this->extractUidsFromFieldValue($item) as $uid) {
                    $uids[] = $uid;
                }
            }
            return array_values(array_unique(array_filter($uids, static fn(int $uid): bool => $uid > 0)));
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $value)), static fn(string $part): bool => $part !== '');
        $uids = [];

        foreach ($parts as $part) {
            if (ctype_digit($part)) {
                $uids[] = (int)$part;
                continue;
            }

            $recordId = strrchr($part, '_');
            if ($recordId !== false && ctype_digit(substr($recordId, 1))) {
                $uids[] = (int)substr($recordId, 1);
            }
        }

        return array_values(array_unique(array_filter($uids, static fn(int $uid): bool => $uid > 0)));
    }

    /**
     * @param int[] $uids
     * @return array<int, string>
     */
    private function fetchRelatedLabels(string $table, array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        sort($uids);
        $cacheKey = $table . ':' . implode(',', $uids);
        if (isset($this->relatedLabelsCache[$cacheKey])) {
            return $this->relatedLabelsCache[$cacheKey];
        }

        $labelField = $this->getLabelFieldForTable($table);
        $qb = $this->connectionPool->getQueryBuilderForTable($table);
        try {
            $rows = $qb->select('uid', $labelField)
                ->from($table)
                ->where(
                    $qb->expr()->in('uid', $qb->createNamedParameter($uids, Connection::PARAM_INT_ARRAY))
                )
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (Exception $e) {
            $this->relatedLabelsCache[$cacheKey] = [];
            return [];
        }

        $labels = [];
        foreach ($rows as $row) {
            $uid = (int)($row['uid'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $label = (string)($row[$labelField] ?? '');
            $labels[$uid] = trim($label) !== '' ? $label : (string)$uid;
        }

        $this->relatedLabelsCache[$cacheKey] = $labels;
        return $labels;
    }

    private function getLabelFieldForTable(string $table): string
    {
        $labelField = $GLOBALS['TCA'][$table]['ctrl']['label'] ?? 'uid';
        return is_string($labelField) && $labelField !== '' ? $labelField : 'uid';
    }

    /**
     * @param int[] $uids
     * @param array<int, string> $labelsByUid
     */
    private function formatRelatedLabels(array $uids, array $labelsByUid, int $previewLimit): string
    {
        if ($uids === []) {
            return '';
        }

        $labels = [];
        foreach ($uids as $uid) {
            $labels[] = $labelsByUid[$uid] ?? (string)$uid;
        }

        $labels = array_values(array_unique($labels));
        $visible = array_slice($labels, 0, $previewLimit);
        $hiddenCount = count($labels) - count($visible);

        if ($hiddenCount > 0) {
            $visible[] = '+' . $hiddenCount . ' weitere';
        }

        return implode(', ', $visible);
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

            if ($fields === []) {
                $labelField = $GLOBALS['TCA'][$foreignTable]['ctrl']['label'] ?? 'uid';
                $fields = [$labelField];
            }

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
     * @param array<string, mixed> $fieldConfig
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

    private function buildDirectFieldConstraint(QueryBuilder $queryBuilder, string $field, array $foreignUids, string $expr): mixed
    {
        $qualifiedField = 't1.' . $field;

        if ($expr === 'neq') {
            return $queryBuilder->expr()->notIn(
                $qualifiedField,
                $queryBuilder->createNamedParameter($foreignUids, Connection::PARAM_INT_ARRAY)
            );
        }

        $constraints = [
            $queryBuilder->expr()->in(
                $qualifiedField,
                $queryBuilder->createNamedParameter($foreignUids, Connection::PARAM_INT_ARRAY)
            ),
        ];

        foreach ($foreignUids as $foreignUid) {
            $constraints[] = sprintf(
                'FIND_IN_SET(%s, %s) > 0',
                $queryBuilder->createNamedParameter((string)$foreignUid),
                $qualifiedField
            );
        }

        return $queryBuilder->expr()->or(...$constraints);
    }

    /**
     * @param int[] $localUids
     */
    private function buildLocalUidConstraint(QueryBuilder $queryBuilder, array $localUids, string $expr): mixed
    {
        if ($expr === 'neq') {
            if ($localUids === []) {
                return null;
            }
            return $queryBuilder->expr()->notIn(
                't1.uid',
                $queryBuilder->createNamedParameter($localUids, Connection::PARAM_INT_ARRAY)
            );
        }

        if ($localUids === []) {
            return $queryBuilder->expr()->eq('t1.uid', 0);
        }

        return $queryBuilder->expr()->in(
            't1.uid',
            $queryBuilder->createNamedParameter($localUids, Connection::PARAM_INT_ARRAY)
        );
    }

    private function mmTableHasColumn(string $tableName, string $columnName): bool
    {
        if (!isset($this->mmTableColumnsCache[$tableName])) {
            $columns = [];
            try {
                $schemaManager = $this->connectionPool
                    ->getConnectionForTable($tableName)
                    ->createSchemaManager();

                // Use listTableColumns() for maximum compatibility
                // (introspectTableColumns requires Name objects which depend on specific Doctrine versions)
                /** @noinspection PhpDeprecatedClassUsageInspection */
                $columns = $schemaManager->listTableColumns($tableName);
            } catch (Exception $e) {
                // Error during introspection, fall back to empty array
            }

            $this->mmTableColumnsCache[$tableName] = array_keys($columns);
        }

        return in_array($columnName, $this->mmTableColumnsCache[$tableName], true);
    }
}

