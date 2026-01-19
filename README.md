Маппинг SQL запросов на DTO с использованием [Doctrine](https://github.com/doctrine/orm)

### Dto

Все данные для DTO выбираются **одним SQL запросом**.

Решаемые проблемы:
  * Избежание [N+1](https://stackoverflow.com/questions/97197/what-is-the-n1-selects-problem-in-orm-object-relational-mapping)
    запросов. Для связей можно указать `fetch="EAGER"`, но такие связи всегда будут подгружаться, даже когда это не нужно
  * Нативный SQL запрос невозможно нормально смаппить на Entity
  * Если Entity не является владельцем ключа, то Doctrine делает лишний JOIN извлекая связанную Entity в отношениях one-to-one,
    на это поведение никакими аннотациями повлиять нельзя
  * Сократить количество данных в SELECT. Doctrine всегда подставляет все имеющиеся столбцы в запрос. Можно переместить
    поля сущности в трейты и подключать трейты в разные варианты сущностей, но и тут не без проблем:
    * Если связи сами являются вариантами сущностей, то для каждой такой связи нужно создавать отдельный трейт,
      иначе не получится нормально типизировать связи
    * В сущности всегда должно присутствовать поле идентификатора, даже если оно не будет использоваться в коде
    * Некоторые связи требуют обязательного наличия поля в классе владельца, даже если оно не будет использоваться в коде
    * При составлении запросов через QueryBuilder в сущность придется добавить все поля из запроса, например `deleted_at IS NULL`,
      даже если они не будут использоваться кодом
    * Сами трейты некоторые считают антипаттерном

### Entity mapping to Dto

Entity и DTO связываются по названию полей в сущности. Наименование классов DTO может быть любым, главное чтобы
поля в DTO совпадали с полями в сущности.

Рассмотрим на примере email рассылки с новостями города, шаблон письма:
```html
<h1>Здравствуйте, {{ Profile.secondName }} {{ Profile.firstName }}!</h1>
<h2>Новости города {{City.name}}:</h2>

{% for News in NewsList %}
    <a href="{{ News.link }}">{{ News.title }}</a>
{% endfor %}
```

Описание реляционной модели:
  * `User` из этой сущности никакие поля не используются
  * `User` one-to-one `Profile` используются `firstName`, `secondName` и `email`
  * `User` many-to-one `City` используются `name`
  * `City` one-to-many `News` используются `title` и `link`

Классы DTO:

```php
use Vologzhan\DoctrineDto\Annotation\Dto;

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
```

### Annotation [@Dto](src/Annotation/Dto.php)

Добавляется к DTO для связи с Entity. Аннотация нужна только при вызове методов [DtoMapper](src/DtoMapper.php)
и только для DTO указанного при вызове метода, для вложенных DTO маппер получит их Entity из Doctrine.
**Обязательно указать полное имя класса с лидирующим \\** - только этот вариант нормально обрабатывает PhpStorm

```php
use Vologzhan\DoctrineDto\Annotation\Dto;

/**
 * @Dto(\App\Entity\User::class) 
 */
class UserForNotification
{
    // ...
}
```

Если DTO для одной Entity не хранятся в одной директории и разбросаны по проекту, то рекомендуется создать пустой
интерфейс с аннотацией и имплементировать этот пустой интерфейс для всех DTO (даже вложенных), это упростит поиск DTO в проекте.

```php
use Vologzhan\DoctrineDto\Annotation\Dto;

/**
 * @Dto(\App\Entity\User::class) 
 */
interface UserDtoInterface
{
}
```

```php
// здесь нет аннотации, она указана в интерфейсе
class UserForNotification implements UserDtoInterface
{
    // ...
}
```

### [DtoMapper](src/DtoMapper.php)

Для возможности использования в любом репозитории и для избегания сложностей в настройке di все **методы** статические:
  * `one` получение одного DTO
  * `oneOrNull` получение одного или нуль DTO
  * `array` для получения списка DTO

Типы **возвращаемых значений** выводятся с помощью дженериков на основании имени класса DTO.

Все методы имеют одинаковую сигнатуру **принимаемых аргументов функции**:
  * `dtoClassName` название класса DTO, например `UserForNotification::class`
  * `doctrine` - EntityManagerInterface или QueryBuilder
  * `sql (optional)` - только для нативных запросов. Для QueryBuilder sql берется из билдера
  * `params (optional)` - только для нативных запросов. Для QueryBuilder параметры берутся из билдера
    * Неименованные параметры `?`, передается индексный массив (ключи - целые числа, нумерация идет по порядку и начинается с нуля)
    * Именованные параметры, например `:user_id`, передается ассоциативный массив

### Native SQL

В запросе в `SELECT` используется просто `*`, маппер на основе DTO сам выберет какие колонки должны возвращаться.

```php
namespace App\Repository;

use App\Dto\UserForNotification;
use Doctrine\ORM\EntityManagerInterface;
use Vologzhan\DoctrineDto\DtoMapper;

class UserForNotificationRepository
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function find(int $id): UserForNotification
    {
        $sql = <<<SQL
            SELECT *
            FROM users u
              LEFT JOIN profile p ON p.user_id = u.id
              LEFT JOIN city c ON c.id = u.city_id
              LEFT JOIN news n ON n.city_id = c.id
            WHERE u.id = ?
            SQL;

        return DtoMapper::one(UserForNotification::class, $this->em, $sql, [$id]);
    }
}
```

### QueryBuilder

Для первой реализации указание всех джойнов обязательно, потом джойны будут достраиваться автоматически.

```php
namespace App\Repository;

use App\Dto\UserForNotification;
use Vologzhan\DoctrineDto\DtoMapper;

class UserForNotificationRepository
{
    public function find(int $id): UserForNotification
    {
        $qb = $this
            ->createQueryBuilder('u')
            ->leftJoin('u.profile', 'p')
            ->leftJoin('u.city', 'c')
            ->leftJoin('c.news', 'n')
            ->andWhere('u.id = :id')
            ->setParameter(':id', $id);

        return DtoMapper::one(UserForNotification::class, $qb);
    }
}
```

