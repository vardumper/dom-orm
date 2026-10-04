# Phalcon

Wire DOM-ORM into a [Phalcon](https://phalcon.io) 5 app. Phalcon is a C-extension
framework, but DOM-ORM is pure PHP, so it works the same way: just add
`EntityManagerTrait` to any class (controllers, services, etc.).

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
            'config' => [__DIR__ . '/../private/dom-orm'],
        ],
        'filename' => 'data.xml',
        'encryption_key' => getenv('DOM_ORM_ENCRYPTION_KEY') ?: null, // optional
    ],
];
```

## Entity

A minimal entity to work with:

```php
// app/Models/Tag.php
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

Add the trait to the controller — no DI registration needed. The trait
auto-initializes on first use:

```php
// app/Controllers/TagController.php
<?php

declare(strict_types=1);

use DOM\ORM\EntityRepository;
use DOM\ORM\Traits\EntityManagerTrait;
use Phalcon\Mvc\Controller;

class TagController extends Controller
{
    use EntityManagerTrait;

    public function add(): void
    {
        $this->persist(new Tag('Hello'));
    }
}
```

Queries go through `EntityRepository` as usual:

```php
$tags = (new EntityRepository(Tag::class))->findAll();
```
