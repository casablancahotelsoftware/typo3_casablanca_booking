<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Frontend;

/**
 * Lists default --cb-* design tokens from the bundled widget stylesheet.
 */
final class ThemeTokenRegistry
{
    /**
     * @return array<string, string> token name => default value
     */
    public static function getDefaultTokens(): array
    {
        static $tokens = null;
        if ($tokens !== null) {
            return $tokens;
        }

        $cssPath = dirname(__DIR__, 3) . '/Resources/Public/Css/widget.css';
        if (!is_readable($cssPath)) {
            return $tokens = [];
        }

        $content = (string)file_get_contents($cssPath);
        if (!preg_match('/\.cb-widget\s*\{([^}]+)\}/s', $content, $match)) {
            return $tokens = [];
        }

        $tokens = [];
        if (preg_match_all('/--cb-[a-z0-9-]+\s*:\s*[^;]+;/', $match[1], $declarations)) {
            foreach ($declarations[0] as $declaration) {
                if (preg_match('/(--cb-[a-z0-9-]+)\s*:\s*([^;]+);/', $declaration, $parts)) {
                    $tokens[trim($parts[1])] = trim($parts[2]);
                }
            }
        }

        return $tokens;
    }
}
