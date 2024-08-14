<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo sprintf('Could not find %s/includes/functions.php, have you run bin/install-wp-tests.sh ?', $_tests_dir) . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin(): void {
	$thispath = __DIR__;
	$file = $thispath . '/../zoninator.php';
	require_once realpath( $file );
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
