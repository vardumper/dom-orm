# CakePHP

Wire DOM-ORM into a [CakePHP](https://book.cakephp.org) 4/5 app. CakePHP controllers
are classes, so just add `EntityManagerTrait` to the controller.

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
            'config' => [__DIR__ . '/../tmp/dom-orm'],
        ],
        'filename' => 'data.xml',
        'encryption_key' => env('DOM_ORM_ENCRYPTION_KEY'), // optional
    ],
];
```

## Usage

Add the trait to the controller — no container registration needed. The trait
auto-initializes on first use:

```php
// src/Controller/TagController.php
<?php

declare(strict_types=1);

namespace App\Controller;

use DOM\ORM\{EntityRepository, Mapping\Item};
use DOM\ORM\Traits\EntityManagerTrait;

class TagController extends AppController
{
    use EntityManagerTrait;

    public function add(): \Cake\Http\Response
    {
        $this->persist(new Tag('Hello'));

        return $this->response->withStringBody('ok');
    }
}
```

Queries go through `EntityRepository` as usual:

```php
$tags = (new EntityRepository(Tag::class))->findAll();
```
