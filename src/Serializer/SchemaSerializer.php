<?php declare(strict_types=1);

namespace DOM\ORM\Serializer;

use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

class SchemaSerializer extends Serializer implements NormalizerInterface
{
    public function __construct(SchemaNormalizer $normalizer, SchemaEncoder $encoder)
    {
        parent::__construct([$normalizer], [$encoder]);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): \ArrayObject|array|string|int|float|bool|null
    {
        return true;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return true;
    }
    // final public function serialize(mixed $data, string $format, array $context = []): string
    // {
    //     // Implement your custom serialization logic here
    //     return '';
    // }

    // public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    // {
    //     return '';
    //     // Implement your custom deserialization logic here
    // }
}
