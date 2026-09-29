<?php

declare(strict_types=1);

namespace Casablanca\CasablancaBooking\Controller;

use DateTimeImmutable;

/**
 * Compact search bar (arrival, departure, occupancy) without calendar grid.
 */
class SearchBarController extends AbstractWidgetController
{
    /**
     * @return mixed
     */
    public function showAction()
    {
        $this->includeFrontendAssets();

        $config = $this->resolveConfig();
        if ($config === null) {
            $this->view->assign('error', 'not_configured');

            return $this->htmlResponseOrNull();
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $common = $this->parseCommonSettings($config);
        $from = new DateTimeImmutable('today');
        $formArrivalMin = $from->modify('+1 day');
        $formArrival = $from->modify('+2 days');
        $formDeparture = $formArrival->modify('+7 days');
        $formDepartureMin = $formArrival->modify('+1 day');
        $compactOccupancy = (int)($settings['compactOccupancy'] ?? 1) === 1;

        $this->tagPageCache($config);

        $this->view->assignMultiple([
            'config' => $config,
            'compactOccupancy' => $compactOccupancy,
            'defaultRooms' => $common['defaultRooms'],
            'rooms' => $this->buildDefaultRooms($common),
            'formArrival' => $formArrival,
            'formArrivalMin' => $formArrivalMin,
            'formDeparture' => $formDeparture,
            'formDepartureMin' => $formDepartureMin,
            'culture' => $common['culture'],
            'ibeBaseUrl' => $config->ibeBaseUrl,
            'tenantId' => $config->tenantId,
            'ibeContextId' => $config->urlFriendlyIbeContextId,
            'ibeLinkTarget' => $this->parseIbeLinkTarget(),
        ]);

        return $this->htmlResponseOrNull();
    }

    /**
     * @return mixed
     */
    public function redirectAction(
        string $arrival = '',
        string $departure = '',
        string $room = ''
    ) {
        $config = $this->resolveConfig();
        if ($config === null) {
            return $this->htmlResponseOrNull('Site not configured for CASABLANCA.');
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $culture = $this->resolveEffectiveCulture($config, $settings);

        $arrivalDate = $this->resolveRequestDateParam($arrival, 'arrival');
        $departureDate = $this->resolveRequestDateParam($departure, 'departure');
        $normalized = $this->ensureDepartureAfterArrival($arrivalDate, $departureDate);

        $this->redirectToIbeWithRooms(
            $config,
            $normalized['arrival'],
            $normalized['departure'],
            $this->parseRoomOccupanciesFromRequest(),
            $room !== '' ? $room : null,
            $culture
        );

        return null;
    }
}
