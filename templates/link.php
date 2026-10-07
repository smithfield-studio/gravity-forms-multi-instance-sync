<?php

/**
 * The link that stands in for a form in a placement it isn't in.
 *
 * Override it by copying this file to your theme as gravity-forms-multi-instance-sync/link.php. Keep $attributes on
 * the link: the script uses them to find it and show or hide it.
 *
 * @var array<string, mixed> $form       The Gravity Forms form
 * @var string               $attributes The link's href, data attribute and visibility
 */

defined('ABSPATH') || exit();
?>
<a class="gf-mis-slot__link button" <?php echo $attributes; ?>><?php esc_html_e(
    'Go to the form',
    'gravity-forms-multi-instance-sync',
); ?></a>
