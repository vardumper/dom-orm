<?php declare(strict_types=1);

namespace DOM\ORM\Serializer;

use DOM\ORM\Serializer\Decoder\SchemaDecoder;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SchemaSerializer implements NormalizerInterface
{
    protected $encoder;

    protected $decoder;

    private SchemaNormalizer $normalizer;

    public function __construct(SchemaNormalizer $normalizer, SchemaEncoder $encoder, SchemaDecoder $decoder)
    {
        $this->normalizer = $normalizer;
        $this->encoder = $encoder;
        $this->decoder = $decoder;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false,
        ];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): \ArrayObject|array|string|int|float|bool|null
    {
        return $this->normalizer->normalize($data, $format, $context);
    }

    public function encode(mixed $data, string $format, array $context = []): string
    {
        return $this->encoder->encode($data, $format, $context);
    }

    public function decode(mixed $data, string $format, array $context = []): ?array
    {
        return $this->decoder->decode($data, $format, $context);
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
