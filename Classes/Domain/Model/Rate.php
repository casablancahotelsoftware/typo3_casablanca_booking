<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Cached representation of a CASABLANCA Rate or package.
 */
final class Rate extends AbstractEntity
{
    /** @var string */
    protected $siteIdentifier = '';

    /** @var string */
    protected $tenantId = '';

    /** @var string */
    protected $ibeContextId = '';

    /** @var string */
    protected $rateId = '';

    /** @var string */
    protected $name = '';

    /** @var string */
    protected $slug = '';

    /** @var string */
    protected $description = '';

    /** @var string */
    protected $shortDescription = '';

    /** @var string */
    protected $imageUrl = '';

    /** @var array<int, array<string, mixed>> */
    protected $images = [];

    /** @var bool */
    protected $isPackage = false;

    /** @var string */
    protected $cateringType = '';

    /** @var int */
    protected $sortOrder = 0;

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

    public function getRateId(): string
    {
        return $this->rateId;
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

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function getImageUrl(): string
    {
        return $this->imageUrl;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getImages(): array
    {
        return $this->images;
    }

    public function isPackage(): bool
    {
        return $this->isPackage;
    }

    public function getCateringType(): string
    {
        return $this->cateringType;
    }

    /**
     * Human-readable catering label for frontend display, or empty when unset.
     */
    public function getFormattedCateringType(): string
    {
        $cateringType = trim($this->cateringType);
        if ($cateringType === '' || strcasecmp($cateringType, 'Undefined') === 0) {
            return '';
        }

        return self::formatCateringTypeLabel($cateringType);
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * Human-readable mapping type for the backend (legacy widget parity).
     */
    public function getDisplayType(): string
    {
        if ($this->isPackage) {
            return 'Package';
        }

        $formatted = $this->getFormattedCateringType();
        if ($formatted === '') {
            return 'Day Rate';
        }

        return $formatted;
    }

    private static function formatCateringTypeLabel(string $cateringType): string
    {
        static $labels = [
            'AllInclusive' => 'All Inclusive',
            'American' => 'American',
            'BedAndBreakfast' => 'Bed and Breakfast',
            'BuffetBreakfast' => 'Buffet Breakfast',
            'CaribbeanBreakfast' => 'Caribbean Breakfast',
            'ContinentalBreakfast' => 'Continental Breakfast',
            'EnglishBreakfast' => 'English Breakfast',
            'EuropeanPlan' => 'European Plan',
            'FamilyPlan' => 'Family Plan',
            'FullBoard' => 'Full Board',
            'FullBreakfast' => 'Full Breakfast',
            'Halfboard_modifiedAmericanPlan' => 'Halfboard',
            'AsBrochured' => 'As Brochured',
            'RoomOnly' => 'Room only',
            'SelfCatering' => 'Self Catering',
            'Bermuda' => 'Bermuda',
            'DinnerBedAndBreakfastPlan' => 'Dinner, Bed and Breakfast',
            'FamilyAmerican' => 'Family American',
            'Breakfast' => 'Breakfast',
            'Modified' => 'Modified',
            'Lunch' => 'Lunch',
            'Dinner' => 'Dinner',
            'BreakfastLunch' => 'Breakfast and Lunch',
        ];

        if (isset($labels[$cateringType])) {
            return $labels[$cateringType];
        }

        return preg_replace('/(?<!^)([A-Z])/', ' $1', $cateringType) ?? $cateringType;
    }
}
