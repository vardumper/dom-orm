# DOM ORM

DOM ORM is a lightweight XML-based persistence layer for small PHP projects. It stores entities in a single XML document, so you can start without a database server.

## Features

- A very lightweight approach to persisting data into a single XML file.
- Supports local and remote storage via Flysystem (S3, Azure, Google Cloud, (S)FTP, etc.).
- Supports one-to-one, one-to-many, many-to-one, and many-to-many patterns.
- Supports AES-256-GCM field-level encryption via `#[Sensitive]` with searchable HMAC hashes.

## Full Documentation

Read the [Documentation](https://linktodocumentation)

## Getting started

```bash
composer require vardumper/dom-orm
```

By default, the XML file is stored on your local filesystem as `storage/data.xml` under the root of your project.
To change the location, configure a Flysystem adapter:

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
Add PHP 8 attributes to describe how the entity is stored:

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

Use `persist` for updates too:
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

DOM ORM generates an ID and `createdAt` automatically, then stores entities in a normalized XML shape.

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

## Relations (Basic Examples)

### One-to-one

```php
#[ORM\Item(entityType: 'user')]
class User extends AbstractEntity {
  public function __construct(
    #[ORM\Fragment] private string $name,
    #[ORM\Group(entity: Profile::class, groupType: 'profile')] private array $profile = [],
  ) { parent::__construct(); }
}

#[ORM\Item(entityType: 'profile')]
class Profile extends AbstractEntity {
  public function __construct(#[ORM\Fragment] private string $bio) { parent::__construct(); }
}
```

Query example:

```php
// Via EntityRepository — returns the User with nested profile data
$user = (new EntityRepository(User::class))->findOneBy(['id' => 'user-1']);
$profiles = $user->getProfile(); // one-to-one is modeled as a group with max one item
$profile = $profiles[0] ?? null;

// Alternative: raw XPath
$profileNodes = $xpath->query('//item[@type="user" and @id="user-1"]/group[@type="profile"]/item[@type="profile"]');
$firstProfileNode = $profileNodes?->item(0); // DOMNode|null
```

Storage example:

```xml
<data>
  <item type="user" id="user-1">
    <fragment name="name"><![CDATA[John]]></fragment>
    <group type="profile">
      <item type="profile" id="profile-1">
        <fragment name="bio"><![CDATA[PHP developer]]></fragment>
      </item>
    </group>
  </item>
</data>
```

### One-to-many

```php
#[ORM\Item(entityType: 'post')]
class Post extends AbstractEntity {
  public function __construct(
    #[ORM\Fragment] private string $title,
    #[ORM\Group(entity: Comment::class, groupType: 'comments')] private array $comments = [],
  ) { parent::__construct(); }
}
```

Query example:

```php
// Via EntityRepository — returns the Post with nested comments
$post = (new EntityRepository(Post::class))->findOneBy(['id' => 'post-1']);
foreach ($post->getComments() as $comment) {
  // each $comment is a Comment entity
}

// Alternative: raw XPath
$commentNodes = $xpath->query('//item[@type="post" and @id="post-1"]/group[@type="comments"]/item[@type="comment"]');
foreach ($commentNodes ?? [] as $commentNode) {
  // each $commentNode is a DOMNode
}
```

Storage example:

```xml
<data>
  <item type="post" id="post-1">
    <fragment name="title"><![CDATA[My first post]]></fragment>
    <group type="comments">
      <item type="comment" id="comment-1">
        <fragment name="text"><![CDATA[Great post]]></fragment>
      </item>
      <item type="comment" id="comment-2">
        <fragment name="text"><![CDATA[Thanks for sharing]]></fragment>
      </item>
    </group>
  </item>
</data>
```

### Many-to-one

```php
#[ORM\Item(entityType: 'employee')]
class Employee extends AbstractEntity {
  public function __construct(
    #[ORM\Fragment] private string $name,
    #[ORM\Fragment] private string $companyId,
  ) { parent::__construct(); }
}
// Many employees can reference the same companyId.
```

Query example:

```php
// Via EntityRepository — find all employees belonging to a company
$employees = (new EntityRepository(Employee::class))->findBy(['companyId' => 'company-1']);

// Alternative: raw XPath (returns DOMNodeList)
$employeeNodes = $xpath->query('//item[@type="employee"][fragment[@name="companyId"]="company-1"]');
```

Storage example:

```xml
<data>
  <item type="company" id="company-1">
    <fragment name="name"><![CDATA[ACME Inc.]]></fragment>
  </item>

  <item type="employee" id="employee-1">
    <fragment name="name"><![CDATA[Alice]]></fragment>
    <fragment name="companyId"><![CDATA[company-1]]></fragment>
  </item>

  <item type="employee" id="employee-2">
    <fragment name="name"><![CDATA[Bob]]></fragment>
    <fragment name="companyId"><![CDATA[company-1]]></fragment>
  </item>
</data>
```

### Many-to-many

```php
#[ORM\Item(entityType: 'enrollment')]
class Enrollment extends AbstractEntity {
  public function __construct(
    #[ORM\Fragment] private string $studentId,
    #[ORM\Fragment] private string $courseId,
  ) { parent::__construct(); }
}
// Use a join entity (Enrollment) to connect students and courses.
```

Query example:

```php
// Via EntityRepository — find all courses for a student (or all students in a course)
$enrollments = (new EntityRepository(Enrollment::class))->findBy(['studentId' => 'student-1']);
$enrollments = (new EntityRepository(Enrollment::class))->findBy(['courseId' => 'course-1']);

// Alternative: raw XPath (returns DOMNodeList)
$courseEnrollmentNodes = $xpath->query('//item[@type="enrollment"][fragment[@name="studentId"]="student-1"]');
$studentEnrollmentNodes = $xpath->query('//item[@type="enrollment"][fragment[@name="courseId"]="course-1"]');
```

Storage example:

```xml
<data>
  <item type="student" id="student-1">
    <fragment name="name"><![CDATA[Alice]]></fragment>
  </item>

  <item type="course" id="course-1">
    <fragment name="title"><![CDATA[Databases 101]]></fragment>
  </item>

  <item type="enrollment" id="enrollment-1">
    <fragment name="studentId"><![CDATA[student-1]]></fragment>
    <fragment name="courseId"><![CDATA[course-1]]></fragment>
  </item>
</data>
```

## Sensitive Data

Mark any `#[Fragment]` property with `#[Sensitive]` to encrypt its value at rest using AES-256-GCM.
Requires `encryption_key` in the config file.

### Entity

```php
use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'user')]
class User extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $username,
        #[ORM\Fragment]
        #[ORM\Sensitive]                 // encrypted at rest
        private string $email,
        #[ORM\Fragment]
        #[ORM\Sensitive]                 // encrypted at rest
        private string $password,
    ) {
        parent::__construct();
    }
}
```

### Storage

Sensitive fragments are stored as AES-256-GCM ciphertext. A deterministic HMAC-SHA256
`searchable-hash` attribute is stored alongside the ciphertext so the field can be searched
without decrypting the entire file:

```xml
<!-- storage/data.xml -->
<data>
  <item type="user" id="e34cbf80edaf490aa39113254b6cdfa9">
    <fragment name="username"><![CDATA[alice]]></fragment>
    <fragment name="email" searchable-hash="a3f9c2..."><![CDATA[base64EncodedCiphertext==]]></fragment>
    <fragment name="password" searchable-hash="7e1d04..."><![CDATA[base64EncodedCiphertext==]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-06-17T06:30:37+00:00]]></fragment>
  </item>
</data>
```

### Querying encrypted fields

`findBy` and `findOneBy` accept plaintext values — the ORM computes the HMAC and matches
against `searchable-hash` transparently:

```php
$user  = (new EntityRepository(User::class))->findOneBy(['email' => 'alice@example.com']);
$users = (new EntityRepository(User::class))->findBy(['email' => 'alice@example.com']);
```

Hydrated entities expose the **decrypted** plaintext value:

```php
$user = (new EntityRepository(User::class))->find('e34cbf80edaf490aa39113254b6cdfa9');
echo $user->getEmail(); // alice@example.com
```

> **Note:** If `encryption_key` is absent from the config, `#[Sensitive]` is silently ignored
> and fields are stored as plain fragments — no error is thrown.

## Querying data

Querying uses XPath internally, then maps results back to entities.

### Querying data with an Entity Repository

Use EntityRepository for object-oriented reads:
```php
$tagRepository = new EntityRepository(Tag::class);
$tag = $tagRepository->findOneBy(['name' => 'Tagname']); // returns a single Tag object
$tag = $tagRepository->find('fec69a494c3145f89af03ae3b3702e19'); // returns a single Tag object
$tags = $tagRepository->findAll(); // returns a Collection of Tag objects
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
    'tag' => (new EntityRepository(Tag::class))->find('fec69a494c3145f89af03ae3b3702e19'),
]);
```

Or decode a DOM element to an array and pass that to Twig:
```php
$xml = DOM\ORM\Storage\StorageService::fromConfig()->read();
$dom = new DOMDocument();
$dom->loadXML($xml);

$item = (new DOM\ORM\Serializer\Encoder\SchemaDecoder())
  ->decode($dom->getElementsByTagName('item')->item(0), DOM\ORM\Serializer\Encoder\SchemaEncoder::FORMAT);

echo $twig->render('index.twig', [
    'title' => 'Hello there!',
    'item' => $item,
]);
```

### XSLT
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

## Testing And Coverage

Run the full Pest test suite:

```bash
./vendor/bin/pest
```

Run Pest with a human-friendly output format:

```bash
./vendor/bin/pest --testdox
```

Generate an HTML coverage report (output directory: `build/coverage-html`):

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-html build/coverage-html
```

Update `clover.xml` (used by CI and quality tooling):

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-clover clover.xml
```

Generate both HTML coverage and a fresh `clover.xml` in one run:

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage-html build/coverage-html --coverage-clover clover.xml
```

### Roadmap

- [ ] Add first-class many-to-many support using hash maps.
- [ ] Add ordering/sorting to the EntityRepository pattern.
- [ ] Add a GraphQL endpoint for flexible headless access.
- [ ] Add migration/cleanup support to remove dropped fragments from XML.
- [ ] Add support to encrypt parts of the XML document or the full file.