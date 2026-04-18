
# Relations

Due to XML being a tree structure, you have relationships by design - simply by looking at where in the tree something was stored. Relationships can be described by its parent, child, sibling relationships.

## One-to-one

You can model a one-to-one relation in two styles. Both produce the same XML — choose whichever suits your type system.

### Typed nullable style

If you prefer stricter types, declare the property as `?Profile`. You can also use an `array` instead. The ORM detects the PHP type automatically — no extra attribute needed:

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

### Array style

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

## One-to-many

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

## Many-to-one

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

## Many-to-many

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
