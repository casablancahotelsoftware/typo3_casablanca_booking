.. include:: /Includes.rst.txt

================
Content elements
================

Insert via **Create new content → Plugins**. Five plugins are available,
each replacing a legacy CASABLANCA JavaScript widget with SSR-rendered
HTML, local ARI cache lookups, and PCI-safe IBE redirect handover.


Prerequisites
=============

Before content elements show real data on the frontend:

1. **Backend configuration** — save credentials in **Tools → CASABLANCA Booking**
   for the current TYPO3 site identifier.
2. **Sync** — run **Sync now** (or wait for the scheduler) so room types, rates,
   and availability are cached locally.
3. **Site sets** — the site ``config.yaml`` must include ``casablanca/booking``
   (and typically ``typo3/fluid-styled-content``) so TypoScript and assets load.

If configuration is missing, plugins show a friendly *not configured* message.
A **503 Oops, an error occurred** on the frontend usually means a PHP error —
check ``var/log/`` and flush caches after updating the extension.


.. _content-elements-shared-general:

Shared General settings
=======================

Most plugins share fields on the **General** FlexForm sheet. Configure once
per content element; defaults come from TypoScript and the backend module
(see :doc:`../Reference/Index`).

+----------------------------+----------+---------+------------------------------------------+
| FlexForm key               | Type     | Default | Effect                                   |
+============================+==========+=========+==========================================+
| ``settings.defaultRooms``  | int      | ``1``   | Pre-selected room count (1–5) for        |
|                            |          |         | multi-room forms. Each room gets its own |
|                            |          |         | adult/child fields. Widget and Search    |
|                            |          |         | Bar only.                                |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.defaultAdults`` | int      | ``2``   | Default adults per room (1–10). Used to  |
|                            |          |         | pre-fill forms (Widget, Search Bar) or   |
|                            |          |         | IBE handover (Room Types, Packages).     |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.defaultChildren``| int     | ``0``   | Default children per room (0–10). Set to |
|                            |          |         | 0 to hide child age inputs on forms.     |
|                            |          |         | Widget and Search Bar only.              |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.defaultChildrenAges``| string | *(empty)* | Comma-separated ages (e.g. ``4,8``). |
|                            |          |         | Used when children > 0. Padded with 0    |
|                            |          |         | for missing ages.                        |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.language``      | select   | Auto    | IBE culture on handover: ``de``, ``en``, |
|                            |          |         | ``it``, ``fr``, or Auto (site default).  |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.ibeLinkTarget`` | select   | ``_self``| ``_self`` = same window; ``_blank`` =   |
|                            |          |         | new tab (requires JavaScript). Form      |
|                            |          |         | fallback always uses same window.        |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.windowDays``    | int      | ``90``  | Look-ahead window in days (7–180) for    |
|                            |          |         | calendar rendering or from-price lookup. |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.appearance``    | select   | ``default``| Layout style: ``default``, ``inherit``, |
|                            |          |         | ``compact``. See :doc:`../Theming/Index`.|
+----------------------------+----------+---------+------------------------------------------+


.. _content-elements-shared-texts:

Texts sheet (editors)
=====================

Each plugin has a **Texts** FlexForm sheet for heading and primary call-to-action
labels. **Empty = default translation** for the current site language (from
``locallang.xlf``). Occupancy labels (*Adults*, *Children*), legend, loading
messages, and errors are **not** FlexForm fields — see :doc:`../Localization/Index`.

Custom FlexForm text applies to **all language versions** of that content
element (``langDisable = 1``). Per-language wording uses XLF or TypoScript
``_LOCAL_LANG``.


Availability calendar (Widget)
================================

**Plugin key:** ``casablancabooking_widget``  
**Replaces:** Buchbarkeitskalender (legacy JS widget)

Purpose
-------

