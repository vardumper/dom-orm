<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Normalizer;

use DOM\ORM\Encryption\EncryptedValue;
use DOM\ORM\Encryption\EncryptionService;
use DOM\ORM\Mapping\Fragment;
use DOM\ORM\{Entity\AbstractEntity, Traits\AttributeResolverTrait};
use Ramsey\Collection\Collection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SchemaNormalizer implements NormalizerInterface
{
    use AttributeResolverTrait;

    public const FORMAT = 'dom_orm_schema';

    public function __construct(
        private readonly ?EncryptionService $encryption = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|string|int|float|bool|\ArrayObject<int, mixed>|null
     */
    public function normalize(mixed $object, string|null $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if (!$object instanceof AbstractEntity) {
            throw new \InvalidArgumentException(\sprintf('The object must extend "%s" or implement %s.', AbstractEntity::class, \JsonSerializable::class));
        }

        $entityType = $this->resolveEntityType($object);
        $data = [
            'item-' . $object->getId() => [
                '@id' => $object->getId(),
                '@type' => $entityType,
            ],
        ];

        $fragments = $this->resolveFragments($object);
        foreach ($fragments as [$storageStrategy, $fragmentName, $propertyName, $dataType]) {
            $name = ($storageStrategy === 'inline') ? '@' . $fragmentName : $fragmentName;
            $methodName = 'get' . \ucfirst($propertyName);

            if (\method_exists($object, $methodName)) {
                $value = $object->{$methodName}();
            } else {
                $value = $object->{$propertyName};
            }

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('c');
            }

            if (\is_array($value) || $value instanceof \Traversable) {
                if ($dataType !== Fragment::DATA_TYPE_JSON_SCALAR) {
                    throw new \InvalidArgumentException(\sprintf(
                        'Fragment "%s" on %s contains an array value. Arrays are rejected by default for Fragment values. Use #[Fragment(dataType: "%s")] for scalar JSON blobs, or prefer #[Group] mappings for domain collections.',
                        $propertyName,
                        $object::class,
                        Fragment::DATA_TYPE_JSON_SCALAR,
                    ));
                }

                $value = $this->encodeJsonScalarArray($value, $propertyName, $object::class);
            }

            if (\is_int($value) || \is_float($value) || \is_bool($value)) {
                $value = (string)$value;
            }

            // Encrypt sensitive string properties when an EncryptionService is configured
            if (
                $this->encryption !== null
                && \is_string($value)
                && $value !== ''
                && \in_array($propertyName, $this->resolveSensitiveProperties($object), true)
            ) {
                $value = new EncryptedValue(
                    $this->encryption->encrypt($value),
                    $this->encryption->searchHash($value),
                );
            }

            $data['item-' . $object->getId()][$name] = $value;
        }

        $groups = $this->resolveGroups($object);

        if ($groups === null) {
            return $data;
        }

        foreach ($groups as [$entity, $groupType, $propertyName]) {
            $name = $groupType ?? $propertyName;
            $methodName = 'get' . \ucfirst($propertyName);

            if (\method_exists($object, $methodName)) {
                $value = $object->{$methodName}();
            } else {
                $value = $object->{$propertyName};
            }

            if ($value === null) {
                continue;
            }

            if ($value instanceof AbstractEntity) {
                // Single entity relation — store as a group with one item
                $data['item-' . $object->getId()][$name][] = $this->normalize($value);
            } elseif (\is_array($value) || $value instanceof Collection || \is_iterable($value)) {
                foreach ($value as $item) {
                    if (\get_class($item) !== $entity) {
                        throw new \InvalidArgumentException(\sprintf('Wrong EntityInterface type given. Expected type was %s', $entity));
                    }
                    $data['item-' . $object->getId()][$name][] = $this->normalize($item);
                }
            } else {
                throw new \InvalidArgumentException(\sprintf(
                    'Groups must be of type Ramsey\Collection, an Array of EntityInterface objects, an Iterable, or a single EntityInterface. %s given',
                    \gettype($value)
                ));
            }
        }

        return $data;
    }

    /**
     * @param array<mixed>|\Traversable<mixed> $value
     */
    private function encodeJsonScalarArray(array|\Traversable $value, string $propertyName, string $entityClass): string
    {
        $arrayValue = \is_array($value) ? $value : \iterator_to_array($value);
        if (!$this->isJsonScalarArray($arrayValue)) {
            throw new \InvalidArgumentException(\sprintf(
                'Fragment "%s" on %s uses dataType "%s" but contains non-scalar values. Only scalar/null JSON array values are supported.',
                $propertyName,
                $entityClass,
                Fragment::DATA_TYPE_JSON_SCALAR,
            ));
        }

        try {
            return \json_encode($arrayValue, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(\sprintf(
                'Failed to JSON-encode fragment "%s" on %s.',
                $propertyName,
                $entityClass,
            ), previous: $exception);
        }
    }

    /**
     * @param array<mixed> $value
     */
    private function isJsonScalarArray(array $value): bool
    {
        foreach ($value as $item) {
            if (\is_array($item)) {
                if (!$this->isJsonScalarArray($item)) {
                    return false;
                }

                continue;
            }

            if ($item === null || \is_scalar($item)) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        /** @todo */
        return true;
    }

    public function supportsNormalization(
        mixed $data,
        string|null $format = null,
        array $context = []
    ): bool {
        if ($format !== static::FORMAT) {
            return false;
        }

        if ($data instanceof \Traversable) {
            $data = \iterator_to_array($data);
        }

        if (\is_array($data)) {
            $invalid_count = \count(\array_filter($data, function ($object) {
                return !$object instanceof AbstractEntity;
            }));

            return $invalid_count === 0;
        }

        if (!$data instanceof AbstractEntity) {
            return false;
        }

        return true;
    }

    public function getSupportedTypes(?string $format): array
    {
        $isCacheable = static::class === __CLASS__ || $this->hasCacheableSupportsMethod();

        $children = [];
        $children[AbstractEntity::class] = $isCacheable;

        return $children;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
