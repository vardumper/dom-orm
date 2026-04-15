<?php

declare(strict_types=1);

namespace DOM\ORM\Encryption;

use function DOM\ORM\getConfig;

/**
 * AES-256-GCM field-level encryption service.
 *
 * Derives two sub-keys from the master key via HKDF so that the encryption key
 * and the search-hash key are always different:
 *   - encryption sub-key : hash_hkdf('sha256', $masterKey, 32, 'dom-orm-encrypt')
 *   - search sub-key     : hash_hkdf('sha256', $masterKey, 32, 'dom-orm-search')
 *
 * Wire format of the stored blob: base64( iv[12] || tag[16] || ciphertext )
 */
final class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private string $encryptKey;
    private string $searchKey;

    public function __construct(string $masterKey)
    {
        $this->encryptKey = \hash_hkdf('sha256', $masterKey, 32, 'dom-orm-encrypt');
        $this->searchKey = \hash_hkdf('sha256', $masterKey, 32, 'dom-orm-search');
    }

    public static function fromConfig(): self
    {
        $config = getConfig();
        $key = $config->get('dom-orm.encryption_key');

        if (!\is_string($key) || $key === '') {
            throw new \RuntimeException(
                'dom-orm.encryption_key must be set in config to use #[Sensitive] encryption.'
            );
        }

        return new self($key);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = \random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = \openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->encryptKey,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed: ' . \openssl_error_string());
        }

        return \base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        $raw = \base64_decode($encoded, strict: true);

        if ($raw === false) {
            throw new \RuntimeException('Decryption failed: invalid base64 encoding.');
        }

        $minLength = self::IV_LENGTH + self::TAG_LENGTH;

        if (\strlen($raw) <= $minLength) {
            throw new \RuntimeException('Decryption failed: payload too short.');
        }

        $iv = \substr($raw, 0, self::IV_LENGTH);
        $tag = \substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = \substr($raw, $minLength);

        $plaintext = \openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->encryptKey,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed: authentication tag mismatch or corrupt data.');
        }

        return $plaintext;
    }

    /**
     * Returns the HMAC-SHA256 hex digest of the plaintext.
     * Equal plaintexts always produce the same hash regardless of the random IV.
     */
    public function searchHash(string $plaintext): string
    {
        return \hash_hmac('sha256', $plaintext, $this->searchKey);
    }
}
