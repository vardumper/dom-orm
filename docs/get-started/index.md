# Get started

## DOM-ORM? Why?
The DOM-ORM project was created to provide a simple, easy-to-use way to store data objects without the need to install and configure a database, access roles and users. Since it stores a plaintext XML file, there's no SQL Client required, and no binary files involved like in SQLite.

## Assumptions
You already know what an ORM is. You have worked with Eloquent or Doctrine.

 - [Object Relational Mapper](https://en.wikipedia.org/wiki/Object-relational_mapping) on Wikipedia
 - [What is Doctrine?](https://www.doctrine-project.org/projects/doctrine-orm/en/current/tutorials/getting-started.html#what-is-doctrine)
 - [What is Eloquent?](https://laravel.com/docs/13.x/eloquent)

## Entities
In an ORM, entities are PHP objects that map directly to database tables. Furthermore, entities are used to interact with the database table. For example to save or update an entity. 

## Respositories
Respositories are also used to interact with the database, mainly to find entities `findAll()`, `find()`, `findBy()`, `findOneBy()` but also to `remove()`

## Installation

```bash
composer require vardumper/dom-orm
```

By default, the XML file is stored on your local filesystem as `storage/data.xml` under the root of your project.
To change the location, configure a Flysystem adapter. Be aware that concurrency and locking only works with the LocalFilesystemAdapter.

```php
// config/dom-orm.php
<?php return [
  'dom-orm' => [
    'flysystem' => [
      'adapter' => League\Flysystem\Local\LocalFilesystemAdapter::class,
      'config' => [__DIR__ . '/storage'],
    ],
    'filename' => 'data.xml',
    'encryption_key' => 'your-secret-key-32-bytes-minimum!', // optional, enables #[Sensitive]
  ],
];
```

## Basic Usage

### Entity
Add PHP 8 attributes to describe how the entity is stored. An Entity becomes an `<item />`, an Entity Property is stored as `<fragment />`. Array collections of entities become `<group />`s.

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
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }
}
```

### Persistence

Use EntityManagerTrait in services or controllers:
```php
// src/Service/SomeService.php
class SomeService {
    use DOM\ORM\Traits\EntityManagerTrait;

    public function addTag(string $name) {
        $this->persist(new Tag($name));
    }
}
```

Use `persist` for saving new and updating existing entities:
```php
// src/Service/SomeService.php
class SomeService {
    use DOM\ORM\Traits\EntityManagerTrait;

    public function updateTag(string $id, string $name) {
        $tag = (new EntityRepository(Tag::class))->find($id);
        $tag->setName($name);
        $this->persist($tag);
    }
}
```

Use `remove` to delete:
```php
// src/Service/SomeService.php
class SomeService {
    use DOM\ORM\Traits\EntityManagerTrait;

    public function removeTag(string $id) {
        (new EntityRepository(Tag::class))->remove($id);
    }
}
```

### Serialization

DOM ORM generates an `ID` (a 32 char UUID string) and a `createdAt` timestamp automatically, then stores entities in a normalized XML shape.

```xml
<!-- storage/data.xml -->
<data>
  <item type="tag" id="e34cbf80edaf490aa39113254b6cdfa9">
    <fragment name="name"><![CDATA[Tagname]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-06-17T06:30:37+00:00]]></fragment>
  </item>
  ...
</data>
```

## Advanced Topics

### Scoping entities with `allowedParentPaths`

By default, entities are appended to the root `<data>` element. The `allowedParentPaths` parameter on `#[ORM\Item]` accepts XPath expressions that constrain **where** an entity may be persisted. This lets you simulate the table-scoping behaviour of a relational database.

#### Single fixed parent — automatic placement

When exactly one path is given, `persist()` resolves it automatically — no manual parent node required.

```php
// src/Entity/Article.php
#[ORM\Item(entityType: 'article', allowedParentPaths: ['//group[@type="articles"]'])]
class Article extends AbstractEntity { ... }
```

```php
// Persists directly under <group type="articles"> — no parent argument needed
$this->persist(new Article('Hello World'));
```

