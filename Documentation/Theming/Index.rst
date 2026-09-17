.. include:: /Includes.rst.txt

=======
Theming
=======

All frontend widgets share a single stylesheet,
``Resources/Public/Css/widget.css``. Every visual element is namespaced
under the ``.cb-`` CSS prefix. Colours, spacing, and typography are
expressed as CSS custom properties on the ``.cb-widget`` scope so hotels
and agencies can re-theme without forking the extension.

This page is a theming cookbook: design tokens, configuration layers,
appearance modes, and practical examples.


Architecture overview
===================

Theming is applied in layers (lowest to highest priority):

1. **Default CSS** — ``widget.css`` ships with the extension and defines
   all ``--cb-*`` tokens and component rules.
2. **Site settings / TypoScript** — global ``theme.accent``,
   ``theme.accentContrast``, and ``theme.radius`` are injected as inline
   styles on the widget wrapper (see :doc:`../Reference/Index`).
3. **FlexForm appearance** — per content element: ``default``,
   ``inherit``, or ``compact`` (outer chrome only, not colours).
4. **Agency CSS** — override ``--cb-*`` tokens in the sitepackage
   stylesheet.
5. **Fluid overrides** — replace templates, partials, or layouts via
   ``plugin.tx_casablancabooking.view.*RootPaths``.

JavaScript (``widget.js``) is always loaded. CSS can be disabled when the
sitepackage provides its own styling (see :ref:`theming-include-css`).


CSS design tokens
=================

All tokens are declared on ``.cb-widget``. Override them in your
sitepackage by scoping to the widget class or a page wrapper:

.. code-block:: css

   .cb-widget {
       --cb-accent: #0a6e5e;
       --cb-accent-contrast: #ffffff;
   }

Typography and layout
---------------------

+---------------------------+---------------+------------------------------------------+
| Token                     | Default       | Used for                                 |
+===========================+===============+==========================================+
| ``--cb-font-family``      | ``inherit``   | Base font for all widget text            |
| ``--cb-radius``           | ``6px``       | Buttons, inputs, calendar cells          |
| ``--cb-card-radius``      | ``8px``       | Room/package cards, detail images        |
| ``--cb-gap``              | ``0.75rem``   | Grid and form field spacing              |
| ``--cb-space``            | ``1.25rem``   | Outer widget padding (compact overrides) |
| ``--cb-border-color``     | ``#d9d9d9``   | Widget border, inputs, room blocks       |
+---------------------------+---------------+------------------------------------------+

Base colours
------------

+---------------------------+---------------+------------------------------------------+
| Token                     | Default       | Used for                                 |
+===========================+===============+==========================================+
| ``--cb-bg``               | ``#ffffff``   | Widget background                        |
| ``--cb-fg``               | ``#1a1a1a``   | Primary text                             |
| ``--cb-muted``            | ``#6b6b6b``   | Labels, hints, secondary text            |
| ``--cb-surface``          | ``#f3f3f3``   | Calendar sidebar background              |
| ``--cb-input-bg``         | ``#ffffff``   | Form inputs and selects                  |
| ``--cb-focus-ring``       | ``var(--cb-accent)`` | Focus outline on inputs           |
| ``--cb-error``            | ``#b42318``   | Error messages                           |
+---------------------------+---------------+------------------------------------------+

Accent and buttons
------------------

+---------------------------+---------------+------------------------------------------+
| Token                     | Default       | Used for                                 |
+===========================+===============+==========================================+
| ``--cb-accent``           | ``#8b1e1e``   | Primary brand colour (CASABLANCA red)    |
| ``--cb-accent-contrast``  | ``#ffffff``   | Text on accent buttons                   |
| ``--cb-button-bg``        | ``var(--cb-accent)`` | Primary button background         |
| ``--cb-button-fg``        | ``var(--cb-accent-contrast)`` | Primary button text        |
| ``--cb-button-radius``    | ``var(--cb-radius)`` | Button corner radius              |
| ``--cb-button-padding``   | ``0.625rem 1.25rem`` | Primary button padding            |
+---------------------------+---------------+------------------------------------------+

Cards and prices
----------------

+---------------------------+---------------+------------------------------------------+
| Token                     | Default       | Used for                                 |
+===========================+===============+==========================================+
| ``--cb-card-bg``          | ``var(--cb-bg)`` | Card background                     |
| ``--cb-card-border``      | ``var(--cb-border-color)`` | Card border                 |
| ``--cb-card-shadow``      | ``0 1px 3px rgba(0,0,0,0.06)`` | Card elevation          |
| ``--cb-price-fg``         | ``var(--cb-accent)`` | Price highlights                  |
| ``--cb-price-size``       | ``1.375rem``  | Card price font size                     |
+---------------------------+---------------+------------------------------------------+

