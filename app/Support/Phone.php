<?php

namespace App\Support;

/**
 * Mobile numbers in international (E.164) format, e.g. +919800000001.
 */
final class Phone
{
    /** The number in E.164 form, or null when it isn't one. Spaces, dashes, dots and brackets are ignored. */
    public static function normalize(?string $phone): ?string
    {
        $phone = preg_replace('/[\s\-.()]/', '', (string) $phone);

        return preg_match('/^\+[1-9][0-9]{6,14}$/', $phone) ? $phone : null;
    }

    /** For display without giving the whole number away, e.g. +91•••••••001. */
    public static function mask(string $phone): string
    {
        return substr($phone, 0, 3).str_repeat('•', max(0, strlen($phone) - 6)).substr($phone, -3);
    }
}
