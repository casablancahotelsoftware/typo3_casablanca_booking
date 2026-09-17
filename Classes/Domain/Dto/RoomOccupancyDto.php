<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Dto;

/**
 * Request-side DTO for POST /calendar-dates and related endpoints.
 */
final class RoomOccupancyDto
{
    /** @var int */
    public $numberOfAdults;

    /** @var int[] */
    public $ageOfChildren;

    /**
     * @param int[] $ageOfChildren
     */
    public function __construct(int $numberOfAdults, array $ageOfChildren = [])
    {
        $this->numberOfAdults = $numberOfAdults;
        $this->ageOfChildren = $ageOfChildren;
    }

    /**
     * @return array{numberOfAdults: int, ageOfChildren: int[]}
     */
    public function toArray(): array
    {
        return [
            'numberOfAdults' => $this->numberOfAdults,
            'ageOfChildren' => array_values(array_map('intval', $this->ageOfChildren)),
        ];
    }

    public function getNumberOfChildren(): int
    {
        return count($this->ageOfChildren);
    }
}
