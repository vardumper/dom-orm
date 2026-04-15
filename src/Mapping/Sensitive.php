<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

/**
 * Marks a Fragment property as sensitive. The persister will encrypt the value
 * at rest using AES-256-GCM and decrypt it on hydration.
 *
 * Requires dom-orm.encryption_key to be set in the config file.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Sensitive
{
}
