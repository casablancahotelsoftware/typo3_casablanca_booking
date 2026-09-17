.. include:: /Includes.rst.txt

============
Introduction
============

What this extension does
========================

* **Background sync** — pulls room types, rates/packages, calendar dates and
  inventory from the CASABLANCA IBE v2 API into local tables.
* **SSR widgets** — five content elements replace the legacy JavaScript web
  widgets with crawlable HTML and JSON-LD.
* **PCI-safe handover** — guests are redirected to the CASABLANCA booking
  engine; TYPO3 never handles payment data.


Replaces legacy widgets
=======================

+----------------------------+--------------------------------+
| Legacy widget              | TYPO3 content element          |
+============================+================================+
| Buchbarkeitskalender       | **Availability calendar**      |
| Zimmertypen-Widget         | **Room types**                 |
| Pauschalen-Widget          | **Packages**                   |
| Header booking snippet     | **Search bar**                 |
| SEO price block            | **Price teaser**               |
+----------------------------+--------------------------------+


Compatibility
=============

+-------------+------------------------------------------+
| Component   | Supported versions                       |
+=============+==========================================+
| TYPO3       | **12.4 – 14.x** (12.4.0 through 14.99.99)|
| PHP         | **8.1 – 8.4**                            |
| Scheduler   | Required (daily sync task)               |
+-------------+------------------------------------------+

Tested against TYPO3 LTS releases 12.4 and 13.4, and TYPO3 v14.x development
releases. Use the ``casablanca/booking`` site set on TYPO3 v13+ for Site
Settings integration.


License
=======

The extension source code and bundled documentation are licensed under the
**GNU Affero General Public License v3.0 only** (AGPL-3.0-only).

* **Copyright:** CASABLANCA hotelsoftware GmbH
* **Author:** Martin Hairer
* **Full license text:** ``LICENSE`` in the extension root

If you modify this extension and make it available to users over a network,
AGPL requires you to offer corresponding source code to those users. See the
``LICENSE`` file for the complete terms.


Documentation map
=================

* :doc:`../Installation/Index` — install via Composer, activate site set
* :doc:`../Configuration/Index` — backend module, credentials, IBE link styles
* :doc:`../ContentElements/Index` — all five plugins and ViewHelpers
* :doc:`../Localization/Index` — frontend labels, translations, FlexForm Texts
* :doc:`../Reference/Index` — TypoScript and Site Settings reference
* :doc:`../Theming/Index` — CSS tokens, appearance modes, agency theming
* :doc:`../Sync/Index` — CLI sync command and scheduler


How to read this manual
=======================

The ``Documentation/`` directory contains the **extension manual** in
reStructuredText format. Installing the extension on a hotel site does
**not** add a documentation module in the TYPO3 backend — editors change
texts via FlexForm **Texts** sheets and see translated labels on the
frontend.

After publishing to TER / Packagist, the manual is built as HTML on
`docs.typo3.org <https://docs.typo3.org>`__ and linked from the extension
page. In your project the sources live next to the extension (git or
``vendor/casablanca/bookingengine-connector/Documentation/``).

See :doc:`../Localization/Index` for details on local preview and label
customisation.
