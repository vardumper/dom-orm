<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Normalizer;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Traits\AttributeResolverTrait;
use Ramsey\Collection\Collection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SchemaNormalizer implements NormalizerInterface
{
    use AttributeResolverTrait;

    /**
     * The supported format.
     */
    public const FORMAT = 'dom_orm_schema';

    /**
     * The supported type to denormalize to.
     */
    public const TYPE = 'array';

    public function normalize(mixed $object, string|null $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if (!$object instanceof AbstractEntity) {
            throw new \InvalidArgumentException(sprintf('The object must extend "%s" or implement %s.', AbstractEntity::class, \JsonSerializable::class));
        }

        $entityType = $this->resolveEntityType($object);
        $data = [
            'item-' . $object->getId() => [
                '@type' => $entityType,
                '@id' => $object->getId(),
                // '@class' => get_class($object), - not really needed we can get the class by the entity attribute
            ],
        ];

        // fragments
        $fragments = $this->resolveFragments($object);
        foreach ($fragments as [$storageStrategy, $fragmentName, $propertyName]) {
            $name = ($storageStrategy === 'inline') ? '@' . $fragmentName : $fragmentName;

            try {
                // we expect private properties to be inaccessible here (therefor throw an Exception)
                $value = $object->{$propertyName};
            } catch (\Throwable) {
                // so we'll try to get the value via the getter
                $methodName = 'get' . ucfirst($propertyName);
                $value = $object->{$methodName}();
            }

            // basic sanitization
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('c');
            }

            $data['item-' . $object->getId()][$name] = $value;
        }

        $groups = $this->resolveGroups($object);

        // nothing more to do here
        if ($groups === null) {
            return $data;
        }

        foreach ($groups as [$entity, $groupType, $propertyName]) {
            $name = $groupType ?? $propertyName;
            $value = null;

            try {
                // we expect private properties to be inaccessible here
                $value = $object->{$propertyName};
            } catch (\Throwable) {
                // so we'll try to get the value via a getter method
                $methodName = 'get' . ucfirst($propertyName);
                if (!method_exists($object, $methodName)) {
                    throw new \InvalidArgumentException(sprintf('Error getting %s value. Make the property public or add a %s() getter method.', $propertyName, $methodName));
                }
                $value = $object->{$methodName}();
            }

            // skip empty groups
            if ($value === null) {
                continue;
            }

            // some basic validation
            if (!is_array($value) && !$value instanceof Collection && !is_iterable($value)) {
                throw new \InvalidArgumentException(sprintf('Groups must be of type Ramsey\Collection, an Array of EntityInterface objects or an Iterable. %s given', gettype($value)));
            }

            // recursion
            foreach ($value as $item) {
                if (get_class($item) !== $entity) {
                    throw new \InvalidArgumentException(sprintf('Wrong EntityInterface type given. Expected type was %s', $entity));
                }
                $data['item-' . $object->getId()][$name][] = $this->normalize($item);
            }
        }

        return $data;
    }

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
        // First check if the format is supported.
        if ($format !== static::FORMAT) {
            return false;
        }

        if ($data instanceof \Traversable) {
            $data = \iterator_to_array($data);
        }

        // If an iterable is passed allow normalization if all items are AbstractEntities.
        if (is_array($data)) {
            $invalid_count = \count(\array_filter($data, function ($object) {
                return !$object instanceof AbstractEntity;
            }));

            return $invalid_count === 0;
        }

        // otherwise object must be an AbstractEntity
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
        foreach (get_declared_classes() as $class) {
            $reflected = new \ReflectionClass($class);
            if ($reflected->isSubclassOf(AbstractEntity::class)) {
                $children[$class] = $isCacheable;
            }
        }

        return $children;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
