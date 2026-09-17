<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Controller;

use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;
use Casablanca\CasablancaBooking\Service\BookingOffer\BookingOffersFetchService;

/**
 * Builds Fluid variables for the shared calendar partial.
 */
trait CalendarPresentationTrait
{
    /**
     * @param array<string, mixed> $common
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function buildCalendarPresentationVariables(
        SiteConfigurationDto $config,
        array $common,
        string $controller,
        string $plugin,
        int $pageType,
        array $options = []
    ): array {
        $offerMode = BookingOffersFetchService::normaliseOfferMode(
            (string)($options['calendarOfferMode'] ?? BookingOffersFetchService::MODE_NONE)
        );
        $offersEnabled = $offerMode !== BookingOffersFetchService::MODE_NONE;

        $fetchUrl = $this->buildCalendarPluginUrl('fetchCalendar', $controller, $plugin, $pageType);
        $offersFetchUrl = '';
        if ($offersEnabled) {
            $offersFetchUrl = $this->buildCalendarPluginUrl('fetchOffers', $controller, $plugin, $pageType);
        }

        $redirectUrl = '';
        if ($controller === 'BookingWidget') {
            $redirectUrl = (string)$this->uriBuilder
                ->reset()
                ->setCreateAbsoluteUri(false)
                ->uriFor('redirect', [], $controller, 'CasablancaBooking', $plugin);
        }

        $preselectedRoomTypeId = trim((string)($options['preselectedRoomTypeId'] ?? ''));
        $preselectedRateId = trim((string)($options['preselectedRateId'] ?? ''));
        $filterRateIds = trim((string)($options['filterRateIds'] ?? ''));

        return [
            'calendarFetchUrl' => $fetchUrl,
            'offersFetchUrl' => $offersFetchUrl,
            'calendarRedirectUrl' => $redirectUrl,
            'calendarPageType' => $pageType,
            'calendarInitialMonths' => (int)($options['calendarInitialMonths'] ?? 1),
            'calendarOfferMode' => $offerMode,
            'calendarOffersEnabled' => $offersEnabled,
            'preselectedRoomTypeId' => $preselectedRoomTypeId,
            'preselectedRateId' => $preselectedRateId,
            'filterRateIds' => $filterRateIds,
            'calendarLayoutClass' => trim((string)($options['calendarLayoutClass'] ?? '')),
            'showCalendarLegend' => (bool)($options['showCalendarLegend'] ?? false),
            'showCalendarHeading' => (bool)($options['showCalendarHeading'] ?? false),
            'preselectedRoomType' => $options['preselectedRoomType'] ?? null,
            'defaultRooms' => $common['defaultRooms'],
            'rooms' => $this->buildDefaultRooms($common),
            'culture' => $common['culture'],
            'windowDays' => $common['windowDays'],
            'ibeBaseUrl' => $config->ibeBaseUrl,
            'tenantId' => $config->tenantId,
            'ibeContextId' => $config->urlFriendlyIbeContextId,
            'ibeLinkTarget' => $this->parseIbeLinkTarget(),
            'config' => $config,
            'showEnquiryButton' => (bool)($options['showEnquiryButton'] ?? false),
            'enquiryUrl' => (string)($options['enquiryUrl'] ?? ''),
            'enquiryLinkTarget' => (string)($options['enquiryLinkTarget'] ?? '_self'),
            'roomMaxOccupancy' => (int)($options['roomMaxOccupancy'] ?? 0),
            'roomMinOccupancy' => (int)($options['roomMinOccupancy'] ?? 0),
            'calendarDefaultAdults' => (int)($common['adults'] ?? 2),
            'calendarDefaultChildren' => (int)($common['defaultChildrenCount'] ?? 0),
        ];
    }

    protected function buildCalendarPluginUrl(
        string $action,
        string $controller,
        string $plugin,
        int $pageType
    ): string {
        return (string)$this->uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(false)
            ->setTargetPageType($pageType)
            ->uriFor($action, [], $controller, 'CasablancaBooking', $plugin);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{
     *     showDetailCalendar: bool,
     *     detailCalendarPosition: string,
     *     detailCalendarInitialMonths: int,
     *     detailCalendarOfferMode: string
     * }
     */
    protected function parseDetailCalendarSettings(array $settings, bool $isPackagePlugin = false): array
    {
        $show = (bool)($settings['showDetailCalendar'] ?? false);
        $position = trim((string)($settings['detailCalendarPosition'] ?? 'below'));
        if (!in_array($position, ['above', 'below'], true)) {
            $position = 'below';
        }

        $months = (int)($settings['detailCalendarInitialMonths'] ?? 1);
        if (!in_array($months, [1, 2], true)) {
            $months = 1;
        }

        if ($isPackagePlugin) {
            $offerMode = BookingOffersFetchService::MODE_NONE;
        } else {
            $offerMode = BookingOffersFetchService::normaliseOfferMode(
                (string)($settings['detailCalendarOfferMode'] ?? BookingOffersFetchService::MODE_RATES_ONLY)
            );
            if (!in_array($offerMode, [BookingOffersFetchService::MODE_NONE, BookingOffersFetchService::MODE_RATES_ONLY], true)) {
                $offerMode = BookingOffersFetchService::MODE_RATES_ONLY;
            }
        }

        return [
            'showDetailCalendar' => $show,
            'detailCalendarPosition' => $position,
            'detailCalendarInitialMonths' => $months,
            'detailCalendarOfferMode' => $offerMode,
        ];
    }
}
