<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\Metadata;

class DtoDoctrine
{
    public string $tableName;
    public string $primaryKey;

    public function __construct(string $tableName, string $primaryKey)
    {
        $this->tableName = $tableName;
        $this->primaryKey = $primaryKey;
    }
}
