<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Sync;

/**
 * Return value from AvailabilityWriter::write().
 */
final class WriterResult
{
    /** @var int */
    public $rowsWritten;

    /** @var int */
    public $rowsChanged;

    /** @var string[] */
    public $changedRoomIds;

    /**
     * @param string[] $changedRoomIds
     */
    public function __construct(int $rowsWritten, int $rowsChanged, array $changedRoomIds)
    {
        $this->rowsWritten = $rowsWritten;
        $this->rowsChanged = $rowsChanged;
        $this->changedRoomIds = $changedRoomIds;
    }

    public function hasChanges(): bool
    {
        return $this->rowsChanged > 0;
    }
}
