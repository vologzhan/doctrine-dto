<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto;

use Vologzhan\DoctrineDto\SqlMetadata\ColumnMetadata;

class DtoHydrator
{
    /**
     * @param ColumnMetadata[] $metadataColumns
     */
    public static function hydrate(array $metadataColumns, array $rows): array
    {
        $dtoList = [];

        foreach ($rows as $row) {
            $currentDtoList = [];
            $skip = false;
            $dto = null;

            foreach ($row as $i => $v) {
                $metadata = $metadataColumns[$i];

                if ($metadata->isPrimaryKey) {
                    $skip = false;
                    if ($v === null) {
                        $parent = $currentDtoList[$metadata->parentClassName] ?? null;

                        if ($parent) {
                            if ($metadata->isArray) {
                                $parent->{$metadata->parentPropertyName} = [];
                            } else {
                                $parent->{$metadata->parentPropertyName} = null;
                            }
                        }

                        $skip = true;
                        continue;
                    }

                    $dto = $dtoList[$metadata->dtoClassName][$v] ?? null;
                    if ($dto === null) {
                        $dto = new $metadata->dtoClassName();

                        $dtoList[$metadata->dtoClassName][$v] = $dto;
                    }
                    $currentDtoList[$metadata->dtoClassName] = $dto;

                    if ($metadata->parentClassName) {
                        $parent = $currentDtoList[$metadata->parentClassName];
                        // todo может ли тут не быть панета???

                        if ($metadata->isArray) {
                            $parent->{$metadata->parentPropertyName}[] = $dto;
                        } else {
                            $parent->{$metadata->parentPropertyName} = $dto;
                        }
                    }
                }

                if ($skip) {
                    continue;
                }

                if ($metadata->dtoPropertyName) {
                    switch ($metadata->type) {
                        case 'float':
                            $v = (float)$v;
                            break;
                        case null:
                            break;
                        default:
                            $v = new $metadata->type($v);
                    }

                    $dto->{$metadata->dtoPropertyName} = $v;
                }
            }
        }

        $dtoClassName = $metadataColumns[0]->dtoClassName;

        return array_values($dtoList[$dtoClassName] ?? []);
    }
}
