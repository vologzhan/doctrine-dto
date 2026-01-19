<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\Metadata;

use phpDocumentor\Reflection\TypeResolver;
use phpDocumentor\Reflection\Types\Context;
use phpDocumentor\Reflection\Types\ContextFactory;
use Vologzhan\DoctrineDto\Exception\DoctrineDtoException;
use Vologzhan\DoctrineDto\Metadata\Dto\DtoMetadata;
use Vologzhan\DoctrineDto\Metadata\Dto\Property;
use Vologzhan\DoctrineDto\Metadata\Dto\PropertyRel;

class DtoMetadataFactory
{
    /**
     * @throws DoctrineDtoException
     */
    public static function parse(string $dtoClassName): DtoMetadata
    {
        $dtoReflection = self::getDtoReflection($dtoClassName);

        return self::parseRecursive($dtoReflection);
    }

    /**
     * @throws DoctrineDtoException
     */
    private static function parseRecursive(\ReflectionClass $class): DtoMetadata
    {
        /** @var Context|null $classContext */
        $classContext = null;

        $properties = [];
        foreach ($class->getProperties() as $prop) {
            $type = $prop->getType();
            if ($type === null) {
                throw new DoctrineDtoException("'$class->name::$prop->name' must be typed");
            }

            if ($type->getName() === 'array') {
                $docComment = $prop->getDocComment();
                if (!$docComment) {
                    throw new DoctrineDtoException("'$class->name::$prop->name' array must be typed using '@var ClassName[]'");
                }

                $match = [];
                $isMatched = preg_match('/@var\s+(\S+)\[/', $docComment, $match);
                if (!$isMatched) {
                    throw new DoctrineDtoException("'$class->name::$prop->name' array must be typed using '@var ClassName[]'");
                }
                $nextClassName = $match[1];

                if ($classContext === null) {
                    $classContext = (new ContextFactory())->createFromReflector($class);
                }
                $resolvedType = (new TypeResolver())->resolve($nextClassName, $classContext);

                $nextClassFullName = (string)$resolvedType->getFqsen();
                $nextDto = self::getDtoReflection($nextClassFullName);

                $properties[] = new PropertyRel($prop->name, true, self::parseRecursive($nextDto));
                continue;
            }

            if ($type->isBuiltin() || is_subclass_of($type->getName(), \DateTimeInterface::class)) {
                $properties[] = new Property($prop->name);
                continue;
            }

            $nextDto = self::getDtoReflection($type->getName());
            $properties[] = new PropertyRel($prop->name, false, self::parseRecursive($nextDto));
        }

        return new DtoMetadata($class->name, $properties);
    }

    /**
     * @throws DoctrineDtoException
     */
    private static function getDtoReflection(string $dtoClassName): \ReflectionClass
    {
        try {
            return new \ReflectionClass($dtoClassName);
        } catch (\ReflectionException $e) {
            throw new DoctrineDtoException("'$dtoClassName' does not exist", 0, $e);
        }
    }
}
