# Laminas

Wire DOM-ORM into a [Laminas](https://docs.laminas.dev) 3 app. Laminas controllers
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
            'config' => [__DIR__ . '/../data/dom-orm'],
        ],
        'filename' => 'data.xml',
        'encryption_key' => getenv('DOM_ORM_ENCRYPTION_KEY') ?: null, // optional
    ],
];
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

Add the trait to the controller — no container registration needed. The trait
auto-initializes on first use:

```php
// src/Controller/TagController.php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tag;
use DOM\ORM\EntityRepository;
use DOM\ORM\Traits\EntityManagerTrait;
use Laminas\Mvc\Controller\AbstractActionController;
use Psr\Http\Message\ResponseInterface;

class TagController extends AbstractActionController
{
    use EntityManagerTrait;

    public function add(): ResponseInterface
    {
        $this->persist(new Tag('Hello'));

        return $this->getResponse();
    }
}
```

Queries go through `EntityRepository` as usual:

```php
$tags = (new EntityRepository(Tag::class))->findAll();
```
