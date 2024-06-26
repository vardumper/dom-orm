# DOM ORM

This is a storage adapter for small web projects. Like any other ORM, it stores enitites (objects) into a flexible XML file. This is for developers who want to start a small project without having to spin up a database.

## Features

- A very lightweight approach to persisting data into a single XML file.
- Supports local and external file storage via Flysystem (S3,Azure,Google Cloud,(S)FTP,etc.)
- Supports Many-to-one, One-to-many and Many-to-many relationships.

## Full Documentation

Read the [Documentation](https://linktodocumentation)

## Getting started

```bash
composer require vardumper/dom-orm
```

By default, the XML file is stored on your local filesystem as `storage/data.xml` under the root of your project.
You can change the storage location by changing the Flysystem adapter and configuring dom-orm to use it, like so:

```php
// config/dom-orm.php
<?php return [
  'dom-orm' => [
    'flysystem' => new LocalAdapter(__DIR__ . '/storage'),
    'filename' => 'data.xml',
  ],
];
```

## Basic Usage

### Entity
By adding PHP8 Attributes to your entity class, DOM ORM knows how to persist it.

```php
// src/Entity/Tag.php
use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'tag')]
class Tag extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private readonly string $name,
    ) {
        parent::__construct();
    }
}
```

### Persistance

An EntityManagerTrait can be used in controllers or services to persist entities to the XML file.
```php
// src/Service/SomeService.php
class SomeService {
    use DOM\ORM\Traits\EntityManagerTrait;
    ...
    public function addTag(string $name) {
      $tag = new Tag($name);
      $this->persist($tag);
    }
}
```

When you want to update an existing Entity, you can use the `persist` method as well.
```php
// src/Service/SomeService.php
class SomeService {
    use DOM\ORM\Traits\EntityManagerTrait;
    ...
    public function updateTag(string $id, string $name) {
      $tag = (new EntityRepository(Tag::class))->find($id);
      $tag->setName($name);
      $this->persist($tag);
    }
}
```

When you want to remove an existing Entity, you can use the `remove` method.
```php
// src/Service/SomeService.php
class SomeService {
    use DOM\ORM\Traits\EntityManagerTrait;
    ...
    public function removeTag(string $id) {
      (new EntityRepository(Tag::class))->remove($id);
    }
}
```

### Serialization

When persisting the entity, DOM ORM automatically generates a UID and adds a creation date for the entity.
The built-in normalizer and encoder transforms the object into a standardized XML format and saves it.

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

## Querying data

Just like persisting a PHP Object to a XML format, querying data is just as easy.
When you query data, internally XPath is used to find the elements, the resulting DOMNodeList is then mapped back to its Entity class object(s).

### Querying data with an Entity Repository

By using the EntityRepository class, you can query data in an object-oriented way, always retrieving instances of Entity object(s).
```php
$tagRepository = new EntityRepository(Tag::class);
$tag = $tagRepository->findOneBy(['name' => 'Tagname']); // returns a single Tag object
$tag = $tagRepository->find('fec69a494c3145f89af03ae3b3702e19'); // return a single Tag object
$tags = $tagRepository->findAll(); // returns a Collection of Tag objects
$tags = $tagRepository->findBy(['name' => 'Tagname']); // returns a Collection of Tag objects
```

### Querying data using DOMXPath

```php
$xml = (new DOM\ORM\Storage\StorageService())->read();
$dom = (new DOMDocument())->loadXML($xml);
$xpath = new DOMXPath($dom);
$tags = $xpath->query('//item[@type="tag"]'); // eg: retrieve all tags at any depth
$tag = $xpath->query('//item[@type="tag" and @id="fec69a494c3145f89af03ae3b3702e19"]'); // eg: retrieve a single tag with a specific ID
```

### Querying data using DOMDocument

```php
$xml = (new DOM\ORM\Storage\StorageService())->read();
$dom = (new DOMDocument())->loadXML($xml);
$entities = $dom->getElementsByTagName('item'); // returns a DOMNodeList of all entities
```

## Templating

### Twig
Probably the easiest way is to query for entities and pass them to your Twig templates:
```php
$twig->render('index.twig', [
    'title' => 'Hello there!',
    'tag' => (new EntityRepository(Tag::class))->find('fec69a494c3145f89af03ae3b3702e19'),
]);
```

Or you could just decoded some DOMElements and pass an array to Twig templates (without instantiating the object):
```php
use EntityManagerTrait;
$serializer = $this->getSerializer();
$item = $serializer->decode($dom->getELementsByTagName('item')->item(0)); // example: decode the first item into an array

echo $twig->render('index.twig', [
    'title' => 'Hello there!',
    'item' => $item,
]);
```

### XSLT
You can use the XML data to transform it into HTML using XSLT.
```php
$xml = (new DOM\ORM\Storage\StorageService())->read();
$dom = (new DOMDocument())->loadXML($xml);
$xslt = (new XSLTProcessor())->importStylesheet(DOMDocument::load('path/to/stylesheet.xsl'));

echo $xslt->transformToXML($dom);
```

### Roadmap

- [ ] Add support for Many-to-many relationships using hash maps.
- [ ] Add ordering/sorting to the EntityRepository pattern.
- [ ] By providing a GraphQL endpoint, you can interact with your DOM-ORM database in a more flexible, headless way.
- [ ] Adding support for migrations (or rather a cleanup) so that removed fragments are removed from the XML file as well.
- [ ] Add support to encrypt parts or the entire XML file for better security.