Full availability calendar with month grid, per-day prices, date-range
selection, multi-room occupancy sidebar, optional offer selection, and
IBE handover. Data is fetched live from the CASABLANCA API on demand;
the local sync cache provides SSR seed data and SEO JSON-LD.

Typical placement
-----------------

* Dedicated booking page
* Room landing pages (with room pre-selection)
* Embedded detail calendar on room pages (via Room Types plugin)


General sheet (Widget-specific)
-------------------------------

+--------------------------------+----------+---------+------------------------------------------+
| FlexForm key                   | Type     | Default | Effect                                   |
+================================+==========+=========+==========================================+
| ``settings.preselectRoomCategory``| select | *(none)* | Restrict calendar to one room type.   |
|                                |          |         | Options populated after sync. Useful   |
|                                |          |         | for room-specific landing pages.         |
+--------------------------------+----------+---------+------------------------------------------+
| ``settings.calendarInitialMonths``| select | ``1``   | Months visible on first load: current    |
|                                |          |         | month only (``1``) or current + next     |
|                                |          |         | (``2``). Additional months load via nav. |
+--------------------------------+----------+---------+------------------------------------------+
| ``settings.calendarOfferMode`` | select   | ``none``| Offers shown after date selection:       |
|                                |          |         | ``none``, ``rates_only``,                |
|                                |          |         | ``packages_only``, ``packages_and_rates``|
+--------------------------------+----------+---------+------------------------------------------+
| ``settings.showEnquiryButton`` | checkbox | ``0``   | Show enquiry button beside Book button.  |
|                                |          |         | Requires ``enquiryUrl``.                 |
+--------------------------------+----------+---------+------------------------------------------+
| ``settings.enquiryUrl``        | string   | *(empty)*| Target URL for enquiry button (contact  |
|                                |          |         | form, request page).                     |
+--------------------------------+----------+---------+------------------------------------------+
| ``settings.enquiryLinkTarget`` | select   | ``_self``| ``_self`` or ``_blank`` for enquiry link.|
+--------------------------------+----------+---------+------------------------------------------+

Offer subtitles in the sidebar list the **catering / board type** when the
synced rate provides one (from API ``cateringType``). Empty or ``Undefined``
values are omitted.

Plus all :ref:`shared General settings <content-elements-shared-general>`
except ``windowDays`` is labelled **Calendar window (days)** here.

**Texts sheet:**

+----------------------------+----------+---------+------------------------------------------+
| ``settings.labels.heading``| string   | *(empty)*| Calendar heading. Empty =            |
|                            |          |         | ``widget.heading.default`` or room name. |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.labels.bookNow``| string   | *(empty)*| Book button. Empty =                 |
|                            |          |         | ``widget.calendar.bookNow``.             |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.labels.enquiry``| string   | *(empty)*| Enquiry button. Empty =              |
|                            |          |         | ``widget.calendar.enquiry``.             |
+----------------------------+----------+---------+------------------------------------------+


Occupancy (Widget)
------------------

* Supports **1–5 rooms** via ``defaultRooms``.
* Each room has independent adults, children, and per-child age inputs.
* JavaScript steppers in the calendar sidebar allow live adjustment.
* On submit, occupancy is passed to the IBE as ``rooms_N__adults``,
  ``rooms_N__children``, and age parameters.
* When a room type is pre-selected, occupancy is clamped to that room's
  ``maxOccupancy`` from the synced mirror.


Date selection and minimum stay (Widget)
----------------------------------------

The availability calendar loads **live** date data from the CASABLANCA API
(``CalendarDates``). Each day includes ``minLengthOfStay`` from the booking
engine — this is **not** a TYPO3 FlexForm setting.

When a guest selects an **arrival** day, the widget stores that day's
``minLengthOfStay``. If the **departure** click would result in fewer nights,
JavaScript extends the range to the minimum allowed stay (see
``pickToDate`` / ``data-cb-min-stay`` in ``widget.js``). Example: arrival
with minimum stay **4** and a departure click **2** nights later → **4**
nights are selected.

