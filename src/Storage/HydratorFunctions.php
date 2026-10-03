<?php

declare(strict_types=1);

/**
 * Shared helpers for compiled hydrator mappers.
 *
 * Generated mapper files (storage/generated/{entityType}.php) are plain PHP in
 * the global namespace and call these helpers by their global names. The
 * functions are defined once (guarded by function_exists) so multiple mapper
 * files can safely load them. They MUST live in the global namespace because
 * the generated mappers reference them unqualified.
 *
 * Loaded by Hydrator::loadFunctions().
 */

if (!\function_exists('dom_orm_hydrator_cast')) {
    /**
     * Cast a string to int/float/bool when $type demands it; return as-is otherwise.
     * Mirrors SchemaDenormalizer::castScalar().
     */
    function dom_orm_hydrator_cast(string $value, ?string $type): mixed
    {
        return match ($type) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => $value === '1' || \strtolower($value) === 'true',
            default => $value,
        };
    }
}

if (!\function_exists('dom_orm_hydrator_is_json_scalar_array')) {
    /**
     * @param array<mixed> $value
     */
    function dom_orm_hydrator_is_json_scalar_array(array $value): bool
    {
        foreach ($value as $item) {
            if (\is_array($item)) {
                if (!dom_orm_hydrator_is_json_scalar_array($item)) {
                    return false;
                }

                continue;
            }

            if ($item === null || \is_scalar($item)) {
                continue;
            }

            return false;
        }

        return true;
    }
}

if (!\function_exists('dom_orm_hydrator_decode_json_scalar')) {
    /**
     * Decode a json_scalar fragment value. Mirrors SchemaDenormalizer::decodeJsonScalarArray().
     *
     * @return array<mixed>
     */
    function dom_orm_hydrator_decode_json_scalar(string $value, string $propertyName, string $entityClass): array
    {
        try {
            $decoded = \json_decode($value, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException(\sprintf(
                'Fragment "%s" on %s is configured as "%s" but did not contain valid JSON.',
                $propertyName,
                $entityClass,
                \DOM\ORM\Mapping\Fragment::DATA_TYPE_JSON_SCALAR,
            ), previous: $exception);
        }

        if (!\is_array($decoded) || !dom_orm_hydrator_is_json_scalar_array($decoded)) {
            throw new \InvalidArgumentException(\sprintf(
                'Fragment "%s" on %s is configured as "%s" but must decode to an array of scalar/null values.',
                $propertyName,
                $entityClass,
                \DOM\ORM\Mapping\Fragment::DATA_TYPE_JSON_SCALAR,
            ));
        }

        return $decoded;
    }
}

if (!\function_exists('dom_orm_hydrator_call')) {
    /**
     * Hydrate a nested group item via its generated mapper when available,
     * otherwise via the reflection fallback.
     *
     * @param array<string, array<string, mixed>> $row
     */
    function dom_orm_hydrator_call(string $entityType, string $entityClass, array $row, ?\DOM\ORM\Encryption\EncryptionService $enc, callable $fallback): \DOM\ORM\Entity\EntityInterface
    {
        $fn = 'dom_orm_hydrate_' . $entityType;
        if (\function_exists($fn)) {
            return $fn($row, $enc, $fallback);
        }

        /** The compiled mapper only carries the type string, so the nested entity */
        /** class may not be autoloaded yet. The reflection path primes it via the */
        /** #[Group(entity: ...)] class-string; do the same here so the reflection */
        /** fallback can resolve the type. */
        \class_exists($entityClass);

        return $fallback($row);
    }
}
