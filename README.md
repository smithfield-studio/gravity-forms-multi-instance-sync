# Multi-Instance Sync for Gravity Forms

Place the same Gravity Form more than once on a page, for example at the top and bottom of a landing page, or in a mobile-only and a desktop-only block.

Gravity Forms doesn't support this on its own: every copy of a form shares the same element IDs, so the second copy's steps, validation, AJAX submission and conditional logic act on the first ([Gravity Forms docs](https://docs.gravityforms.com/embedding-one-form-multiple-times-per-page/)). Plugins that rename the copies' IDs depend on matching Gravity Forms' markup exactly and break as it changes.

## How it works

- Each form renders once per page. Later placements render an empty slot with a link to the form.
- When a slot is about to scroll into view, the form moves into it: one form, so the visitor's answers, current step and conditional fields carry over.
- The form stays put while its current slot is near the viewport or it's submitting, and the slot it leaves keeps its height so the page above doesn't jump.
- If the form's first placement is hidden (say, a mobile-only block on desktop) and a later one shows, a small inline script moves it in as the page is parsed.
- Without JS, the slot's link jumps to the form.

## Requirements

- Gravity Forms 2.5+
- PHP 8.2+
- WordPress 6.5+ (for the bundled translations)

## Installation

```sh
composer require smithfield-studio/gravity-forms-multi-instance-sync
```

Or install it as a regular plugin and activate it. No settings.

## Filters

### `gform_multi_instance_sync_link_text`

Text for the link in an empty slot. Defaults to "Go to the form", translated for da_DK, de_DE, es_ES, fi/fi_FI, fr_FR, it_IT, nb_NO, nl_NL, pt_BR, pt_PT and sv_SE.

```php
add_filter('gform_multi_instance_sync_link_text', fn ($text, $form) => $form['button']['text'] ?: $text, 10, 2);
```

### `gform_multi_instance_sync_link_class`

Classes for the same link. Defaults to `gf-mis-slot__link button`.

```php
add_filter('gform_multi_instance_sync_link_class', fn () => 'gf-mis-slot__link wp-block-button__link');
```

## WP Rocket

The plugin excludes its inline scripts from WP Rocket's Delay JavaScript Execution, so a form in a hidden placement still moves to a visible one before the visitor interacts. The scroll script (`assets/multi-instance-sync.js`) can be delayed: until it runs, later placements show their link.

## Limitations

- Only one copy of each form exists on the page, so two placements can't show the form at the same time. If both are in view, the second one shows its link.
- Without JS, a link in a visible placement may point at a form in a hidden one.
