<?php

/**
 * PHPUnit bootstrap file.
 *
 * @package Multi-Instance Sync for Gravity Forms
 */

$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if ($_phpunit_polyfills_path !== false) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path);
}

if (!file_exists("{$_tests_dir}/includes/functions.php")) {
    echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
    exit(1);
}

require_once "{$_tests_dir}/includes/functions.php";

// Gravity Forms isn't needed: the tests call the plugin's gform_get_form_filter callback with sample markup
tests_add_filter('muplugins_loaded', function (): void {
    require dirname(__FILE__, 2) . '/gravity-forms-multi-instance-sync.php';
});

require "{$_tests_dir}/includes/bootstrap.php";
