.. include:: /Includes.rst.txt

=========
Reference
=========

TypoScript and Site Settings reference for ``plugin.tx_casablancabooking``.
Use this page when configuring the extension globally (sitepackage or Site
Management) rather than per content element via FlexForm.


Overview
========

Configuration is available through two mechanisms depending on TYPO3 version:

* **TYPO3 v12** — TypoScript constants in the template module
  (``constants.typoscript`` from the ``casablanca/booking`` site set).
* **TYPO3 v13+** — Site Settings in **Site Management → Settings** under
  the **CASABLANCA Booking** category (``settings.definitions.yaml``).

Per-plugin FlexForm settings override TypoScript defaults where both exist.
See :doc:`../ContentElements/Index` for content-element-specific options.


plugin.tx_casablancabooking settings
====================================

Global plugin settings
----------------------

+--------------------------------+----------+---------------+------------------------------------------+
| Setting                        | Type     | Default       | Effect                                   |
+================================+==========+===============+==========================================+
| ``settings.includeCss``        | bool/int | ``1``         | Include ``widget.css`` on pages with     |
|                                |          |               | booking plugins. Set ``0`` when the      |
|                                |          |               | sitepackage provides its own stylesheet. |
|                                |          |               | JavaScript is always loaded.             |
+--------------------------------+----------+---------------+------------------------------------------+
| ``settings.appearance``        | string   | ``default``   | Default layout style for all plugins     |
|                                |          |               | unless overridden in FlexForm. Values:   |
|                                |          |               | ``default``, ``inherit``, ``compact``.   |
+--------------------------------+----------+---------------+------------------------------------------+
| ``settings.windowDays``        | int      | ``90``        | Default calendar/price look-ahead window |
|                                |          |               | (days). Overridden by FlexForm.          |
+--------------------------------+----------+---------------+------------------------------------------+
| ``settings.defaultAdults``     | int      | ``2``         | Default adult count for forms and IBE    |
|                                |          |               | handover when not set in FlexForm.       |
+--------------------------------+----------+---------------+------------------------------------------+
| ``settings.defaultChildrenAges``| string  | *(empty)*     | Comma-separated default child ages       |
|                                |          |               | (e.g. ``4,8``). Falls back to backend    |
|                                |          |               | module occupancy when empty.             |
+--------------------------------+----------+---------------+------------------------------------------+
| ``settings.language``          | string   | *(empty)*     | Default IBE culture code. Empty = site   |
|                                |          |               | default / backend ``defaultCulture``.    |
+--------------------------------+----------+---------------+------------------------------------------+

Theme settings (``settings.theme``)
-----------------------------------

These map to inline CSS custom properties on the widget wrapper:

+--------------------------------+----------+---------------+------------------------------------------+
| Setting                        | Type     | Default       | CSS variable                             |
+================================+==========+===============+==========================================+
| ``settings.theme.accent``      | string   | *(empty)*     | ``--cb-accent``                          |
+--------------------------------+----------+---------------+------------------------------------------+
| ``settings.theme.accentContrast``| string | *(empty)*     | ``--cb-accent-contrast``                 |
+--------------------------------+----------+---------------+------------------------------------------+
| ``settings.theme.radius``      | string   | *(empty)*     | ``--cb-radius``                          |
+--------------------------------+----------+---------------+------------------------------------------+

