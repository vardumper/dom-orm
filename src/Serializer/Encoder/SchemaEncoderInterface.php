<?php
declare(strict_types=1);

namespace DOM\ORM\Serializer\Encoder;

interface SchemaEncoderInterface
{
    public function decode(string $data, string $format): array;
    public function encode(array $data, string $format): string;
}
