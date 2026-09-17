<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Api\Client;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Dto\RateDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Service\Api\CasablancaHttpClient;

/**
 * Endpoint wrapper for GET /rates.
 */
final class RatesClient
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
     * @param string[] $rateIds
     * @return RateDto[]
     */
    public function fetchAll(array $rateIds = []): array
    {
        $query = [
            'culture' => $this->config->defaultCulture,
        ];
        if ($rateIds !== []) {
            $query['rateIds'] = implode(',', $rateIds);
        }

        $response = $this->http->getJson('/rates', $query);

        $items = Typo3Adapter::isArrayList($response)
            ? $response
            : (array)($response['values'] ?? []);

        $result = [];
        foreach ($items as $item) {
            $result[] = RateDto::fromArray((array)$item);
        }

        return $result;
    }
}
