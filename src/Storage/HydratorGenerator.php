<?php

declare(strict_types=1);

namespace DOM\ORM\Storage;

use DOM\ORM\{Entity\AbstractEntity, Mapping\Fragment, Mapping\FragmentMap, Mapping\Group, Mapping\Item, Mapping\Sensitive};

/**
 * Emits a plain-PHP hydration mapper per entity class.
 *
 * The generated function reproduces SchemaDenormalizer::instantiateEntity()
 * exactly — fragment-map renames, recursive group hydration, encryption,
 * json-scalar decoding, datetime parsing and scalar casting — but with no
 * per-field reflection, so it is opcache-friendly and ~an order of magnitude
 * faster than the reflection path.
 *
 * Generated file layout (global namespace, fully-qualified names):
 *
 *   <?php
 *   // dom-orm-hydrator: entity_type=user class=App\Entity\User hash=<sha256>
 *   function dom_orm_hydrate_user(array $row, ?EncryptionService $enc, callable $fallback): EntityInterface { ... }
 *
 * The hash line is read without executing the file (Hydrator::readFileHash) so
 * staleness can be detected before the global function is (re)defined.
 */
final class HydratorGenerator
{
    private const DATETIME_ATTRIBUTES = ['createdAt', 'updatedAt', 'deletedAt'];

    /**
     * Bumped whenever the emitted mapper code changes shape (not just the entity
     * metadata), so existing generated files are detected as stale and rewritten.
     */
    private const GENERATOR_VERSION = '2';

    /**
     * Generate the mapper file for an entity class.
     *
     * @return string|null the written file path, or null when $class is not a
     *                     hydratable entity (no #[Item] / not an AbstractEntity).
     */
    public static function generateForClass(string $class, string $generatedDir): ?string
    {
        if (!\is_subclass_of($class, AbstractEntity::class)) {
            return null;
        }

        $entityType = self::entityTypeFor($class);
        if ($entityType === null) {
            return null;
        }

        $meta = self::resolveMeta($class);
        $hash = self::signatureHash($class, $meta);
        $code = self::emit($entityType, $class, $meta, $hash);

        if (!\is_dir($generatedDir) && !\mkdir($generatedDir, 0755, true) && !\is_dir($generatedDir)) {
            throw new \RuntimeException(\sprintf('Failed to create generated dir: %s', $generatedDir));
        }

        $path = $generatedDir . \DIRECTORY_SEPARATOR . $entityType . '.php';
        $tmp = $path . '.tmp-' . \getmypid();
        if (\file_put_contents($tmp, $code) === false) {
            throw new \RuntimeException(\sprintf('Failed to write hydrator: %s', $path));
        }

        if (!\rename($tmp, $path)) {
            if (!\copy($tmp, $path) || !\unlink($tmp)) {
                @\unlink($tmp);

                throw new \RuntimeException(\sprintf('Failed to write hydrator: %s', $path));
            }
        }

        return $path;
    }

    /**
     * Canonical metadata for a class, used both for code emission and for the
     * staleness signature hash.
     *
     * @return array{
     *   ctorParams: array<string, array{type: string|null, sensitive: bool, jsonScalar: bool, datetime: bool}>,
     *   fragments: array<string, string|null>,
     *   groups: list<array{0: string, 1: string|null, 2: string, 3: bool}>,
     *   sensitiveProps: list<string>,
     *   fragmentMap: array<string, string|null>,
     *   propertyTypes: array<string, string|null>,
     *   setterFields: array<string, array{type: string|null, sensitive: bool, jsonScalar: bool, datetime: bool}>
     * }
     */
    public static function metaForClass(string $class): array
    {
        return self::resolveMeta($class);
    }

    /**
     * SHA-256 of the class identity + source mtime + canonical metadata.
     * Changes when the entity file is edited or its mapping attributes change.
     */
    /**
     * @param array<string, mixed> $meta
     */
    public static function signatureHash(string $class, array $meta): string
    {
        $source = (new \ReflectionClass($class))->getFileName();
        $mtime = ($source !== false && \is_file($source)) ? (string)\filemtime($source) : '';

        /** $meta is built in a fixed (reflection) order, so plain json_encode is */
        /** stable across runs. (JSON_SORT_KEYS was removed in PHP 8.5.) */
        /** GENERATOR_VERSION forces a rewrite when the emitted code shape changes. */
        return \hash('sha256', self::GENERATOR_VERSION . '|' . $class . '|' . $mtime . '|' . \json_encode($meta));
    }

