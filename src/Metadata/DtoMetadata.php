<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\Metadata;

class DtoMetadata
{
    public string $className;
    public bool $isArray;

    public ?string $parentClass;
    public ?string $parentProperty;

    /** @var Property[] */
    public array $properties;

    /** @var DtoMetadata[] */
    public array $relations;

    public DtoDoctrine $doctrine;

    public function __construct(
        string $className,
        bool $isArray,
        ?string $parentClass,
        ?string $parentProperty,
        array $properties,
        array $relations,
        ?DtoDoctrine $doctrine = null
    ) {
        $this->className = $className;
        $this->isArray = $isArray;
        $this->parentClass = $parentClass;
        $this->parentProperty = $parentProperty;
        $this->properties = $properties;
        $this->relations = $relations;

        if ($doctrine !== null) {
            $this->doctrine = $doctrine;
        }
    }
}
