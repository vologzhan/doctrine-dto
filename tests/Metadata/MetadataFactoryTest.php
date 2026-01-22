<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\Tests\Metadata;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\Setup;
use PHPUnit\Framework\TestCase;
use Vologzhan\DoctrineDto\Metadata\Dto\DtoMetadata;
use Vologzhan\DoctrineDto\Metadata\Dto\Property;
use Vologzhan\DoctrineDto\Metadata\Dto\PropertyRel;
use Vologzhan\DoctrineDto\Metadata\MetadataFactory;
use Vologzhan\DoctrineDto\Tests\Dto\CityDto;
use Vologzhan\DoctrineDto\Tests\Dto\NewsDto;
use Vologzhan\DoctrineDto\Tests\Dto\ProfileDto;
use Vologzhan\DoctrineDto\Tests\Dto\UserDto;
use Vologzhan\DoctrineDto\Tests\Entity\User;

final class MetadataFactoryTest extends TestCase
{
    private MetadataFactory $metadataFactory;

    protected function setUp(): void
    {
        $reader = new AnnotationReader();
        $driver = new AnnotationDriver($reader, [__DIR__ . '../Entity']);

        $config = Setup::createConfiguration(true);
        $config->setMetadataDriverImpl($driver);

        $em = EntityManager::create(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            $config
        );

        $this->metadataFactory = new MetadataFactory($em);
    }

    public function testCreate(): void
    {
        $expected = new DtoMetadata(UserDto::class, [
            new PropertyRel('profile', false, new DtoMetadata(ProfileDto::class, [
                new Property('firstName'),
                new Property('secondName'),
                new Property('email'),
            ])),
            new PropertyRel('city', false, new DtoMetadata(CityDto::class, [
                new Property('name'),
                new PropertyRel('news', true, new DtoMetadata(NewsDto::class, [
                    new Property('title'),
                    new Property('link'),
                ]))
            ]))
        ]);

        $expected->tableName = 'users';
        $expected->primaryKey = 'id';
        $expected->properties[0]->dtoMetadata->tableName = 'profile';
        $expected->properties[0]->dtoMetadata->primaryKey = 'id';
        $expected->properties[0]->property->columnName = 'id';
        $expected->properties[0]->foreignColumn = 'user_id';
        $expected->properties[0]->dtoMetadata->properties[0]->columnName = 'first_name';
        $expected->properties[0]->dtoMetadata->properties[1]->columnName = 'second_name';
        $expected->properties[0]->dtoMetadata->properties[2]->columnName = 'email';

        $expected->properties[1]->dtoMetadata->tableName = 'city';
        $expected->properties[1]->dtoMetadata->primaryKey = 'id';
        $expected->properties[1]->property->columnName = 'city_id';
        $expected->properties[1]->foreignColumn = 'id';
        $expected->properties[1]->dtoMetadata->properties[0]->columnName = 'name';

        $expected->properties[1]->dtoMetadata->properties[1]->dtoMetadata->tableName = 'news';
        $expected->properties[1]->dtoMetadata->properties[1]->dtoMetadata->primaryKey = 'id';
        $expected->properties[1]->dtoMetadata->properties[1]->property->columnName = 'id';
        $expected->properties[1]->dtoMetadata->properties[1]->foreignColumn = 'city_id';
        $expected->properties[1]->dtoMetadata->properties[1]->dtoMetadata->properties[0]->columnName = 'title';
        $expected->properties[1]->dtoMetadata->properties[1]->dtoMetadata->properties[1]->columnName = 'link';

        $actual = $this->metadataFactory->create(UserDto::class, User::class);

        $this->assertEquals($expected, $actual);
    }
}
