<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Normalizer;

use DOM\ORM\{Entity\AbstractEntity, Entity\EntityInterface, Serializer\Encoder\SchemaEncoder, Traits\AttributeResolverTrait};
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

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
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

        return $type === static::TYPE && $format === static::FORMAT;
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

    private function instantiateEntity(array $data): EntityInterface
    {
        $entityData = $data[\array_key_first($data)];
        $entityClass = $this->getEntityByEntityType($entityData['@type']);

        $reflection = new \ReflectionClass($entityClass);
        $params = $reflection->getConstructor()->getParameters();
        $constructorArgs = [];

        foreach ($params as $param) {
            if (!isset($entityData[$param->getName()])) {
                continue;
            }
            if (\in_array($param->getName(), self::DATETIME_ATTRIBUTES, true)) {
                $entityData[$param->getName()] = new \DateTimeImmutable($entityData[$param->getName()]);
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
            if (\in_array($key, self::DATETIME_ATTRIBUTES, true)) {
                $value = new \DateTimeImmutable($value);
            }
            $method = 'set' . \ucfirst($key);
            $ret->{$method}($value);
        }

        return $ret;
    }
}
