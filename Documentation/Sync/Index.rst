.. include:: /Includes.rst.txt

====
Sync
====

Background sync
===============

On the first save in **Tools → CASABLANCA Booking**, the extension:

* registers a TYPO3 Scheduler task (**CASABLANCA availability sync**) that runs
  ``casablanca-booking:sync`` once per day for all configured sites
* schedules the daily run at the hour and minute of that first save (per instance),
  so API load is spread across customers
* runs an initial sync immediately when a new configuration is saved and the
  connection check succeeds

Sync also runs automatically when an administrator clears the **frontend cache**
or **all caches** in the TYPO3 backend (and via ``cache:flush --group=pages`` on
the CLI).

The task remains visible under **System → Scheduler** for inspection; no manual
setup is required.

Command
=======

.. code-block:: bash

   vendor/bin/typo3 casablanca-booking:sync [--site=main] [--force] [--days=14]

Tables
======

* ``tx_casablancabooking_domain_model_availability`` — per-day ARI cache
* ``tx_casablancabooking_domain_model_roomtype`` — room metadata
* ``tx_casablancabooking_domain_model_rate`` — rates and packages
* ``tx_casablancabooking_domain_model_synclog`` — audit trail

Cache invalidation
==================

Changed rows flush targeted page-cache tags so only affected widgets refresh.
