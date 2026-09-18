# CASABLANCA Booking Engine — TYPO3 Extension

Integrates the CASABLANCA Internet Booking Engine (IBE v2) into TYPO3. Replaces the legacy [JavaScript web widgets](https://docs.casablanca.at/cloud/module/widget/) with native CMS content elements, scheduled ARI synchronisation, SSR-rendered prices for SEO, and PCI-safe redirect handover to the booking engine.

**Extension key:** `casablanca_booking`  
**Composer package:** `casablanca/bookingengine-connector`  
**Author:** Martin Hairer  
**Copyright:** CASABLANCA hotelsoftware GmbH  
**License:** AGPL-3.0-only (see [LICENSE](LICENSE))  
**Compatibility:** TYPO3 12.4 – 14.x · PHP 8.1 – 8.6

## Quick start

1. `composer require casablanca/bookingengine-connector`
2. Activate the extension and run `vendor/bin/typo3 extension:setup`
3. Open **Tools → CASABLANCA Booking**
4. Enter **Tenant ID** and **API key** (IBE domain defaults to `https://bookingengine.casablanca.at`)
5. Insert content elements on your pages

## Content elements

| Element | Replaces legacy widget |
|---|---|
| **Search bar** | Header booking snippet |
| **Availability calendar** | Buchbarkeitskalender |
| **Room types** | Zimmertypen-Widget |
| **Packages** | Pauschalen-Widget |
| **Price teaser** | SEO “from €X” block |

## Sync

```bash
vendor/bin/typo3 casablanca-booking:sync
vendor/bin/typo3 casablanca-booking:sync --site=main --days=14
```

A daily scheduler task is created automatically on first save (staggered to the save time). An initial sync runs when the connection check succeeds; sync also runs after backend cache clears.

## Theming

All widgets use the `.cb-` CSS prefix and `--cb-*` design tokens declared on `.cb-widget`. Agencies typically:

1. Override tokens in the sitepackage stylesheet (accent colour, radius, fonts).
2. Set global accent/radius via Site Settings or TypoScript (`plugin.tx_casablancabooking.theme`).
3. Disable bundled CSS (`includeCss = 0`) only when the sitepackage ships a complete replacement.

See the full theming cookbook: [Documentation/Theming/Index.rst](Documentation/Theming/Index.rst) (CSS token table, appearance modes, Fluid overrides, do's and don'ts).

## Localization

Frontend labels (*Adults*, *Book now*, calendar legend, …) follow the TYPO3
site language via `locallang.xlf` (EN/DE shipped). Editors can override
heading and primary buttons per content element in the FlexForm **Texts**
sheet; integrators use TypoScript `_LOCAL_LANG` for site-wide wording.

See [Documentation/Localization/Index.rst](Documentation/Localization/Index.rst).

## Documentation

| Format | Location |
|--------|----------|
| **TER / Sphinx (RST)** | [Documentation/](Documentation/) — published on [docs.typo3.org](https://docs.typo3.org) after release |

Installing the extension does **not** show the manual inside the TYPO3 backend
(editors use FlexForm labels and the frontend only).

**Calendar minimum stay:** enforced from the CASABLANCA API (`minLengthOfStay`
per arrival day), not from TYPO3 FlexForm.

## PCI DSS

Widgets never call the CASABLANCA API from the browser. Booking and payment happen only on the IBE after HTTP 303 redirect.
