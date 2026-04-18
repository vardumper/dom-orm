# DOM ORM

DOM ORM is a lightweight XML-based persistence layer for small PHP projects. It stores entities in a single XML document, so you can start without a database server.

## Features

- A very lightweight approach to persisting data into a single XML file.
- Supports local and remote storage via Flysystem (S3, Azure, Google Cloud, (S)FTP, etc.).
- Supports one-to-one, one-to-many, many-to-one, and many-to-many patterns.
- Supports AES-256-GCM field-level encryption via `#[Sensitive]` with searchable HMAC hashes.
- Supports schema evolution (rename/remove fragments) via `#[FragmentMap]` and CLI commands.


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

You can model a one-to-one relation in two styles. Both produce the same XML — choose whichever suits your type system.

#### Array style (classic)

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
$profiles = $user->getProfile(); // array — one-to-one is modeled as a group with max one item
$profile = $profiles[0] ?? null;

// Alternative: raw XPath
$profileNodes = $xpath->query('//item[@type="user" and @id="user-1"]/group[@type="profile"]/item[@type="profile"]');
$firstProfileNode = $profileNodes?->item(0); // DOMNode|null
```

#### Typed nullable style

If you prefer stricter types, declare the property as `?Profile` instead of `array`. The ORM detects the PHP type automatically — no extra attribute needed:

```php
#[ORM\Item(entityType: 'user')]
class User extends AbstractEntity {
  public function __construct(
    #[ORM\Fragment] private string $name,
    #[ORM\Group(entity: Profile::class, groupType: 'profile')] private ?Profile $profile = null,
  ) { parent::__construct(); }
}
```

Query example:

```php
// Via EntityRepository — $profile is a Profile instance or null, never an array
$user = (new EntityRepository(User::class))->findOneBy(['id' => 'user-1']);
$profile = $user->getProfile(); // ?Profile

// Alternative: raw XPath (XML shape is identical to the array style)
$profileNodes = $xpath->query('//item[@type="user" and @id="user-1"]/group[@type="profile"]/item[@type="profile"]');
$firstProfileNode = $profileNodes?->item(0); // DOMNode|null
```

Storage example (identical XML for both styles):

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

When `$profile` is `null`, no `<group>` element is written:

```xml
<data>
  <item type="user" id="user-2">
    <fragment name="name"><![CDATA[Jane]]></fragment>
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

## Export and Query Cache

DOM ORM can export your data to JSON, YAML, or PHP array format, and optionally maintain a
**read-only PHP query cache** derived from the XML for faster reads without any database.

## Concurrency

DOM ORM uses a read-modify-write workflow: it loads the XML file into memory, changes the DOM,
and writes the whole document back. Without coordination, concurrent writers can overwrite each
other.

### Local storage

When you use the default local Flysystem adapter, DOM ORM automatically uses a blocking
`flock()` lock file next to the XML store:

```text
storage/data.xml
storage/data.xml.lock
```

The lock is held across the full read-modify-write cycle, so concurrent PHP processes queue
behind the active writer instead of corrupting the XML or silently dropping updates.

Every write operation reloads the latest XML while holding that lock, applies the mutation,
then writes the updated document back. That prevents a long-lived in-memory DOM from
overwriting changes another PHP process committed between two writes.

You can override the lock path explicitly:

```php
<?php return [
  'dom-orm' => [
    'flysystem' => [
      'adapter' => League\Flysystem\Local\LocalFilesystemAdapter::class,
      'config' => [__DIR__ . '/storage'],
    ],
    'filename' => 'data.xml',
    'lock_file' => __DIR__ . '/storage/data.xml.lock',
  ],
];
```

### Remote storage

For remote adapters such as S3, Azure Blob Storage, Google Cloud Storage, or SFTP, DOM ORM does
**not** provide a distributed lock. PHP `flock()` only works on local filesystems, so DOM ORM
cannot make cross-process or cross-host write safety guarantees for remote storage by itself.

That means remote storage remains vulnerable to last-write-wins races unless you add external
coordination.

Use one of these patterns when running against remote storage:

- Route all writes through a single worker or queue.
- Guard writes with an external distributed lock such as Redis, PostgreSQL advisory locks, or a cloud-native lease mechanism.
- Treat remote adapters as read-mostly snapshots and perform writes on a single local authority.

### Practical guidance

- Local storage with the built-in lock is suitable for multi-process PHP workloads on a single host.
- Remote storage is still fine for portability and backups, but not as a high-concurrency primary write store unless you add your own locking layer.
- The PHP query cache improves read performance only. It does not provide write coordination or transactional guarantees.