Leave theme values empty to use the extension defaults from ``widget.css``
(``#8b1e1e`` accent, ``6px`` radius). Accepts any valid CSS colour or
length value (e.g. ``#0a6e5e``, ``4px``, ``0.25rem``).


View paths (``plugin.tx_casablancabooking.view``)
-------------------------------------------------

+--------------------------------+----------+---------------+------------------------------------------+
| Setting                        | Type     | Default       | Effect                                   |
+================================+==========+===============+==========================================+
| ``view.templateRootPath``      | string   | *(empty)*     | Additional Fluid template root.          |
|                                |          |               | Merged at priority 10 with extension     |
|                                |          |               | path at priority 0.                      |
+--------------------------------+----------+---------------+------------------------------------------+
| ``view.partialRootPath``       | string   | *(empty)*     | Additional Fluid partial root.           |
+--------------------------------+----------+---------------+------------------------------------------+
| ``view.layoutRootPath``        | string   | *(empty)*     | Additional Fluid layout root.            |
+--------------------------------+----------+---------------+------------------------------------------+

Example — override templates from a sitepackage:

.. code-block:: typoscript

   plugin.tx_casablancabooking.view {
       templateRootPath = EXT:my_sitepackage/Resources/Private/Extensions/CasablancaBooking/Templates/
       partialRootPath = EXT:my_sitepackage/Resources/Private/Extensions/CasablancaBooking/Partials/
       layoutRootPath = EXT:my_sitepackage/Resources/Private/Extensions/CasablancaBooking/Layouts/
   }


Site Settings (TYPO3 v13+)
==========================

When using the ``casablanca/booking`` site set, these settings appear in
**Site Management → Settings → CASABLANCA Booking**:

+-------------------------------------+------+---------+------------------------------------------+
| Site setting key                    | Type | Default | Maps to                                  |
+=====================================+======+=========+==========================================+
| ``casablancaBooking.includeCss``    | bool | ``true``| ``plugin.tx_casablancabooking.settings. |
|                                     |      |         | includeCss``                             |
+-------------------------------------+------+---------+------------------------------------------+
| ``casablancaBooking.theme.accent``  | str  | ``''``  | ``settings.theme.accent``                |
+-------------------------------------+------+---------+------------------------------------------+
| ``casablancaBooking.theme.accentContrast``| str | ``''`` | ``settings.theme.accentContrast``   |
+-------------------------------------+------+---------+------------------------------------------+
| ``casablancaBooking.theme.radius``  | str  | ``''``  | ``settings.theme.radius``                |
+-------------------------------------+------+---------+------------------------------------------+

YAML example in ``config/sites/main/settings.yaml``:

.. code-block:: yaml

   casablancaBooking:
     includeCss: true
     theme:
       accent: '#0a6e5e'
       accentContrast: '#ffffff'
       radius: '4px'


TypoScript constants (TYPO3 v12)
================================

Constants are defined in
``Configuration/Sets/CasablancaBooking/constants.typoscript`` and surfaced
in the template **Constants** editor under **plugin.tx_casablancabooking**:

.. code-block:: typoscript

   plugin.tx_casablancabooking {
       includeCss = 1
       appearance = default
       theme {
           accent =
           accentContrast =
           radius =
       }
       view {
           templateRootPath =
           partialRootPath =
           layoutRootPath =
       }
   }


TypoScript setup mapping
========================

``setup.typoscript`` wires constants into plugin settings:

.. code-block:: typoscript

   plugin.tx_casablancabooking {
       settings {
           includeCss = {$plugin.tx_casablancabooking.includeCss}
           appearance = {$plugin.tx_casablancabooking.appearance}
           theme {
               accent = {$plugin.tx_casablancabooking.theme.accent}
               accentContrast = {$plugin.tx_casablancabooking.theme.accentContrast}
               radius = {$plugin.tx_casablancabooking.theme.radius}
           }
       }
   }

   # Site Settings bridge (v13+)
   plugin.tx_casablancabooking.settings {
       includeCss = {$casablancaBooking.includeCss}
       theme {
           accent = {$casablancaBooking.theme.accent}
           accentContrast = {$casablancaBooking.theme.accentContrast}
           radius = {$casablancaBooking.theme.radius}
       }
   }


Frontend assets
===============

Regardless of ``includeCss``, the setup registers assets globally:

.. code-block:: typoscript

   page.includeCSS.casablancaBookingWidget = EXT:casablanca_booking/Resources/Public/Css/widget.css
   page.includeJSFooter.casablancaBookingWidget = EXT:casablanca_booking/Resources/Public/JavaScript/widget.js

At runtime, ``includeCss`` controls whether CSS is actually added via the
AssetCollector (TYPO3 v12+) or PageRenderer.


Occupancy defaults (backend module)
===================================

Default occupancy for widgets is resolved in this order:

1. FlexForm ``defaultAdults``, ``defaultChildren``, ``defaultChildrenAges``
2. TypoScript ``settings.defaultAdults`` / ``defaultChildrenAges``
3. Backend module record (``default_adults``, ``default_children_ages``)

Multi-room occupancy (Widget and Search Bar) uses FlexForm
``defaultRooms`` (1–5). Each room repeats the per-room adult/child defaults.

See :doc:`../ContentElements/Index` for occupancy behaviour per plugin.


Stay length: API vs TYPO3 settings
==================================

+----------------------------+---------------------------+------------------------------------------+
| Feature                    | Source                    | Notes                                    |
+============================+===========================+==========================================+
| Calendar minimum stay      | **CASABLANCA API**        | ``minLengthOfStay`` per day on live      |
| (Widget, detail calendars) | (``CalendarDates``)       | ``fetchCalendar``. Enforced in JS when   |
|                            |                           | selecting departure. Not editable in     |
|                            |                           | TYPO3.                                   |
+----------------------------+---------------------------+------------------------------------------+
| Calendar bookable nights   | **API** (synced mirror)   | ``bookable_nights`` per day in sync      |
|                            |                           | cache; used for restrictions display.    |
+----------------------------+---------------------------+------------------------------------------+
| Packages overview filter   | **Synced API data** +     | ``bookable_nights_with_packages`` from   |
|                            | **TYPO3 FlexForm**        | sync compared to ``stayNights``. Disable |
|                            |                           | with ``filterByStayNights = 0``.         |
+----------------------------+---------------------------+------------------------------------------+
| Packages / room IBE links  | **TYPO3 FlexForm**        | ``stayNights`` sets default nights on    |
|                            |                           | book buttons (Packages). Not minimum     |
|                            |                           | stay enforcement.                        |
+----------------------------+---------------------------+------------------------------------------+
| Search bar default dates   | **TYPO3 (hardcoded +7)**  | Form only; IBE receives submitted dates.   |
+----------------------------+---------------------------+------------------------------------------+

To maintain stay rules in one place, configure them in CASABLANCA. The
calendar widget reads restrictions from the API automatically. Packages
overview filtering uses synced ``bookable_nights_with_packages`` — turn off
``filterByStayNights`` if you want every synced package listed regardless
of the FlexForm ``stayNights`` value.
