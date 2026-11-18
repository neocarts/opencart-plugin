<?php
namespace Neocarts;

/** X-Neocarts-Signature: t=<unix>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")> */
final class Signature
{
    const TOLERANCE = 300;

    public static function verify(string $header, string $body, string $secret, ?int $now = null): bool
    {
        if ($secret === '' || !preg_match('/^t=(\d+),v1=([0-9a-f]{64})$/', trim($header), $m)) {
            return false;
        }
        $now = $now === null ? time() : $now;
        if (abs($now - (int) $m[1]) > self::TOLERANCE) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $m[1] . '.' . $body, $secret), $m[2]);
    }

    public static function sign(string $body, string $secret, ?int $t = null): string
    {
        $t = $t === null ? time() : $t;

        return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $body, $secret);
    }
}
