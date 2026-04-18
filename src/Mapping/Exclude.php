<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

/**
 * Marks a Fragment property as excluded from all exports (JSON, YAML, PHP, XML).
 * The field is still persisted to the XML store; it is only omitted when exporting.
 *
 * Useful for internal or derived fields that should not appear in headless/API exports.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Exclude
{
}
