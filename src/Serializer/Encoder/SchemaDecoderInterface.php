<?php
declare(strict_types=1);

namespace DOM\ORM\Serializer\Encoder;

interface SchemaDecoderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function decode(string $data, string $format): array;
}
