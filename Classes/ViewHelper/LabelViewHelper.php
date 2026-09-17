<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\ViewHelper;

use Casablanca\CasablancaBooking\Service\Frontend\LabelResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders a frontend label with optional FlexForm override.
 *
 * Usage: {casablanca:label(key: 'widget.adults', override: labels.adults, default: 'Adults')}
 */
class LabelViewHelper extends AbstractViewHelper
{
    /** @var bool */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('key', 'string', 'locallang key (without LLL prefix)', true);
        $this->registerArgument('override', 'string', 'Editor override from FlexForm Texts sheet', false, '');
        $this->registerArgument('default', 'string', 'Fallback if translation is missing', false, '');
        $this->registerArgument('arguments', 'array', 'Replacement values for sprintf placeholders', false, []);
    }

    public function render(): string
    {
        /** @var LabelResolver $resolver */
        $resolver = GeneralUtility::makeInstance(LabelResolver::class);

        $arguments = is_array($this->arguments['arguments']) ? $this->arguments['arguments'] : [];

        return $resolver->resolve(
            (string)$this->arguments['key'],
            (string)$this->arguments['default'],
            (string)$this->arguments['override'],
            $arguments
        );
    }
}
