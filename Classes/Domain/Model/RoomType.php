<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Cached representation of a CASABLANCA RoomType.
 *
 * Populated by the same sync command that writes Availability rows — kept in
 * its own table because (a) the backend FlexForm dropdown needs it even when
 * no availability has been synced yet, and (b) rate/room metadata changes at
 * a very different frequency than ARI data.
 */
final class RoomType extends AbstractEntity
{
    protected string $siteIdentifier = '';
    protected string $tenantId = '';
    protected string $ibeContextId = '';

    /** The UUID-ish identifier assigned by CASABLANCA. */
    protected string $roomTypeId = '';

    protected string $companyId = '';
    protected string $name = '';
    protected string $slug = '';
    protected string $description = '';
    protected string $imageUrl = '';

    /** @var array<int, array<string, mixed>> */
    protected array $images = [];

    protected int $standardOccupancy = 2;
    protected int $minOccupancy = 1;
    protected int $maxOccupancy = 4;
    protected int $sortOrder = 0;

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

    public function getCompanyId(): string
    {
        return $this->companyId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    /** @return array<int, array<string, mixed>> */
    public function getImages(): array
    {
        return $this->images;
    }

    public function getStandardOccupancy(): int
    {
        return $this->standardOccupancy;
    }

    public function getMinOccupancy(): int
    {
        return $this->minOccupancy;
    }

    public function getMaxOccupancy(): int
    {
        return $this->maxOccupancy;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }
}
