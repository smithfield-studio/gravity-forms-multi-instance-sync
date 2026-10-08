# Changelog

## 1.0.1

- The main script is excluded from WP Rocket's Delay JS by its file name, so the exclusion holds when the script tag's id is stripped (e.g. by Soil's clean-up).

## 1.0.0

- Each Gravity Form renders once per page and moves between its placements: into a placement as it nears the viewport, or as soon as it's revealed (a modal, tab, accordion or menu, by `display` or `visibility`), keeping the visitor's answers and step. When its placement is hidden again, it moves to a shown one.
- A form in a hidden placement moves to a visible one as the page is parsed. The plugin's scripts are excluded from WP Rocket's Delay JS.
- Empty placements show a link to the form, from a template a theme can override, translated for 12 locales. Translations in wp-content/languages/plugins take precedence.
- Requires WordPress 6.3 and PHP 8.4.
