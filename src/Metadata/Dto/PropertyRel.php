<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\Metadata\Dto;

class PropertyRel extends Property
{
    public bool $isArray;
    public DtoMetadata $dtoMetadata;

    public function __construct(string $name, bool $isArray, DtoMetadata $dtoMetadata)
    {
        parent::__construct($name);
        $this->isArray = $isArray;
        $this->dtoMetadata = $dtoMetadata;
    }
}
