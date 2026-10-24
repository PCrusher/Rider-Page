<?php
declare(strict_types=1);

/** Encrypts small secrets (such as the two-factor key) with a key that lives in storage/config.php, not in the database. */
final class Crypto
{
    private static function key(): string
    {
        return hash('sha256', 'rider-page-secrets|' . (string)((rp_config()['secret'] ?? '') ?: 'rider-page'), true);
    }

    public static function seal(string $plain): string
    {
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
        }
        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
            return 'v2:' . base64_encode($iv . $tag . (string)$cipher);
        }
        return 'v0:' . base64_encode($plain);
    }

    /** @return ?string null when the text was changed or the key is not the same */
    public static function open(string $sealed): ?string
    {
        $v = substr($sealed, 0, 3);
        $raw = base64_decode(substr($sealed, 3), true);
        if ($raw === false) {
            return null;
        }
        if ($v === 'v1:' && function_exists('sodium_crypto_secretbox_open') && strlen($raw) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key());
            return $plain === false ? null : $plain;
        }
        if ($v === 'v2:' && function_exists('openssl_decrypt') && strlen($raw) > 28) {
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
            return $plain === false ? null : $plain;
        }
        return $v === 'v0:' ? $raw : null;
    }
}
