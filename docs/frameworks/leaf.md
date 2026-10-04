# Leaf PHP

Wire DOM-ORM into a [Leaf PHP](https://leafphp.dev) app (4.x, 5.x). Leaf's route
handlers are closures, so register a small service that uses `EntityManagerTrait`
in the app container.

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
// app/Orm.php
<?php

declare(strict_types=1);

use DOM\ORM\Traits\EntityManagerTrait;

class Orm
{
    use EntityManagerTrait;
}
```

Register it in the app container:

```php
// config/app.php
<?php

return [
    // ...
    'services' => [
        'domOrm' => Orm::class,
    ],
];
```

## Usage

Resolve the service from the app in a route handler:

```php
// app/routes.php
app()->get('/tags', function () {
    $orm = app()->get('domOrm');
    $orm->persist(new Tag('Hello'));

    return response()->json(['ok' => true]);
});
```

Queries go through `EntityRepository` as usual:

```php
$tags = (new EntityRepository(Tag::class))->findAll();
```
