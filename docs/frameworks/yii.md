# Yii 3

Wire DOM-ORM into a [Yii 3](https://www.yiiframework.com) app. Yii 3 uses invokable
action classes (no controller inheritance), so add `EntityManagerTrait` to the action.

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

## Entity

A minimal entity to work with:

```php
// src/Domain/Tag.php
<?php

declare(strict_types=1);

namespace App\Domain;

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

Add the trait to the invokable action — no container registration needed. The trait
auto-initializes on first use:

```php
// src/Web/Tag/Action.php
<?php

declare(strict_types=1);

namespace App\Web\Tag;

use App\Domain\Tag;
use DOM\ORM\EntityRepository;
use DOM\ORM\Traits\EntityManagerTrait;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Action
{
    use EntityManagerTrait;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->persist(new Tag('Hello'));

        return $this->responseFactory->createResponse();
    }
}
```

Register the route:

```php
// config/common/routes.php
use App\Web;
use Yiisoft\Router\Route;

return [
    Route::post('/tags')->action(Web\Tag\Action::class)->name('tag.add'),
];
```

Queries go through `EntityRepository` as usual:

```php
$tags = (new EntityRepository(Tag::class))->findAll();
```
