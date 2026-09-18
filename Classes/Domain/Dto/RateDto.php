<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Dto;

use Casablanca\CasablancaBooking\Utility\DescriptionPresenter;

/**
 * DTO mirroring the CASABLANCA `Rate` schema.
 */
final class RateDto
{
    /** @var string */
    public $id;

    /** @var string */
    public $name;

    /** @var string */
    public $description;

    /** @var string */
    public $shortDescription;

    /** @var string */
    public $imageUrl;

    /**
     * @var array<int, array<string, mixed>>
     */
    public $images;

    /** @var bool */
    public $isPackage;

    /** @var string */
    public $cateringType;

    /** @var int */
    public $sort;

    public function __construct(
        string $id,
        string $name,
        string $description,
        string $shortDescription,
        string $imageUrl,
        array $images,
        bool $isPackage,
        string $cateringType,
        int $sort
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->shortDescription = $shortDescription;
        $this->imageUrl = $imageUrl;
        $this->images = $images;
        $this->isPackage = $isPackage;
        $this->cateringType = $cateringType;
        $this->sort = $sort;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $images = isset($data['images']) && is_array($data['images'])
            ? $data['images']
            : [];

        return new self(
            (string)($data['id'] ?? ''),
            (string)($data['name'] ?? ''),
            (string)($data['description'] ?? ''),
            DescriptionPresenter::extractShortDescriptionFromApi($data),
            (string)($data['imageUrl'] ?? ''),
            $images,
            (bool)($data['isPackage'] ?? false),
            (string)($data['cateringType'] ?? ''),
            (int)($data['sort'] ?? 0)
        );
    }
}
