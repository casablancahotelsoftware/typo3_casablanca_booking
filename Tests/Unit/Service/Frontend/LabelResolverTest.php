<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Service\Frontend;

use Casablanca\CasablancaBooking\Service\Frontend\LabelResolver;
use PHPUnit\Framework\TestCase;

final class LabelResolverTest extends TestCase
{
    public function testOverrideTakesPrecedence(): void
    {
        $resolver = new LabelResolver();

        self::assertSame(
            'Custom heading',
            $resolver->resolve('widget.heading.default', 'Check availability and book', 'Custom heading')
        );
    }

    public function testDefaultFallbackWhenNoTranslation(): void
    {
        $resolver = new LabelResolver();

        self::assertSame(
            'Fallback label',
            $resolver->resolve('widget.unit.test.nonexistent', 'Fallback label')
        );
    }

    public function testBuildJavaScriptLabelsIncludesOccupancyKeys(): void
    {
        $resolver = new LabelResolver();
        $labels = $resolver->buildJavaScriptLabels(['bookNow' => 'Reserve']);

        self::assertArrayHasKey('adults', $labels);
        self::assertArrayHasKey('children', $labels);
        self::assertSame('Reserve', $labels['calendarBookNow']);
    }
}
