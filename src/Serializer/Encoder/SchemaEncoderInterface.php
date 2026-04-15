<?php
declare(strict_types=1);

namespace DOM\ORM\Serializer\Encoder;

interface SchemaEncoderInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function encode(array $data, string $format): string;
}
