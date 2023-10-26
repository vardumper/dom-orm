<?php

declare(strict_types=1);

use DOM\ORM\Entity\AbstractEntity;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SchemaNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * The supported format.
     */
    public const FORMAT = 'dom_orm_schema';

    /**
     * The supported type to denormalize to.
     */
    public const TYPE = 'dom_orm_type';

    public function normalize($object, $format = null, array $context = [])
    {
        if (!$object instanceof \DateTime) {
            throw new \InvalidArgumentException('The object must be a DateTime instance');
        }

        return $object->format('Y-m-d H:i:s');
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = [])
    {
        return true;
    }

    public function supportsNormalization($data, $format = null)
    {
        // First check if the format is supported.
        if ($format !== static::FORMAT) {
            return false;
        }

        if ($data instanceof \Traversable) {
            $data = iterator_to_array($data);
        }

        // If an iterable is passed allow normalization if all items are AbstractEntities.
        if (is_array($data)) {
            $invalid_count = count(array_filter($data, function ($object) {
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

    public function supportsDenormalization($data, $type, $format = null)
    {
        // First, look into the data.
        // A string was passed, check if we can transform it to an array
        $valid = false;
        if (is_string($data)) {
            $isJson = (json_decode($data) !== null);
            if ($isJson) {
                $valid = true; // string is valid json
            }
        }

        return $type === static::TYPE && $format === static::FORMAT;
    }

    private function getJson($data): ?array
    {
        if (!is_string($data)) {
            return null;
        }

        $decodedData = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decodedData;
    }
}