Days with ``minLengthOfStay > 1`` appear in the **Restrictions apply**
legend. There is no editor setting to override minimum stay in the widget;
change restrictions in CASABLANCA (PMS / rate rules) and re-sync or wait
for the live calendar fetch to reflect updates.

**Search bar** uses a separate default: changing arrival auto-fills departure
as arrival + **7** nights (form convenience only). Calendar widgets always
use API ``minLengthOfStay`` per arrival day.


Search bar
==========

**Plugin key:** ``casablancabooking_searchbar``  
**Replaces:** Header booking snippet

Purpose
-------

Compact arrival/departure/occupancy form **without** a calendar grid.
Submits directly to the IBE via HTTP 303 redirect. Ideal for site headers,
hero sections, and sticky booking bars.

Typical placement
-----------------

* Site header / navigation bar
* Homepage hero
* Sidebar booking snippet

FlexForm settings
-----------------

Uses the :ref:`shared General settings <content-elements-shared-general>`
**except**:

* No ``windowDays`` (no calendar)
* No room pre-selection
* No calendar/offer/enquiry options

**Appearance sheet:** ``settings.appearance`` (default / inherit / compact).
Use **inherit** in headers — see :doc:`../Theming/Index`.

**Texts sheet:** ``settings.labels.heading`` (empty = ``searchbar.heading``),
``settings.labels.submit`` (empty = ``searchbar.submit``).

**Compact occupancy:** ``settings.compactOccupancy`` (checkbox, default **on**).
When enabled, the frontend shows **one row**: arrival, departure, guest icon,
and search button. Rooms, adults, and children appear in a collapsible panel
below when the editor or guest clicks the icon. Disable to keep the legacy
layout with all occupancy fields visible in the form grid.


Occupancy (Search Bar)
----------------------

Same multi-room model as the Widget (``defaultRooms``, per-room
adults/children/ages). Default dates: arrival = today + 2 days,
departure = arrival + 7 nights.


Room types
==========

**Plugin key:** ``casablancabooking_roomtypes``  
**Replaces:** Zimmertypen-Widget

Purpose
-------

Room type cards with synced name, description, image, and from-price.
Supports overview (grid) and detail (single room from URL slug) modes.
Optional embedded availability calendar on detail pages.

Typical placement
-----------------

* **Overview:** room listing page
* **Detail:** dedicated room detail page (one plugin instance, slug from URL)


General sheet (Room Types-specific)
-----------------------------------

+----------------------------+----------+---------+------------------------------------------+
| FlexForm key               | Type     | Default | Effect                                   |
+============================+==========+=========+==========================================+
| ``settings.filterRoomType``| select   | *(all)* | Show only one room in overview mode.     |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.stayNights``    | int      | ``7``   | Default stay length for IBE book links   |
|                            |          |         | (arrival + N nights). Range 1–30.        |
+----------------------------+----------+---------+------------------------------------------+

Plus ``windowDays``, ``defaultAdults``, ``defaultChildren``, ``language``
from shared settings (children count used for IBE handover only).

**Texts sheet (overview):** ``settings.labels.heading`` (empty =
``roomtypes.heading``), ``settings.labels.book`` (empty = ``roomtypes.book``),
``settings.labels.details`` (empty = ``roomtypes.details``).


Display sheet
-------------

