<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\SqlMetadata;

class SqlMetadata
{
    public string $sql;

    /** @var ColumnMetadata[] */
    public array $columns;

    public function __construct(string $sql, array $columns)
    {
        $this->sql = $sql;
        $this->columns = $columns;
    }
}
