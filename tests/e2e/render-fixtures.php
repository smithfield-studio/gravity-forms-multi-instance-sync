<?php

/**
 * Renders the e2e fixture pages from the plugin's own output: src/Plugin.php runs with the few WordPress functions it
 * calls stubbed, against markup shaped like Gravity Forms' (same ID patterns, and a conditional field that's looked
 * up by ID on every change, as Gravity Forms does).
 *
 * php tests/e2e/render-fixtures.php <output dir>
 */

namespace {
    define('ABSPATH', __DIR__);

    function add_action(): void {}

    function add_filter(): void {}

    function apply_filters(string $hook, mixed $value): mixed {
        return $GLOBALS['fixture_filters'][$hook] ?? $value;
    }

    function is_admin(): bool {
        return false;
    }

    function wp_doing_ajax(): bool {
        return false;
    }

    function wp_enqueue_script(): void {}

    function plugins_url(string $path): string {
        return $path;
    }

    function locate_template(): string {
        return '';
    }

    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES);
    }

    function esc_html_e(string $text): void {
        echo htmlspecialchars($text, ENT_QUOTES);
    }
}

namespace SmithfieldStudio\GravityFormsMultiInstanceSync\Tests {
    use SmithfieldStudio\GravityFormsMultiInstanceSync\Plugin;

    require dirname(__DIR__, 2) . '/src/Plugin.php';

    $out = rtrim($argv[1] ?? __DIR__ . '/.fixtures', '/');
    is_dir($out) || mkdir($out, 0777, true);

    $form = static fn(int $id): string => <<<HTML
        <div class='gform_wrapper' id='gform_wrapper_{$id}'>
            <form id='gform_{$id}' method='post'>
                <div class='gfield' id='field_{$id}_1'><label for='input_{$id}_1'>Email</label><input id='input_{$id}_1' name='input_1' type='email'></div>
                <div class='gfield' id='field_{$id}_3'><label><input id='choice_{$id}_3_1' name='input_3.1' type='checkbox' value='Other'> Other</label></div>
                <div class='gfield' id='field_{$id}_26' style='display: none'><label for='input_{$id}_26'>Which other department?</label><input id='input_{$id}_26' name='input_26'></div>
                <script>
                    document.getElementById('choice_{$id}_3_1').addEventListener('change', function (event) {
                        document.getElementById('field_{$id}_26').style.display = event.target.checked ? '' : 'none';
                    });
                </script>
            </form>
        </div>
        HTML;

    $page = static fn(string $body, string $css = ''): string => <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            body { margin: 0; font: 16px sans-serif; }
            .spacer { height: 2400px; }
            .gfield { padding: 12px; }
            {$css}
        </style>
        </head>
        <body>
        {$body}
        </body>
        </html>
        HTML;

    $placements = static function (int $id, int $count) use ($form): array {
        $plugin = new Plugin(dirname(__DIR__, 2) . '/gravity-forms-multi-instance-sync.php', 'test');
        $output = [];
        for ($i = 0; $i < $count; $i++) {
            $output[] = $plugin->render($form($id), ['id' => $id]);
        }

        return $output;
    };

    $breakpoints = '@media (max-width: 767px) { .desktop-only { display: none; } } @media (min-width: 768px) { .mobile-only { display: none; } }';

    // Landing pages: the form at the top and again at the bottom
    [$top, $bottom] = $placements(7, 2);
    file_put_contents(
        "{$out}/top-and-bottom.html",
        $page(
            "<h1>Landing page</h1><section id='top'>{$top}</section><div class='spacer'></div><section id='bottom'>{$bottom}</section><div class='spacer'></div>",
        ),
    );

    // A mobile-only block first and a desktop-only block second, as on /lp/contract-management/
    [$mobile, $desktop] = $placements(7, 2);
    file_put_contents(
        "{$out}/mobile-and-desktop.html",
        $page(
            "<section class='mobile-only' id='mobile'>{$mobile}</section><section class='desktop-only' id='desktop'>{$desktop}</section><div class='spacer'></div>",
            $breakpoints,
        ),
    );

    // The same, hidden with visibility at fixed widths, so resizing changes neither placement's size
    [$mobile, $desktop] = $placements(7, 2);
    file_put_contents(
        "{$out}/mobile-and-desktop-visibility.html",
        $page(
            "<section class='mobile-only' id='mobile'>{$mobile}</section><section class='desktop-only' id='desktop'>{$desktop}</section><div class='spacer'></div>",
            'section { width: 320px; } @media (max-width: 767px) { .desktop-only { visibility: hidden; } } @media (min-width: 768px) { .mobile-only { visibility: hidden; } }',
        ),
    );

    // The form on the page and in a modal
    [$inPage, $inModal] = $placements(7, 2);
    file_put_contents(
        "{$out}/modal.html",
        $page(
            "<section id='top'>{$inPage}</section><div class='spacer'></div><div id='modal' style='display: none; position: fixed; inset: 10vh 10vw; background: #fff; overflow: auto'>{$inModal}</div>",
        ),
    );