+----------------------------------+----------+---------+------------------------------------------+
| FlexForm key                     | Type     | Default | Effect                                   |
+==================================+==========+=========+==========================================+
| ``settings.displayMode``         | select   | ``overview``| ``overview`` = card grid; ``detail`` = |
|                                  |          |         | single room from URL slug.               |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.showName``            | checkbox | ``1``   | Show room name on cards/detail.          |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.showDescription``     | checkbox | ``1``   | Show room description.                   |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.overviewDescriptionMode``| select | ``teaser``| Overview only: short teaser or truncated |
|                                  |          |         | full description with More control.        |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.overviewDescriptionLimit``| int  | ``250`` | Character limit for full overview text.  |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.overviewLayout``        | select   | ``grid``| Overview card layout: ``grid`` or ``list``.|
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.showPrice``           | checkbox | ``1``   | Show from-price (cheapest in window).    |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.showImage``           | checkbox | ``1``   | Show room image.                         |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.cardLinkType``        | select   | ``book``| Overview only: ``book`` = IBE direct;    |
|                                  |          |         | ``details`` = TYPO3 detail page link.    |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.detailPageUid``       | page     | *(none)*| Target page for Details links. Must host |
|                                  |          |         | this plugin in Detail mode.               |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.showDetailCalendar``  | checkbox | ``0``   | Embed booking calendar on detail pages.  |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.detailCalendarPosition``| select | ``below``| Calendar above or below detail content.|
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.detailCalendarInitialMonths``| select | ``1`` | Visible months for detail calendar.  |
+----------------------------------+----------+---------+------------------------------------------+
| ``settings.detailCalendarOfferMode``| select | ``rates_only``| Offers after date pick: ``none`` or |
|                                  |          |         | ``rates_only`` (this room's rates).      |
+----------------------------------+----------+---------+------------------------------------------+

Plus ``settings.ibeLinkTarget`` and ``settings.appearance``.


Display modes
-------------

**Overview mode**

* Renders a responsive card grid of all synced room types.
* Cards link to IBE (book) or detail page (details) per ``cardLinkType``.
* From-price uses ``windowDays`` look-ahead from local availability cache.

**Detail mode**

* Single room selected by ``roomSlug`` URL argument.
* Image carousel (when multiple images exist), full description, price, and book button.
* Optional embedded calendar with the same colour legend as the availability widget.


Detail calendar options
-----------------------

When ``showDetailCalendar`` is enabled on a detail page:

* Shows the calendar colour legend (available, restricted, no arrival, unavailable).
* Calendar is scoped to the current room type.
* Position controlled by ``detailCalendarPosition`` (above/below content).
* ``detailCalendarInitialMonths``: 1 or 2 months initially visible.
* ``detailCalendarOfferMode``: show standard rates for this room after
  dates are selected, or dates only (``none``).


Route enhancer (Room Types)
---------------------------

Pretty URLs require an Extbase route enhancer. Copy from
``Configuration/SiteConfiguration/RouteEnhancer.yaml.example``:

.. code-block:: yaml

   routeEnhancers:
     CasablancaRoomDetail:
       type: Extbase
       extension: CasablancaBooking
       plugin: RoomTypes
       limitToPages:
         - 123   # your room detail page UID
       routes:
         - routePath: '/{roomSlug}'
           _controller: 'RoomTypes::show'
           _arguments:
             roomSlug: roomSlug
       defaultController: 'RoomTypes::show'
       aspects:
         roomSlug:
           type: CasablancaRoomSlug

URL formats (both supported):

* Pretty path: ``/room-detail/double-room``
* Query fallback: ``/room-detail?tx_casablancabooking_roomtypes[roomSlug]=double-room``

Run sync after upgrade so room slugs are generated in the local mirror.


Packages
========

**Plugin key:** ``casablancabooking_packages``  
**Replaces:** Pauschalen-Widget

Purpose
-------

Package (Pauschale) cards from the synced rate mirror. Overview mode can
optionally filter by bookable stay length (``stayNights`` +
``filterByStayNights``); detail mode shows one package with optional
embedded calendar.

Typical placement
-----------------

* **Overview:** packages/offers listing page
* **Detail:** dedicated package detail page


General sheet (Packages-specific)
---------------------------------

+----------------------------+----------+---------+------------------------------------------+
| FlexForm key               | Type     | Default | Effect                                   |
+============================+==========+=========+==========================================+
| ``settings.filterPackage`` | select   | *(all)* | Show only one package in overview.       |
|                            |          |         | *(all)* does not bypass stay-length       |
|                            |          |         | filtering — see below.                   |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.stayNights``    | int      | ``7``   | Default stay for IBE book links and the  |
|                            |          |         | nights label on cards. When stay-length   |
|                            |          |         | filtering is on, overview lists only     |
|                            |          |         | packages bookable for this length.       |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.filterByStayNights`` | check | **on** | When **on**, overview shows only         |
|                            |          |         | packages bookable for ``stayNights``.      |
|                            |          |         | When **off**, all synced packages are     |
|                            |          |         | listed (still respects ``filterPackage``).|
+----------------------------+----------+---------+------------------------------------------+

Plus ``windowDays``, ``defaultAdults``, ``language`` (no children count
on Packages flexform).

**Texts sheet (overview):** ``settings.labels.heading`` (empty =
``packages.heading``), ``settings.labels.book`` (empty = ``packages.book``),
``settings.labels.details`` (empty = ``packages.details``).


Display sheet
-------------

Same fields as Room Types Display sheet, with package-specific labels:

* ``displayMode`` — overview list vs. detail from ``packageSlug``
* ``cardLinkType`` — book opens IBE with ``rateIds`` preselection; details
  links to package detail page
* ``detailPageUid`` — target for Details card links
* ``showDetailCalendar``, ``detailCalendarPosition``,
  ``detailCalendarInitialMonths`` — embed calendar on package detail pages
  (no ``detailCalendarOfferMode`` on packages; packages use rate preselection)

Plus ``settings.ibeLinkTarget`` and ``settings.appearance``.


Display modes
-------------

**Overview mode**

* Grid of package cards. By default only packages bookable for
  ``stayNights`` are listed; disable ``filterByStayNights`` to show all
  synced packages.
* Book button opens IBE with ``rateIds={rateId}``.

**Detail mode**

* Single package from ``packageSlug`` URL argument.
* Book now preselects the package rate in the IBE.


Route enhancer (Packages)
-------------------------

Copy from ``Configuration/SiteConfiguration/RouteEnhancerPackages.yaml.example``:

.. code-block:: yaml

   routeEnhancers:
     CasablancaPackageDetail:
       type: Extbase
       extension: CasablancaBooking
       plugin: Packages
       limitToPages:
         - 456   # your package detail page UID
       routes:
         - routePath: '/{packageSlug}'
           _controller: 'Packages::show'
           _arguments:
             packageSlug: packageSlug
       defaultController: 'Packages::show'
       aspects:
         packageSlug:
           type: CasablancaPackageSlug

URL formats:

* Pretty path: ``/package-detail/zeit-zu-zweit``
* Query fallback: ``/package-detail?tx_casablancabooking_packages[packageSlug]=zeit-zu-zweit``


Price teaser
============

**Plugin key:** ``casablancabooking_priceteaser``  
**Replaces:** SEO "from €X" block

Purpose
-------

Minimal centred "from €X" price display with ``Hotel`` JSON-LD structured
data. No booking form — ideal for SEO landing pages and hero price callouts.

Typical placement
-----------------

* SEO landing pages ("Hotel in … ab €X")
* Homepage price highlight
* Campaign pages

FlexForm settings
-----------------

+----------------------------+----------+---------+------------------------------------------+
| FlexForm key               | Type     | Default | Effect                                   |
+============================+==========+=========+==========================================+
| ``settings.roomType``      | select   | *(all)* | Restrict to one room type. Empty =       |
|                            |          |         | cheapest across all rooms.               |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.windowDays``    | int      | ``90``  | Days ahead for cheapest-price lookup     |
|                            |          |         | (7–180).                                 |
+----------------------------+----------+---------+------------------------------------------+
| ``settings.appearance``    | select   | ``default``| Layout style (default/inherit/compact).|
+----------------------------+----------+---------+------------------------------------------+
| ``settings.labels.heading``| string   | *(empty)*| Optional heading above price. Empty = no |
|                            |          |         | heading (price line only).               |
+----------------------------+----------+---------+------------------------------------------+

No occupancy, language, or IBE link settings — display only.


ViewHelpers
===========

Agencies can use Fluid ViewHelpers in sitepackage templates. Namespace:
``casablanca`` (vendor ``Casablanca\CasablancaBooking\ViewHelper``).


casablanca:cheapestPrice
------------------------

Renders the cheapest available from-price for a site/room in a day window
from the local availability cache.

**Usage:**

.. code-block:: html

   {casablanca:cheapestPrice(
       siteIdentifier: 'main',
       roomTypeId: 'uuid-here',
       days: 90,
       format: '%.2f',
       currency: 1
   )}

+---------------+----------+----------+------------------------------------------+
| Argument      | Type     | Default  | Description                              |
+===============+==========+==========+==========================================+
| ``siteIdentifier``| string | *required* | TYPO3 site identifier                |
+---------------+----------+----------+------------------------------------------+
| ``roomTypeId``| string   | ``''``   | Optional room type UUID. Empty = all.    |
+---------------+----------+----------+------------------------------------------+
| ``rateId``    | string   | ``''``   | Optional package rate UUID.              |
+---------------+----------+----------+------------------------------------------+
| ``days``      | int      | ``90``   | Look-ahead window (1–365 days).          |
+---------------+----------+----------+------------------------------------------+
| ``format``    | string   | ``'%.2f'``| ``sprintf`` format for price number.     |
+---------------+----------+----------+------------------------------------------+
| ``currency``  | bool     | ``true`` | Append currency code (e.g. ``EUR``).     |
+---------------+----------+----------+------------------------------------------+

Returns empty string when no price is available.


casablanca:ibeLink
------------------

Builds an IBE deep-link URL for custom templates (buttons, links, CTAs).

**Usage:**

.. code-block:: html

   <a href="{casablanca:ibeLink(
       siteIdentifier: 'main',
       arrival: '2026-06-01',
       departure: '2026-06-08',
       adults: 2,
       children: 0,
       roomTypeIds: 'uuid-here',
       rateIds: 'package-rate-uuid'
   )}">Book now</a>

