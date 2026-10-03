<?php

declare(strict_types=1);

use DOM\ORM\Encryption\EncryptionService;
use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Serializer\Normalizer\SchemaDenormalizer;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use Ramsey\Collection\Collection;
use Tests\Fixtures\{JsonScalarArrayFragmentEntity, MigratingPerson, RelComment, RelPost, RelProfile, RelUserSingle, SensitiveUser, Tag, TypedFieldEntity};

// Canonical decoded array structure produced by SchemaDecoder/SchemaEncoder::decode
function makeTagData(string $id = 'abc123', string $name = 'TestTag', string $createdAt = '2024-01-01T00:00:00+00:00'): array
{
    return [
        'data' => [
            [
                'item-' . $id => [
                    '@id' => $id,
                    '@type' => 'tag',
                    'name' => $name,
                    'createdAt' => $createdAt,
                ],
            ],
        ],
    ];
}

it('denormalize returns a Collection when data key is present', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $result = $denormalizer->denormalize(makeTagData(), Tag::class, SchemaNormalizer::FORMAT);
    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(1);
});

it('denormalize returns null when data key is absent', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $result = $denormalizer->denormalize([], Tag::class, SchemaNormalizer::FORMAT);
    expect($result)->toBeNull();
});

it('denormalize instantiates the correct entity type', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(makeTagData(), Tag::class, SchemaNormalizer::FORMAT);
    expect($collection->first())->toBeInstanceOf(Tag::class);
});

it('denormalize restores scalar fragment values on the entity', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(makeTagData('id1', 'Hello'), Tag::class, SchemaNormalizer::FORMAT);
    /** @var Tag $tag */
    $tag = $collection->first();
    expect($tag->getName())->toBe('Hello');
    expect($tag->getId())->toBe('id1');
});

it('denormalize restores createdAt as a DateTimeImmutable', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(makeTagData(), Tag::class, SchemaNormalizer::FORMAT);
    expect($collection->first()->getCreatedAt())->toBeInstanceOf(\DateTimeImmutable::class);
});

it('denormalize handles multiple entities in data array', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $data = [
        'data' => [
            makeTagData('id1', 'First')['data'][0],
            makeTagData('id2', 'Second')['data'][0],
        ],
    ];
    $collection = $denormalizer->denormalize($data, Tag::class, SchemaNormalizer::FORMAT);
    expect($collection)->toHaveCount(2);
});

it('getSupportedTypes includes AbstractEntity key', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $types = $denormalizer->getSupportedTypes(SchemaNormalizer::FORMAT);
    expect($types)->toBeArray()->toHaveKey(AbstractEntity::class);
});

it('hasCacheableSupportsMethod returns true', function (): void {
    $denormalizer = new SchemaDenormalizer();
    expect($denormalizer->hasCacheableSupportsMethod())->toBeTrue();
});