    /**
     * @return array{
     *   ctorParams: array<string, array{type: string|null, sensitive: bool, jsonScalar: bool, datetime: bool}>,
     *   fragments: array<string, string|null>,
     *   groups: list<array{0: string, 1: string|null, 2: string, 3: bool}>,
     *   sensitiveProps: list<string>,
     *   fragmentMap: array<string, string|null>,
     *   propertyTypes: array<string, string|null>,
     *   setterFields: array<string, array{type: string|null, sensitive: bool, jsonScalar: bool, datetime: bool}>
     * }
     */
    private static function resolveMeta(string $class): array
    {
        $rc = new \ReflectionClass($class);
        $sensitiveProps = [];
        $fragments = [];
        $groups = [];
        $propertyTypes = [];

        $properties = $rc->getProperties();
        $parent = $rc->getParentClass();
        if ($parent !== false) {
            $properties = \array_merge($properties, $parent->getProperties());
        }

        foreach ($properties as $prop) {
            $propName = $prop->getName();
            $type = $prop->getType();
            $propertyTypes[$propName] = ($type instanceof \ReflectionNamedType) ? $type->getName() : null;

            foreach ($prop->getAttributes(Fragment::class) as $attr) {
                $fragments[$propName] = $attr->newInstance()->dataType;
                if (!empty($prop->getAttributes(Sensitive::class))) {
                    $sensitiveProps[] = $propName;
                }
            }

            foreach ($prop->getAttributes(Group::class) as $attr) {
                $group = $attr->newInstance();
                $propType = $prop->getType();
                $isSingle = $propType instanceof \ReflectionNamedType
                    && !$propType->isBuiltin()
                    && \is_subclass_of($propType->getName(), AbstractEntity::class);
                $groups[] = [$group->entity, $group->groupType, $propName, $isSingle];
            }
        }

        $fragmentMap = [];
        foreach ($rc->getAttributes(FragmentMap::class) as $attr) {
            $fragmentMap = \array_merge($fragmentMap, $attr->newInstance()->map);
        }

        $ctorParams = [];
        $ctor = $rc->getConstructor();
        if ($ctor !== null) {
            foreach ($ctor->getParameters() as $p) {
                $name = $p->getName();
                $ptype = $p->getType();
                $ctorParams[$name] = [
                    'type' => ($ptype instanceof \ReflectionNamedType) ? $ptype->getName() : null,
                    'sensitive' => \in_array($name, $sensitiveProps, true),
                    'jsonScalar' => ($fragments[$name] ?? null) === Fragment::DATA_TYPE_JSON_SCALAR,
                    'datetime' => \in_array($name, self::DATETIME_ATTRIBUTES, true),
                ];
            }
        }

        $groupPropNames = \array_map(static fn (array $g): string => $g[2], $groups);
        $setterFields = [];
        foreach ($fragments as $propName => $dataType) {
            if (\array_key_exists($propName, $ctorParams)) {
                continue;
            }
            if (\in_array($propName, $groupPropNames, true)) {
                continue;
            }
            $setterFields[$propName] = [
                'type' => $propertyTypes[$propName] ?? null,
                'sensitive' => \in_array($propName, $sensitiveProps, true),
                'jsonScalar' => $dataType === Fragment::DATA_TYPE_JSON_SCALAR,
                'datetime' => \in_array($propName, self::DATETIME_ATTRIBUTES, true),
            ];
        }

        return [
            'ctorParams' => $ctorParams,
            'fragments' => $fragments,
            'groups' => $groups,
            'sensitiveProps' => $sensitiveProps,
            'fragmentMap' => $fragmentMap,
            'propertyTypes' => $propertyTypes,
            'setterFields' => $setterFields,
        ];
    }

