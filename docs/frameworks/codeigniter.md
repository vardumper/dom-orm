# CodeIgniter 4

Wire DOM-ORM into a [CodeIgniter 4](https://codeigniter.com) app. CodeIgniter
controllers are classes, so just add `EntityManagerTrait` to the controller.

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
            'config' => [ROOTPATH . 'writable/dom-orm'],
        ],
        'filename' => 'data.xml',
        'encryption_key' => env('DOM_ORM_ENCRYPTION_KEY'), // optional
    ],
];
```

## Entity

A minimal entity to work with:

```php
// app/Entities/Tag.php
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

Add the trait to the controller — no service provider needed. The trait
auto-initializes on first use:

```php
// app/Controllers/TagController.php
<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Entities\Tag;
use DOM\ORM\EntityRepository;
use DOM\ORM\Traits\EntityManagerTrait;

class TagController extends BaseController
{
    use EntityManagerTrait;

    public function add(): \CodeIgniter\HTTP\Response
    {
        $this->persist(new Tag('Hello'));

        return $this->response->setJSON(['ok' => true]);
    }
}
```

Queries go through `EntityRepository` as usual:

```php
$tags = (new EntityRepository(Tag::class))->findAll();
```
