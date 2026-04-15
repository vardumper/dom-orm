<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Normalizer;

use DOM\ORM\{Entity\AbstractEntity, Traits\AttributeResolverTrait};
use Ramsey\Collection\Collection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SchemaNormalizer implements NormalizerInterface
{
    use AttributeResolverTrait;

    public const FORMAT = 'dom_orm_schema';

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
        foreach ($fragments as [$storageStrategy, $fragmentName, $propertyName]) {
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

            if (!\is_array($value) && !$value instanceof Collection && !\is_iterable($value)) {
                throw new \InvalidArgumentException(\sprintf('Groups must be of type Ramsey\Collection, an Array of EntityInterface objects or an Iterable. %s given', \gettype($value)));
            }

            foreach ($value as $item) {
                if (\get_class($item) !== $entity) {
                    throw new \InvalidArgumentException(\sprintf('Wrong EntityInterface type given. Expected type was %s', $entity));
                }
                $data['item-' . $object->getId()][$name][] = $this->normalize($item);
            }
        }

        return $data;
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
