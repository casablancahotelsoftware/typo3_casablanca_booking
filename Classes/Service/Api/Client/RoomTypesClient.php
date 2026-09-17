<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Api\Client;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Dto\RoomTypeDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Service\Api\CasablancaHttpClient;

/**
 * Endpoint wrapper for GET /room-types.
 */
final class RoomTypesClient
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
     * @return RoomTypeDto[]
     */
    public function fetchAll(): array
    {
        $response = $this->http->getJson('/room-types', [
            'culture' => $this->config->defaultCulture,
        ]);

        $items = Typo3Adapter::isArrayList($response)
            ? $response
            : (array)($response['values'] ?? []);

        $result = [];
        foreach ($items as $item) {
            $result[] = RoomTypeDto::fromArray((array)$item);
        }

        return $result;
    }
}