+---------------+----------+----------+------------------------------------------+
| Argument      | Type     | Default  | Description                              |
+===============+==========+==========+==========================================+
| ``siteIdentifier``| string | *required* | TYPO3 site identifier                |
+---------------+----------+----------+------------------------------------------+
| ``arrival``   | string   | ``''``   | Arrival date ``Y-m-d``. Default: tomorrow.|
+---------------+----------+----------+------------------------------------------+
| ``departure`` | string   | ``''``   | Departure date ``Y-m-d``. Default: +1 day.|
+---------------+----------+----------+------------------------------------------+
| ``adults``    | int      | ``2``    | Number of adults.                        |
+---------------+----------+----------+------------------------------------------+
| ``children``  | int      | ``0``    | Number of children.                      |
+---------------+----------+----------+------------------------------------------+
| ``childrenAges``| string | ``''``   | Comma-separated child ages.              |
+---------------+----------+----------+------------------------------------------+
| ``roomTypeIds``| string  | ``''``   | Pre-selected room type ID(s), comma-sep. |
+---------------+----------+----------+------------------------------------------+
| ``rateIds``   | string   | ``''``   | Package rate ID for preselection.        |
+---------------+----------+----------+------------------------------------------+
| ``culture``   | string   | ``''``   | IBE culture segment override.            |
+---------------+----------+----------+------------------------------------------+
| ``linkStyle`` | string   | ``''``   | Override link style: ``full_path``,      |
|               |          |          | ``tenant_only``, ``culture_only``.       |
+---------------+----------+----------+------------------------------------------+

Returns empty string when site is not configured.
