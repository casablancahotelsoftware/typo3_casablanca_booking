<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Per-run audit of the ARI sync command. Used to power the backend health
 * dashboard and to answer "when was this last refreshed?" without hitting
 * the API.
 */
final class SyncLog extends AbstractEntity
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILURE = 'failure';
    public const STATUS_PARTIAL = 'partial';

    protected string $siteIdentifier = '';
    protected int $startedAt = 0;
    protected int $finishedAt = 0;
    protected string $status = '';
    protected int $rowsWritten = 0;
    protected int $rowsChanged = 0;
    protected int $cacheTagsFlushed = 0;
    protected string $message = '';

    public function getSiteIdentifier(): string
    {
        return $this->siteIdentifier;
    }

    public function getStartedAt(): int
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): int
    {
        return $this->finishedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRowsWritten(): int
    {
        return $this->rowsWritten;
    }

    public function getRowsChanged(): int
    {
        return $this->rowsChanged;
    }

    public function getCacheTagsFlushed(): int
    {
        return $this->cacheTagsFlushed;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getDurationSeconds(): int
    {
        if ($this->finishedAt === 0) {
            return 0;
        }
        return max(0, $this->finishedAt - $this->startedAt);
    }
}
