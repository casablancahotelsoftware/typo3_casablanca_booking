<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Utility;

/**
 * Resolves overview description text from API payloads and stored entities.
 */
final class DescriptionPresenter
{
    /**
     * @param array<string, mixed> $data
     */
    public static function extractShortDescriptionFromApi(array $data): string
    {
        foreach (['shortDescription', 'shortText', 'teaser'] as $key) {
            $value = trim((string)($data[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public static function resolveOverviewText(
        string $shortDescription,
        string $fullDescription,
        string $mode
    ): string {
        if ($mode === 'teaser') {
            return $shortDescription !== '' ? $shortDescription : $fullDescription;
        }

        return $fullDescription;
    }

    /**
     * @return array{
     *     mode: string,
     *     html: string,
     *     preview: string,
     *     needsExpand: bool
     * }
     */
    public static function buildOverviewPresentation(
        string $shortDescription,
        string $fullDescription,
        string $mode,
        int $limit
    ): array {
        if ($mode === 'teaser') {
            $html = self::resolveOverviewText($shortDescription, $fullDescription, 'teaser');
            if ($html === '') {
                return [
                    'mode' => 'teaser',
                    'html' => '',
                    'preview' => '',
                    'needsExpand' => false,
                ];
            }

            return [
                'mode' => 'teaser',
                'html' => $html,
                'preview' => '',
                'needsExpand' => false,
            ];
        }

        if ($fullDescription === '') {
            return [
                'mode' => 'full',
                'html' => '',
                'preview' => '',
                'needsExpand' => false,
            ];
        }

        $truncated = self::truncatePlainText($fullDescription, $limit);

        return [
            'mode' => 'full',
            'html' => $fullDescription,
            'preview' => $truncated['preview'],
            'needsExpand' => $truncated['needsExpand'],
        ];
    }

    public static function truncatePlainText(string $html, int $limit): array
    {
        $plain = trim(strip_tags($html));
        if ($plain === '' || mb_strlen($plain) <= $limit) {
            return [
                'preview' => $plain,
                'needsExpand' => false,
            ];
        }

        return [
            'preview' => rtrim(mb_substr($plain, 0, $limit)) . '…',
            'needsExpand' => true,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $images
     * @return array<int, array{url: string, sort: int}>
     */
    public static function normalizeCarouselImages(array $images, string $fallbackImageUrl = ''): array
    {
        $normalized = [];
        foreach ($images as $image) {
            if (!is_array($image)) {
                continue;
            }
            $url = trim((string)($image['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $normalized[] = [
                'url' => $url,
                'sort' => (int)($image['sort'] ?? 0),
            ];
        }

        usort(
            $normalized,
            static function (array $a, array $b): int {
                return $a['sort'] <=> $b['sort'];
            }
        );

        if ($normalized === [] && $fallbackImageUrl !== '') {
            return [['url' => $fallbackImageUrl, 'sort' => 0]];
        }

        return $normalized;
    }
}
