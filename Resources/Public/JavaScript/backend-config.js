/**
 * Backend module: live Booking Engine URL preview.
 */
(function () {
    'use strict';

    var previewIds = [
        'cb-tenant-id',
        'cb-ibe-base-url',
        'cb-ibe-context',
        'cb-default-culture',
        'cb-ibe-link-style'
    ];

    function updateBookingEngineUrlPreview() {
        var preview = document.getElementById('cb-ibe-url-preview');
        if (!preview) {
            return;
        }

        var baseEl = document.getElementById('cb-ibe-base-url');
        var tenantEl = document.getElementById('cb-tenant-id');
        var spaceEl = document.getElementById('cb-ibe-context');
        var cultureEl = document.getElementById('cb-default-culture');
        var styleEl = document.getElementById('cb-ibe-link-style');

        var base = baseEl ? baseEl.value.replace(/\/+$/, '') : '';
        var tenant = tenantEl ? tenantEl.value : '';
        var space = spaceEl ? spaceEl.value : 'bookingengine';
        var culture = cultureEl && cultureEl.value ? cultureEl.value : 'de';
        var style = styleEl ? styleEl.value : 'full_path';

        if (!base) {
            preview.textContent = preview.getAttribute('data-placeholder') || '';
            preview.classList.add('cb-backend__url-preview--empty');
            return;
        }

        preview.classList.remove('cb-backend__url-preview--empty');

        var segments = [base, encodeURIComponent(culture)];

        if (style !== 'culture_only') {
            segments.push(encodeURIComponent(tenant || 'tenant-id'));
        }

        if (style === 'full_path') {
            segments.push(encodeURIComponent(space || 'bookingengine'));
        }

        var query = 'arrivalDate=2026-06-01&departureDate=2026-06-08&numberOfRooms=1&rooms_0__adults=2&rooms_0__children=0';
        preview.textContent = segments.join('/') + '?' + query;
    }

    previewIds.forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        el.addEventListener('input', updateBookingEngineUrlPreview);
        el.addEventListener('change', updateBookingEngineUrlPreview);
    });

    updateBookingEngineUrlPreview();
})();
