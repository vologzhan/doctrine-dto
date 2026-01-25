<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Vologzhan\DoctrineDto\Annotation\Dto;
use Vologzhan\DoctrineDto\Metadata\DtoDoctrine;
use Vologzhan\DoctrineDto\Metadata\DtoMetadata;
use Vologzhan\DoctrineDto\Metadata\Property;
use Vologzhan\DoctrineDto\Metadata\PropertyDoctrine;
use Vologzhan\DoctrineDto\Exception\DoctrineDtoException;

class DtoMetadataFactory
{
    private EntityManagerInterface $em;

    private AnnotationReader $annotationReader;
    private PropertyInfoExtractor $propertyInfoExtractor;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;

        $this->annotationReader = new AnnotationReader(); // todo di
        $this->propertyInfoExtractor = new PropertyInfoExtractor([], [new PhpDocExtractor()]); // todo di
    }

    /**
     * @throws DoctrineDtoException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function create(string $dtoClassName): DtoMetadata
    {
        $dtoReflection = new \ReflectionClass($dtoClassName);
        $dtoMetadata = $this->createRecursive($dtoReflection);

        $entityClassName = $this->getEntityClassName($dtoReflection);
        $entityMetadata = $this->em->getClassMetadata($entityClassName);

        $this->addDoctrineMetadataRecursive($dtoMetadata, $entityMetadata);

        return $dtoMetadata;
    }

    /**
     * @throws DoctrineDtoException
     * @throws \ReflectionException
     */
    private function createRecursive(
        \ReflectionClass $class,
        ?\ReflectionClass $parentClassRef = null,
        ?\ReflectionProperty $parentPropertyRef = null
    ): DtoMetadata {
        $properties = [];
        $relations = [];
        foreach ($class->getProperties() as $prop) {
            $type = $prop->getType();
            if ($type === null) {
                throw new DoctrineDtoException("'$class->name::$prop->name' must be typed");
            }

            if ($type->getName() === 'array') {
                $docTypes = $this->propertyInfoExtractor->getTypes($class->name, $prop->name);
                if (count($docTypes) !== 1 || !$docTypes[0]->isCollection()) {
                    throw new DoctrineDtoException("'$class->name::$prop->name' array must be typed by a single class '@var ClassName[]'");
                }

                $collectionType = $docTypes[0]->getCollectionValueType();
                if ($collectionType === null) {
                    throw new DoctrineDtoException("'$class->name::$prop->name' array must be typed by a single class '@var ClassName[]'");
                }

                $nextClassName = $collectionType->getClassName();
                $relations[] = $this->createRecursive(new \ReflectionClass($nextClassName), $class, $prop);
                continue;
            }

            $typeName = $type->getName();
            if (is_a($typeName, \DateTimeInterface::class, true) || $type->isBuiltin()) {
                $properties[] = new Property($prop->name, $typeName);
                continue;
            }

            $nextClass = new \ReflectionClass($type->getName());
            $relations[] = $this->createRecursive($nextClass, $class, $prop);
        }

        $parentClass = null;
        if ($parentClassRef) {
            $parentClass = $parentClassRef->name;
        }

        $parentProperty = null;
        $isArray = false;
        if ($parentPropertyRef) {
            $parentProperty = $parentPropertyRef->name;
            $isArray = $parentPropertyRef->getType()->getName() === 'array';
        }

        return new DtoMetadata(
            $class->name,
            $isArray,
            $parentClass,
            $parentProperty,
            $properties,
            $relations,
        );
    }

    /**
     * @throws DoctrineDtoException
     * @throws MappingException
     */
    private function addDoctrineMetadataRecursive(DtoMetadata $dtoMetadata, ClassMetadata $entityMetadata): void
    {
        $tableName = $entityMetadata->getTableName();
        $primaryKey = $entityMetadata->getSingleIdentifierColumnName();
        $dtoMetadata->doctrine = new DtoDoctrine($tableName, $primaryKey);

        foreach ($dtoMetadata->relations as $rel) {
            $nextEntity = $entityMetadata->getAssociationMapping($rel->parentProperty);
            $nextEntityMetadata = $this->em->getClassMetadata($nextEntity['targetEntity']);

            $this->addDoctrineMetadataRecursive($rel, $nextEntityMetadata);
        }

        foreach ($dtoMetadata->properties as $prop) {
            if (!$entityMetadata->hasField($prop->name)) {
                throw new DoctrineDtoException("'$entityMetadata->name::$prop->name' does not exist");
            }

            $columnName = $entityMetadata->getColumnName($prop->name);
            $prop->doctrine = new PropertyDoctrine($columnName);
        }
    }

    private function getEntityClassName(\ReflectionClass $dtoClass): string
    {
        $annotation = $this->annotationReader->getClassAnnotation($dtoClass, Dto::class);

        if ($annotation) {
            return $annotation->entityClassName;
        }

        $annotation = $this->getEntityClassNameFromInterface($dtoClass);
        if ($annotation) {
            return $annotation->entityClassName;
        }

        throw new DoctrineDtoException(sprintf("Class '%s' annotation '%s' not found", $dtoClass->name, Dto::class));
    }

    private function getEntityClassNameFromInterface(\ReflectionClass $dtoClass): ?Dto
    {
        foreach ($dtoClass->getInterfaces() as $interface) {
            $annotation = $this->annotationReader->getClassAnnotation($interface, Dto::class);
            if ($annotation) {
                return $annotation;
            }

            $annotation = $this->getEntityClassNameFromInterface($interface);
            if ($annotation) {
                return $annotation;
            }
        }

        return null;
    }
}
