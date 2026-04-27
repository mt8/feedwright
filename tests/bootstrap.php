<?php
/**
 * PHPUnit bootstrap file for Integration tests (loads WordPress test suite).
 *
 * @package Feedwright
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests_dir ) {
	$wp_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test suite at {$wp_tests_dir}/includes/functions.php" . PHP_EOL;
	exit( 1 );
}

require $wp_tests_dir . '/includes/functions.php';

/**
 * Load the Feedwright plugin under test.
 */
function _feedwright_manually_load_plugin() {
	require dirname( __DIR__ ) . '/feedwright.php';
}
tests_add_filter( 'muplugins_loaded', '_feedwright_manually_load_plugin' );

require $wp_tests_dir . '/includes/bootstrap.php';