    /**
     * Emit the mapper PHP source.
     */
    /**
     * @param array<string, mixed> $meta
     */
    private static function emit(string $entityType, string $class, array $meta, string $hash): string
    {
        $fnName = 'dom_orm_hydrate_' . $entityType;
        $L = [];
        $L[] = '<?php';
        $L[] = '// Generated by DOM-ORM HydratorGenerator. Do not edit by hand.';
        $L[] = '// dom-orm-hydrator: entity_type=' . $entityType . ' class=' . $class . ' hash=' . $hash;
        $L[] = '';
        $L[] = 'function ' . $fnName . '(array $row, ?\DOM\ORM\Encryption\EncryptionService $enc, callable $fallback): \DOM\ORM\Entity\EntityInterface {';
        $L[] = '    $entityData = $row[\array_key_first($row)];';

        /** FragmentMap renames/removals. */
        if ($meta['fragmentMap'] !== []) {
            $L[] = '';
            $L[] = '    // FragmentMap renames/removals';
            foreach ($meta['fragmentMap'] as $old => $new) {
                $L[] = "    if (\\array_key_exists('" . $old . "', \$entityData)) {";
                if ($new !== null) {
                    $L[] = "        if (!\\array_key_exists('" . $new . "', \$entityData)) { \$entityData['" . $new . "'] = \$entityData['" . $old . "']; }";
                }
                $L[] = "        unset(\$entityData['" . $old . "']);";
                $L[] = '    }';
            }
        }

        /** Groups (nested entity hydration). */
        if ($meta['groups'] !== []) {
            $L[] = '';
            $L[] = '    // Groups (nested entity hydration)';
            foreach ($meta['groups'] as [$groupEntityClass, $groupType, $propName, $isSingle]) {
                $key = $groupType ?? $propName;
                $groupEntityType = self::entityTypeForClass($groupEntityClass) ?? $groupEntityClass;
                $L[] = "    if (\\array_key_exists('" . $key . "', \$entityData)) {";
                $L[] = "        \$items = \$entityData['" . $key . "'];";
                if ($isSingle) {
                    $L[] = "        \$entityData['" . $key . "'] = !empty(\$items) ? \\dom_orm_hydrator_call('" . $groupEntityType . "', '" . $groupEntityClass . "', \$items[0], \$enc, \$fallback) : null;";
                } else {
                    $L[] = "        \$entityData['" . $key . "'] = \\array_map(static fn (array \$i) => \\dom_orm_hydrator_call('" . $groupEntityType . "', '" . $groupEntityClass . "', \$i, \$enc, \$fallback), \$items);";
                }
                $L[] = '    }';
            }
        }

        /** Constructor arguments. */
        $L[] = '';
        $L[] = '    $constructorArgs = [];';
        foreach ($meta['ctorParams'] as $name => $info) {
            $L[] = "    if (isset(\$entityData['" . $name . "'])) {";
            $L[] = "        \$v = \$entityData['" . $name . "'];";
            $L[] = self::valueTransforms($name, $class, $info);
            $L[] = "        \$constructorArgs['" . $name . "'] = \$v;";
            $L[] = '    }';
        }

        $L[] = '    $ret = new \\' . $class . '(...$constructorArgs);';
        $L[] = "    \$ret->setId(\$entityData['@id']);";

        /** Setters (non-constructor fields). */
        if ($meta['setterFields'] !== []) {
            $L[] = '';
            $L[] = '    // Setters (non-constructor fields)';
            foreach ($meta['setterFields'] as $name => $info) {
                $method = 'set' . \ucfirst($name);
                $L[] = "    if (isset(\$entityData['" . $name . "'])) {";
                $L[] = "        \$v = \$entityData['" . $name . "'];";
                $L[] = self::valueTransforms($name, $class, $info);
                $L[] = "        if (\\method_exists(\$ret, '" . $method . "')) { \$ret->" . $method . '($v); }';
                $L[] = '    }';
            }
        }

        $L[] = '    return $ret;';
        $L[] = '}';
        $L[] = '';

        return \implode("\n", $L);
    }

    /**
     * Emit the decrypt / json-scalar / datetime / cast lines for one field.
     *
     * @param array{type: string|null, sensitive: bool, jsonScalar: bool, datetime: bool} $info
     */
    private static function valueTransforms(string $name, string $class, array $info): string
    {
        $out = '';
        if ($info['sensitive']) {
            $out .= "        if (\$enc !== null && \\is_string(\$v) && \$v !== '') { \$v = \$enc->decrypt(\$v); }\n";
        }
        if ($info['jsonScalar']) {
            $out .= "        if (\\is_string(\$v)) { \$v = \\dom_orm_hydrator_decode_json_scalar(\$v, '" . $name . "', '" . $class . "'); }\n";
        }
        if ($info['datetime']) {
            $out .= "        if (\\is_string(\$v)) { \$v = new \\DateTimeImmutable(\$v); }\n";
        }
        if (\in_array($info['type'], ['int', 'float', 'bool'], true)) {
            $out .= "        if (\\is_string(\$v)) { \$v = \\dom_orm_hydrator_cast(\$v, '" . $info['type'] . "'); }\n";
        }

        return $out;
    }

    private static function entityTypeFor(string $class): ?string
    {
        $rc = new \ReflectionClass($class);
        foreach ($rc->getAttributes(Item::class) as $attr) {
            return $attr->newInstance()->entityType;
        }

        return null;
    }

    private static function entityTypeForClass(string $class): ?string
    {
        if (!\class_exists($class)) {
            return null;
        }

        return self::entityTypeFor($class);
    }
}
