<?php

namespace SmithfieldStudio\GravityFormsMultiInstanceSync;

/**
 * Gravity Forms can't run the same form twice on one page: the copies share element IDs, so the second one's
 * steps, validation and conditional logic act on the first. Each form renders once per page instead, and later
 * placements get an empty slot that the form moves into as the visitor scrolls to it or reveals it
 * (assets/multi-instance-sync.js). Without JS, a slot is a link to the form.
 */
final class Plugin {
    /** @var array<int, int> Placements rendered so far this request, by form ID */
    private array $placements = [];

    public function __construct(
        private readonly string $file,
        private readonly string $version,
    ) {}

    public function boot(): void {
        add_action('init', [$this, 'loadTextdomain']);
        add_filter('gform_get_form_filter', [$this, 'render'], 99, 2);
        add_filter('rocket_delay_js_exclusions', [$this, 'excludeFromDelayJs']);
    }

    /**
     * Loaded by path rather than load_plugin_textdomain(), so it works wherever the plugin is installed (plugins,
     * mu-plugins, a symlink). WordPress 6.5+ picks the .l10n.php file next to the .mo.
     */
    public function loadTextdomain(): void {
        $domain = 'gravity-forms-multi-instance-sync';
        $locale = determine_locale();

        load_textdomain($domain, dirname($this->file) . "/languages/{$domain}-{$locale}.mo", $locale);
    }

    /**
     * @param array<string, mixed> $form
     */
    public function render(string $formString, array $form): string {
        $id = $this->formId($form);

        if (is_admin() || wp_doing_ajax() || $id === 0) {
            return $formString;
        }

        $placement = $this->placements[$id] = ($this->placements[$id] ?? 0) + 1;

        if ($placement === 1) {
            return sprintf(
                '<div class="gf-mis-slot" id="gf-mis-form-%1$d" data-gf-mis-form="%1$d"><div class="gf-mis-slot__form">%2$s</div>%3$s</div>',
                $id,
                $formString,
                $this->link($form, true),
            );
        }

        wp_enqueue_script(
            'gf-multi-instance-sync',
            plugins_url('assets/multi-instance-sync.js', $this->file),
            [],
            $this->version,
            ['in_footer' => true, 'strategy' => 'defer'],
        );

        return sprintf(
            '<div class="gf-mis-slot" data-gf-mis-form="%1$d">%2$s</div><script id="gf-mis-move-%1$d-%3$d">%4$s</script>',
            $id,
            $this->link($form, false),
            $placement,
            $this->moveScript($id),
        );
    }

    /**
     * WP Rocket's Delay JS would hold back the move out of a hidden placement until the visitor interacts.
     *
     * @param mixed $excluded
     * @return list<string>
     */
    public function excludeFromDelayJs(mixed $excluded): array {
        $excluded = is_array($excluded) ? array_values(array_filter($excluded, 'is_string')) : [];
        $excluded[] = 'gf-mis-move-';

        return $excluded;
    }

    /**
     * @param array<string, mixed> $form
     */
    private function formId(array $form): int {
        return is_numeric($form['id'] ?? null) ? (int) $form['id'] : 0;
    }

    /**
     * Renders templates/link.php, or the theme's gravity-forms-multi-instance-sync/link.php.
     *
     * @param array<string, mixed> $form
     */
    private function link(array $form, bool $hidden): string {
        $template = locate_template('gravity-forms-multi-instance-sync/link.php')
        ?: dirname($this->file) . '/templates/link.php';

        /**
         * Path to the link template, for themes that keep views elsewhere.
         *
         * @param string $template
         * @param array $form
         */
        $template = (string) apply_filters('gform_multi_instance_sync_link_template', $template, $form);

        $attributes = sprintf(
            'href="#gf-mis-form-%d" data-gf-mis-link%s',
            $this->formId($form),
            $hidden ? ' style="display: none"' : '',
        );

        ob_start();
        (static function (string $template, array $form, string $attributes): void {
            include $template;
        })($template, $form, $attributes);

        return trim((string) ob_get_clean());
    }

    /**
     * Runs as the page is parsed, before the deferred script: pages often pair a mobile-only and a desktop-only
     * placement, so if the form sits in a hidden one and this one shows, it moves in straight away.
     */
    private function moveScript(int $id): string {
        return <<<JS
            (function (slot) {
                var current = document.getElementById('gf-mis-form-{$id}');
                var form = current && current.querySelector('.gf-mis-slot__form');
                if (!form || form.getClientRects().length || !slot.getClientRects().length) return;
                current.querySelector('[data-gf-mis-link]').style.display = '';
                current.removeAttribute('id');
                slot.querySelector('[data-gf-mis-link]').style.display = 'none';
                slot.id = 'gf-mis-form-{$id}';
                slot.prepend(form);
            })(document.currentScript.previousElementSibling);
            JS;
    }
}
