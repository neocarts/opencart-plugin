<?php
namespace Neocarts;

/** OpenCart language code (uk-ua, en-gb, …) → a locale Neocarts speaks. */
final class Locale
{
    /** Add a locale here when Neocarts supports it. */
    const SUPPORTED = ['uk', 'en'];
    const FALLBACK = 'en';

    /**
     * @param array $map merchant overrides from the admin: ['ru-ru' => 'uk', …]
     */
    public static function resolve(string $ocCode, array $map = []): string
    {
        $ocCode = strtolower($ocCode);
        if (isset($map[$ocCode]) && in_array($map[$ocCode], self::SUPPORTED, true)) {
            return $map[$ocCode];
        }
        $prefix = substr($ocCode, 0, 2);
        if ($prefix === 'ua') {
            return 'uk';
        }

        return in_array($prefix, self::SUPPORTED, true) ? $prefix : self::FALLBACK;
    }
}
