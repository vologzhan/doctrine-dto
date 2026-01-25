<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\Metadata;

class Property
{
    public string $name;
    public ?string $type;

    public PropertyDoctrine $doctrine;

    public function __construct(string $name, ?string $type, ?PropertyDoctrine $doctrine = null)
    {
        $this->name = $name;
        $this->type = $type;

        if ($doctrine !== null) {
            $this->doctrine = $doctrine;
        }
    }
}