The resulting XML:
```xml
<data>
  <group type="articles">
    <item type="article" id="...">
      <fragment name="title"><![CDATA[Hello World]]></fragment>
    </item>
  </group>
</data>
```

#### Multiple allowed parents — explicit placement required

When several paths are listed, the entity may live in different locations and you must pass the target parent node explicitly.

```php
#[ORM\Item(entityType: 'comment', allowedParentPaths: [
    '//group[@type="articles"]/item[@type="article"]',
    '//group[@type="posts"]/item[@type="post"]',
])]
class Comment extends AbstractEntity { ... }
```

```php
$dom   = new DOMDocument();
$dom->loadXML(DOM\ORM\Storage\StorageService::fromConfig()->read());
$xpath = new DOMXPath($dom);

// Place a comment under a specific article
$articleNode = $xpath->query('//group[@type="articles"]/item[@type="article" and @id="abc123"]')->item(0);
$this->persist(new Comment('Great post!'), $articleNode);
```

#### Querying scoped entities

Use `EntityRepository` to retrieve entities — results are always typed entity objects or a `Collection`:

```php
// All articles — returns a Collection<Article>
$articles = (new EntityRepository(Article::class))->findAll();

// One article by ID — returns a single Article object
$article = (new EntityRepository(Article::class))->find('abc123');

// All comments for a specific article — returns a Collection<Comment>
$comments = (new EntityRepository(Comment::class))->findBy(['articleId' => 'abc123']);

// One comment by content — returns a single Comment object or null
$comment = (new EntityRepository(Comment::class))->findOneBy(['body' => 'Great post!']);
```

## Querying data

Querying uses XPath internally, then maps results back to their entity instance.

### Querying data with an Entity Repository

Use EntityRepository for object-oriented reads:
```php
$tagRepository = new EntityRepository(Tag::class);
$tag = $tagRepository->findOneBy(['name' => 'Tagname']); // returns a single Tag object
$tag = $tagRepository->find('fec69a494c3145f89af03ae3b3702e19'); // returns a single Tag object
$tags = $tagRepository->findAll(); // returns a Collection of all Tag objects
$tags = $tagRepository->findBy(['name' => 'Tagname']); // returns a Collection of Tag objects
```

### Querying data using DOMXPath

```php
$xml = DOM\ORM\Storage\StorageService::fromConfig()->read();
$dom = new DOMDocument();
$dom->loadXML($xml);
$xpath = new DOMXPath($dom);
$tags = $xpath->query('//item[@type="tag"]'); // retrieve all tags at any depth
$tag = $xpath->query('//item[@type="tag" and @id="fec69a494c3145f89af03ae3b3702e19"]'); // retrieve one tag by ID
```

### Querying data using DOMDocument

```php
$xml = DOM\ORM\Storage\StorageService::fromConfig()->read();
$dom = new DOMDocument();
$dom->loadXML($xml);
$entities = $dom->getElementsByTagName('item'); // returns a DOMNodeList of all entities
```

## Templating

### Twig
Query entities and pass them to Twig:
```php
$twig->render('index.twig', [
    'title' => 'Hello there!',
    'tag' => (new EntityRepository(Tag::class))->findOneBy(['name' => 'My cool tag']),
]);
```

Or decode an Entity to an array and pass that to Twig:
```php
$tagEntity = (new EntityRepository(Tag::class))->findOneBy(['name' => 'My cool tag']);
$tagArray = current((new DOM\ORM\Serializer\Normalizer\SchemaNormalizer())->normalize($tagEntity, DOM\ORM\Serializer\Normalizer\SchemaNormalizer::FORMAT));

echo $twig->render('index.twig', [
    'title' => 'Hello there!',
    'tag' => $tagArray,
]);
```

### XSLT
Not popular - at least not as a templating engine - yet powerful. Especially when it comes to tree structures. 
Transform XML to HTML with XSLT:
```php
$xml = DOM\ORM\Storage\StorageService::fromConfig()->read();
$dom = new DOMDocument();
$dom->loadXML($xml);

$xsl = new DOMDocument();
$xsl->load('path/to/stylesheet.xsl');

$processor = new XSLTProcessor();
$processor->importStylesheet($xsl);

echo $processor->transformToXML($dom);
```