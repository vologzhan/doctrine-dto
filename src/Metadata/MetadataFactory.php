<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\Metadata;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Vologzhan\DoctrineDto\Exception\DoctrineDtoException;
use Vologzhan\DoctrineDto\Metadata\Dto\DtoMetadata;
use Vologzhan\DoctrineDto\Metadata\Dto\Property;
use Vologzhan\DoctrineDto\Metadata\Dto\PropertyRel;

final class MetadataFactory
{
    private EntityManagerInterface $em;
    private PropertyInfoExtractor $propertyInfoExtractor;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->propertyInfoExtractor = new PropertyInfoExtractor([], [new PhpDocExtractor()]);
    }

    /**
     * @throws DoctrineDtoException
     * @throws MappingException
     * @throws \ReflectionException
     */
    public function create(string $dtoClassName, string $entityClassName): DtoMetadata
    {
        $dtoReflection = new \ReflectionClass($dtoClassName);
        $dtoMetadata = $this->createRecursive($dtoReflection);

        $entityMetadata = $this->em->getClassMetadata($entityClassName);
        $this->addEntityMetadataRecursive($dtoMetadata, $entityMetadata);

        return $dtoMetadata;
    }

    /**
     * @throws DoctrineDtoException
     * @throws \ReflectionException
     */
    private function createRecursive(\ReflectionClass $class): DtoMetadata
    {
        $properties = [];
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

                $valueTypes = $docTypes[0]->getCollectionValueTypes();
                if (count($valueTypes) !== 1) {
                    throw new DoctrineDtoException("'$class->name::$prop->name' array must be typed by a single class '@var ClassName[]'");
                }

                $nextClassName = $valueTypes[0]->getClassName();
                $nextClass = new \ReflectionClass($nextClassName);
                $properties[] = new PropertyRel($prop->name, true, $this->createRecursive($nextClass));
                continue;
            }

            if ($type->isBuiltin() || is_subclass_of($type->getName(), \DateTimeInterface::class)) {
                $properties[] = new Property($prop->name);
                continue;
            }

            $nextClass = new \ReflectionClass($type->getName());
            $properties[] = new PropertyRel($prop->name, false, $this->createRecursive($nextClass));
        }

        return new DtoMetadata($class->name, $properties);
    }

    /**
     * @throws DoctrineDtoException
     * @throws MappingException
     */
    private function addEntityMetadataRecursive(DtoMetadata $dtoMetadata, ClassMetadata $entityMetadata): void
    {
        $dtoMetadata->tableName = $entityMetadata->getTableName();

        foreach ($dtoMetadata->properties as $prop) {
            if ($prop instanceof PropertyRel) {
                $nextEntity = $entityMetadata->getAssociationMapping($prop->property->name);
                $nextEntityMetadata = $this->em->getClassMetadata($nextEntity['targetEntity']);

                if ($nextEntity['isOwningSide']) {
                    $prop->property->columnName = $nextEntity['joinColumns'][0]['name'];
                    $prop->foreignColumn = $nextEntity['joinColumns'][0]['referencedColumnName'];
                } else {
                    $ownerMapping = $nextEntityMetadata->getAssociationMapping($nextEntity['mappedBy']);
                    $prop->property->columnName = $ownerMapping['joinColumns'][0]['referencedColumnName'];
                    $prop->foreignColumn = $ownerMapping['joinColumns'][0]['name'];
                }

                $this->addEntityMetadataRecursive($prop->dtoMetadata, $nextEntityMetadata);
                continue;
            }

            if (!$entityMetadata->hasField($prop->name)) {
                throw new DoctrineDtoException("'$entityMetadata->name::$prop->name' does not exist");
            }
            $prop->columnName = $entityMetadata->getColumnName($prop->name);
        }
    }
}
