<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Api\Client;

use Casablanca\CasablancaBooking\Domain\Dto\InventoryCacheDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Service\Api\CasablancaHttpClient;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Endpoint wrapper for GET /inventory-cache/{from}/{until}.
 */
final class InventoryCacheClient
{
    /** @var CasablancaHttpClient */
    private $http;

    /** @var SiteConfigurationDto */
    private $config;

    public function __construct(CasablancaHttpClient $http, SiteConfigurationDto $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * @return iterable<int, InventoryCacheDto>
     */
    public function streamRange(DateTimeInterface $from, DateTimeInterface $until): iterable
    {
        $chunkDays = max(1, $this->config->syncChunkDays);
        $cursor = $from instanceof DateTimeImmutable
            ? $from
            : new DateTimeImmutable($from->format('Y-m-d'));
        $end = $until instanceof DateTimeImmutable
            ? $until
            : new DateTimeImmutable($until->format('Y-m-d'));

        while ($cursor <= $end) {
            $chunkEnd = $cursor->modify('+' . ($chunkDays - 1) . ' days');
            if ($chunkEnd > $end) {
                $chunkEnd = $end;
            }

            $path = sprintf(
                '/inventory-cache/%s/%s',
                $cursor->format('Y-m-d'),
                $chunkEnd->format('Y-m-d')
            );

            foreach ($this->http->paginate($path) as $page) {
                foreach ($page as $item) {
                    yield InventoryCacheDto::fromArray((array)$item);
                }
            }

            $cursor = $chunkEnd->modify('+1 day');
        }
    }
}