Calendar availability states
----------------------------

+-------------------------------+---------------+--------------------------------------+
| Token                         | Default       | Used for                             |
+===============================+===============+======================================+
| ``--cb-state-available-bg``   | ``#e9f5ea``   | Available day background             |
| ``--cb-state-available-fg``   | ``#1f6a2a``   | Available day text                   |
| ``--cb-state-restricted-bg``  | ``#fff4d9``   | Restricted day background            |
| ``--cb-state-restricted-fg``  | ``#8a5a00``   | Restricted day text                  |
| ``--cb-state-no-arrival-bg``  | ``#eceff3``   | No-arrival day background            |
| ``--cb-state-no-arrival-fg``  | ``#4a5568``   | No-arrival day text                  |
| ``--cb-state-unavailable-bg`` | ``#f5e6e6``   | Unavailable day background           |
| ``--cb-state-unavailable-fg`` | ``#8a2a2a``   | Unavailable day text                 |
+-------------------------------+---------------+--------------------------------------+

Calendar interaction
--------------------

+-------------------------------+---------------+--------------------------------------+
| Token                         | Default       | Used for                             |
+===============================+===============+======================================+
| ``--cb-stepper-bg``           | ``#e8e8e8``   | Occupancy stepper row background     |
| ``--cb-stepper-btn-bg``       | ``#dddddd``   | Stepper +/- buttons                  |
| ``--cb-selection-bg``         | ``#f5d547``   | Selected date range background       |
| ``--cb-selection-fg``         | ``#1a1a1a``   | Selected date range text             |
| ``--cb-enquiry-bg``           | ``var(--cb-selection-bg)`` | Enquiry button background |
| ``--cb-enquiry-fg``           | ``var(--cb-selection-fg)`` | Enquiry button text       |
| ``--cb-past-bg``              | ``#ececec``   | Past day cell background             |
| ``--cb-past-fg``              | ``#aaaaaa``   | Past day cell text                   |
| ``--cb-past-mark``            | ``#bbbbbb``   | Past day strike-through mark         |
+-------------------------------+---------------+--------------------------------------+

Offer selection sidebar
-----------------------

+-------------------------------+---------------+--------------------------------------+
| Token                         | Default       | Used for                             |
+===============================+===============+======================================+
| ``--cb-offer-bg``             | ``#ffffff``   | Offer row background                 |
| ``--cb-offer-border``         | ``#e2e2e2``   | Offer row border                     |
| ``--cb-offer-selected-bg``    | ``#fffbea``   | Selected offer background            |
| ``--cb-offer-selected-border``| ``#c9a900``   | Selected offer border                |
| ``--cb-clear-bg``             | ``#ffffff``   | Date-clear button background         |
| ``--cb-clear-fg``             | ``#333333``   | Date-clear button icon               |
| ``--cb-clear-hover-bg``       | ``#f0f0f0``   | Date-clear button hover              |
+-------------------------------+---------------+--------------------------------------+


Configuration layers in detail
==============================

Layer 1: Default CSS
--------------------

The extension registers ``widget.css`` automatically unless disabled.
The file defines every ``--cb-*`` token and all ``.cb-*`` component
classes. You do not need to copy this file — override tokens instead.


Layer 2: Site settings (TYPO3 v13+)
-----------------------------------

When the ``casablanca/booking`` site set is active, configure global
theme values in **Site Management → Settings → CASABLANCA Booking**:

* **Accent color** → ``--cb-accent``
* **Accent contrast color** → ``--cb-accent-contrast``
* **Border radius** → ``--cb-radius``

These are rendered as inline ``style`` attributes on the widget
``<section>`` wrapper. See :doc:`../Reference/Index` for the full
settings table.


Layer 3: FlexForm appearance
----------------------------

Each content element has an **Appearance** sheet with **Layout style**:

+-------------+---------------------------------------------------------------+
| Value       | Effect                                                        |
+=============+===============================================================+
| ``default`` | Card with border, padding, and white background (default)     |
| ``inherit`` | Transparent background, no border, no padding — blends into   |
|             | the page layout                                               |
| ``compact`` | Reduced ``--cb-gap`` (0.5rem) and ``--cb-space`` (0.75rem)  |
+-------------+---------------------------------------------------------------+

Appearance affects outer chrome only. It does **not** change colours.
Use **inherit** in headers/footers where the sitepackage already provides
a styled container. Use **compact** in sidebars or narrow columns.


