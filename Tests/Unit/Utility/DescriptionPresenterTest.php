<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Utility;

use Casablanca\CasablancaBooking\Utility\DescriptionPresenter;
use PHPUnit\Framework\TestCase;

final class DescriptionPresenterTest extends TestCase
{
    public function testExtractShortDescriptionPrefersKnownKeys(): void
    {
        self::assertSame(
            'Short teaser',
            DescriptionPresenter::extractShortDescriptionFromApi([
                'shortText' => 'ignored',
                'shortDescription' => 'Short teaser',
            ])
        );
    }

    public function testBuildOverviewPresentationUsesTeaserFallback(): void
    {
        $result = DescriptionPresenter::buildOverviewPresentation('', '<p>Full text</p>', 'teaser', 250);

        self::assertSame('teaser', $result['mode']);
        self::assertSame('<p>Full text</p>', $result['html']);
        self::assertFalse($result['needsExpand']);
    }

    public function testBuildOverviewPresentationTruncatesFullMode(): void
    {
        $full = str_repeat('A', 300);
        $result = DescriptionPresenter::buildOverviewPresentation('', $full, 'full', 250);

        self::assertTrue($result['needsExpand']);
        self::assertStringEndsWith('…', $result['preview']);
    }

    public function testNormalizeCarouselImagesFallsBackToPrimaryImage(): void
    {
        $images = DescriptionPresenter::normalizeCarouselImages([], 'https://example.test/room.jpg');

        self::assertSame([
            ['url' => 'https://example.test/room.jpg', 'sort' => 0],
        ], $images);
    }
}
