.. include:: /Includes.rst.txt

==============
Localization
==============

Frontend labels such as *Check availability and book*, *Adults*,
*Room occupancy*, and *Book now* are **not hardcoded** in templates.
They come from XLF translation files and follow the **TYPO3 site language**
of the page being rendered.

This chapter explains who can change which text, how translations work,
and where the extension manual itself is published.


How labels are resolved
=======================

Labels are applied in layers (highest priority wins for editor overrides):

1. **FlexForm Texts sheet** — per content element: heading and primary
   buttons only. Empty field = use layer 2 or 3.
2. **TypoScript ``_LOCAL_LANG``** — site-wide wording overrides for
   integrators (no extra FlexForm fields).
3. **Shipped XLF** — ``Resources/Private/Language/locallang.xlf`` (English
   source) and ``de.locallang.xlf`` (German). Additional languages via
   ``it.locallang.xlf`` etc. or a sitepackage ``locallangXMLOverride``.

JavaScript-built UI (adding rooms in the calendar, dynamic steppers) reads
the same translations from ``window.CB_LABELS``. The controller builds a
JSON payload via ``LabelResolver`` (``cbLabelsJson``); a hidden
``.cb-labels-json`` element and inline script merge it into
``window.CB_LABELS`` before ``widget.js`` runs.


Site language (automatic translation)
=====================================

When a visitor opens a **German** site language, TYPO3 loads
``de.locallang.xlf`` and strings such as *Adults* become *Erwachsene*.

When the demo page is **English**, you see the English source strings from
``locallang.xlf``. No FlexForm configuration is required for standard
EN/DE labels.

Occupancy hints, calendar legend, loading messages, and error texts are
**always** translated via XLF — they are not FlexForm fields.


FlexForm Texts sheet (editors)
==============================

Each plugin has a **Texts** tab in the content element record.

.. important::

   FlexForm uses ``langDisable = 1``: a custom heading you enter applies to
   **all language versions** of that content element. For different wording
   per language, use XLF or ``_LOCAL_LANG`` (integrator), not FlexForm.

Leave any field **empty** to keep the default translation for the current
page language.

+-------------+--------------------------------+------------------------------------------+
| Plugin      | FlexForm keys                  | When empty                               |
+=============+================================+==========================================+
| Widget      | ``labels.heading``             | ``widget.heading.default`` or room title |
|             | ``labels.bookNow``             | ``widget.calendar.bookNow``              |
|             | ``labels.enquiry``             | ``widget.calendar.enquiry``              |
+-------------+--------------------------------+------------------------------------------+
| Search bar  | ``labels.heading``             | ``searchbar.heading``                    |
|             | ``labels.submit``              | ``searchbar.submit``                     |
+-------------+--------------------------------+------------------------------------------+
| Room types  | ``labels.heading``             | ``roomtypes.heading`` (overview only)    |
|             | ``labels.book``                | ``roomtypes.book``                       |
|             | ``labels.details``             | ``roomtypes.details``                    |
+-------------+--------------------------------+------------------------------------------+
| Packages    | ``labels.heading``             | ``packages.heading`` (overview only)     |
|             | ``labels.book``                | ``packages.book`` / ``packages.bookNow`` |
|             | ``labels.details``             | ``packages.details``                     |
+-------------+--------------------------------+------------------------------------------+
| Price teaser| ``labels.heading``             | No heading (price line only)             |
+-------------+--------------------------------+------------------------------------------+

Labels **not** in FlexForm (XLF / ``_LOCAL_LANG`` only): *Adults*,
*Children*, *Room occupancy*, calendar legend, *Loading availability…*,
*from*, empty states, backend module strings.


TypoScript ``_LOCAL_LANG`` (integrators)
========================================

Override any locallang key site-wide without touching FlexForm:

.. code-block:: typoscript

   plugin.tx_casablancabooking._LOCAL_LANG.de {
       widget.heading.default = Jetzt Verfügbarkeit prüfen
       widget.adults = Erwachsene
       widget.calendar.bookNow = Zur Buchung
       widget.calendar.occupancy = Gäste
   }

   plugin.tx_casablancabooking._LOCAL_LANG.en {
       widget.calendar.bookNow = Reserve now
   }

