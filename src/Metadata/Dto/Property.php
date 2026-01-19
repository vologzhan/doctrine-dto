<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\Metadata\Dto;

class Property
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}
