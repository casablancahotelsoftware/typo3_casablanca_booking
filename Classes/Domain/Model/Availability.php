<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Model;

use DateTimeImmutable;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Normalised, per-day availability for a single RoomType at a single site.
 *
 * This is the flat shape the frontend controller reads — the API's nested
 * CalendarDate + InventoryCache payloads are reduced to these fields by
 * the sync command. One row per (site, roomType, date).
 *
 * Note: this entity intentionally has no `pid` / `uid` fields in the Extbase
 * sense — it uses a composite primary key (site_identifier, room_type_id,
 * effective_date) and is managed as a read-through cache of the upstream API.
 * We still extend AbstractEntity so Fluid can iterate and query it.
 */
final class Availability extends AbstractEntity
{
    protected string $siteIdentifier = '';
    protected string $tenantId = '';
    protected string $ibeContextId = '';
    protected string $roomTypeId = '';
    protected ?DateTimeImmutable $effectiveDate = null;
    protected ?float $fromPrice = null;
    protected string $currency = 'EUR';
    protected bool $isAvailable = false;
    protected bool $isArrivalAllowed = false;
    protected bool $isDepartureAllowed = false;
    protected int $minLengthOfStay = 0;
    protected int $maxLengthOfStay = 0;

    /** @var int[] */
    protected array $bookableNights = [];

    /** @var int[] */
    protected array $bookableNightsWithPackages = [];

    /** @var string[] Human-readable restriction flags (e.g. ['Restriktionen']). */
    protected array $restrictions = [];

    protected bool $previousDayBlocked = false;
    protected bool $nextDayBlocked = false;

    public function getSiteIdentifier(): string
    {
        return $this->siteIdentifier;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getIbeContextId(): string
    {
        return $this->ibeContextId;
    }

    public function getRoomTypeId(): string
    {
        return $this->roomTypeId;
    }

    public function getEffectiveDate(): ?DateTimeImmutable
    {
        return $this->effectiveDate;
    }

    public function getFromPrice(): ?float
    {
        return $this->fromPrice;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function isArrivalAllowed(): bool
    {
        return $this->isArrivalAllowed;
    }

    public function isDepartureAllowed(): bool
    {
        return $this->isDepartureAllowed;
    }

    public function getMinLengthOfStay(): int
    {
        return $this->minLengthOfStay;
    }

    public function getMaxLengthOfStay(): int
    {
        return $this->maxLengthOfStay;
    }

    /** @return int[] */
    public function getBookableNights(): array
    {
        return $this->bookableNights;
    }

    /** @return int[] */
    public function getBookableNightsWithPackages(): array
    {
        return $this->bookableNightsWithPackages;
    }

    /** @return string[] */
    public function getRestrictions(): array
    {
        return $this->restrictions;
    }

    public function hasRestrictions(): bool
    {
        return $this->restrictions !== [];
    }

    public function isPreviousDayBlocked(): bool
    {
        return $this->previousDayBlocked;
    }

    public function isNextDayBlocked(): bool
    {
        return $this->nextDayBlocked;
    }

    /**
     * Derives the CSS availability-state class used by the calendar template.
     * The names match the public-facing CSS variables in widget.css.
     */
    public function getCalendarStateClass(): string
    {
        if (!$this->isAvailable) {
            return 'cb-state-unavailable';
        }
        if ($this->restrictions !== []) {
            return 'cb-state-restricted';
        }
        if (!$this->isArrivalAllowed) {
            return 'cb-state-no-arrival';
        }
        return 'cb-state-available';
    }
}
