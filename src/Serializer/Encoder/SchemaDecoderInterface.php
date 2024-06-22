<?php
declare(strict_types=1);

namespace DOM\ORM\Serializer\Encoder;

interface SchemaDecoderInterface
{
    public function decode(string $data, string $format): array;
}
