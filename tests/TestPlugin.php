<?php

use SmithfieldStudio\GravityFormsMultiInstanceSync\Plugin;

class TestPlugin extends WP_UnitTestCase {
    private Plugin $plugin;

    /** @var array<string, mixed> */
    private array $form = ['id' => 7, 'button' => ['text' => 'Book a demo']];

    private string $markup = "<div class='gform_wrapper' id='gform_wrapper_7'><form id='gform_7'></form></div>";

    public function set_up(): void {
        parent::set_up();
        $this->plugin = new Plugin(dirname(__DIR__) . '/gravity-forms-multi-instance-sync.php', 'test');
        wp_dequeue_script('gf-multi-instance-sync');
    }

    public function test_first_placement_renders_the_form_with_a_hidden_link(): void {
        $html = $this->plugin->render($this->markup, $this->form);

        $this->assertStringContainsString('id="gf-mis-form-7"', $html);
        $this->assertStringContainsString('<div class="gf-mis-slot__form">' . $this->markup . '</div>', $html);
        $this->assertStringContainsString('href="#gf-mis-form-7" data-gf-mis-link style="display: none"', $html);
        $this->assertFalse(wp_script_is('gf-multi-instance-sync', 'enqueued'));
    }

    public function test_later_placements_render_a_slot_link_and_mover_instead_of_the_form(): void {
        $this->plugin->render($this->markup, $this->form);
        $html = $this->plugin->render($this->markup, $this->form);

        $this->assertStringNotContainsString('gform_wrapper_7', $html);
        $this->assertStringContainsString('<div class="gf-mis-slot" data-gf-mis-form="7">', $html);
        $this->assertStringContainsString('href="#gf-mis-form-7" data-gf-mis-link>Go to the form</a>', $html);
        $this->assertStringContainsString('<script id="gf-mis-move-7-2">', $html);
        $this->assertTrue(wp_script_is('gf-multi-instance-sync', 'enqueued'));
    }

    public function test_each_form_is_counted_separately(): void {
        $this->plugin->render($this->markup, $this->form);
        $other = $this->plugin->render("<div id='gform_wrapper_8'></div>", ['id' => 8]);

        $this->assertStringContainsString('id="gf-mis-form-8"', $other);
    }

    public function test_admin_requests_are_left_alone(): void {
        set_current_screen('dashboard');

        $this->assertSame($this->markup, $this->plugin->render($this->markup, $this->form));

        set_current_screen('front');
    }

    public function test_the_link_template_can_be_replaced(): void {
        $template = get_temp_dir() . 'gf-mis-link-test.php';
        file_put_contents($template, '<a class="custom" <?php echo $attributes; ?>>Custom</a>');
        $filter = fn(): string => $template;
        add_filter('gform_multi_instance_sync_link_template', $filter);

        $this->plugin->render($this->markup, $this->form);
        $html = $this->plugin->render($this->markup, $this->form);

        remove_filter('gform_multi_instance_sync_link_template', $filter);
        unlink($template);
        $this->assertStringContainsString('<a class="custom" href="#gf-mis-form-7" data-gf-mis-link>Custom</a>', $html);
    }

    public function test_the_scripts_are_excluded_from_wp_rocket_delay_js(): void {
        $this->assertSame(
            ['existing', 'gf-mis-move-', 'gf-multi-instance-sync-js'],
            $this->plugin->excludeFromDelayJs(['existing']),
        );
        $this->assertSame(['gf-mis-move-', 'gf-multi-instance-sync-js'], $this->plugin->excludeFromDelayJs(null));
    }

    public function test_ajax_requests_are_left_alone(): void {
        add_filter('wp_doing_ajax', '__return_true');

        $this->assertSame($this->markup, $this->plugin->render($this->markup, $this->form));

        remove_filter('wp_doing_ajax', '__return_true');
    }

    public function test_forms_without_an_id_are_left_alone(): void {
        $this->assertSame($this->markup, $this->plugin->render($this->markup, ['title' => 'No ID']));
    }

    public function test_the_mover_targets_this_form_by_its_slot_and_link(): void {
        $this->plugin->render($this->markup, $this->form);
        $html = $this->plugin->render($this->markup, $this->form);

        $this->assertStringContainsString("document.getElementById('gf-mis-form-7')", $html);
        $this->assertStringContainsString("slot.id = 'gf-mis-form-7'", $html);
        $this->assertStringContainsString('document.currentScript.previousElementSibling', $html);
    }

    public function test_third_placements_get_their_own_mover(): void {
        $this->plugin->render($this->markup, $this->form);
        $this->plugin->render($this->markup, $this->form);
        $html = $this->plugin->render($this->markup, $this->form);

        $this->assertStringContainsString('<script id="gf-mis-move-7-3">', $html);
    }

    public function test_the_link_text_is_translated(): void {
        $locale = fn(): string => 'sv_SE';
        add_filter('locale', $locale);
        unload_textdomain('gravity-forms-multi-instance-sync');
        $this->plugin->loadTextdomain();

        $this->plugin->render($this->markup, $this->form);
        $html = $this->plugin->render($this->markup, $this->form);

        remove_filter('locale', $locale);
        unload_textdomain('gravity-forms-multi-instance-sync');
        $this->assertStringContainsString('>Gå till formuläret</a>', $html);
    }

    public function test_a_translation_installed_in_wp_content_languages_wins_over_the_bundled_one(): void {
        $file = WP_LANG_DIR . '/plugins/gravity-forms-multi-instance-sync-sv_SE.mo';
        wp_mkdir_p(dirname($file));
        $mo = new MO();
        $mo->add_entry(new Translation_Entry(['singular' => 'Go to the form', 'translations' => ['Till formuläret']]));
        $mo->export_to_file($file);

        $locale = fn(): string => 'sv_SE';
        add_filter('locale', $locale);
        unload_textdomain('gravity-forms-multi-instance-sync');
        $this->plugin->loadTextdomain();

        $this->plugin->render($this->markup, $this->form);
        $html = $this->plugin->render($this->markup, $this->form);

        remove_filter('locale', $locale);
        unload_textdomain('gravity-forms-multi-instance-sync');
        unlink($file);
        $this->assertStringContainsString('>Till formuläret</a>', $html);
    }

    public function test_the_form_filter_runs_late_to_wrap_other_filters_output(): void {
        $this->assertArrayHasKey(99, $GLOBALS['wp_filter']['gform_get_form_filter']->callbacks);
    }
}
