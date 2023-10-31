<?php declare(strict_types=1);

namespace App\Serializer;

use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

class SchemaSerializer extends Serializer
{
    public function __construct(NormalizerInterface $normalizer, EncoderInterface $encoder)
    {
        parent::__construct([$normalizer], [$encoder]);
    }

    public function serialize(mixed $data, string $format, array $context = []): string
    {
        // Implement your custom serialization logic here
        return '';
    }

    public function deserialize(mixed $data, string $type, string $format, array $context = []): mixed
    {
        return '';
        // Implement your custom deserialization logic here
    }
}
