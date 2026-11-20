<?php
/**
 * Integration test bootstrap: real WordPress, real WooCommerce, real database.
 *
 * Run with `composer test:integration` (and `composer test:integration:multisite`).
 * Database settings are read from the environment; see wp-tests-config.php.
 *
 * @package Catavio
 */

$catavio_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : dirname( __DIR__, 3 ) . '/vendor/wp-phpunit/wp-phpunit';

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

require_once $catavio_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		$woocommerce = getenv( 'CATAVIO_WC_FILE' ) ? getenv( 'CATAVIO_WC_FILE' ) : dirname( __DIR__, 4 ) . '/woocommerce/woocommerce.php';

		require_once $woocommerce;
		require_once dirname( __DIR__, 3 ) . '/catavio.php';
	}
);

// WooCommerce's tables, roles and capabilities, as on a real store.
tests_add_filter(
	'setup_theme',
	function () {
		WC_Install::install();

		$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reload roles after install.
		wp_roles();
	}
);

// Nothing in these tests may reach the network.
tests_add_filter(
	'pre_http_request',
	function () {
		return new WP_Error( 'catavio_tests_offline', 'External requests are blocked in the integration tests.' );
	}
);

require $catavio_tests_dir . '/includes/bootstrap.php';