    // A modal that fades in and is hidden with visibility rather than display, as many popup plugins do
    [$inPage, $inModal] = $placements(7, 2);
    file_put_contents(
        "{$out}/modal-visibility.html",
        $page(
            "<section id='top'>{$inPage}</section><div class='spacer'></div><div id='modal'>{$inModal}</div>",
            '#modal { position: fixed; inset: 10vh 10vw; background: #fff; overflow: auto; visibility: hidden; opacity: 0; transition: opacity 0.2s, visibility 0s 0.2s; } #modal.is-open { visibility: visible; opacity: 1; transition: opacity 0.2s; }',
        ),
    );

    // A modal hidden by an attribute selector, with no transition
    [$inPage, $inModal] = $placements(7, 2);
    file_put_contents(
        "{$out}/modal-aria-hidden.html",
        $page(
            "<section id='top'>{$inPage}</section><div class='spacer'></div><div id='modal' aria-hidden='true'>{$inModal}</div>",
            '#modal { position: fixed; inset: 10vh 10vw; background: #fff; overflow: auto; } #modal[aria-hidden="true"] { visibility: hidden; }',
        ),
    );

    // A panel revealed by a sibling selector on its toggle, with no transition or size change
    [$inPage, $inPanel] = $placements(7, 2);
    file_put_contents(
        "{$out}/sibling-toggle.html",
        $page(
            "<section id='top'>{$inPage}</section><button id='toggle' aria-expanded='false'>More</button><section class='panel' id='panel'>{$inPanel}</section><div class='spacer'></div>",
            '.panel { visibility: hidden; } button[aria-expanded="true"] + .panel { visibility: visible; }',
        ),
    );

    // A CSS-only tab revealed by :checked, with no attribute change
    [$inPage, $inTab] = $placements(7, 2);
    file_put_contents(
        "{$out}/checked-tab.html",
        $page(
            "<section id='top'>{$inPage}</section><label><input type='checkbox' id='show-tab'> Show</label><section class='tab' id='tab'>{$inTab}</section><div class='spacer'></div>",
            '.tab { visibility: hidden; } label:has(#show-tab:checked) + .tab { visibility: visible; }',
        ),
    );

    // A panel shown while focus is within its menu
    [$inPage, $inPanel] = $placements(7, 2);
    file_put_contents(
        "{$out}/focus-within.html",
        $page(
            "<section id='top'>{$inPage}</section><div class='menu'><button id='open'>Contact us</button><section class='panel' id='panel'>{$inPanel}</section></div><button id='elsewhere'>Elsewhere</button><div class='spacer'></div>",
            '.panel { visibility: hidden; } .menu:focus-within .panel { visibility: visible; }',
        ),
    );

    // A panel shown while the pointer is over its menu
    [$inPage, $inPanel] = $placements(7, 2);
    file_put_contents(
        "{$out}/hover.html",
        $page(
            "<section id='top'>{$inPage}</section><div class='menu'><span id='open'>Contact us</span><section class='panel' id='panel'>{$inPanel}</section></div><p id='elsewhere'>Elsewhere</p><div class='spacer'></div>",
            '.panel { visibility: hidden; } .menu:hover .panel { visibility: visible; }',
        ),
    );

    // Two placements close enough to be on screen together
    [$first, $second] = $placements(7, 2);
    file_put_contents(
        "{$out}/side-by-side.html",
        $page(
            "<section id='first'>{$first}</section><section id='second'>{$second}</section><div class='spacer'></div>",
        ),
    );

    // Two different forms, each placed twice
    [$aTop, $aBottom] = $placements(7, 2);
    [$bTop, $bBottom] = $placements(8, 2);
    file_put_contents(
        "{$out}/two-forms.html",
        $page(
            "<section id='a-top'>{$aTop}</section><section id='b-top'>{$bTop}</section><div class='spacer'></div><section id='a-bottom'>{$aBottom}</section><div class='spacer'></div><section id='b-bottom'>{$bBottom}</section><div class='spacer'></div>",
        ),
    );

    // A theme template with wrapper markup and a margin, like full button markup
    $template = "{$out}/wrapped-link.php";
    file_put_contents(
        $template,
        '<div class="wp-block-buttons" style="margin-top: 2rem"><div class="wp-block-button"><a class="wp-block-button__link" <?php echo $attributes; ?>>Go to the form</a></div></div>',
    );
    $GLOBALS['fixture_filters']['gform_multi_instance_sync_link_template'] = $template;
    [$top, $bottom] = $placements(7, 2);
    unset($GLOBALS['fixture_filters']);
    file_put_contents(
        "{$out}/wrapped-link.html",
        $page(
            "<section id='top'>{$top}</section><div class='spacer'></div><section id='bottom'>{$bottom}</section><div class='spacer'></div>",
        ),
    );

    // One placement only: nothing to do
    [$only] = $placements(7, 1);
    file_put_contents("{$out}/single.html", $page("<section id='only'>{$only}</section>"));

    echo "Rendered fixtures to {$out}\n";
}
