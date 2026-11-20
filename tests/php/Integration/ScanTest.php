<?php
/**
 * A full scan against real WooCommerce products.
 *
 * @package Catavio
 */

namespace Catavio\Tests\Integration;

/**
 * Every product type, scanned batch by batch through the real scanner.
 */
class ScanTest extends TestCase {

	/**
	 * Full scan reports type aware issues.
	 *
	 * @return void
	 */
	public function test_full_scan_reports_type_aware_issues() {
		$healthy = $this->complete_simple();

		$no_price = $this->complete_simple( array( 'regular_price' => '' ) );

		$variable = new \WC_Product_Variable();
		$variable->set_name( 'Wool Hat' );
		$variable->set_status( 'publish' );
		$variable->save();

		$external = new \WC_Product_External();
		$external->set_name( 'Partner Gloves' );
		$external->set_status( 'publish' );
		$external->set_regular_price( '25' );
		$external->save();

		$downloadable = $this->complete_simple(
			array(
				'name'         => 'Knitting Pattern',
				'virtual'      => true,
				'downloadable' => true,
			)
		);

		$grouped = new \WC_Product_Grouped();
		$grouped->set_name( 'Winter Set' );
		$grouped->set_status( 'publish' );
		$grouped->set_children( array( $healthy->get_id(), $no_price->get_id() ) );
		$grouped->save();

		$draft = $this->complete_simple(
			array(
				'status'      => 'draft',
				'description' => '',
			)
		);

		$state = $this->run_full_scan();

		$this->assertSame( 'complete', $state['status'] );

		$this->assertSame( array(), $this->issue_codes( $healthy->get_id() ) );
		$this->assertSame( 100, (int) get_post_meta( $healthy->get_id(), '_catavio_score', true ) );

		$this->assertContains( 'missing_price', $this->issue_codes( $no_price->get_id() ) );
		$this->assertContains( 'no_variations', $this->issue_codes( $variable->get_id() ) );
		$this->assertContains( 'missing_external_url', $this->issue_codes( $external->get_id() ) );
		$this->assertContains( 'missing_downloadable_files', $this->issue_codes( $downloadable->get_id() ) );

		// A grouped product has no price of its own and is not marked down for it.
		$this->assertNotContains( 'missing_price', $this->issue_codes( $grouped->get_id() ) );
		$this->assertNotSame( '', get_post_meta( $grouped->get_id(), '_catavio_scanned_at', true ) );

		// Drafts are not scanned by default.
		$this->assertSame( '', get_post_meta( $draft->get_id(), '_catavio_scanned_at', true ) );

		// Optional checks stay off until turned on.
		foreach ( array( 'missing_gtin', 'missing_brand', 'missing_gallery_images' ) as $optional ) {
			$this->assertNotContains( $optional, $this->issue_codes( $healthy->get_id() ) );
		}

		$summary = get_option( 'catavio_summary' );
		$this->assertSame( 6, (int) $summary['scanned'] );
		$this->assertSame( 1, (int) $summary['healthy'] );

		// Topics: a product counts once per topic, however many issues it has
		// there. Three products have a "structure" issue: the variable one,
		// the external one and the downloadable one.
		$this->assertArrayHasKey( 'structure', $summary['topics'] );
		$this->assertSame( 3, $summary['topics']['structure']['affected'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		$response = rest_get_server()->dispatch( new \WP_REST_Request( 'GET', '/catavio/v1/summary' ) )->get_data();
		$topics   = wp_list_pluck( $response['topics'], 'complete', 'group' );

		$this->assertTrue( $response['topicsRecorded'] );
		$this->assertSame( 50, $topics['structure'] );
		$this->assertArrayHasKey( 'content', $topics );

		// No optional check is on, so no topic made only of optional checks
		// is shown; every built-in topic still has a check turned on.
		$this->assertCount( 5, $response['topics'] );
	}

	/**
	 * Scan actions fire once per product and scan.
	 *
	 * @return void
	 */
	public function test_scan_actions_fire_once_per_product_and_scan() {
		$this->complete_simple();
		$this->complete_simple();

		$started   = did_action( 'catavio_scan_started' );
		$scanned   = did_action( 'catavio_product_scanned' );
		$completed = did_action( 'catavio_scan_completed' );

		$this->run_full_scan();

		$this->assertSame( 1, did_action( 'catavio_scan_started' ) - $started );
		$this->assertSame( 2, did_action( 'catavio_product_scanned' ) - $scanned );
		$this->assertSame( 1, did_action( 'catavio_scan_completed' ) - $completed );
	}

	/**
	 * Turning on an optional check applies to the next scan.
	 *
	 * @return void
	 */
	public function test_turning_on_an_optional_check_applies_to_the_next_scan() {
		$product = $this->complete_simple();

		// Nothing turned off, so every optional check is turned on.
		$this->scanner()->get_settings()->set_disabled_checks(
			array(),
			$this->scanner_registry()->get_check_ids(),
			$this->scanner_registry()->get_default_off_ids()
		);

		$this->run_full_scan();

		$this->assertContains( 'missing_gtin', $this->issue_codes( $product->get_id() ) );
		$this->assertContains( 'missing_gallery_images', $this->issue_codes( $product->get_id() ) );
	}

	/**
	 * The check registry.
	 *
	 * @return \Catavio\Checks\Registry
	 */
	private function scanner_registry() {
		return \Catavio\Plugin::instance()->get_registry();
	}
}