### Export formats

Run one or more `--json`, `--yaml`, `--xml`, or `--php` flags to produce output files alongside
your XML store:

```bash
# Export as JSON and YAML (auto-named next to storage/data.xml)
./vendor/bin/dom-orm export --json --yaml

# Export to specific paths
./vendor/bin/dom-orm export --json /tmp/snapshot.json --yaml /tmp/snapshot.yaml
```

The exported shape is a flat, human-readable structure grouped by entity type:

```json
{
  "user": [
    { "id": "uuid1", "type": "user", "name": "Alice", "createdAt": "2024-01-01T00:00:00+00:00" }
  ]
}
```

### Read-only query cache

The PHP array cache generates a PHP file that PHP's opcache can pre-compile, giving O(1) ID
lookups and fast in-memory scans without XPath overhead.

#### 1. Configure the cache path

Add `cache_path` to your `dom-orm.php`:

```php
<?php return [
    'dom-orm' => [
        // … existing config …
        'cache_path'     => __DIR__ . '/storage/cache.php',
        'cache_strategy' => 'manual',   // 'manual' (default) or 'on_persist'
      'export_on_persist' => [
        'file' => __DIR__ . '/storage/export', // optional base path
        'json' => true,
        'yaml' => false,
        'xml' => false,
        'php' => false,
      ],
    ],
];
```

| Option | Values | Description |
|--------|--------|-------------|
| `cache_path` | file path | Where the PHP cache file is written. `null` disables the cache entirely. |
| `cache_strategy` | `manual` | Cache is only rebuilt when you run `build-cache`. Recommended for write-heavy workloads. |
| | `on_persist` | Cache is rebuilt automatically after every `persist()` / `remove()`. Convenient for small datasets. |
| `export_on_persist.file` | file path or `null` | Optional base path for save-time exports. Defaults to `storage/export` when omitted. |
| `export_on_persist.xml` | `true`, `false`, or file path | Write an XML snapshot whenever `cache_strategy` is `on_persist`. |
| `export_on_persist.yaml` | `true`, `false`, or file path | Write a YAML snapshot whenever `cache_strategy` is `on_persist`. |
| `export_on_persist.json` | `true`, `false`, or file path | Write a JSON snapshot whenever `cache_strategy` is `on_persist`. |
| `export_on_persist.php` | `true`, `false`, or file path | Write a PHP snapshot whenever `cache_strategy` is `on_persist`. |

#### 2. Build the cache

```bash
./vendor/bin/dom-orm build-cache
# → Cache written to /path/to/storage/cache.php.
```

Re-run any time after modifying the XML directly (persisting data, import, migrate, cleanup, etc.).

When `cache_strategy` is `on_persist`, DOM ORM can also emit one or several export formats in
the same save cycle. That keeps the XML source, the PHP query cache, and any derived read-only
snapshots in sync automatically.

#### 3. Reads are served from cache automatically

Once the cache file exists, `EntityRepository::find()`, `findAll()`, `findBy()`, and
`findOneBy()` use the cache instead of XPath — no code changes needed:

```php
$repo = new EntityRepository(User::class);
$user = $repo->find('uuid1');        // reads from cache.php
$users = $repo->findBy(['name' => 'Alice']);  // in-memory filter over cache
```

Queries involving encrypted sensitive fields fall back to XPath automatically (the cache
stores ciphertext, which cannot be matched without knowing the plaintext).

#### 4. Flush the cache

```bash
./vendor/bin/dom-orm flush-cache
```

XML remains the source of truth at all times. The cache is a derivative artifact that can be
rebuilt or deleted at any point.

> **Tip:** Add `build-cache` to your deployment script after running `migrate` and `cleanup`
> to keep reads fast after a schema change:
> ```bash
> ./vendor/bin/dom-orm migrate && ./vendor/bin/dom-orm cleanup && ./vendor/bin/dom-orm build-cache
> ```

## Schema Evolution

When you rename or remove a `#[Fragment]` property from an entity, the old XML fragment stays
in the file. DOM ORM handles this gracefully:

- **Unknown fragments are non-fatal** — hydration silently skips any fragment whose setter no
  longer exists, so your app keeps working after a property is removed.
- **Orphaned data is cleaned up explicitly** via CLI commands — there is no silent data loss.

### Declaring renames and removals

Add `#[ORM\FragmentMap]` at the class level to describe what changed. The attribute is
repeatable, so you can accumulate historical changes across multiple releases:

