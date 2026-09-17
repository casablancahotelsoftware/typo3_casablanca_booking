<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Dto;

use DateTimeImmutable;

/**
 * DTO mirroring the CASABLANCA `CalendarDate` response schema.
 */
final class CalendarDateDto
{
    /** @var DateTimeImmutable */
    public $effectiveDate;

    /** @var float|null */
    public $fromPrice;

    /** @var bool */
    public $isAvailable;

    /** @var bool */
    public $isArrivalAllowed;

    /** @var bool */
    public $isDepartureAllowed;

    /** @var bool */
    public $isPreviousDayBlocked;

    /** @var bool */
    public $isNextDayBlocked;

    /** @var int */
    public $minLengthOfStay;

    /** @var int */
    public $maxLengthOfStay;

    /** @var int[] */
    public $bookableNights;

    /** @var int[] */
    public $bookableNightsWithPackages;

    /**
     * @param int[] $bookableNights
     * @param int[] $bookableNightsWithPackages
     */
    public function __construct(
        DateTimeImmutable $effectiveDate,
        ?float $fromPrice,
        bool $isAvailable,
        bool $isArrivalAllowed,
        bool $isDepartureAllowed,
        bool $isPreviousDayBlocked,
        bool $isNextDayBlocked,
        int $minLengthOfStay,
        int $maxLengthOfStay,
        array $bookableNights,
        array $bookableNightsWithPackages
    ) {
        $this->effectiveDate = $effectiveDate;
        $this->fromPrice = $fromPrice;
        $this->isAvailable = $isAvailable;
        $this->isArrivalAllowed = $isArrivalAllowed;
        $this->isDepartureAllowed = $isDepartureAllowed;
        $this->isPreviousDayBlocked = $isPreviousDayBlocked;
        $this->isNextDayBlocked = $isNextDayBlocked;
        $this->minLengthOfStay = $minLengthOfStay;
        $this->maxLengthOfStay = $maxLengthOfStay;
        $this->bookableNights = $bookableNights;
        $this->bookableNightsWithPackages = $bookableNightsWithPackages;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $adjacent = isset($data['adjacentDays']) && is_array($data['adjacentDays'])
            ? $data['adjacentDays']
            : [];

        return new self(
            new DateTimeImmutable((string)($data['effectiveDate'] ?? 'now')),
            isset($data['fromPrice']) ? (float)$data['fromPrice'] : null,
            (bool)($data['isAvailable'] ?? false),
            (bool)($data['isArrivalAllowed'] ?? false),
            (bool)($data['isDepartureAllowed'] ?? false),
            (bool)($adjacent['isPreviousDayBlocked'] ?? false),
            (bool)($adjacent['isNextDayBlocked'] ?? false),
            (int)($data['minLengthOfStay'] ?? 0),
            (int)($data['maxLengthOfStay'] ?? 0),
            array_map('intval', (array)($data['bookableNights'] ?? [])),
            array_map('intval', (array)($data['bookableNightsWithPackages'] ?? []))
        );
    }
}
