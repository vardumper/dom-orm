<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Normalizer;

use DOM\ORM\Encryption\EncryptionService;
use DOM\ORM\{Entity\AbstractEntity, Entity\EntityInterface, Serializer\Encoder\SchemaEncoder, Traits\AttributeResolverTrait};
use DOM\ORM\Mapping\Fragment;
use Ramsey\Collection\Collection;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class SchemaDenormalizer implements DenormalizerInterface
{
    use AttributeResolverTrait;

    private const FORMAT = 'dom_orm_schema';

    private const TYPE = 'array';

    private const RESERVED_ATTRIBUTES = ['@id', '@type'];

    private const DATETIME_ATTRIBUTES = ['createdAt', 'updatedAt', 'deletedAt'];

    public function __construct(
        private readonly ?EncryptionService $encryption = null,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (\is_subclass_of($type, AbstractEntity::class)) {
            // Prime attribute cache for the requested root type so entityType lookups
            // also work when the class was only referenced as a class-string.
            $this->resolveEntityType($type);
        }

        if (isset($data['data'])) {
            $ret = new Collection($type);
            foreach ($data['data'] as $row) {
                $entity = $this->instantiateEntity($row);
                $ret->add($entity);
            }

            return $ret;
        }

        return null;
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = []
    ): bool {
        $isXml = (\simplexml_load_string($data) !== false);
        if ($isXml || $data instanceof \DOMDocument) {
            throw new \InvalidArgumentException(sprintf('You don\'t need to pass XML directly to the denormalize() method. Please use the decode() method of %s instead.', SchemaEncoder::class));
        }

        $valid = false;

        if (\is_string($data)) {
            $isJson = (\json_decode($data) !== null);
            if ($isJson) {
                $valid = true;
            }

            try {
                Yaml::parse($data);
                $valid = true;
            } catch (ParseException) {
            }
        }

        if ($valid) {
            return true;
        }

        return $type === self::TYPE && $format === self::FORMAT;
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

    /**
     * @param array<string, array<string, mixed>> $data
     */
    private function instantiateEntity(array $data): EntityInterface
    {
        $entityData = $data[\array_key_first($data)];
        $entityClass = $this->getEntityByEntityType($entityData['@type']);

        // Apply FragmentMap: rename keys and drop nulls before any hydration.
        $fragmentMap = $this->resolveFragmentMap($entityClass);
        if ($fragmentMap !== []) {
            foreach ($fragmentMap as $oldName => $newName) {
                if (!\array_key_exists($oldName, $entityData)) {
                    continue;
                }
                if ($newName !== null && !\array_key_exists($newName, $entityData)) {
                    // Rename: move value to new key so existing hydration logic handles it.
                    $entityData[$newName] = $entityData[$oldName];
                }
                // Drop the legacy key in both rename and removal cases.
                unset($entityData[$oldName]);
            }
        }

        // Unwrap single-entity groups: transform decoded group arrays into entity instances.
        $groups = $this->resolveGroups($entityClass);
        if ($groups !== null) {
            foreach ($groups as [$groupEntity, $groupType, $propName, $isSingle]) {
                // Prime entity-type cache for nested relation targets referenced
                // as class-strings in #[Group(entity: ...)] metadata.
                $this->resolveEntityType($groupEntity);

                $key = $groupType ?? $propName;
                if (!\array_key_exists($key, $entityData)) {
                    continue;
                }
                $items = $entityData[$key];
                if ($isSingle) {
                    $entityData[$key] = !empty($items) ? $this->instantiateEntity($items[0]) : null;
                } else {
                    $entityData[$key] = \array_map(
                        fn (array $item): EntityInterface => $this->instantiateEntity($item),
                        $items,
                    );
                }
            }
        }

        $fragmentDataTypes = $this->resolveFragmentDataTypes($entityClass);

        $params = $this->resolveConstructorParams($entityClass);
        $constructorArgs = [];

        $sensitiveProps = $this->resolveSensitiveProperties($entityClass);

        foreach ($params as $param) {
            if (!isset($entityData[$param->getName()])) {
                continue;
            }

            $paramValue = $entityData[$param->getName()];

            if (
                $this->encryption !== null
                && \is_string($paramValue)
                && $paramValue !== ''
                && \in_array($param->getName(), $sensitiveProps, true)
            ) {
                $paramValue = $this->encryption->decrypt($paramValue);
                $entityData[$param->getName()] = $paramValue;
            }

            if (
                \is_string($paramValue)
                && ($fragmentDataTypes[$param->getName()] ?? null) === Fragment::DATA_TYPE_JSON_SCALAR
            ) {
                $paramValue = $this->decodeJsonScalarArray($paramValue, $param->getName(), $entityClass);
                $entityData[$param->getName()] = $paramValue;
            }

            if (\in_array($param->getName(), self::DATETIME_ATTRIBUTES, true)
                && \is_string($paramValue)) {
                $entityData[$param->getName()] = new \DateTimeImmutable($paramValue);
            }

            if (\is_string($entityData[$param->getName()])) {
                $entityData[$param->getName()] = $this->castScalar($entityData[$param->getName()], $param->getType());
            }

            if (!isset($constructorArgs[$param->getName()])) {
                $constructorArgs[$param->getName()] = $entityData[$param->getName()];
            }
        }

        /** @var EntityInterface $ret */
        $ret = new $entityClass(...$constructorArgs);
        $ret->setId($entityData['@id']);
        foreach ($entityData as $key => $value) {
            if (\in_array($key, self::RESERVED_ATTRIBUTES, true)) {
                continue;
            }

            // Constructor args are already hydrated (and potentially decrypted)
            // above, so avoid reprocessing/redecryption via setters.
            if (\array_key_exists($key, $constructorArgs)) {
                continue;
            }

            if (
                $this->encryption !== null
                && \is_string($value)
                && $value !== ''
                && \in_array($key, $sensitiveProps, true)
            ) {
                $value = $this->encryption->decrypt($value);
            }

            if (
                \is_string($value)
                && ($fragmentDataTypes[$key] ?? null) === Fragment::DATA_TYPE_JSON_SCALAR
            ) {
                $value = $this->decodeJsonScalarArray($value, $key, $entityClass);
            }

            if (\in_array($key, self::DATETIME_ATTRIBUTES, true) && \is_string($value)) {
                $value = new \DateTimeImmutable($value);
            }

            $method = 'set' . \ucfirst($key);
            // Guard against orphaned fragments whose setter no longer exists.
            if (!\method_exists($ret, $method)) {
                continue;
            }

            // Cast string back to the setter's declared scalar type when possible.
            if (\is_string($value)) {
                $setterRef = new \ReflectionMethod($ret, $method);
                $setterParams = $setterRef->getParameters();
                if (!empty($setterParams)) {
                    $value = $this->castScalar($value, $setterParams[0]->getType());
                }
            }

            $ret->{$method}($value);
        }

        return $ret;
    }

    /**
     * @param class-string<AbstractEntity> $entityClass
     * @return array<string, string|null>
     */
    private function resolveFragmentDataTypes(string $entityClass): array
    {
        $types = [];
        $fragments = $this->resolveFragments($entityClass);
        if ($fragments === null) {
            return $types;
        }

        foreach ($fragments as [, , $propertyName, $dataType]) {
            $types[$propertyName] = $dataType;
        }

        return $types;
    }

    /**
     * @return array<mixed>
     */
    private function decodeJsonScalarArray(string $value, string $propertyName, string $entityClass): array
    {
        try {
            $decoded = \json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(\sprintf(
                'Fragment "%s" on %s is configured as "%s" but did not contain valid JSON.',
                $propertyName,
                $entityClass,
                Fragment::DATA_TYPE_JSON_SCALAR,
            ), previous: $exception);
        }

        if (!\is_array($decoded) || !$this->isJsonScalarArray($decoded)) {
            throw new \InvalidArgumentException(\sprintf(
                'Fragment "%s" on %s is configured as "%s" but must decode to an array of scalar/null values.',
                $propertyName,
                $entityClass,
                Fragment::DATA_TYPE_JSON_SCALAR,
            ));
        }

        return $decoded;
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
     * Cast a string value to int, float, or bool when the reflection type demands it.
     * Returns the original string for any other type or when $type is null.
     */
    private function castScalar(string $value, ?\ReflectionType $type): mixed
    {
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => $value === '1' || \strtolower($value) === 'true',
            default => $value,
        };
    }
}
