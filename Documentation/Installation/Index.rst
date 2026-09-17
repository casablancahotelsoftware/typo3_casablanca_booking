.. include:: /Includes.rst.txt

============
Installation
============

Composer
========

Install the package ``casablanca/bookingengine-connector`` (TYPO3 extension key
``casablanca_booking``):

.. code-block:: bash

   composer require casablanca/bookingengine-connector
   vendor/bin/typo3 extension:setup -e casablanca_booking

Activate the extension in the Extension Manager, then open
**Tools → CASABLANCA Booking** and enter your Tenant ID and API key.

Scheduler
=========

On first save the extension creates a daily scheduler task (staggered to the save time) and runs an initial sync when the connection check succeeds.
You can also run:

.. code-block:: bash

   vendor/bin/typo3 casablanca-booking:sync
