<?php

namespace SmithfieldStudio\GravityFormsMultiInstanceSync;

/**
 * Gravity Forms can't run the same form twice on one page: the copies share element IDs, so the second one's
 * steps, validation and conditional logic act on the first. Each form renders once per page instead, and later
 * placements get an empty slot that the form moves into as the visitor scrolls (assets/multi-instance-sync.js).
 * Without JS, a slot is a link to the form.
 */
final class Plugin
{
    /** @var array<int, int> Placements rendered so far this request, by form ID */
    private array $placements = [];

    public function __construct(
        private readonly string $file,
        private readonly string $version,
    ) {}

    public function boot(): void
    {
        add_action('init', [$this, 'loadTextdomain']);
        add_filter('gform_get_form_filter', [$this, 'render'], 99, 2);
        add_filter('rocket_delay_js_exclusions', [$this, 'excludeFromDelayJs']);
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain('gravity-forms-multi-instance-sync', false, dirname(plugin_basename($this->file)) . '/languages');
    }

    /**
     * @param array<string, mixed> $form
     */
    public function render(string $formString, array $form): string
    {
        if (is_admin() || wp_doing_ajax() || empty($form['id'])) {
            return $formString;
        }

        $id = (int) $form['id'];
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
     * @return array<int, string>
     */
    public function excludeFromDelayJs(mixed $excluded): array
    {
        $excluded = is_array($excluded) ? $excluded : [];
        $excluded[] = 'gf-mis-move-';

        return $excluded;
    }

    /**
     * @param array<string, mixed> $form
     */
    private function link(array $form, bool $hidden): string
    {
        /**
         * Text for the link that stands in for the form in an empty placement.
         *
         * @param string $text
         * @param array $form
         */
        $label = (string) apply_filters(
            'gform_multi_instance_sync_link_text',
            __('Go to the form', 'gravity-forms-multi-instance-sync'),
            $form,
        );

        /**
         * Classes for the link that stands in for the form in an empty placement, e.g. the theme's button classes.
         *
         * @param string $classes
         * @param array $form
         */
        $classes = (string) apply_filters('gform_multi_instance_sync_link_class', 'gf-mis-slot__link button', $form);

        return sprintf(
            '<a class="%s" href="#gf-mis-form-%d"%s>%s</a>',
            esc_attr($classes),
            (int) $form['id'],
            $hidden ? ' style="display: none"' : '',
            esc_html($label),
        );
    }

    /**
     * Runs as the page is parsed, before the deferred script: pages often pair a mobile-only and a desktop-only
     * placement, so if the form sits in a hidden one and this one shows, it moves in straight away.
     */
    private function moveScript(int $id): string
    {
        return <<<JS
            (function (slot) {
                var current = document.getElementById('gf-mis-form-{$id}');
                var form = current && current.querySelector('.gf-mis-slot__form');
                if (!form || form.getClientRects().length || !slot.getClientRects().length) return;
                current.querySelector('a[href="#gf-mis-form-{$id}"]').style.display = '';
                current.removeAttribute('id');
                slot.querySelector('a[href="#gf-mis-form-{$id}"]').style.display = 'none';
                slot.id = 'gf-mis-form-{$id}';
                slot.prepend(form);
            })(document.currentScript.previousElementSibling);
            JS;
    }
}
