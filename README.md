# Multi-Instance Sync for Gravity Forms

Place the same Gravity Form more than once on a page, for example at the top and bottom of a landing page, or in a mobile-only and a desktop-only block.

Gravity Forms doesn't support this on its own: every copy of a form shares the same element IDs, so the second copy's steps, validation, AJAX submission and conditional logic act on the first ([Gravity Forms docs](https://docs.gravityforms.com/embedding-one-form-multiple-times-per-page/)). Plugins that rename the copies' IDs depend on matching Gravity Forms' markup exactly and break as it changes.

## How it works

- Each form renders once per page. Later placements render an empty slot with a link to the form.
- When a slot is about to scroll into view, the form moves into it: one form, so the visitor's answers, current step and conditional fields carry over.
- When a slot is revealed (a modal opening, a tab or accordion showing it), the form moves into it straight away, and back again when it closes.
- The form stays put while its current slot is near the viewport or it's submitting, and the slot it leaves keeps its height so the page above doesn't jump.
- If the form's first placement is hidden (say, a mobile-only block on desktop) and a later one shows, a small inline script moves it in as the page is parsed.
- Without JS, the slot's link jumps to the form.

## Requirements

- Gravity Forms 2.5+
- PHP 8.2+

## Installation

```sh
composer require smithfield-studio/gravity-forms-multi-instance-sync
```

Or install it as a regular plugin and activate it. No settings.

## The link

An empty slot shows a link to the form, "Go to the form", translated for da_DK, de_DE, es_ES, fi/fi_FI, fr_FR, it_IT, nb_NO, nl_NL, pt_BR, pt_PT and sv_SE (`languages/`, from `gravity-forms-multi-instance-sync.pot`).

To change its markup, copy `templates/link.php` to your theme as `gravity-forms-multi-instance-sync/link.php`. Keep `$attributes` on the link: the script uses them to find it and show or hide it.

```php
<a class="button button--primary" <?php echo $attributes; ?>><?php esc_html_e('Book a demo', 'my-theme'); ?></a>
```

For themes that keep views elsewhere, `gform_multi_instance_sync_link_template` filters the template's path.

## WP Rocket

The plugin excludes its inline scripts from WP Rocket's Delay JavaScript Execution, so a form in a hidden placement still moves to a visible one before the visitor interacts. The scroll script (`assets/multi-instance-sync.js`) can be delayed: until it runs, later placements show their link.

## Limitations

- Only one copy of each form exists on the page, so two placements can't show the form at the same time. If both are in view, the second one shows its link.
- Without JS, a link in a visible placement may point at a form in a hidden one.

## Development

```sh
composer install && npm install

composer format        # Mago
composer phpstan       # PHPStan, level 10
npm run check          # Oxfmt + Oxlint
npm run test:e2e       # Playwright, against fixture pages rendered by the plugin's PHP

bin/install-wp-tests.sh wordpress_test root '' 127.0.0.1 latest
composer test          # PHPUnit, against the WordPress test suite
```

Translations: `npm run translate:make-pot`, update the `.po` files, then `npm run translate:make-mo` and `npm run translate:make-php`.

CI runs all of these on pull requests to `main`.

