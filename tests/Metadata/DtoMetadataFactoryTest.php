<?php

declare(strict_types=1);

namespace Vologzhan\DoctrineDto\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Vologzhan\DoctrineDto\Annotation\Dto;
use Vologzhan\DoctrineDto\Metadata\DtoMetadataFactory;
use Vologzhan\DoctrineDto\Metadata\Dto\DtoMetadata;
use Vologzhan\DoctrineDto\Metadata\Dto\Property;
use Vologzhan\DoctrineDto\Metadata\Dto\PropertyRel;

final class DtoMetadataFactoryTest extends TestCase
{
    public function test(): void
    {
        $metadata = DtoMetadataFactory::parse(UserForNotification::class);

        $this->assertEquals(
            new DtoMetadata(UserForNotification::class, [
                new PropertyRel('profile', false, new DtoMetadata(ProfileForNotification::class, [
                    new Property('firstName'),
                    new Property('secondName'),
                    new Property('email'),
                ])),
                new PropertyRel('city', false, new DtoMetadata(CityForNotification::class, [
                    new Property('name'),
                    new PropertyRel('news', true, new DtoMetadata(NewsForNotification::class, [
                        new Property('title'),
                        new Property('link'),
                    ]))
                ]))
            ]),
            $metadata,
        );
    }
}

/**
 * @Dto(\App\Entity\User::class)
 */
class UserForNotification
{
    public ProfileForNotification $profile;
    public CityForNotification $city;
}

class ProfileForNotification
{
    public string $firstName;
    public string $secondName;
    public string $email;
}

class CityForNotification
{
    public string $name;

    /** @var NewsForNotification[] */
    public array $news;
}

class NewsForNotification
{
    public string $title;
    public string $link;
}
