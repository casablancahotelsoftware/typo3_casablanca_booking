<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Service\Sync;

/**
 * Result of synchronising a single TYPO3 site.
 */
final class SyncSiteResult
{
    /** @var bool */
    public $success;

    /** @var int */
    public $rowsWritten;

    /** @var int */
    public $rowsChanged;

    /** @var int */
    public $cacheTagsFlushed;

    /** @var int */
    public $roomTypesWritten;

    /** @var int */
    public $ratesWritten;

    /** @var string */
    public $message;

    public function __construct(
        bool $success,
        int $rowsWritten = 0,
        int $rowsChanged = 0,
        int $cacheTagsFlushed = 0,
        int $roomTypesWritten = 0,
        int $ratesWritten = 0,
        string $message = ''
    ) {
        $this->success = $success;
        $this->rowsWritten = $rowsWritten;
        $this->rowsChanged = $rowsChanged;
        $this->cacheTagsFlushed = $cacheTagsFlushed;
        $this->roomTypesWritten = $roomTypesWritten;
        $this->ratesWritten = $ratesWritten;
        $this->message = $message;
    }
}