Layer 4: Agency CSS
-------------------

Add overrides to your sitepackage stylesheet **after** the extension CSS
(or with ``includeCss = 0`` and your own copy of the rules):

.. code-block:: css

   /* Brand the booking widgets site-wide */
   .cb-widget {
       --cb-accent: #0a6e5e;
       --cb-accent-contrast: #ffffff;
       --cb-radius: 4px;
       --cb-card-radius: 4px;
       --cb-font-family: "Source Sans 3", sans-serif;
   }

   /* Secondary buttons already use accent via .cb-button--secondary */
   .cb-button--secondary {
       --cb-button-bg: transparent;
   }


Layer 5: Fluid overrides
------------------------

Point TypoScript view paths to your sitepackage:

.. code-block:: typoscript

   plugin.tx_casablancabooking.view {
       templateRootPaths.20 = EXT:my_sitepackage/Resources/Private/Extensions/CasablancaBooking/Templates/
       partialRootPaths.20 = EXT:my_sitepackage/Resources/Private/Extensions/CasablancaBooking/Partials/
       layoutRootPaths.20 = EXT:my_sitepackage/Resources/Private/Extensions/CasablancaBooking/Layouts/
   }

The default layout wraps content in:

.. code-block:: html

   <section class="{cbWidgetClasses}" style="{cbThemeStyle}" ...>

Copy ``Resources/Private/Layouts/Default.html`` as a starting point.


.. _theming-include-css:

Disabling bundled CSS (includeCss = 0)
======================================

Set ``includeCss`` to ``0`` (or ``false`` in Site Settings) when your
sitepackage ships a complete replacement stylesheet:

**TypoScript (v12 constants module):**

.. code-block:: typoscript

   plugin.tx_casablancabooking.includeCss = 0

**Site Settings (v13+):**

Disable **Include default widget CSS** in the Site editor, or in
``settings.yaml``:

.. code-block:: yaml

   casablancaBooking:
     includeCss: false

When ``includeCss`` is disabled:

* ``widget.js`` is still loaded (calendar interactivity, occupancy
  steppers, IBE handover).
* Your sitepackage must define all required ``.cb-*`` classes **or** copy
  ``widget.css`` and customise tokens.
* Inline theme values from Site Settings (``theme.accent`` etc.) still
  apply via ``cbThemeStyle``.


Examples
========

Hotel brand colours
-------------------

.. code-block:: css

   .cb-widget {
       --cb-accent: #2c5282;
       --cb-accent-contrast: #ffffff;
       --cb-price-fg: #2c5282;
       --cb-button-bg: var(--cb-accent);
   }

Flat design (no shadows, square corners)
-----------------------------------------

.. code-block:: css

   .cb-widget {
       --cb-radius: 0;
       --cb-card-radius: 0;
       --cb-card-shadow: none;
   }

Dark header search bar (inherit appearance)
--------------------------------------------

In the Search Bar content element, set **Layout style** to **Inherit**.
Then style the parent container in your sitepackage:

.. code-block:: css

   .site-header .cb-widget--inherit {
       --cb-fg: #ffffff;
       --cb-muted: rgba(255, 255, 255, 0.7);
       --cb-input-bg: rgba(255, 255, 255, 0.1);
       --cb-border-color: rgba(255, 255, 255, 0.3);
   }

TypoScript accent only (no custom CSS file)
-------------------------------------------

.. code-block:: typoscript

   plugin.tx_casablancabooking.theme {
       accent = #0a6e5e
       accentContrast = #ffffff
       radius = 4px
   }


Do's and don'ts
===============

**Do**

* Override ``--cb-*`` tokens rather than editing ``widget.css`` directly.
* Use the ``.cb-`` prefix for any custom selectors targeting widget
  internals.
* Set **Layout style → Inherit** when embedding widgets inside an
  already-styled site header or hero.
* Disable ``includeCss`` only when your sitepackage provides equivalent
  ``.cb-*`` rules.
* Test calendar state colours (available, restricted, unavailable) after
  changing accent colours — they use separate state tokens.

**Don't**

* Do not remove the ``cb-widget`` class from Fluid overrides — tokens
  are scoped to it.
* Do not fork ``widget.css`` for a colour change alone; token overrides
  are sufficient.
* Do not rely on un-prefixed class names (``button``, ``card``) — they
  are not used.
* Do not disable JavaScript — the calendar, multi-room occupancy, and
  new-tab IBE links require ``widget.js``.
* Do not expect FlexForm **Layout style** to change colours; use Site
  Settings or agency CSS for that.
