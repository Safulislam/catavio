<?php
/**
 * WordPress test suite configuration for the integration tests.
 *
 * Every value comes from the environment, so no credentials are kept in the
 * repository. The test suite empties its tables on every run: it must be
 * pointed at a database used for nothing else, and this file refuses any
 * database whose name does not contain "test".
 *
 *   CATAVIO_TESTS_DB_NAME      default catavio_tests
 *   CATAVIO_TESTS_DB_USER      default root
 *   CATAVIO_TESTS_DB_PASSWORD  default empty
 *   CATAVIO_TESTS_DB_HOST      default localhost (MAMP: localhost:/Applications/MAMP/tmp/mysql/mysql.sock)
 *   CATAVIO_WP_DIR             WordPress core directory; default: the site this plugin sits in
 *
 * @package Catavio
 */

$catavio_tests_db = getenv( 'CATAVIO_TESTS_DB_NAME' ) ? getenv( 'CATAVIO_TESTS_DB_NAME' ) : 'catavio_tests';

if ( false === stripos( $catavio_tests_db, 'test' ) ) {
	fwrite( STDERR, "Refusing to run: the test database name must contain \"test\" (got \"{$catavio_tests_db}\").\n" );
	exit( 1 );
}

define( 'ABSPATH', rtrim( getenv( 'CATAVIO_WP_DIR' ) ? getenv( 'CATAVIO_WP_DIR' ) : dirname( __DIR__, 6 ), '/' ) . '/' );

define( 'DB_NAME', $catavio_tests_db );
define( 'DB_USER', getenv( 'CATAVIO_TESTS_DB_USER' ) ? getenv( 'CATAVIO_TESTS_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', (string) getenv( 'CATAVIO_TESTS_DB_PASSWORD' ) );
define( 'DB_HOST', getenv( 'CATAVIO_TESTS_DB_HOST' ) ? getenv( 'CATAVIO_TESTS_DB_HOST' ) : 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the test suite reads it.

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Catavio Tests' );
define( 'WP_PHP_BINARY', PHP_BINARY );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 3 ) . '/vendor/yoast/phpunit-polyfills' );
