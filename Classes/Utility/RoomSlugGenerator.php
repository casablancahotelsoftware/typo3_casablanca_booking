<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Utility;

/**
 * Generates URL-safe slugs from room type names.
 */
final class RoomSlugGenerator
{
    public function generate(string $name): string
    {
        $slug = strtolower(trim($name));
        if ($slug === '') {
            return 'room';
        }

        if (function_exists('iconv')) {
            $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
            if ($transliterated !== false) {
                $slug = strtolower($transliterated);
            }
        }

        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'room';
    }
}
