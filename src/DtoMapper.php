<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class DtoMapper
{
    private EntityManagerInterface $entityManager;
    private DtoMetadataFactory $dtoMetadataFactory;

    public function __construct(EntityManagerInterface $entityManager, DtoMetadataFactory $dtoMetadataFactory)
    {
        $this->entityManager = $entityManager;
        $this->dtoMetadataFactory = $dtoMetadataFactory;
    }

    /**
     * @template T
     * @param class-string<T> $dtoClassName
     * @param QueryBuilder $queryBuilder
     * @return T[]
     */
    public function array(string $dtoClassName, QueryBuilder $queryBuilder): array
    {
        $dtoMetadata = $this->dtoMetadataFactory->create($dtoClassName);

        $sql = $queryBuilder->getQuery()->getSQL();
        $sqlMetadata = SqlMetadataFactory::create($dtoMetadata, $sql);

        $paramValues = [];
        $paramTypes = [];
        foreach ($queryBuilder->getQuery()->getParameters() as $param) {
            $paramValues[] = $param->getValue();
            $paramTypes[] = $param->getType();
        }

        $rows = $this->entityManager->getConnection()->fetchAllNumeric($sqlMetadata->sql, $paramValues, $paramTypes);

        return DtoHydrator::hydrate($sqlMetadata->columns, $rows);
    }
}
