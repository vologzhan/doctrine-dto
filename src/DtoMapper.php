<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

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
     * @param EntityManagerInterface|QueryBuilder $doctrine
     * @return T[]
     */
    public static function array(string $dtoClassName, $doctrine, string $sql = '', array $params = []): array
    {
        // todo
    }
}
