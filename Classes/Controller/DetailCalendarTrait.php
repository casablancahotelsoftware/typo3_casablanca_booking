<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Controller;

use Casablanca\CasablancaBooking\Compatibility\Typo3Adapter;
use Casablanca\CasablancaBooking\Domain\Dto\SiteConfigurationDto;

/**
 * Assigns detail-page calendar variables to the view.
 */
trait DetailCalendarTrait
{
    /**
     * @param array<string, mixed> $common
     * @param array<string, mixed> $settings
     * @param array<int, array<string, mixed>> $cards
     */
    protected function assignDetailCalendarVariables(
        SiteConfigurationDto $config,
        array $common,
        array $settings,
        array $cards,
        bool $isPackagePlugin = false
    ): void {
        $detailCalendar = $this->parseDetailCalendarSettings($settings, $isPackagePlugin);

        $this->view->assignMultiple([
            'showDetailCalendar' => $detailCalendar['showDetailCalendar'],
            'detailCalendarPosition' => $detailCalendar['detailCalendarPosition'],
        ]);

        if (!$detailCalendar['showDetailCalendar'] || $cards === []) {
            return;
        }

        $options = [
            'calendarInitialMonths' => $detailCalendar['detailCalendarInitialMonths'],
            'calendarOfferMode' => $detailCalendar['detailCalendarOfferMode'],
            'calendarLayoutClass' => 'cb-calendar-layout--detail',
            'showCalendarLegend' => false,
            'showCalendarHeading' => false,
        ];

        if ($isPackagePlugin) {
            $rateId = $cards[0]['package']->getRateId();
            $options['preselectedRateId'] = $rateId;
            $options['filterRateIds'] = $rateId;
        } else {
            $roomType = $cards[0]['roomType'];
            $maxOccupancy = $roomType->getMaxOccupancy();
            $minOccupancy = $roomType->getMinOccupancy();

            $options['preselectedRoomTypeId'] = $roomType->getRoomTypeId();
            $options['preselectedRoomType'] = $roomType;
            $options['roomMaxOccupancy'] = $maxOccupancy;
            $options['roomMinOccupancy'] = $minOccupancy;

            if ($maxOccupancy > 0) {
                $clamped = $this->clampOccupancyToMax(
                    $common['adults'],
                    $common['defaultChildrenCount'],
                    $maxOccupancy
                );
                $common = array_merge($common, [
                    'adults' => $clamped['adults'],
                    'defaultChildrenCount' => $clamped['children'],
                    'defaultRooms' => 1,
                ]);
                $common['childrenAges'] = $this->padAgesToCount(
                    $common['childrenAges'],
                    $common['defaultChildrenCount']
                );
            }
        }

        $controller = $isPackagePlugin ? 'Packages' : 'RoomTypes';
        $pageType = $isPackagePlugin
            ? Typo3Adapter::CALENDAR_PAGE_TYPE_PACKAGES
            : Typo3Adapter::CALENDAR_PAGE_TYPE_ROOM_TYPES;

        $calendarVars = $this->buildCalendarPresentationVariables(
            $config,
            $common,
            $controller,
            $controller,
            $pageType,
            $options
        );

        $this->view->assignMultiple($calendarVars);
    }
}
