<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Dto;

/**
 * Resolved CASABLANCA credentials and settings for a single TYPO3 site.
 *
 * Created by ApiClientFactory by merging values from
 *   - the TYPO3 Site object's `config.yaml`, and
 *   - the backend configuration record (ConfigurationRepository).
 * API keys are stored encrypted in the backend module only.
 */
final class SiteConfigurationDto
{
    /** @var string */
    public $siteIdentifier;

    /** @var string */
    public $tenantId;

    /** @var string */
    public $urlFriendlyIbeContextId;

    /** @var string */
    public $apiKey;

    /** @var string */
    public $apiBaseUrl;

    /** @var string */
    public $ibeBaseUrl;

    /** @var string */
    public $defaultCulture;

    /** @var int */
    public $syncRangeDays;

    /** @var int */
    public $syncChunkDays;

    /** @var int */
    public $paginationTop;

    /** @var RoomOccupancyDto */
    public $defaultOccupancy;

    /**
     * The service segment inserted between the API host and the tenant id.
     *
     * @var string
     */
    public $servicePath;

    /** @var string One of IbeLinkStyle::* constants */
    public $ibeLinkStyle;

    public function __construct(
        string $siteIdentifier,
        string $tenantId,
        string $urlFriendlyIbeContextId,
        string $apiKey,
        string $apiBaseUrl,
        string $ibeBaseUrl,
        string $defaultCulture,
        int $syncRangeDays,
        int $syncChunkDays,
        int $paginationTop,
        RoomOccupancyDto $defaultOccupancy,
        string $servicePath = 'ibe',
        string $ibeLinkStyle = 'full_path'
    ) {
        $this->siteIdentifier = $siteIdentifier;
        $this->tenantId = $tenantId;
        $this->urlFriendlyIbeContextId = $urlFriendlyIbeContextId;
        $this->apiKey = $apiKey;
        $this->apiBaseUrl = $apiBaseUrl;
        $this->ibeBaseUrl = $ibeBaseUrl;
        $this->defaultCulture = $defaultCulture;
        $this->syncRangeDays = $syncRangeDays;
        $this->syncChunkDays = $syncChunkDays;
        $this->paginationTop = $paginationTop;
        $this->defaultOccupancy = $defaultOccupancy;
        $this->servicePath = $servicePath;
        $this->ibeLinkStyle = $ibeLinkStyle;
    }

    public function isComplete(): bool
    {
        return $this->tenantId !== ''
            && $this->urlFriendlyIbeContextId !== ''
            && $this->apiKey !== '';
    }

    public function getCacheTag(): string
    {
        return 'casablanca_ari_' . $this->siteIdentifier;
    }

    public function getCacheTagForRoom(string $roomTypeId): string
    {
        return $this->getCacheTag() . '_room_' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $roomTypeId);
    }
}