```php
use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'user')]
#[ORM\FragmentMap([
    'fullName'    => 'name',   // renamed: old XML key → new property name
    'legacyEmail' => null,     // removed: drop this fragment during cleanup
])]
class User extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment] private string $name,
        #[ORM\Fragment] private string $email,
    ) {
        parent::__construct();
    }
}
```

Map semantics:

| Value | Meaning |
|-------|---------|
| `'oldKey' => 'newKey'` | Renamed — old XML data is read into the new property until `migrate` is run |
| `'oldKey' => null`     | Removed — fragment is ignored during hydration; pruned by `cleanup` |

**Conflict rule:** if an XML item already has both `oldKey` and `newKey`, the new key wins and
the old fragment is treated as orphaned.

### How hydration works with a FragmentMap

The ORM applies the fragment map at read time **before** constructor/setter hydration, so
entities load correctly from old XML without any manual migration step:

1. Fragment `fullName` exists in XML → it is presented to the constructor as `name`.
2. Fragment `legacyEmail` exists in XML → it is dropped before hydration (setter is never called).
3. New entities written after the class change will only contain `name`/`email` fragments.

Old XML items keep their original shape until you run the CLI migration.

### CLI workflow

Always run `--dry-run` first to preview changes before writing.

#### 1. Preview and apply renames/removals

```bash
# Preview what migrate would change
./vendor/bin/dom-orm migrate --dry-run

# Apply all FragmentMap renames and removals to the XML
./vendor/bin/dom-orm migrate
```

Example output:

```
Applied: 42 fragment(s) renamed, 7 fragment(s) removed across 31 item(s).
Done.
```

#### 2. Remove truly orphaned fragments

After `migrate`, run `cleanup` to prune any remaining fragments that are not declared in any
current entity class (including ones with no `#[FragmentMap]` history):

```bash
# Preview what cleanup would delete
./vendor/bin/dom-orm cleanup --dry-run

# Apply cleanup
./vendor/bin/dom-orm cleanup
```

Example output:

```
Removed: 12 orphaned fragment(s) removed across 8 item(s).
Done.
```

> **Tip:** Add `./vendor/bin/dom-orm migrate && ./vendor/bin/dom-orm cleanup` to your
> deployment script to keep the XML clean after every schema change.

## Schema Evolution

When you rename or remove a `#[Fragment]` property from an entity, the old XML fragment stays
in the file. DOM ORM handles this gracefully:

- **Unknown fragments are non-fatal** — hydration silently skips any fragment whose setter no
  longer exists, so your app keeps working after a property is removed.
- **Orphaned data is cleaned up explicitly** via CLI commands — there is no silent data loss.

### Declaring renames and removals

Add `#[ORM\FragmentMap]` at the class level to describe what changed. The attribute is
repeatable, so you can accumulate historical changes across multiple releases:

```php
use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'user')]
#[ORM\FragmentMap([
    'fullName'    => 'name',   // renamed: old XML key → new property name
    'legacyEmail' => null,     // removed: drop this fragment during cleanup
])]
class User extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment] private string $name,
        #[ORM\Fragment] private string $email,
    ) {
        parent::__construct();
    }
}
```

Map semantics:

| Value | Meaning |
|-------|---------|
| `'oldKey' => 'newKey'` | Renamed — old XML data is read into the new property until `migrate` is run |
| `'oldKey' => null`     | Removed — fragment is ignored during hydration; pruned by `cleanup` |

**Conflict rule:** if an XML item already has both `oldKey` and `newKey`, the new key wins and
the old fragment is treated as orphaned.

### How hydration works with a FragmentMap

The ORM applies the fragment map at read time **before** constructor/setter hydration, so
entities load correctly from old XML without any manual migration step:

1. Fragment `fullName` exists in XML → it is presented to the constructor as `name`.
2. Fragment `legacyEmail` exists in XML → it is dropped before hydration (setter is never called).
3. New entities written after the class change will only contain `name`/`email` fragments.

Old XML items keep their original shape until you run the CLI migration.

### CLI workflow

Always run `--dry-run` first to preview changes before writing.

#### 1. Preview and apply renames/removals

```bash
# Preview what migrate would change
./vendor/bin/dom-orm migrate --dry-run

# Apply all FragmentMap renames and removals to the XML
./vendor/bin/dom-orm migrate
```

Example output:

```
Applied: 42 fragment(s) renamed, 7 fragment(s) removed across 31 item(s).
Done.
```

#### 2. Remove truly orphaned fragments

