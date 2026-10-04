# Slim

Wire DOM-ORM into a [Slim](https://www.slimframework.com) 4 app. Slim's route
handlers are closures, so register a small service that uses `EntityManagerTrait`
in the DI container (Pimple, PHP-DI, or any PSR-11).

## Installation

```bash
composer require vardumper/dom-orm
```

## Config

Create `config/dom-orm.php` (DOM-ORM auto-loads it from the working directory):

```php
// config/dom-orm.php
<?php return [
    'dom-orm' => [
        'flysystem' => [
            'adapter' => League\Flysystem\Local\LocalFilesystemAdapter::class,
            'config' => [__DIR__ . '/../storage/dom-orm'],
        ],
        'filename' => 'data.xml',
        'encryption_key' => getenv('DOM_ORM_ENCRYPTION_KEY') ?: null, // optional
    ],
];
```

## Wiring

Create a service that uses the trait:

```php
// src/Orm.php
<?php

declare(strict_types=1);

use DOM\ORM\Traits\EntityManagerTrait;

class Orm
{
    use EntityManagerTrait;
}
```

Register it in the container (PHP-DI example):

```php
// config/container.php
<?php

use DI\ContainerBuilder;

$builder = new ContainerBuilder();
$builder->addDefinitions([
    'domOrm' => Orm::class,
]);

return $builder->build();
```

## Entity

A minimal entity to work with:

```php
// src/Entity/Tag.php
use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'tag')]
class Tag extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $name,
    ) {
        parent::__construct();
    }
}
```

## Usage

Resolve the service from the container in a route handler:

```php
// src/routes.php
$app->get('/tags', function (Psr\Http\Message\ResponseInterface $response) {
    $orm = $this->container->get('domOrm');
    $orm->persist(new Tag('Hello'));

    return $response;
});
```

Queries go through `EntityRepository` as usual:

```php
$tags = (new EntityRepository(Tag::class))->findAll();
```
