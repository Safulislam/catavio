<?php
/**
 * Mapping from a WooCommerce product to a snapshot.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Scanner\ProductSnapshot;

/**
 * Stands in for a WC_Product.
 *
 * ProductSnapshot::from_product() calls only WooCommerce CRUD getters, which is
 * what makes this substitution possible and what keeps the rule set free of
 * WooCommerce. That the real WC_Product honours these method names is verified
 * separately against a live catalog.
 */
class FakeProduct {

	/**
	 * Values returned by the getters.
	 *
	 * @var array<string, mixed>
	 */
	private $values;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $values Values returned by the getters.
	 */
	public function __construct( array $values = array() ) {
		$this->values = array_merge(
			array(
				'id'                => 77,
				'type'              => 'simple',
				'name'              => 'Test Product',
				'description'       => 'Long description.',
				'short_description' => 'Short description.',
				'image_id'          => 0,
				'price'             => '10.00',
				'sku'               => 'TEST-1',
				'category_ids'      => array( 3 ),
				'manage_stock'      => false,
				'stock_status'      => 'instock',
				'stock_quantity'    => null,
				'virtual'           => false,
				'downloadable'      => false,
				'downloads'         => array(),
				'children'          => array(),
				'attributes'        => array(),
			),
			$values
		);
	}

	/**
	 * Route WooCommerce style getters to the value map.
	 *
	 * @param string  $method    Method name.
	 * @param mixed[] $arguments Call arguments.
	 * @return mixed
	 * @throws \BadMethodCallException When the production code calls a getter
	 *                                 this double does not model, so that an
	 *                                 unmodelled dependency cannot pass silently.
	 */
	public function __call( $method, $arguments ) {
		unset( $arguments );

		if ( 0 !== strpos( $method, 'get_' ) ) {
			throw new \BadMethodCallException( 'Unexpected call: ' . $method );
		}

		$key = substr( $method, 4 );

		if ( ! array_key_exists( $key, $this->values ) ) {
			throw new \BadMethodCallException( 'Unexpected getter: ' . $method );
		}

		return $this->values[ $key ];
	}
}

/**
 * A product attribute stand in.
 */
class FakeAttribute {

	/**
	 * Whether the attribute is used for variations.
	 *
	 * @var bool
	 */
	private $variation;

	/**
	 * Constructor.
	 *
	 * @param bool $variation Whether used for variations.
	 */
	public function __construct( $variation ) {
		$this->variation = (bool) $variation;
	}

	/**
	 * Whether the attribute is used for variations.
	 *
	 * @return bool
	 */
	public function get_variation() {
		return $this->variation;
	}
}

/**
 * Tests the WooCommerce to snapshot mapping.
 */
class ProductSnapshotTest extends TestCase {

	/**
	 * Reset the stubbed meta store.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['catavio_test_meta'] = array();
	}

	/**
	 * Scalar fields are copied and cast.
	 *
	 * @return void
	 */
	public function test_maps_scalar_fields() {
		$snapshot = ProductSnapshot::from_product(
			new FakeProduct(
				array(
					'id'           => '42',
					'name'         => 'Mapped Product',
					'price'        => 19.5,
					'sku'          => 'MAP-1',
					'category_ids' => array( '7', '9' ),
				)
			)
		);

		$this->assertSame( 42, $snapshot->id );
		$this->assertSame( 'Mapped Product', $snapshot->name );
		$this->assertSame( '19.5', $snapshot->price );
		$this->assertSame( 'MAP-1', $snapshot->sku );
		$this->assertSame( array( 7, 9 ), $snapshot->category_ids );
	}

	/**
	 * Alt text is read from the featured image attachment.
	 *
	 * @return void
	 */
	public function test_reads_alt_text_from_the_featured_image() {
		$GLOBALS['catavio_test_meta']['88:_wp_attachment_image_alt'] = '  A described image  ';

		$snapshot = ProductSnapshot::from_product( new FakeProduct( array( 'image_id' => 88 ) ) );

		$this->assertSame( 88, $snapshot->featured_image_id );
		$this->assertSame( 'A described image', $snapshot->featured_image_alt );
	}

	/**
	 * No image means no attachment lookup and no alt text.
	 *
	 * @return void
	 */
	public function test_skips_alt_text_when_there_is_no_image() {
		$snapshot = ProductSnapshot::from_product( new FakeProduct( array( 'image_id' => 0 ) ) );

		$this->assertSame( 0, $snapshot->featured_image_id );
		$this->assertSame( '', $snapshot->featured_image_alt );
	}

	/**
	 * Attributes are counted, and variation attributes counted separately.
	 *
	 * @return void
	 */
	public function test_counts_variation_attributes() {
		$snapshot = ProductSnapshot::from_product(
			new FakeProduct(
				array(
					'type'       => 'variable',
					'attributes' => array(
						new FakeAttribute( true ),
						new FakeAttribute( false ),
						new FakeAttribute( true ),
					),
					'children'   => array( 1, 2, 3, 4 ),
				)
			)
		);

		$this->assertSame( 3, $snapshot->attribute_count );
		$this->assertSame( 2, $snapshot->variation_attribute_count );
		$this->assertSame( 4, $snapshot->child_count );
		$this->assertTrue( $snapshot->is_variable() );
	}

	/**
	 * A null stock quantity stays null rather than becoming zero.
	 *
	 * Casting it to zero would make "not set" indistinguishable from
	 * "none left", and the stock check depends on telling them apart.
	 *
	 * @return void
	 */
	public function test_preserves_a_null_stock_quantity() {
		$snapshot = ProductSnapshot::from_product( new FakeProduct( array( 'stock_quantity' => null ) ) );

		$this->assertNull( $snapshot->stock_quantity );

		$zero = ProductSnapshot::from_product( new FakeProduct( array( 'stock_quantity' => 0 ) ) );

		$this->assertSame( 0, $zero->stock_quantity );
	}

	/**
	 * The external URL is only read when the product type provides it.
	 *
	 * Only WC_Product_External defines get_product_url(), so calling it
	 * unconditionally would fatal on every other product type.
	 *
	 * @return void
	 */
	public function test_reads_the_external_url_only_when_available() {
		$simple = ProductSnapshot::from_product( new FakeProduct() );

		$this->assertSame( '', $simple->external_url );

		$external = ProductSnapshot::from_product(
			new FakeProduct(
				array(
					'type'        => 'external',
					'product_url' => 'https://example.com/item',
				)
			)
		);

		$this->assertSame( 'https://example.com/item', $external->external_url );
		$this->assertTrue( $external->is_external() );
	}

	/**
	 * Downloads are counted rather than copied.
	 *
	 * Storing the file list would duplicate product data the plugin does not
	 * own; the checks only need to know whether any file is attached.
	 *
	 * @return void
	 */
	public function test_counts_downloads_without_copying_them() {
		$snapshot = ProductSnapshot::from_product(
			new FakeProduct(
				array(
					'downloadable' => true,
					'downloads'    => array(
						'a' => 'file-a',
						'b' => 'file-b',
					),
				)
			)
		);

		$this->assertTrue( $snapshot->is_downloadable );
		$this->assertSame( 2, $snapshot->download_count );
	}
}
