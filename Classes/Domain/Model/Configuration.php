<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Backend-stored CASABLANCA site configuration record.
 */
final class Configuration extends AbstractEntity
{
    /** @var string */
    protected $siteIdentifier = '';

    /** @var string */
    protected $tenantId = '';

    /** @var string */
    protected $urlFriendlyIbeContextId = 'bookingengine';

    /** @var string */
    protected $apiKeyEncrypted = '';

    /** @var string */
    protected $apiBaseUrl = 'https://api.casablanca.at';

    /** @var string */
    protected $ibeBaseUrl = 'https://bookingengine.casablanca.at';

    /** @var bool */
    protected $useCustomIbeDomain = false;

    /** @var string */
    protected $ibeLinkStyle = 'full_path';

    /** @var string */
    protected $servicePath = 'ibe';

    /** @var string */
    protected $defaultCulture = 'de';

    /** @var int */
    protected $syncRangeDays = 365;

    /** @var int */
    protected $syncChunkDays = 31;

    /** @var int */
    protected $paginationTop = 100;

    /** @var int */
    protected $defaultAdults = 2;

    /** @var int[] */
    protected $defaultChildrenAges = [];

    /** @var string unknown|ok|error */
    protected $connectionStatus = 'unknown';

    /** @var int */
    protected $connectionCheckedAt = 0;

    /** @var string */
    protected $connectionMessage = '';

    public function getSiteIdentifier(): string
    {
        return $this->siteIdentifier;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getUrlFriendlyIbeContextId(): string
    {
        return $this->urlFriendlyIbeContextId;
    }

    public function getApiKeyEncrypted(): string
    {
        return $this->apiKeyEncrypted;
    }

    public function getApiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    public function getIbeBaseUrl(): string
    {
        return $this->ibeBaseUrl;
    }

    public function isUseCustomIbeDomain(): bool
    {
        return $this->useCustomIbeDomain;
    }

    public function getIbeLinkStyle(): string
    {
        return $this->ibeLinkStyle;
    }

    public function setIbeLinkStyle(string $ibeLinkStyle): void
    {
        $this->ibeLinkStyle = $ibeLinkStyle;
    }

    public function getServicePath(): string
    {
        return $this->servicePath;
    }

    public function getDefaultCulture(): string
    {
        return $this->defaultCulture;
    }

    public function getSyncRangeDays(): int
    {
        return $this->syncRangeDays;
    }

    public function getSyncChunkDays(): int
    {
        return $this->syncChunkDays;
    }

    public function getPaginationTop(): int
    {
        return $this->paginationTop;
    }

    public function getDefaultAdults(): int
    {
        return $this->defaultAdults;
    }

    /** @return int[] */
    public function getDefaultChildrenAges(): array
    {
        return $this->defaultChildrenAges;
    }

    public function getConnectionStatus(): string
    {
        return $this->connectionStatus;
    }

    public function isConnectionOk(): bool
    {
        return $this->connectionStatus === 'ok';
    }

    public function getConnectionCheckedAt(): int
    {
        return $this->connectionCheckedAt;
    }

    public function getConnectionMessage(): string
    {
        return $this->connectionMessage;
    }
}
