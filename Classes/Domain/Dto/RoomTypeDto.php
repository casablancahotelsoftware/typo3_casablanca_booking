<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Dto;

/**
 * DTO mirroring the CASABLANCA `RoomType` schema.
 */
final class RoomTypeDto
{
    /** @var string */
    public $id;

    /** @var string */
    public $name;

    /** @var string */
    public $description;

    /** @var string */
    public $imageUrl;

    /**
     * List of image objects from the API, each with url and sort keys.
     *
     * @var array<int, array<string, mixed>>
     */
    public $images;

    /** @var string */
    public $companyId;

    /** @var int */
    public $sort;

    /** @var int */
    public $standardOccupancy;

    /** @var int */
    public $minOccupancy;

    /** @var int */
    public $maxOccupancy;

    /**
     * @param array<int, array<string, mixed>> $images
     */
    public function __construct(
        string $id,
        string $name,
        string $description,
        string $imageUrl,
        array $images,
        string $companyId,
        int $sort,
        int $standardOccupancy,
        int $minOccupancy,
        int $maxOccupancy
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->imageUrl = $imageUrl;
        $this->images = $images;
        $this->companyId = $companyId;
        $this->sort = $sort;
        $this->standardOccupancy = $standardOccupancy;
        $this->minOccupancy = $minOccupancy;
        $this->maxOccupancy = $maxOccupancy;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $occupancy = isset($data['occupancy']) && is_array($data['occupancy'])
            ? $data['occupancy']
            : [];
        $images = isset($data['images']) && is_array($data['images'])
            ? $data['images']
            : [];

        return new self(
            (string)($data['id'] ?? ''),
            (string)($data['name'] ?? ''),
            (string)($data['description'] ?? ''),
            (string)($data['imageUrl'] ?? ''),
            $images,
            (string)($data['companyId'] ?? ''),
            (int)($data['sort'] ?? 0),
            (int)($occupancy['standardOccupancy'] ?? 2),
            (int)($occupancy['minOccupancy'] ?? 1),
            (int)($occupancy['maxOccupancy'] ?? 4)
        );
    }
}
