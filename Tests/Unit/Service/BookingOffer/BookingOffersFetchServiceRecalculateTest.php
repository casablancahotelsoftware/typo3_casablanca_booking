<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Tests\Unit\Service\BookingOffer;

use Casablanca\CasablancaBooking\Domain\Dto\RoomOccupancyDto;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Domain\Repository\RateRepository;
use Casablanca\CasablancaBooking\Service\Api\Client\BookingOffersClientInterface;
use Casablanca\CasablancaBooking\Service\BookingOffer\BookingOffersFetchService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class BookingOffersFetchServiceRecalculateTest extends TestCase
{
    public function testFetchCreatesOfferWhenNoExistingId(): void
    {
        $client = new RecordingBookingOffersClient();
        $client->createResponse = $this->minimalBookingOfferResponse('new-offer-id');

        $service = new BookingOffersFetchService(new RateRepository($this->createMock(ConnectionPool::class)));
        $result = $service->fetch(
            $client,
            $this->siteConfig(),
            '2026-06-01',
            '2026-06-05',
            new RoomOccupancyDto(2),
            BookingOffersFetchService::MODE_RATES_ONLY,
            null,
            null
        );

        self::assertSame(1, $client->createCalls);
        self::assertSame(0, $client->recalculateCalls);
        self::assertSame('new-offer-id', $result['bookingOfferId']);
        self::assertNotEmpty($result['offers']);
    }

    public function testFetchRecalculatesWhenExistingIdProvided(): void
    {
        $client = new RecordingBookingOffersClient();
        $client->recalculateResponse = $this->minimalBookingOfferResponse('existing-offer-id');

        $service = new BookingOffersFetchService(new RateRepository($this->createMock(ConnectionPool::class)));
        $result = $service->fetch(
            $client,
            $this->siteConfig(),
            '2026-06-01',
            '2026-06-05',
            new RoomOccupancyDto(2),
            BookingOffersFetchService::MODE_RATES_ONLY,
            null,
            'existing-offer-id'
        );

        self::assertSame(0, $client->createCalls);
        self::assertSame(1, $client->recalculateCalls);
        self::assertSame('existing-offer-id', $client->lastRecalculateId);
        self::assertSame(['includeRates'], $client->lastSelectionCriteria);
        self::assertSame('existing-offer-id', $result['bookingOfferId']);
    }

    public function testFetchFallsBackToCreateWhenRecalculateReturns404(): void
    {
        $client = new RecordingBookingOffersClient();
        $client->recalculateException = new RuntimeException(
            'CASABLANCA API error 404 on PUT /booking-offers/x'
        );
        $client->createResponse = $this->minimalBookingOfferResponse('fresh-offer-id');

        $service = new BookingOffersFetchService(new RateRepository($this->createMock(ConnectionPool::class)));
        $result = $service->fetch(
            $client,
            $this->siteConfig(),
            '2026-06-01',
            '2026-06-05',
            new RoomOccupancyDto(2),
            BookingOffersFetchService::MODE_RATES_ONLY,
            null,
            'stale-offer-id'
        );

        self::assertSame(1, $client->recalculateCalls);
        self::assertSame(1, $client->createCalls);
        self::assertSame('fresh-offer-id', $result['bookingOfferId']);
    }

    public function testShouldFallbackToCreateAfterRecalculateFailure(): void
    {
        $service = new BookingOffersFetchService(new RateRepository($this->createMock(ConnectionPool::class)));
        $method = new ReflectionMethod(BookingOffersFetchService::class, 'shouldFallbackToCreateAfterRecalculateFailure');
        $method->setAccessible(true);

        self::assertTrue($method->invoke(
            $service,
            new RuntimeException('CASABLANCA API error 404 on PUT /booking-offers/x')
        ));
        self::assertTrue($method->invoke(
            $service,
            new RuntimeException('CASABLANCA API error 400 on PUT /booking-offers/x')
        ));
        self::assertFalse($method->invoke(
            $service,
            new RuntimeException('CASABLANCA API error 500 on PUT /booking-offers/x')
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalBookingOfferResponse(string $id): array
    {
        return [
            'id' => $id,
            'configurations' => [
                [
                    'rateGroups' => [
                        [
                            'roomStayOffers' => [
                                [
                                    'rateId' => 'rate-1',
                                    'totalPrice' => 120.0,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function siteConfig(): SiteConfigurationDto
    {
        return new SiteConfigurationDto(
            'test-site',
            'tenant-uuid',
            'bookingengine',
            'api-key',
            'https://api.casablanca.at',
            'https://bookingengine.casablanca.at',
            'de',
            365,
            31,
            100,
            new RoomOccupancyDto(2)
        );
    }
}

final class RecordingBookingOffersClient implements BookingOffersClientInterface
{
    /** @var int */
    public $createCalls = 0;

    /** @var int */
    public $recalculateCalls = 0;

    /** @var string */
    public $lastRecalculateId = '';

    /** @var array<string, mixed> */
    public $lastSelectionCriteria = [];

    /** @var array<string, mixed> */
    public $createResponse = [];

    /** @var array<string, mixed> */
    public $recalculateResponse = [];

    /** @var RuntimeException|null */
    public $recalculateException;

    public function create(
        string $arrivalDate,
        string $departureDate,
        RoomOccupancyDto $roomOccupancy,
        array $selectionCriteria = [],
        array $stayFilter = []
    ): array {
        $this->createCalls++;

        return $this->createResponse;
    }

    public function recalculate(
        string $bookingOfferId,
        string $arrivalDate,
        string $departureDate,
        RoomOccupancyDto $roomOccupancy,
        array $selectionCriteria = []
    ): array {
        $this->recalculateCalls++;
        $this->lastRecalculateId = $bookingOfferId;
        $this->lastSelectionCriteria = $selectionCriteria;
        if ($this->recalculateException !== null) {
            throw $this->recalculateException;
        }

        return $this->recalculateResponse;
    }

    public function getById(string $bookingOfferId): array
    {
        return [];
    }
}