After `migrate`, run `cleanup` to prune any remaining fragments that are not declared in any
current entity class (including ones with no `#[FragmentMap]` history):

```bash
# Preview what cleanup would delete
./vendor/bin/dom-orm cleanup --dry-run

# Apply cleanup
./vendor/bin/dom-orm cleanup
```

Example output:

```
Removed: 12 orphaned fragment(s) removed across 8 item(s).
Done.
```

> **Tip:** Add `./vendor/bin/dom-orm migrate && ./vendor/bin/dom-orm cleanup` to your
> deployment script to keep the XML clean after every schema change.

## VCS Versioning

DOM ORM can automatically commit your XML data file to a Git or Mercurial repository after
every write. This gives you a full audit trail and an off-site backup without any extra
infrastructure.

### Prerequisites

- **Git:** `git` must be on the system `PATH`. Run `git --version` to verify.
- **Mercurial:** `hg` must be on the system `PATH`. Run `hg --version` to verify.
- The storage directory (configured under `flysystem.config.location`) must already be inside
  an initialised repository (`git init` / `hg init`). DOM ORM does **not** create the repo for you.
- For push to work, a remote must be configured and any required credentials (SSH key or
  credential helper) must be available to the PHP process.

### Configuration

Add the following keys to your `config/dom-orm.php`:

```php
<?php return [
    'dom-orm' => [
        // … existing config …
        'versioning'           => true,          // enable VCS commits on every write
        'version_control'      => 'git',          // 'git' (default) or 'hg'
        'version_control_push' => 'manual',       // 'manual' (default) or 'on_persist'
    ],
];
```

| Option | Values | Description |
|--------|--------|-------------|
| `versioning` | `true` / `false` | Master switch. Defaults to `false` — no VCS activity until explicitly enabled. |
| `version_control` | `git` / `hg` | Which VCS binary to use. Defaults to `'git'`. |
| `version_control_push` | `manual` | Commit locally on every write, but **never push** automatically. Use a cron job or CI to push on your own schedule. |
| | `on_persist` | Commit **and** push on every write. Convenient for low-traffic projects where blocking on a network call is acceptable. |

> **Tip:** Start with `version_control_push: 'manual'` and add a cron-driven push once you
> have verified that commits are being created correctly.

### How it works

1. After DOM ORM writes the XML file, `VcsService::commit()` is called automatically.
2. It stages all changes in the storage directory (`add -A` / `addremove`).
3. It creates a commit whose message names the calling class and method, e.g.:
   `DOM-ORM: App\Repository\UserRepository::save`
4. If `version_control_push` is `on_persist`, it pushes to the configured remote.

Failures are **non-fatal** — a PHP `E_USER_WARNING` is emitted and the application continues
normally. The XML write that already succeeded is never rolled back.

### Automatic push via cron job

When `version_control_push` is set to `'manual'`, you control when pushes happen. A cron job
is the simplest way to push on a schedule.

#### Every N minutes

Open the crontab editor:

```bash
crontab -e
```

Push every 15 minutes (replace paths to match your setup):

```cron
*/15 * * * * cd /var/www/my-project/storage && git push >> /var/log/dom-orm-push.log 2>&1
```

Push every 30 minutes:

```cron
*/30 * * * * cd /var/www/my-project/storage && git push >> /var/log/dom-orm-push.log 2>&1
```

#### Every N hours

Push every 2 hours:

```cron
0 */2 * * * cd /var/www/my-project/storage && git push >> /var/log/dom-orm-push.log 2>&1
```

Push once a day at 03:00:

```cron
0 3 * * * cd /var/www/my-project/storage && git push >> /var/log/dom-orm-push.log 2>&1
```

#### Mercurial

Replace `git push` with `hg push`:

```cron
*/15 * * * * cd /var/www/my-project/storage && hg push >> /var/log/dom-orm-push.log 2>&1
```

#### Quick cron reference

| Field | Meaning | Example |
|-------|---------|---------|
| `*/15 * * * *` | Every 15 minutes | — |
| `*/30 * * * *` | Every 30 minutes | — |
| `0 */2 * * *` | Every 2 hours | on the hour |
| `0 */6 * * *` | Every 6 hours | on the hour |
| `0 3 * * *` | Once a day | 03:00 |

> **Security note:** Avoid embedding credentials in cron commands or remote URLs. Use SSH keys
> or a Git credential helper so secrets are never stored in plain text in the crontab.

### Roadmap

- [ ] Add first-class many-to-many support using hash maps.
- [ ] Add ordering/sorting to the EntityRepository pattern.