Per-plugin signatures (if you need plugin-scoped overrides):

.. code-block:: typoscript

   plugin.tx_casablancabooking_widget._LOCAL_LANG.de {
       widget.heading.default = Verfügbarkeit prüfen
   }


Adding a new language
=====================

1. Copy ``locallang.xlf`` to ``Resources/Private/Language/fr.locallang.xlf``
   (or add ``<target>`` entries in a sitepackage override file).
2. Register the language in your TYPO3 site configuration.
3. Flush caches.

Agencies can also ship overrides in the sitepackage:

.. code-block:: typoscript

   plugin.tx_casablancabooking._LOCAL_LANG.fr {
       widget.adults = Adultes
   }


Locallang key reference (frontend)
==================================

Common keys agencies override:

+-------------------------------+--------------------------------+
| Key                           | Default (EN)                   |
+===============================+================================+
| ``widget.heading.default``    | Check availability and book    |
| ``widget.heading.room``       | Availability for %s            |
| ``widget.adults``             | Adults                         |
| ``widget.children``           | Children                       |
| ``widget.rooms.count``        | Rooms                          |
| ``widget.calendar.occupancy`` | Room occupancy                 |
| ``widget.calendar.bookNow``   | Book now                       |
| ``widget.calendar.enquiry``   | Enquiry                        |
| ``widget.calendar.addRoom``   | Add another room               |
| ``searchbar.heading``         | Search availability            |
| ``searchbar.submit``          | Search & book                  |
| ``searchbar.occupancyToggle`` | Guests and rooms               |
| ``roomtypes.heading``         | Our rooms                      |
| ``roomtypes.book``            | Book now                       |
| ``packages.heading``          | Packages & offers              |
| ``widget.legend.available``   | Available                      |
| ``widget.calendar.loading``   | Loading availability…          |
+-------------------------------+--------------------------------+

Full list: ``Resources/Private/Language/locallang.xlf``.


JavaScript labels
=================

``Resources/Public/JavaScript/widget.js`` must not contain user-visible
English strings. Dynamic room blocks read ``window.CB_LABELS``:

* ``adults``, ``children``, ``childrenAges``
* ``calendarBookNow``, ``calendarRemoveRoom``, ``roomN``, etc.

``Resources/Private/Partials/Calendar/LabelsScript.html`` outputs
``CB_LABELS`` from the same translations (and FlexForm overrides for
book/enquiry buttons). If you override a label in Fluid, flush caches
so the inline script is regenerated.


Fluid ViewHelper
================

Templates use ``casablanca:label`` for headings and CTAs with optional
FlexForm override:

.. code-block:: html

   <casablanca:label key="widget.calendar.bookNow"
                     override="{labels.bookNow}"
                     default="Book now" />


How to read this manual
=======================

The ``Documentation/`` folder in this extension is the **official TYPO3
extension manual** (reStructuredText). It is **not** shown inside the TYPO3
backend after installation — editors do not browse ``.rst`` files in the CMS.

Where you **do** see it:

* **docs.typo3.org** — after the extension is published (Composer / TER),
  TYPO3 Intercept builds HTML from ``Documentation/`` and hosts it on
  docs.typo3.org. The TER extension page links to that manual.
* **Git / Composer** — source files live in the repository or under
  ``vendor/casablanca/bookingengine-connector/Documentation/`` after
  ``composer require``.
* **README.md** — short overview on GitHub and Packagist.

Local HTML preview (authors only):

.. code-block:: bash

   docker run --rm -it \
     -v $(pwd):/project \
     ghcr.io/typo3-documentation/render-guides:latest \
     --path Documentation

Open the generated HTML in ``Documentation-GENERATED-temp/`` in a browser.
This is for writing and reviewing docs, not for hotel editors.

See also: :doc:`../ContentElements/Index` (FlexForm Texts per plugin),
:doc:`../Reference/Index` (TypoScript).
