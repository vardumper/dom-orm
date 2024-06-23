<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Normalizer;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Traits\AttributeResolverTrait;
use Ramsey\Collection\Collection;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class SchemaDenormalizer implements DenormalizerInterface
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

    private const RESERVED_ATTRIBUTES = ['@id', '@type'];

    private const DATETIME_ATTRIBUTES = ['createdAt', 'updatedAt', 'deletedAt'];

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        /** @todo */
        if (count($data['data']) > 1) {
            // we need a collection
            $ret = new Collection($type);
            foreach ($data['data'] as $key => $data) {
                $entity = $this->instantiateEntity($data);
                $ret->add($entity);
            }

            return $ret;
        }

        // we need a single entity
        if (count($data['data']) === 1) {
            return $this->instantiateEntity($data['data'][0]);
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

        $valid = false; // default

        // Look into the $data passed: if a string, check if we can transform it to an array
        if (\is_string($data)) {
            $isJson = (\json_decode($data) !== null);
            if ($isJson) {
                $valid = true; // string is valid JSON
            }

            try {
                Yaml::parse($data);
                $valid = true; // string is valid YAML
            } catch (ParseException) {
                // Catch exception and continue execution if not valid YAML
            }
        }

        if ($valid) {
            return true;
        }

        // otherwise: neither json nor yaml, cheack params
        return $type === static::TYPE && $format === static::FORMAT;
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

    /**
     * Instantiate an entity from stored values
     */
    private function instantiateEntity(array $data): EntityInterface
    {
        $entityData = $data[array_key_first($data)];
        $entityClass = $this->getEntityByEntityType($entityData['@type']);

        // get entity constructor params
        $reflection = new \ReflectionClass($entityClass);
        $params = $reflection->getConstructor()->getParameters();
        $constructoArgs = [];
        foreach ($params as $param) {
            // skip missing
            if (!isset($entityData[$param->getName()])) {
                continue;
            }
            // convert datetime strings to objects
            if (in_array($param->getName(), self::DATETIME_ATTRIBUTES, true)) {
                $entityData[$param->getName()] = new \DateTimeImmutable($entityData[$param->getName()]);
            }

            // dont set stuff twice
            if (!isset($constructoArgs[$param->getName()])) {
                $constructoArgs[$param->getName()] = $entityData[$param->getName()];
            }

            // @todo how about groups, collections, arrays?
        }

        // @todo how to use php8.3 named arguments dynamically?
        /** @var EntityInterface $ret */
        $ret = new $entityClass(...$constructoArgs);
        foreach ($entityData as $key => $value) {
            if (in_array($key, self::RESERVED_ATTRIBUTES, true)) {
                continue;
            }
            // convert datetime strings to objects
            if (in_array($key, self::DATETIME_ATTRIBUTES, true)) {
                $value = new \DateTimeImmutable($value);
            }
            $method = 'set' . ucfirst($key);
            $ret->{$method}($value);
        }

        return $ret;
    }
}
