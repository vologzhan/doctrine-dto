<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\SqlMetadata;

class ColumnMetadata
{
    public ?string $dtoClassName = null;
    public ?string $dtoPropertyName = null;

    public ?string $parentClassName = null;
    public ?string $parentPropertyName = null;

    public bool $isPrimaryKey = false;
    public bool $isArray = false;
    public ?string $type = null;
}
