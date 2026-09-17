<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\ViewHelper;

use Casablanca\CasablancaBooking\Domain\Repository\AvailabilityRepository;
use DateTimeImmutable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the cheapest available from-price for a site/room in a day window.
 *
 * Usage: {casablanca:cheapestPrice(siteIdentifier: 'main', roomTypeId: 'uuid', days: 90)}
 */
class CheapestPriceViewHelper extends AbstractViewHelper
{
    /** @var bool */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('siteIdentifier', 'string', 'TYPO3 site identifier', true);
        $this->registerArgument('roomTypeId', 'string', 'Optional room type UUID', false, '');
        $this->registerArgument('rateId', 'string', 'Optional package rate UUID', false, '');
        $this->registerArgument('days', 'int', 'Look-ahead window in days', false, 90);
        $this->registerArgument('format', 'string', 'sprintf format for price output', false, '%.2f');
        $this->registerArgument('currency', 'bool', 'Append currency code', false, true);
    }

    public function render(): string
    {
        /** @var AvailabilityRepository $repository */
        $repository = GeneralUtility::makeInstance(AvailabilityRepository::class);

        $days = max(1, min(365, (int)$this->arguments['days']));
        $from = new DateTimeImmutable('today');
        $until = $from->modify(sprintf('+%d days', $days - 1));

        $roomTypeId = trim((string)$this->arguments['roomTypeId']);
        $rateId = trim((string)$this->arguments['rateId']);
        $result = $repository->findCheapestPrice(
            (string)$this->arguments['siteIdentifier'],
            $from,
            $until,
            $roomTypeId !== '' ? $roomTypeId : null,
            $rateId !== '' ? $rateId : null
        );

        if ($result === null || !isset($result['price'])) {
            return '';
        }

        $formatted = sprintf((string)$this->arguments['format'], (float)$result['price']);
        if (!empty($this->arguments['currency'])) {
            $formatted .= ' ' . (string)($result['currency'] ?? 'EUR');
        }

        return $formatted;
    }
}
