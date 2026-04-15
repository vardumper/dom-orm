<?php

declare(strict_types=1);

use DOM\ORM\Encryption\EncryptionService;
use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Traits\EntityManagerTrait;
use Tests\Fixtures\SensitiveUser;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function sensitiveStorageFile(): string
{
    return getcwd() . '/storage/sensitive_test.xml';
}

function sensitiveConfigFile(): string
{
    return getcwd() . '/dom-orm.php';
}

function sensitiveEncryptionKey(): string
{
    return 'test-sensitive-key-32-bytes-long!';
}

// ---------------------------------------------------------------------------
// Service class (uses EntityManagerTrait)
// ---------------------------------------------------------------------------

final class SensitiveUserService
{
    use EntityManagerTrait;

    public function add(SensitiveUser $user): void
    {
        $this->persist($user);
    }
}

// ---------------------------------------------------------------------------
// Setup / teardown
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    // Write a config file with encryption_key pointing to our test XML
    file_put_contents(sensitiveConfigFile(), '<?php return ' . var_export([
        'dom-orm' => [
            'flysystem' => [
                'adapter' => \League\Flysystem\Local\LocalFilesystemAdapter::class,
                'config' => [
                    'location' => dirname(sensitiveStorageFile()),
                ],
            ],
            'filename' => basename(sensitiveStorageFile()),
            'encryption_key' => sensitiveEncryptionKey(),
        ],
    ], true) . ';');

    if (!is_dir(dirname(sensitiveStorageFile()))) {
        mkdir(dirname(sensitiveStorageFile()), 0755, true);
    }

    file_put_contents(sensitiveStorageFile(), '<data />');
});

afterEach(function (): void {
    // Remove the test config and data file
    if (file_exists(sensitiveConfigFile())) {
        unlink(sensitiveConfigFile());
    }
    if (file_exists(sensitiveStorageFile())) {
        unlink(sensitiveStorageFile());
    }

    // Reset shared EntityManagerTrait singletons for all using classes so
    // the next test starts with a fresh storage and serializer.
    $resetStatics = static function (string $class): void {
        $reflection = new ReflectionClass($class);
        foreach (['sharedStorage', 'sharedSerializer'] as $prop) {
            while ($reflection !== false) {
                if ($reflection->hasProperty($prop)) {
                    $p = $reflection->getProperty($prop);
                    $p->setAccessible(true);
                    $p->setValue(null, null);
                    break;
                }
                $reflection = $reflection->getParentClass();
            }
            // Re-obtain reflection after the loop mutated the variable
            $reflection = new ReflectionClass($class);
        }
    };

    $resetStatics(SensitiveUserService::class);
    $resetStatics(\DOM\ORM\Repository\EntityRepository::class);
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

it('stores sensitive fields as ciphertext, not plaintext', function (): void {
    $user = new SensitiveUser('alice', 'alice@example.com', 's3cr3t', 'uid-1');
    (new SensitiveUserService())->add($user);

    $xml = file_get_contents(sensitiveStorageFile());

    // Username is plain
    expect($xml)->toContain('alice');

    // Email and password must NOT appear as plaintext
    expect($xml)->not->toContain('alice@example.com');
    expect($xml)->not->toContain('s3cr3t');
});

it('adds a searchable-hash attribute to sensitive fragments', function (): void {
    $user = new SensitiveUser('alice', 'alice@example.com', 's3cr3t', 'uid-2');
    (new SensitiveUserService())->add($user);

    $xml = file_get_contents(sensitiveStorageFile());

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $xpath = new DOMXPath($dom);

    // email fragment must have searchable-hash
    $emailFragments = $xpath->query('//fragment[@name="email" and @searchable-hash]');
    expect($emailFragments)->not->toBeNull();
    expect($emailFragments->length)->toBe(1);

    // password fragment must have searchable-hash
    $passwordFragments = $xpath->query('//fragment[@name="password" and @searchable-hash]');
    expect($passwordFragments->length)->toBe(1);

    // username fragment must NOT have searchable-hash
    $usernameFragments = $xpath->query('//fragment[@name="username" and not(@searchable-hash)]');
    expect($usernameFragments->length)->toBe(1);
});

it('decrypts sensitive fields on find()', function (): void {
    $user = new SensitiveUser('alice', 'alice@example.com', 's3cr3t', 'uid-3');
    (new SensitiveUserService())->add($user);

    $found = (new EntityRepository(SensitiveUser::class))->find('uid-3');

    expect($found)->not->toBeNull();
    expect($found->getEmail())->toBe('alice@example.com');
    expect($found->getPassword())->toBe('s3cr3t');
    expect($found->getUsername())->toBe('alice');
});

it('finds entities by encrypted field via findOneBy()', function (): void {
    $user = new SensitiveUser('alice', 'alice@example.com', 's3cr3t', 'uid-4');
    (new SensitiveUserService())->add($user);

    $found = (new EntityRepository(SensitiveUser::class))->findOneBy([
        'email' => 'alice@example.com',
    ]);

    expect($found)->not->toBeNull();
    expect($found->getId())->toBe('uid-4');
    expect($found->getEmail())->toBe('alice@example.com');
});

it('finds entities by encrypted field via findBy()', function (): void {
    $user1 = new SensitiveUser('alice', 'alice@example.com', 's3cr3t', 'uid-5a');
    $user2 = new SensitiveUser('alice2', 'other@example.com', 'p4ssw0rd', 'uid-5b');
    $service = new SensitiveUserService();
    $service->add($user1);
    $service->add($user2);

    $results = (new EntityRepository(SensitiveUser::class))->findBy([
        'email' => 'alice@example.com',
    ]);

    expect($results)->not->toBeNull();
    expect($results->count())->toBe(1);
    expect($results->first()->getEmail())->toBe('alice@example.com');
});

it('does not encrypt when no encryption_key is configured', function (): void {
    // Overwrite config without encryption_key
    file_put_contents(sensitiveConfigFile(), '<?php return ' . var_export([
        'dom-orm' => [
            'flysystem' => [
                'adapter' => \League\Flysystem\Local\LocalFilesystemAdapter::class,
                'config' => [
                    'location' => dirname(sensitiveStorageFile()),
                ],
            ],
            'filename' => basename(sensitiveStorageFile()),
        ],
    ], true) . ';');

    $user = new SensitiveUser('bob', 'bob@example.com', 'open', 'uid-6');
    (new SensitiveUserService())->add($user);

    $xml = file_get_contents(sensitiveStorageFile());

    // Without a key, plaintext is stored as-is
    expect($xml)->toContain('bob@example.com');
    expect($xml)->toContain('open');
});

it('verifies encryption uses a random IV (same plaintext → different ciphertext each time)', function (): void {
    $enc = new EncryptionService(sensitiveEncryptionKey());

    $ct1 = $enc->encrypt('same-value');
    $ct2 = $enc->encrypt('same-value');

    expect($ct1)->not->toBe($ct2);

    // But both decrypt to the same plaintext
    expect($enc->decrypt($ct1))->toBe('same-value');
    expect($enc->decrypt($ct2))->toBe('same-value');
});

it('produces the same searchHash for the same plaintext', function (): void {
    $enc = new EncryptionService(sensitiveEncryptionKey());

    $h1 = $enc->searchHash('alice@example.com');
    $h2 = $enc->searchHash('alice@example.com');

    expect($h1)->toBe($h2);

    // Different plaintext → different hash
    expect($enc->searchHash('other@example.com'))->not->toBe($h1);
});
