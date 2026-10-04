# Laravel

Wire DOM-ORM into a Laravel app (9+, 10.x, 11.x, 12.x). The library is framework-agnostic —
just add `EntityManagerTrait` to any class (controllers, services, jobs, etc.).

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
            'config' => [base_path('storage/dom-orm')],
        ],
        'filename' => 'data.xml',
        'encryption_key' => env('DOM_ORM_ENCRYPTION_KEY'), // optional
    ],
];
```

## Usage

Add the trait to any class — no service provider needed. The trait auto-initializes
on first use:

```php
// app/Http/Controllers/TagController.php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use DOM\ORM\{EntityRepository, Mapping\Item};
use DOM\ORM\Traits\EntityManagerTrait;

class TagController extends Controller
{
    use EntityManagerTrait;

    public function add(string $name): Response
    {
        $this->persist(new Tag($name));

        return response('ok');
    }
}
```

Queries go through `EntityRepository` as usual:

```php
$tags = (new EntityRepository(Tag::class))->findAll();
```