it('denormalizes a single-entity group into an entity instance', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $data = [
        'data' => [
            [
                'item-user-single-1' => [
                    '@id' => 'user-single-1',
                    '@type' => 'rel_user_single',
                    'username' => 'bob',
                    'profile' => [
                        [
                            'item-profile-bob' => [
                                '@id' => 'profile-bob',
                                '@type' => 'rel_profile',
                                'bio' => 'Bob bio',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $collection = $denormalizer->denormalize($data, RelUserSingle::class, SchemaNormalizer::FORMAT);
    expect($collection)->toBeInstanceOf(Collection::class);
    /** @var RelUserSingle $user */
    $user = $collection->first();
    expect($user)->toBeInstanceOf(RelUserSingle::class);
    expect($user->getProfile())->toBeInstanceOf(RelProfile::class);
    expect($user->getProfile()->getBio())->toBe('Bob bio');
});

it('denormalizes a missing single-entity group key as null', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $data = [
        'data' => [
            [
                'item-user-single-2' => [
                    '@id' => 'user-single-2',
                    '@type' => 'rel_user_single',
                    'username' => 'charlie',
                ],
            ],
        ],
    ];

    $collection = $denormalizer->denormalize($data, RelUserSingle::class, SchemaNormalizer::FORMAT);
    /** @var RelUserSingle $user */
    $user = $collection->first();
    expect($user)->toBeInstanceOf(RelUserSingle::class);
    expect($user->getProfile())->toBeNull();
});

it('denormalizes one-to-many group items into entity instances, not raw arrays', function (): void {
    // Regression test: SchemaDenormalizer must hydrate every item in a multi-entity
    // group into a proper entity object. Previously isSingle=false groups were left
    // as raw PHP arrays, causing get_class($item) to throw when the entity was
    // later re-normalized (e.g. during an upsert persist).
    $denormalizer = new SchemaDenormalizer();
    $data = [
        'data' => [
            [
                'item-post-1' => [
                    '@id' => 'post-1',
                    '@type' => 'rel_post',
                    'title' => 'Hello',
                    'comments' => [
                        [
                            'item-comment-1' => [
                                '@id' => 'comment-1',
                                '@type' => 'rel_comment',
                                'body' => 'First',
                            ],
                        ],
                        [
                            'item-comment-2' => [
                                '@id' => 'comment-2',
                                '@type' => 'rel_comment',
                                'body' => 'Second',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $collection = $denormalizer->denormalize($data, RelPost::class, SchemaNormalizer::FORMAT);
    /** @var RelPost $post */
    $post = $collection->first();
    expect($post)->toBeInstanceOf(RelPost::class);
    expect($post->getComments())->toHaveCount(2);
    expect($post->getComments()[0])->toBeInstanceOf(RelComment::class);
    expect($post->getComments()[0]->getBody())->toBe('First');
    expect($post->getComments()[1])->toBeInstanceOf(RelComment::class);
    expect($post->getComments()[1]->getBody())->toBe('Second');
});

it('re-normalizing a denormalized one-to-many entity does not throw', function (): void {
    // Regression test: after denormalization, group items must be entity objects so
    // that SchemaNormalizer::normalize() can call get_class() on them without error.
    $denormalizer = new SchemaDenormalizer();
    $normalizer = new SchemaNormalizer();
    $data = [
        'data' => [
            [
                'item-post-2' => [
                    '@id' => 'post-2',
                    '@type' => 'rel_post',
                    'title' => 'Round-trip',
                    'comments' => [
                        [
                            'item-comment-3' => [
                                '@id' => 'comment-3',
                                '@type' => 'rel_comment',
                                'body' => 'Only comment',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $collection = $denormalizer->denormalize($data, RelPost::class, SchemaNormalizer::FORMAT);
    /** @var RelPost $post */
    $post = $collection->first();

    // Must not throw "get_class(): Argument #1 must be of type object, array given"
    expect(fn () => $normalizer->normalize($post, SchemaNormalizer::FORMAT))->not->toThrow(\TypeError::class);
});

it('supportsDenormalization rejects raw XML strings', function (): void {
    $denormalizer = new SchemaDenormalizer();
    expect(fn () => $denormalizer->supportsDenormalization('<data />', Tag::class, SchemaNormalizer::FORMAT))
        ->toThrow(\InvalidArgumentException::class);
});

it('supportsDenormalization accepts JSON strings', function (): void {
    expect((new SchemaDenormalizer())->supportsDenormalization('{"a": 1}', Tag::class, SchemaNormalizer::FORMAT))->toBeTrue();
});

it('supportsDenormalization accepts YAML strings', function (): void {
    expect((new SchemaDenormalizer())->supportsDenormalization("a: 1\nb: two", Tag::class, SchemaNormalizer::FORMAT))->toBeTrue();
});

it('supportsDenormalization accepts the array type with the schema format', function (): void {
    /** "a: b: c" is neither valid JSON nor valid YAML, so the type/format check decides. */
    expect((new SchemaDenormalizer())->supportsDenormalization('a: b: c', 'array', SchemaNormalizer::FORMAT))->toBeTrue();
});

it('supportsDenormalization rejects other types and formats', function (): void {
    $denormalizer = new SchemaDenormalizer();
    expect($denormalizer->supportsDenormalization('a: b: c', 'array', 'json'))->toBeFalse();
    expect($denormalizer->supportsDenormalization('a: b: c', Tag::class, SchemaNormalizer::FORMAT))->toBeFalse();
});

it('denormalize casts string scalars to int, float, and bool', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $data = [
        'data' => [
            [
                'item-typed-1' => [
                    '@id' => 'typed-1',
                    '@type' => 'typed_entity',
                    'label' => 'Test',
                    'count' => '42',
                    'ratio' => '3.14',
                    'active' => '1',
                ],
            ],
        ],
    ];
    $entity = $denormalizer->denormalize($data, TypedFieldEntity::class, SchemaNormalizer::FORMAT)->first();
    expect($entity->getCount())->toBe(42);
    expect($entity->getRatio())->toBe(3.14);
    expect($entity->getActive())->toBeTrue();
});

it('denormalize decodes a JSON scalar array fragment', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $data = [
        'data' => [
            [
                'item-js-1' => [
                    '@id' => 'js-1',
                    '@type' => 'json_scalar_array_fragment_entity',
                    'payload' => \json_encode([
                        'a' => 1,
                        'b' => 'two',
                        'c' => null,
                    ]),
                ],
            ],
        ],
    ];
    $entity = $denormalizer->denormalize($data, JsonScalarArrayFragmentEntity::class, SchemaNormalizer::FORMAT)->first();
    expect($entity->getPayload())->toBe([
        'a' => 1,
        'b' => 'two',
        'c' => null,
    ]);
});

it('denormalize throws for a JSON scalar fragment that is not valid JSON', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $data = [
        'data' => [
            [
                'item-js-2' => [
                    '@id' => 'js-2',
                    '@type' => 'json_scalar_array_fragment_entity',
                    'payload' => 'not-json',
                ],
            ],
        ],
    ];
    expect(fn () => $denormalizer->denormalize($data, JsonScalarArrayFragmentEntity::class, SchemaNormalizer::FORMAT))
        ->toThrow(\InvalidArgumentException::class);
});

it('denormalize throws for a JSON scalar fragment that is not an array', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $data = [
        'data' => [
            [
                'item-js-3' => [
                    '@id' => 'js-3',
                    '@type' => 'json_scalar_array_fragment_entity',
                    'payload' => '42',
                ],
            ],
        ],
    ];
    /** A bare JSON scalar decodes to a non-array, so decoding must reject it. */
    expect(fn () => $denormalizer->denormalize($data, JsonScalarArrayFragmentEntity::class, SchemaNormalizer::FORMAT))
        ->toThrow(\InvalidArgumentException::class);
});

it('denormalize decrypts sensitive fragments when an encryption service is present', function (): void {
    $encryption = new EncryptionService('test-sensitive-key-32-bytes-long!');
    $denormalizer = new SchemaDenormalizer($encryption);
    $data = [
        'data' => [
            [
                'item-sens-1' => [
                    '@id' => 'sens-1',
                    '@type' => 'sensitive_user',
                    'username' => 'alice',
                    'email' => $encryption->encrypt('alice@example.com'),
                    'password' => $encryption->encrypt('secret'),
                ],
            ],
        ],
    ];
    $entity = $denormalizer->denormalize($data, SensitiveUser::class, SchemaNormalizer::FORMAT)->first();
    expect($entity->getUsername())->toBe('alice');
    expect($entity->getEmail())->toBe('alice@example.com');
    expect($entity->getPassword())->toBe('secret');
});

it('denormalize renames and drops fragments via the fragment map', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $data = [
        'data' => [
            [
                'item-mig-1' => [
                    '@id' => 'mig-1',
                    '@type' => 'migrating_person',
                    'fullName' => 'Jane Doe',
                    'legacyBio' => 'old bio',
                ],
            ],
        ],
    ];
    $entity = $denormalizer->denormalize($data, MigratingPerson::class, SchemaNormalizer::FORMAT)->first();
    expect($entity->getName())->toBe('Jane Doe');
});
