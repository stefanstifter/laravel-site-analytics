<?php

declare(strict_types=1);

namespace Stifter\SiteAnalytics\GeoIp;

use Locale;

/**
 * Displays an ISO 3166-1 alpha-2 country code as a flag and a name in the app locale, e.g. "🇦🇹 Österreich".
 */
final class Country
{
    public static function label(?string $code): string
    {
        if ($code === null || preg_match('/^[A-Z]{2}$/', $code) !== 1) {
            return __('Unknown');
        }

        return self::flag($code).' '.self::name($code);
    }

    /**
     * The country's name in the app locale, or the code itself without the intl extension.
     */
    public static function name(string $code): string
    {
        if (! class_exists(Locale::class)) {
            return $code;
        }

        $name = Locale::getDisplayRegion('-'.$code, app()->getLocale());

        return $name === '' ? $code : $name;
    }

    /**
     * The flag emoji, made of the two regional indicator symbols for the code's letters.
     */
    public static function flag(string $code): string
    {
        return implode('', array_map(fn (string $letter): string => mb_chr(0x1F1E6 + ord($letter) - ord('A')), str_split($code)));
    }
}
