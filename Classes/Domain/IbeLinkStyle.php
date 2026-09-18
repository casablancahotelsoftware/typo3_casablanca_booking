<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain;

/**
 * IBE frontend handover URL path styles (API URLs are unaffected).
 */
final class IbeLinkStyle
{
    public const FULL_PATH = 'full_path';
    public const CULTURE_SPACE = 'culture_space';
    public const CULTURE_ONLY = 'culture_only';

    /** @deprecated Legacy value; normalized to CULTURE_SPACE */
    public const LEGACY_TENANT_ONLY = 'tenant_only';

    /**
     * @return string[]
     */
    public static function getAll(): array
    {
        return [
            self::FULL_PATH,
            self::CULTURE_SPACE,
            self::CULTURE_ONLY,
        ];
    }

    public static function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === self::LEGACY_TENANT_ONLY) {
            return self::CULTURE_SPACE;
        }

        if (in_array($value, self::getAll(), true)) {
            return $value;
        }

        return self::FULL_PATH;
    }
}
