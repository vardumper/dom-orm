# Symfony

Wire DOM-ORM into a [Symfony](https://symfony.com) 8 app. The library is framework-agnostic —
just add `EntityManagerTrait` to any service (controllers are services too).

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
            'config' => [__DIR__ . '/../var/dom-orm'],
        ],
        'filename' => 'data.xml',
        'encryption_key' => getenv('DOM_ORM_ENCRYPTION_KEY') ?: null, // optional
    ],
];
```

> Symfony's `%env()%` placeholders are not resolved inside a plain PHP config file.
> Use `getenv()` as above, or set `DOM_ORM_ENCRYPTION_KEY` and let DOM-ORM's env
> config take over.

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

Add the trait to any service — no wrapper class needed. The trait auto-initializes
on first use:

```php
// src/Controller/TagController.php
<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tag;
use DOM\ORM\EntityRepository;
use DOM\ORM\Traits\EntityManagerTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class TagController extends AbstractController
{
    use EntityManagerTrait;

    public function add(string $name): Response
    {
        $this->persist(new Tag($name));
        return new Response('ok');
    }
}
```

Queries go through `EntityRepository` as usual:

```php
$tags = (new EntityRepository(Tag::class))->findAll();
```
