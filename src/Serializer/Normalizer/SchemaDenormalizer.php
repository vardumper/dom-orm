<?php

declare(strict_types=1);

namespace DOM\ORM\Serializer\Normalizer;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Traits\AttributeResolverTrait;
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

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        /** @todo */
        return true;
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
}