<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\ViewHelper;

use Casablanca\CasablancaBooking\ViewHelper\LabelViewHelper;
use PHPUnit\Framework\TestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;

final class LabelViewHelperTest extends TestCase
{
    public function testOverrideTakesPrecedence(): void
    {
        $viewHelper = new LabelViewHelper();
        $viewHelper->setRenderingContext(new RenderingContext());
        $viewHelper->initializeArguments();
        $viewHelper->setArguments([
            'key' => 'widget.adults',
            'override' => 'Guests',
            'default' => 'Adults',
            'arguments' => [],
            'extensionName' => 'casablanca_booking',
        ]);

        self::assertSame('Guests', $viewHelper->render());
    }

    public function testDefaultFallbackWhenNoTranslation(): void
    {
        $viewHelper = new LabelViewHelper();
        $viewHelper->setRenderingContext(new RenderingContext());
        $viewHelper->initializeArguments();
        $viewHelper->setArguments([
            'key' => 'widget.nonexistent.key',
            'override' => '',
            'default' => 'Fallback label',
            'arguments' => [],
            'extensionName' => 'casablanca_booking',
        ]);

        self::assertSame('Fallback label', $viewHelper->render());
    }
}
