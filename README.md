<h1 align="center">DOM ORM</h1>

<p align="center" dir="auto">
    <a href="https://packagist.org/packages/vardumper/dom-orm" rel="nofollow">
        <img src="https://poser.pugx.org/vardumper/dom-orm/v/stable" alt="Latest Stable Version" />
    </a>
    <a href="https://packagist.org/packages/vardumper/dom-orm" rel="nofollow">
        <img src="https://img.shields.io/packagist/dt/vardumper/dom-orm" alt="Total Downloads" />
    </a>
    <img src="https://img.shields.io/badge/license-mit-red" alt="License" />
    <img src="https://img.shields.io/badge/unit%20tests-passing-green?style=flat&amp;color=%234c1" style="max-width: 100%;">
    <img src="https://raw.githubusercontent.com/vardumper/dom-orm/refs/heads/main/coverage.svg">
    <a href="https://dtrack.erikpoehler.us/projects/4e028df9-0be3-4c3d-b383-7b1468262c27"><img src="https://dtrack.erikpoehler.us/api/v1/badge/vulns/project/4e028df9-0be3-4c3d-b383-7b1468262c27?apiKey=odt_nG83W_EAcQZkk6b5KqknIVoK8nfNjSz38Ompnn" ></a>
</p>

DOM ORM is a lightweight, zero-setup, XML-based persistence layer for small to medium size datasets in PHP projects. It stores entities in a single XML document, allowing you to use relational data without the need to install a database server or driver.

## TLDR;
Entity:
```php
// src/Entity/Tag.php
use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'tag')]
class Tag extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $name
    ) {
        parent::__construct();
    }
}
```
Persistence:
```php
use DOM\ORM\Traits\EntityManagerTrait;
$this->persist(new Tag('Tagname'));
```
Storage:
```xml
<item type="tag" id="e34cbf80edaf490aa39113254b6cdfa9">
  <fragment name="name"><![CDATA[Tagname]]></fragment>
  <fragment name="createdAt"><![CDATA[2024-06-17T06:30:37+00:00]]></fragment>
</item>
```
Query (and hydrate):
```php
$tag = (new EntityRepository(Tag::class))->findOneBy(['name' => 'Tagname']);
echo $tag->getName();
```

## Features

- A PHP-native object-relational-mapper - without the need of a database.
- It operates mainly in-memory to reduce file reads/writes as much as possible.
- For small datasets (<100K), it beats all Doctrine ORM drivers (MariaDB, MySQL, Postgres, SQLite, etc.) in find by ID lookups.
- Very Lightweight, zero-setup persistence — entities live in a single XML file, the single source of truth.
- In-Memory (process runtime), Local file and Remote Storage via Flysystem (S3, Azure, Google Cloud, (S)FTP).
- Relationships: one-to-one, one-to-many, many-to-one, and many-to-many.
- Lazy Resultsets, fast reads, Schema evolution
- Git / Mercurial versioning out of the box.
- Field-level AES-256-GCM encryption via `#[Sensitive]`, with searchable HMAC hashes.
- Export to JSON, YAML, and XML.
- Works with PHP 8.3, 8.4, 8.5 and 8.6

## Installation

```bash
composer require vardumper/dom-orm
```

## Documenation

Extensive Documentation has been made [available here](https://vardumper.github.io/dom-orm/).

## Demos
 - [Virtual Filesystem](https://dom-orm.erikpoehler.com/virtual-filesystem/)
 - [Blog](https://dom-orm.erikpoehler.com/blog/)
