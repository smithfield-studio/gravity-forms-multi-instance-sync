<?php

/**
 * The link that stands in for a form in a placement it isn't in.
 *
 * Override it by copying this file to your theme as gravity-forms-multi-instance-sync/link.php. The plugin wraps it in
 * an element it shows and hides, so the template can be any markup. Keep $attributes on the link.
 *
 * @var array<string, mixed> $form       The Gravity Forms form
 * @var string               $attributes The link's href
 */

defined('ABSPATH') || exit();
?>
<a class="gf-mis-slot__link button" <?php echo $attributes; ?>><?php esc_html_e(
    'Go to the form',
    'gravity-forms-multi-instance-sync',
); ?></a>
