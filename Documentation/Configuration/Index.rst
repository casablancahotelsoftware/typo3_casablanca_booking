.. include:: /Includes.rst.txt

=============
Configuration
=============

Backend module
==============

Open **Tools → CASABLANCA Booking** to manage one configuration record
per TYPO3 site. Database configuration takes precedence over optional
``casablanca_booking:`` keys in ``config/sites/*/config.yaml``.


Module overview
---------------

The module has two views:

* **Index** — lists all saved site configurations with connection status.
* **Edit** — create or edit a configuration; includes **Configuration**
  and **Mapping codes** tabs.

Actions available on saved configurations:

* **Save** — persist credentials and IBE settings; runs connection check.
* **Test connection** — verify API credentials without a full sync.
* **Sync now** — import availability, room types, and rates immediately.
* **Cancel** — return to the index without saving.


Configuration tab — field reference
-----------------------------------

Credentials
~~~~~~~~~~~

+----------------------------+----------+---------------+------------------------------------------+
| Field                      | Required | Default       | Effect                                   |
+============================+==========+===============+==========================================+
| **Site identifier**        | Yes      | —             | TYPO3 site this configuration belongs to.|
|                            |          |               | Select on create; read-only after save.  |
|                            |          |               | Must match ``config/sites/*/config.yaml``|
|                            |          |               | site identifier.                         |
+----------------------------+----------+---------------+------------------------------------------+
| **Tenant ID**              | Yes      | —             | CASABLANCA tenant UUID from the IBE      |
|                            |          |               | admin panel. Required for API and sync.  |
+----------------------------+----------+---------------+------------------------------------------+
| **API key**                | Yes (new)| —             | CASABLANCA API key. Stored **encrypted** |
|                            |          |               | in the database. On edit, leave blank to |
|                            |          |               | keep the existing key. Never stored in   |
|                            |          |               | site YAML or environment variables.      |
+----------------------------+----------+---------------+------------------------------------------+

Booking Engine and Space settings
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

+----------------------------+----------+---------------+------------------------------------------+
| Field                      | Required | Default       | Effect                                   |
+============================+==========+===============+==========================================+
| **Use custom IBE domain**  | No       | unchecked     | When checked, indicates a non-CASABLANCA |
|                            |          |               | hosted booking domain. Affects link-style |
|                            |          |               | validation warnings.                     |
+----------------------------+----------+---------------+------------------------------------------+
| **IBE base URL**           | No       | ``https://bookingengine.casablanca.at`` | Base URL for frontend IBE handover links. |
+----------------------------+----------+---------------+------------------------------------------+
| **Space name**             | No       | ``bookingengine`` | IBE context slug (URL-friendly space   |
|                            |          |               | ID). In CASABLANCA terminology the       |
|                            |          |               | **Space** (formerly *Betrieb*) is the    |
|                            |          |               | IBE context. Required for ``full_path``  |
|                            |          |               | link style.                              |
+----------------------------+----------+---------------+------------------------------------------+
| **Link style**             | No       | ``full_path`` | Controls IBE handover URL path pattern.  |
|                            |          |               | See :ref:`configuration-link-styles`.    |
+----------------------------+----------+---------------+------------------------------------------+
| **Booking Engine URL**     | —        | *(preview)*   | Read-only live preview of the resulting  |
|                            |          |               | IBE URL based on current field values.   |
|                            |          |               | Updates as you edit (JavaScript).        |
+----------------------------+----------+---------------+------------------------------------------+
| **Default culture**        | No       | ``de``        | Default IBE culture segment (e.g. ``de``,|
|                            |          |               | ``en``). Used when FlexForm language is |
|                            |          |               | Auto and for API sync requests.            |
+----------------------------+----------+---------------+------------------------------------------+

Sync
~~~~

The Sync section displays informational text only (no editable fields in
the UI). Sync behaviour is controlled by internal defaults on the
configuration record:

+----------------------------+----------+---------------+------------------------------------------+
| Database field             | UI field | Default       | Effect                                   |
+============================+==========+===============+==========================================+
| ``sync_range_days``        | —        | ``365``       | How many days ahead availability is      |
|                            |          |               | imported per sync run.                   |
+----------------------------+----------+---------------+------------------------------------------+
| ``sync_chunk_days``        | —        | ``31``        | API request chunk size (days per call).  |
+----------------------------+----------+---------------+------------------------------------------+
| ``pagination_top``         | —        | ``100``       | API pagination page size.                |
+----------------------------+----------+---------------+------------------------------------------+
| ``default_adults``         | —        | ``2``         | Fallback default adults when not set in  |
|                            |          |               | FlexForm or TypoScript.                  |
+----------------------------+----------+---------------+------------------------------------------+
| ``default_children_ages``| —        | *(empty)*     | Fallback child ages (comma-separated in  |
|                            |          |               | DB). Used for occupancy defaults.        |
+----------------------------+----------+---------------+------------------------------------------+
| ``api_base_url``           | —        | ``https://api.casablanca.at`` | CASABLANCA API host.       |
+----------------------------+----------+---------------+------------------------------------------+
| ``service_path``           | —        | ``ibe``       | API path segment between host and tenant.|
+----------------------------+----------+---------------+------------------------------------------+

