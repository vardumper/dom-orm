<?php

declare(strict_types=1);

namespace DOM\ORM\Encryption;

/**
 * Carries an AES-256-GCM ciphertext together with its deterministic HMAC-SHA256
 * search hash so the encoder can persist both in a single <fragment> element.
 */
final class EncryptedValue
{
    public function __construct(
        /**
         * Base64-encoded iv(12) + tag(16) + ciphertext
         */
        public readonly string $ciphertext,
        /**
         * HMAC-SHA256 hex of the original plaintext, used for XPath searches
         */
        public readonly string $searchHash,
    ) {
    }
}
