<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\QueryBuilder;
use PHPSQLParser\PHPSQLParser;
use Vologzhan\DoctrineDto\Exception\DoctrineDtoException;
use Vologzhan\DoctrineDto\Metadata\Dto\DtoMetadata;
use Vologzhan\DoctrineDto\Metadata\Dto\Property;
use Vologzhan\DoctrineDto\Metadata\Dto\PropertyRel;
use Vologzhan\DoctrineDto\Metadata\MetadataFactory;
use Vologzhan\DoctrineDto\Tests\Entity\User;

class DtoMapper
{
    /**
     * @template T
     * @param class-string<T> $dtoClassName
     * @param EntityManagerInterface|QueryBuilder $doctrine
     * @return T
     */
    public static function one(string $dtoClassName, $doctrine, string $sql = '', array $params = [])
    {
        // todo
    }

    /**
     * @template T
     * @param class-string<T> $dtoClassName
     * @param EntityManagerInterface|QueryBuilder $doctrine
     * @return T|null
     */
    public static function oneOrNull(string $dtoClassName, $doctrine, string $sql = '', array $params = [])
    {
        // todo
    }

    /**
     * @template T
     * @param class-string<T> $dtoClassName
     * @param QueryBuilder $doctrine
     * @return T[]
     *
     * @throws DoctrineDtoException
     */
    public static function array(string $dtoClassName, QueryBuilder $doctrine): string
    {
        $em = $doctrine->getEntityManager();

        $sql = $doctrine->getQuery()->getSQL();
        if ($sql === '') {
            throw new DoctrineDtoException('empty sql');
        }

        $from = self::eraseSelectStatement($sql);
        $selects = self::getSelect($dtoClassName, $from, $em);
        $fullSql = sprintf("SELECT %s %s", implode(', ', array_keys($selects)), $from);

        $paramValues = [];
        $paramTypes = [];

        foreach ($doctrine->getQuery()->getParameters() as $param) {
            $paramValues[$param->getName()] = $param->getValue();
            $paramTypes[$param->getName()] = $param->getType();
        }

        $result = $em->getConnection()->fetchAllNumeric($fullSql, $paramValues, $paramTypes);

        return $sql;
    }

    private static function eraseSelectStatement(string $sql): string
    {
        return preg_replace('/SELECT\s+(.+?)\s+FROM/i', 'FROM', $sql);
    }

    private static function getSelect(string $dtoClassName, string $sql, EntityManagerInterface $em): array
    {
        $metadataList = self::getMetadata($dtoClassName, $sql, $em);

        $columns = [];
        foreach ($metadataList as $alias => $meta) {
            // false - id missing in properties
            // todo ключи только для листов
            $columns[sprintf('%s.%s', $alias, $meta->primaryKey)] = false;

            foreach ($meta->properties as $property) {
                if ($property instanceof Property) {
                    $columns[sprintf('%s.%s', $alias, $property->columnName)] = true;
                }
            }
        }

        return $columns;
    }

    /**
     * @throws MappingException
     * @throws DoctrineDtoException
     * @throws \ReflectionException
     */
    private static function getMetadata(string $dtoClassName, string $sql, EntityManagerInterface $em): array
    {
        $parser = new PHPSQLParser();
        $ast = $parser->parse($sql);

        $metadataFactory = new MetadataFactory($em);
        $metadata = $metadataFactory->create($dtoClassName, User::class);

        /** @var DtoMetadata[] $joins */
        $joins = [];
        foreach ($ast['FROM'] as $i => $from) {
            $table = $from['table'];
            $alias = $from['alias']['name'] ?? null;
            $name = $alias ?: $table;

            if ($i === 0) {
                if ($table !== $metadata->tableName) {
                    throw new DoctrineDtoException("Запрос должен начинаться с 'FROM $metadata->tableName'");
                }
                $joins[$name] = $metadata;

                continue; // основная таблица
            }

            $rel1 = $from['ref_clause'][0]['no_quotes']['parts'][0];
            $rel2 = $from['ref_clause'][2]['no_quotes']['parts'][0];

            $currentMeta = null;
            $currentRelation = null;
            if (array_key_exists($rel1, $joins)) {
                $currentMeta = $joins[$rel1];
                $currentRelation = $rel2;
            } else {
                $currentMeta = $joins[$rel2] ?? null;
                $currentRelation = $rel1;
            }
            if ($currentMeta === null) {
                throw new DoctrineDtoException("JOIN parse error"); // todo добавить больше инфы в ошибку
            }

            $nextMeta = null;
            foreach ($currentMeta->properties as $prop) {
                if ($prop instanceof PropertyRel && $prop->dtoMetadata->tableName === $table) {
                    $nextMeta = $prop->dtoMetadata;
                    break;
                }
            }
            if ($nextMeta === null) {
                throw new DoctrineDtoException("Metadata for JOIN not found"); // todo добавить больше инфы в ошибку
            }

            $joins[$currentRelation] = $nextMeta;
        }

        return $joins;
    }
}
