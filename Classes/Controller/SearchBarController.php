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
        $formArrival = $from->modify('+2 days');
        $formDeparture = $formArrival->modify('+7 days');
        $compactOccupancy = (int)($settings['compactOccupancy'] ?? 1) === 1;

        $this->tagPageCache($config);

        $this->view->assignMultiple([
            'config' => $config,
            'compactOccupancy' => $compactOccupancy,
            'defaultRooms' => $common['defaultRooms'],
            'rooms' => $this->buildDefaultRooms($common),
            'formArrival' => $formArrival,
            'formDeparture' => $formDeparture,
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

        $this->redirectToIbeWithRooms(
            $config,
            $this->resolveRequestDateParam($arrival, 'arrival'),
            $this->resolveRequestDateParam($departure, 'departure'),
            $this->parseRoomOccupanciesFromRequest(),
            $room !== '' ? $room : null,
            $culture
        );

        return null;
    }
}
