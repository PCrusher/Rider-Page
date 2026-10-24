<?php
declare(strict_types=1);

/** Time-based one-time passwords (RFC 6238), the 6-digit codes of authenticator apps. */
final class Totp
{
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(): string
    {
        return self::encode(random_bytes(20));
    }

    public static function encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::B32[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function decode(string $b32): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32) ?? '')) as $c) {
            $bits .= str_pad(decbin((int)strpos(self::B32, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int)bindec($byte));
            }
        }
        return $out;
    }

    /** The 6-digit code for one 30-second step. */
    public static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::decode($secret), true);
        $o = ord($hash[19]) & 0x0f;
        $v = ((ord($hash[$o]) & 0x7f) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
        return str_pad((string)($v % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Checks a code, allowing one step of clock difference either way. A step that was used before
     * (or an older one) is refused, so a code works only once.
     * @return ?int the accepted step, or null
     */
    public static function verify(string $secret, string $input, int $lastStep = 0, ?int $now = null): ?int
    {
        $input = preg_replace('/\D/', '', $input) ?? '';
        if (strlen($input) !== 6) {
            return null;
        }
        $current = intdiv($now ?? time(), 30);
        for ($d = -1; $d <= 1; $d++) {
            $step = $current + $d;
            if ($step > $lastStep && hash_equals(self::code($secret, $step), $input)) {
                return $step;
            }
        }
        return null;
    }

    /** The address that authenticator apps read from the QR code. */
    public static function uri(string $account, string $issuer, string $secret): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }
}
