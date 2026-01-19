<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Dto
{
    public string $entityClassName = '';
}
