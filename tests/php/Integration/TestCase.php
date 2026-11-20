<?php
/**
 * Shared helpers for the integration tests.
 *
 * @package Catavio
 */

namespace Catavio\Tests\Integration;

use Catavio\Capabilities;
use Catavio\HealthScore;
use Catavio\Plugin;
use WP_UnitTestCase;

/**
 * Builds products and runs scans through the plugin's real services.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Reset the plugin's options and memoized state before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		Capabilities::reset();
		HealthScore::reset_penalties();
		delete_option( 'catavio_settings' );
		delete_option( 'catavio_summary' );
		delete_option( 'catavio_scan_state' );
	}

	/**
	 * The plugin's scanner.
	 *
	 * @return \Catavio\Scanner\Scanner
	 */
	protected function scanner() {
		$scanner = Plugin::instance()->get_scanner();

		$this->assertNotNull( $scanner, 'Catavio did not boot: WooCommerce missing or unsupported.' );

		return $scanner;
	}

	/**
	 * Run a whole scan, batch by batch, as the dashboard does.
	 *
	 * @return array Final job state.
	 */
	protected function run_full_scan() {
		$scanner = $this->scanner();
		$scanner->start();

		$state = array();

		for ( $i = 0; $i < 100; $i++ ) {
			$state = $scanner->run_batch();

			if ( 'running' !== $state['status'] ) {
				break;
			}
		}

		return $state;
	}

	/**
	 * The issue codes stored for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	protected function issue_codes( $product_id ) {
		$codes = get_post_meta( $product_id, '_catavio_issue_code', false );
		sort( $codes );

		return $codes;
	}

	/**
	 * An image attachment with alt text.
	 *
	 * @return int Attachment ID.
	 */
	protected function image() {
		$id = self::factory()->attachment->create_object(
			array(
				'file'           => 'scarf.jpg',
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		update_post_meta( $id, '_wp_attachment_image_alt', 'A merino wool scarf' );

		return $id;
	}

	/**
	 * A simple product with every core field filled in.
	 *
	 * @param array $overrides Setter name => value, without "set_".
	 * @return \WC_Product_Simple
	 */
	protected function complete_simple( array $overrides = array() ) {
		$category = wp_insert_term( 'Scarves ' . wp_generate_password( 6, false ), 'product_cat' );

		$product = new \WC_Product_Simple();
		$fields  = array_merge(
			array(
				'name'              => 'Merino Scarf',
				'status'            => 'publish',
				'description'       => 'A soft merino wool scarf, knitted in Scotland.',
				'short_description' => 'Soft merino wool.',
				'regular_price'     => '40',
				'sku'               => 'SCARF-' . wp_generate_password( 6, false ),
				'category_ids'      => array( (int) $category['term_id'] ),
				'image_id'          => $this->image(),
			),
			$overrides
		);

		foreach ( $fields as $field => $value ) {
			$product->{"set_{$field}"}( $value );
		}

		$product->save();

		return $product;
	}
}
