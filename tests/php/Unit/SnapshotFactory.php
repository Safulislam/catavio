<?php
/**
 * Snapshot builders for the check tests.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use Catavio\Scanner\ProductSnapshot;

/**
 * Builds product snapshots that pass every applicable check, so that each test
 * can remove exactly one thing and assert on exactly that.
 */
class SnapshotFactory {

	/**
	 * A complete simple product.
	 *
	 * @param array<string, mixed> $overrides Properties to change.
	 * @return ProductSnapshot
	 */
	public static function simple( array $overrides = array() ) {
		$snapshot = new ProductSnapshot();

		$snapshot->id                 = 101;
		$snapshot->type               = 'simple';
		$snapshot->name               = 'Merino Wool Scarf';
		$snapshot->description        = '<p>A warm scarf made from merino wool.</p>';
		$snapshot->short_description  = 'Warm merino wool scarf.';
		$snapshot->featured_image_id  = 55;
		$snapshot->featured_image_alt = 'A grey merino wool scarf';
		$snapshot->price              = '29.00';
		$snapshot->sku                = 'SCARF-001';
		$snapshot->category_ids       = array( 12 );
		$snapshot->manages_stock      = false;
		$snapshot->stock_status       = 'instock';
		$snapshot->stock_quantity     = null;

		return self::apply( $snapshot, $overrides );
	}

	/**
	 * A complete variable product.
	 *
	 * @param array<string, mixed> $overrides Properties to change.
	 * @return ProductSnapshot
	 */
	public static function variable( array $overrides = array() ) {
		return self::simple(
			array_merge(
				array(
					'id'                        => 202,
					'type'                      => 'variable',
					'name'                      => 'Running Shoe',
					'sku'                       => 'SHOE-001',
					'attribute_count'           => 2,
					'variation_attribute_count' => 1,
					'child_count'               => 3,
				),
				$overrides
			)
		);
	}

	/**
	 * A complete grouped product.
	 *
	 * Grouped products carry no price of their own: WooCommerce derives the
	 * displayed price from the children.
	 *
	 * @param array<string, mixed> $overrides Properties to change.
	 * @return ProductSnapshot
	 */
	public static function grouped( array $overrides = array() ) {
		return self::simple(
			array_merge(
				array(
					'id'           => 303,
					'type'         => 'grouped',
					'name'         => 'Winter Bundle',
					'sku'          => 'BUNDLE-001',
					'price'        => '',
					'stock_status' => '',
					'child_count'  => 2,
				),
				$overrides
			)
		);
	}

	/**
	 * A complete external/affiliate product.
	 *
	 * @param array<string, mixed> $overrides Properties to change.
	 * @return ProductSnapshot
	 */
	public static function external( array $overrides = array() ) {
		return self::simple(
			array_merge(
				array(
					'id'           => 404,
					'type'         => 'external',
					'name'         => 'Partner Backpack',
					'sku'          => 'EXT-001',
					'external_url' => 'https://example.com/backpack',
					'stock_status' => '',
				),
				$overrides
			)
		);
	}

	/**
	 * A complete virtual product.
	 *
	 * @param array<string, mixed> $overrides Properties to change.
	 * @return ProductSnapshot
	 */
	public static function virtual( array $overrides = array() ) {
		return self::simple(
			array_merge(
				array(
					'id'         => 505,
					'name'       => 'Online Fitting Session',
					'sku'        => 'VIRT-001',
					'is_virtual' => true,
				),
				$overrides
			)
		);
	}

	/**
	 * A complete downloadable product.
	 *
	 * @param array<string, mixed> $overrides Properties to change.
	 * @return ProductSnapshot
	 */
	public static function downloadable( array $overrides = array() ) {
		return self::simple(
			array_merge(
				array(
					'id'              => 606,
					'name'            => 'Knitting Pattern PDF',
					'sku'             => 'DL-001',
					'is_virtual'      => true,
					'is_downloadable' => true,
					'download_count'  => 1,
				),
				$overrides
			)
		);
	}

	/**
	 * Apply overrides to a snapshot.
	 *
	 * @param ProductSnapshot      $snapshot  Snapshot to modify.
	 * @param array<string, mixed> $overrides Properties to change.
	 * @return ProductSnapshot
	 * @throws \InvalidArgumentException When an unknown property is given, so
	 *                                   that a typo in a test fails loudly
	 *                                   instead of silently testing nothing.
	 */
	private static function apply( ProductSnapshot $snapshot, array $overrides ) {
		foreach ( $overrides as $property => $value ) {
			if ( ! property_exists( $snapshot, $property ) ) {
				throw new \InvalidArgumentException( 'Unknown snapshot property: ' . $property );
			}

			$snapshot->$property = $value;
		}

		return $snapshot;
	}
}
