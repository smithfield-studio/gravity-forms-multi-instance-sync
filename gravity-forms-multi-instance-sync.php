<?php

declare(strict_types=1);

/**
 * Plugin Name:       Multi-Instance Sync for Gravity Forms
 * Plugin URI:        https://github.com/smithfield-studio/gravity-forms-multi-instance-sync
 * Description:       Place the same Gravity Form more than once on a page. It renders once and moves to whichever placement the visitor scrolls to, keeping their answers.
 * Version:           1.0.0
 * Author:            Smithfield
 * Author URI:        https://smithfield.studio
 * License:           MIT
 * License URI:       https://opensource.org/license/mit
 * GitHub Plugin URI: https://github.com/smithfield-studio/gravity-forms-multi-instance-sync
 * GitHub Branch:     main
 * Requires PHP:      8.4
 * Requires at least: 6.0
 * Text Domain:       gravity-forms-multi-instance-sync
 * Domain Path:       /languages
 *
 * @package Multi-Instance Sync for Gravity Forms
 */
namespace SmithfieldStudio\GravityFormsMultiInstanceSync;

defined('ABSPATH') || exit();

require_once __DIR__ . '/src/Plugin.php';

new Plugin(__FILE__, '1.0.0')->boot();
