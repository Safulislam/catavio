<?php
/**
 * Uninstall removes everything the readme lists, on every site.
 *
 * @package Catavio
 */

namespace Catavio\Tests\Integration;

/**
 * Runs the real uninstall.php after a real scan.
 */
class UninstallTest extends TestCase {

	const OPTIONS = array( 'catavio_version', 'catavio_onboarding_pending', 'catavio_summary', 'catavio_scan_state', 'catavio_settings' );

	const META = array( '_catavio_issues', '_catavio_passed', '_catavio_issue_code', '_catavio_score', '_catavio_severity', '_catavio_scanned_at' );

	/**
	 * Fill the current site with everything the plugin stores.
	 *
	 * @return int A scanned product.
	 */
	private function populate_site() {
		$product = $this->complete_simple( array( 'sku' => '' ) );

		$this->run_full_scan();
		$this->scanner()->start();

		update_option( 'catavio_version', '1.1.0' );
		update_option( 'catavio_onboarding_pending', 1 );
		update_option( 'catavio_settings', array( 'disabled_checks' => array( 'missing_sku' ) ) );

		return $product->get_id();
	}

	/**
	 * Assert the current site holds nothing of the plugin's.
	 *
	 * @param string $site Label for failure messages.
	 * @return void
	 */
	private function assert_site_is_clean( $site ) {
		global $wpdb;

		foreach ( self::OPTIONS as $option ) {
			$this->assertFalse( get_option( $option ), "{$site}: option {$option} left behind" );
		}

		foreach ( self::META as $key ) {
			$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$this->assertSame( 0, $count, "{$site}: meta {$key} left behind" );
		}
	}

	/**
	 * Run the plugin's uninstall.php.
	 *
	 * @return void
	 */
	private function uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'catavio/catavio.php' );
		}

		include dirname( __DIR__, 3 ) . '/uninstall.php';
	}

	/**
	 * The readme lists what uninstall removes.
	 *
	 * @return void
	 */
	public function test_the_readme_lists_what_uninstall_removes() {
		$readme = (string) file_get_contents( dirname( __DIR__, 3 ) . '/readme.txt' );

		foreach ( self::META as $key ) {
			$this->assertStringContainsString( "`{$key}`", $readme );
		}
	}

	/**
	 * Uninstall removes every option and meta key.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_every_option_and_meta_key() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Covered by the multisite test.' );
		}

		$product = $this->populate_site();
		$this->assertNotSame( '', get_post_meta( $product, '_catavio_score', true ) );

		$this->uninstall();

		$this->assert_site_is_clean( 'single site' );
		$this->assertNotNull( get_post( $product ), 'Uninstall must never delete products.' );
	}

	/**
	 * Run with WP_MULTISITE=1.
	 *
	 * @group multisite
	 */
	public function test_uninstall_cleans_every_site_of_a_network() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Run with WP_MULTISITE=1 (composer test:integration:multisite).' );
		}

		$sites = array( get_current_blog_id() );

		for ( $i = 0; $i < 2; $i++ ) {
			$sites[] = self::factory()->blog->create();
		}

		// The main site is filled by a real scan. The others get the same
		// options and meta written directly: uninstall removes keys, it does
		// not care how they got there, and WooCommerce need not be installed
		// on every test site for that.
		$this->populate_site();

		foreach ( array_slice( $sites, 1 ) as $site ) {
			switch_to_blog( $site );

			$post = self::factory()->post->create();

			foreach ( self::META as $key ) {
				add_post_meta( $post, $key, 'x' );
			}

			foreach ( self::OPTIONS as $option ) {
				update_option( $option, 'x' );
			}

			restore_current_blog();
		}

		$this->uninstall();

		foreach ( $sites as $site ) {
			switch_to_blog( $site );
			$this->assert_site_is_clean( "site {$site}" );
			restore_current_blog();
		}
	}
}
