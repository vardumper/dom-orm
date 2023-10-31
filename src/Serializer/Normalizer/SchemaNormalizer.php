<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Normalizer;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class SchemaNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * The supported format.
     */
    public const FORMAT = 'dom_orm_schema';

    /**
     * The supported type to denormalize to.
     */
    public const TYPE = 'array';
    private JsonSerializableNormalizer $normalizer;

    public function __construct(JsonSerializableNormalizer $jsonSerializableNormalizer)
    {
        $this->normalizer = $jsonSerializableNormalizer;
    }

    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if (!$object instanceof AbstractEntity) {
            throw new \InvalidArgumentException(sprintf('The object must extend "%s" or implement %s.', AbstractEntity::class, \JsonSerializable::class));
        }

        // return $this->normalizer->serializer->normalize($object->jsonSerialize(), $format, $context);
        if (function_exists('\uopz_implement')) {
            \uopz_implement($object::class, \JsonSerializable::class);
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return true;
    }

    public function supportsNormalization(
        mixed $data,
        string|null $format = null
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

    public function supportsDenormalization(
        mixed $data,
        string $type,
        string|null $format = null
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
            } catch (ParseException $e) {
                // Cathc exception and continue execution if not valid YAML
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
}
