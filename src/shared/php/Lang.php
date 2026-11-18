<?php
namespace Neocarts;

/**
 * The plugin's texts, one file per language in lang/<oc code>.php, shared by all three branches.
 * A language the plugin does not ship falls back to en-gb key by key. Adding a language = adding a file.
 */
final class Lang
{
    const FALLBACK = 'en-gb';

    private static $cache = [];

    public static function load(string $ocCode): array
    {
        $ocCode = strtolower($ocCode);
        if (!isset(self::$cache[$ocCode])) {
            $texts = self::file(self::FALLBACK);
            if ($ocCode !== self::FALLBACK) {
                $texts = array_merge($texts, self::file($ocCode));
            }
            self::$cache[$ocCode] = $texts;
        }

        return self::$cache[$ocCode];
    }

    /** @return string[] codes of the shipped languages */
    public static function available(): array
    {
        return array_map(function ($f) {
            return basename($f, '.php');
        }, glob(self::dir() . '*.php'));
    }

    private static function file(string $code): array
    {
        $file = self::dir() . basename($code) . '.php';

        return is_file($file) ? require $file : [];
    }

    private static function dir(): string
    {
        return dirname(__DIR__) . '/lang/';
    }
}
