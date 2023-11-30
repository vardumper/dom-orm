# DOM ORM

This is a storage adapter for small web projects. Like any other ORM, it stores enitites (objects) into a flexible XML file. This is for developers who want to start a small project without having to spin up a database.

## Features

- A very lightweight approach to persisting data into a single XML file.
- Drop-in replacement for Doctrine ORM.
- No database required.
- No configuration required.
- No dependencies.
- No SQL.
- No migrations.
- Supports data fixtures.
- Supports local and external file storage via Flysystem (S3,Azure,Google Cloud,(S)FTP,Redis,etc.)
- Supports Many-to-one, One-to-many and Many-to-many relationships.
- Headless API supporting XML, YAML & JSON
- Supports versioning (using Mercurial or Git) to track and backup a full history.
- Caching baked-in (Local or Redis)

## Documentation

Read the [Documentation](https://linktodocumentation)

## Basic Example

Take a basic entity like this:

```
class Car {
  public string $make;
  public string $model;
  public Color[] $colors;

  public function __construct(string $make, string $model)
  {
    $this->make = $make;
    $this->model = $model;
  }
}
```

Just like Doctrine ORM, by adding PHP8 Attributes, this library knows how to persist the data into an XML file.

```diff
use DOM\ORM\Mapping\Fragment;
use DOM\ORM\Mapping\Group;
use DOM\ORM\Mapping\Item;

+ #[Item('car')]
! class Car extends AbstractEntity implements JsonSerializable {
+   #[Fragment]
  public string $make;
+   #[Fragment]
  public string $model;
+   #[Group(entity: Color::class)]
  public Color[] $colors;
  public function __construct(string $make, string $model)
  {
+    parent::__construct();
    $this->make = $make;
    $this->model = $model;
  }
}

```

Now you can create a new Car and persist it to the XML file like so:

```

$car = new Car('Opel', 'Tigra', [new Color('red'), new Color('blue')]);
$repository->persist($car);

```

The resulting XML looks like this:

```

<item type="car" id="0b58fe63d6da4d7e8c6a12e39adf0bb4">
    <fragment type="make">Opel</fragment>
    <fragment type="model">Tigra</fragment>
    <fragment type="createdAt">2023-11-01 22:00:00</fragment>
</item>
```
