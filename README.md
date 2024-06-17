# DOM ORM

This is a storage adapter for small web projects. Like any other ORM, it stores enitites (objects) into a flexible XML file. This is for developers who want to start a small project without having to spin up a database.

## Features

- A very lightweight approach to persisting data into a single XML file.
- Supports local and external file storage via Flysystem (S3,Azure,Google Cloud,(S)FTP,etc.)
- Supports Many-to-one, One-to-many and Many-to-many relationships.

## Documentation

Read the [Documentation](https://linktodocumentation)

## Basic Usage

By adding PHP8 Attributes to your entity class, DOM ORM knows how to persist it.

```php
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
class SomeService {
    use DOM\ORM\Traits\EntityManagerTrait;
    ...
    public function doStuff() {
      $tag = new Tag('Tagname');
      $this->persist($tag);
    }
}
```

### Serialization

When persisting the entity, DOM ORM automatically generates a UID and adds a creation date for the entity.
The built-in normalizer and encoder transforms the object into a standardized XML format and saves it.

```xml
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
When you query data, internally the query is transformed into an XPath query and the result is mapped back to its Entity class object(s).

```php
    $tags = $this->findAll('tag');

```php
    $tag = $this->find('e34cbf80edaf490aa39113254b6cdfa9');
```
@todo
### XPath
### XSLT
### Repository Pattern
### ~GraphQL~ (coming soon)