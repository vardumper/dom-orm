# Encryption of sensitive Data

Mark any `#[Fragment]` property with `#[Sensitive]` to encrypt its value at rest using AES-256-GCM.
Requires `encryption_key` in the config file.

## Entity

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

## Storage

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

## Querying encrypted fields

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
