<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Domain;

use Casablanca\CasablancaBooking\Domain\IbeLinkStyle;
use PHPUnit\Framework\TestCase;

final class IbeLinkStyleTest extends TestCase
{
    public function testNormalizeMapsLegacyTenantOnlyToCultureSpace(): void
    {
        self::assertSame(
            IbeLinkStyle::CULTURE_SPACE,
            IbeLinkStyle::normalize(IbeLinkStyle::LEGACY_TENANT_ONLY)
        );
    }

    public function testNormalizeFallsBackToFullPathForUnknownValue(): void
    {
        self::assertSame(
            IbeLinkStyle::FULL_PATH,
            IbeLinkStyle::normalize('invalid')
        );
    }
}