Automatic sync schedule:

* A daily TYPO3 Scheduler task is created on first save (staggered to
  the save time).
* An initial sync runs when a **new** configuration passes the connection
  check.
* Sync also triggers after backend cache clears.


Connection status
~~~~~~~~~~~~~~~~~

After save or **Test connection**, the module shows:

+-------------+---------------------------------------------------------------+
| Status      | Meaning                                                       |
+=============+===============================================================+
| OK (green)  | API credentials verified successfully.                        |
| Error (red) | Connection failed — check Tenant ID, API key, and network.    |
| Unknown     | Not tested yet.                                               |
+-------------+---------------------------------------------------------------+

Displays last-checked timestamp and error message when available.


Mapping codes tab
-----------------

Visible only when connection status is **OK**. Lists synced entities with
PMS mapping codes for editors:

* **Space** — space title, Space ID
* **Company ID**
* **Room types** — Id, Name, Type, Code, Min./Std./Max. occupancy
* **Rates (all)** — standard rates
* **Packages** — package rates

Requires at least one successful sync. Use **Sync now** if the tab shows
*No mapping codes synced yet*.


Expected workflow
-----------------

1. **Add configuration** — choose a TYPO3 **Site identifier**, enter **Tenant
   ID** and **API key**, click **Save**.
2. **Edit configuration** — after save the URL contains the configuration
   ``uid``; a flash message confirms the record was stored (encrypted API key in
   the database).
3. **Connection check** — runs automatically on save; the status indicator
   shows green (OK), red (failed), or grey (not tested yet).
4. **Automatic sync** — on first save the extension creates a daily TYPO3
   Scheduler task (staggered to the save time) and runs an initial import when
   the connection check succeeds. Sync also runs after backend cache clears.
5. **Sync now** — imports availability, room types, and rates for the site
   immediately.
6. **Mapping codes** — tab appears when connection status is OK; lists synced
   room types, rates, and packages with PMS codes for editors.

Use **Test connection** anytime to re-verify credentials without running a
full sync. The scheduler task can be inspected under **System → Scheduler**.

In CASABLANCA terminology the **Space** (formerly *Betrieb*) is the IBE context
configured as **Space name**.


.. _configuration-link-styles:

IBE link styles
===============

Link style affects **frontend handover URLs only**. API sync URLs always use
``{apiBaseUrl}/{servicePath}/{tenant}/{space}/...``.

+---------------+------------------------------------------+----------------------------------------+
| Value         | Resulting path                           | When to use                            |
+===============+==========================================+========================================+
| ``full_path`` | ``{base}/{culture}/{tenant}/{space}``  | Default CASABLANCA domain              |
+---------------+------------------------------------------+----------------------------------------+
| ``culture_space`` | ``{base}/{culture}/{space}``       | Custom domain; proxy injects tenant    |
+---------------+------------------------------------------+----------------------------------------+
| ``culture_only`` | ``{base}/{culture}``                  | Custom domain; proxy injects tenant    |
|               |                                          | and space (e.g. Cloudflare worker)     |
+---------------+------------------------------------------+----------------------------------------+

Example query string (all styles):

.. code-block:: text

   ?arrivalDate=2026-06-01&departureDate=2026-06-08&numberOfRooms=1&rooms_0__adults=2&rooms_0__children=0

The backend module shows a live **Booking Engine URL** preview when editing
configuration, including a pattern line (e.g. ``Pattern: {culture}/{space}``).
The preview updates when tenant, space, culture, link style, or IBE base URL
changes (readable in light and dark backend mode).

Validation
----------

* ``full_path`` requires a space name
* ``culture_space`` and ``culture_only`` with the default CASABLANCA domain show
  a non-blocking backend warning (intended for custom IBE domains)
* Legacy ``tenant_only`` values are normalized to ``culture_space`` on load


API key storage
===============

API keys are entered only in **Tools → CASABLANCA Booking** and stored encrypted
in the database. Environment variables and plain-text keys in site YAML are not
supported.


Site YAML (optional)
====================

Developers may still add ``casablanca_booking:`` to ``config/sites/*/config.yaml``.
Database configuration from the backend module takes precedence when present.

Optional YAML key for link style:

.. code-block:: yaml

   casablanca_booking:
     ibeLinkStyle: full_path   # or culture_space, culture_only
