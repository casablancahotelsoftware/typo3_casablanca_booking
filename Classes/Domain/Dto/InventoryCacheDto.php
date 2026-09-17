<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Dto;

use DateTimeImmutable;

/**
 * DTO mirroring the CASABLANCA `InventoryCacheResponse` schema.
 */
final class InventoryCacheDto
{
    /** @var DateTimeImmutable */
    public $effectiveDate;

    /** @var string */
    public $roomTypeId;

    /** @var int|null */
    public $definitiveAvailable;

    public function __construct(
        DateTimeImmutable $effectiveDate,
        string $roomTypeId,
        ?int $definitiveAvailable
    ) {
        $this->effectiveDate = $effectiveDate;
        $this->roomTypeId = $roomTypeId;
        $this->definitiveAvailable = $definitiveAvailable;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            new DateTimeImmutable((string)($data['effectiveDate'] ?? 'now')),
            (string)($data['roomTypeId'] ?? ''),
            isset($data['definitiveAvailable']) ? (int)$data['definitiveAvailable'] : null
        );
    }
}
