# Yii

Wire DOM-ORM into a Yii app (2.0+ or 3.x). The library is framework-agnostic —
just add `EntityManagerTrait` to any class (controllers, services, etc.).

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
            'config' => [__DIR__ . '/../runtime/dom-orm'],
        ],
        'filename' => 'data.xml',
        'encryption_key' => getenv('DOM_ORM_ENCRYPTION_KEY') ?: null, // optional
    ],
];
```

## Usage

Add the trait to any class — no container registration needed. The trait
auto-initializes on first use:

```php
// controllers/TagController.php
<?php

declare(strict_types=1);

use DOM\ORM\{EntityRepository, Mapping\Item};
use DOM\ORM\Traits\EntityManagerTrait;
use yii\web\Controller;

class TagController extends Controller
{
    use EntityManagerTrait;

    public function actionAdd(string $name): void
    {
        $this->persist(new Tag($name));
    }
}
```

Queries go through `EntityRepository` as usual:

```php
$tags = (new EntityRepository(Tag::class))->findAll();
```
