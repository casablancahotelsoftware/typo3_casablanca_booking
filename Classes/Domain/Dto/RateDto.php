<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Dto;

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
    public $imageUrl;

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
        string $imageUrl,
        bool $isPackage,
        string $cateringType,
        int $sort
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->imageUrl = $imageUrl;
        $this->isPackage = $isPackage;
        $this->cateringType = $cateringType;
        $this->sort = $sort;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['id'] ?? ''),
            (string)($data['name'] ?? ''),
            (string)($data['description'] ?? ''),
            (string)($data['imageUrl'] ?? ''),
            (bool)($data['isPackage'] ?? false),
            (string)($data['cateringType'] ?? ''),
            (int)($data['sort'] ?? 0)
        );
    }
}
