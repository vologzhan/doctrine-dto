<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto;

use PHPSQLParser\PHPSQLParser;
use Vologzhan\DoctrineDto\Exception\DoctrineDtoException;
use Vologzhan\DoctrineDto\Metadata\DtoMetadata;
use Vologzhan\DoctrineDto\SqlMetadata\ColumnMetadata;
use Vologzhan\DoctrineDto\SqlMetadata\SqlMetadata;

class SqlMetadataFactory
{
    public static function create(DtoMetadata $dtoMetadata, string $sql): SqlMetadata
    {
        $from = self::eraseSelectStatement($sql);
        $joinMetadataMap = self::arrangeMetadataByJoins($dtoMetadata, $from);
        $columnMetadataMap = self::createColumnMetadataMap($joinMetadataMap);
        $fullSql = sprintf("SELECT %s %s", implode(', ', array_keys($columnMetadataMap)), $from);

        return new SqlMetadata($fullSql, array_values($columnMetadataMap));
    }

    private static function eraseSelectStatement(string $sql): string
    {
        return preg_replace('/SELECT\s+(.+?)\s+FROM/i', 'FROM', $sql);
    }

    /**
     * @return array<string, DtoMetadata> Ключи - алиасы в SELECT
     * @throws DoctrineDtoException
     */
    private static function arrangeMetadataByJoins(DtoMetadata $metadata, string $sql): array
    {
        $parser = new PHPSQLParser();
        $ast = $parser->parse($sql);

        /** @var DtoMetadata[] $dtoMetadataMap */
        $dtoMetadataMap = [];
        foreach ($ast['FROM'] as $i => $from) {
            $table = $from['table'];
            $alias = $from['alias']['name'] ?? null;
            $name = $alias ?: $table;

            if ($i === 0) {
                if ($table !== $metadata->doctrine->tableName) {
                    throw new DoctrineDtoException("Запрос должен начинаться с 'FROM {$metadata->doctrine->tableName}'");
                }
                $dtoMetadataMap[$name] = $metadata;

                continue; // основная таблица
            }

            $rel1 = $from['ref_clause'][0]['no_quotes']['parts'][0];
            $rel2 = $from['ref_clause'][2]['no_quotes']['parts'][0];

            $currentMeta = null;
            $currentRelation = null;
            if (array_key_exists($rel1, $dtoMetadataMap)) {
                $currentMeta = $dtoMetadataMap[$rel1];
                $currentRelation = $rel2;
            } else {
                $currentMeta = $dtoMetadataMap[$rel2] ?? null;
                $currentRelation = $rel1;
            }
            if ($currentMeta === null) {
                throw new DoctrineDtoException("JOIN parse error"); // todo добавить больше инфы в ошибку
            }

            $nextMeta = null;
            foreach ($currentMeta->relations as $rel) {
                $tableName = $rel->doctrine->tableName;
                if ($tableName === $table || sprintf("public.$tableName", ) === $table) { // todo schema from Doctrine
                    $nextMeta = $rel;
                    break;
                }
            }
            if ($nextMeta === null) {
                throw new DoctrineDtoException("Metadata for JOIN not found"); // todo добавить больше инфы в ошибку
            }

            $dtoMetadataMap[$currentRelation] = $nextMeta;
        }

        return $dtoMetadataMap;
    }

    /**
     * @param DtoMetadata[] $dtoMetadataList
     * @return array<string, ColumnMetadata> Ключи - название колонок в SELECT
     */
    private static function createColumnMetadataMap(array $dtoMetadataList): array
    {
        /** @var ColumnMetadata[] $columns */
        $columns = [];

        foreach ($dtoMetadataList as $alias => $dtoMetadata) {
            $columnMetadata = new ColumnMetadata();
            $columnMetadata->isPrimaryKey = true;
            $columnMetadata->dtoClassName = $dtoMetadata->className;
            $columnMetadata->parentClassName = $dtoMetadata->parentClass;
            $columnMetadata->parentPropertyName = $dtoMetadata->parentProperty;
            $columnMetadata->isArray = $dtoMetadata->isArray;

            $nameInQuery = sprintf('%s.%s', $alias, $dtoMetadata->doctrine->primaryKey);
            $columns[$nameInQuery] = $columnMetadata;

            foreach ($dtoMetadata->properties as $property) {
                $nameInQuery = sprintf('%s.%s', $alias, $property->doctrine->columnName);
                $columnMetadata = $columns[$nameInQuery] ?? null;
                if ($columnMetadata === null) {
                    $columnMetadata = new ColumnMetadata();
                    $columns[$nameInQuery] = $columnMetadata;
                }

                $columnMetadata->dtoPropertyName = $property->name;
                $columnMetadata->type = $property->type;
            }
        }

        return $columns;
    }
}
