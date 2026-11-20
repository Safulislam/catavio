<?php
/**
 * Immutable view of the product data the checks need.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * A flat, read only record of one product.
 *
 * This is the single seam between WooCommerce and the check rules. Every check
 * reads a snapshot rather than a WC_Product, which buys three things:
 *
 * 1. The WooCommerce getters are called once per product instead of once per
 *    check, so adding a check costs no extra queries.
 * 2. The check rules become pure functions over plain data, so the whole rule
 *    set is unit testable without bootstrapping WordPress or WooCommerce.
 * 3. All WooCommerce API knowledge lives in one place, from_product().
 */
class ProductSnapshot {

	/**
	 * Product type slug: simple, variable, grouped, external.
	 *
	 * @var string
	 */
	public $type = 'simple';

	/**
	 * Product ID.
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Product title.
	 *
	 * @var string
	 */
	public $name = '';

	/**
	 * Long description.
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Short description.
	 *
	 * @var string
	 */
	public $short_description = '';

	/**
	 * Featured image attachment ID, 0 when unset.
	 *
	 * @var int
	 */
	public $featured_image_id = 0;

	/**
	 * Alt text stored on the featured image.
	 *
	 * @var string
	 */
	public $featured_image_alt = '';

	/**
	 * Active price. For variable products this is the lowest variation price.
	 *
	 * @var string
	 */
	public $price = '';

	/**
	 * Stock keeping unit.
	 *
	 * @var string
	 */
	public $sku = '';

	/**
	 * Assigned product category term IDs.
	 *
	 * @var int[]
	 */
	public $category_ids = array();

	/**
	 * The site's default product category, usually "Uncategorized".
	 *
	 * WooCommerce assigns this to every product that is given no category, so
	 * the category check needs it to tell "categorised" from "left in the
	 * default bucket". Zero when the site has no default.
	 *
	 * @var int
	 */
	public $default_category_id = 0;

	/**
	 * Whether WooCommerce manages stock for this product.
	 *
	 * @var bool
	 */
	public $manages_stock = false;

	/**
	 * Stock status slug: instock, outofstock, onbackorder.
	 *
	 * @var string
	 */
	public $stock_status = '';

	/**
	 * Managed stock quantity, null when not managed.
	 *
	 * @var int|null
	 */
	public $stock_quantity = null;

	/**
	 * Number of attributes defined on the product.
	 *
	 * @var int
	 */
	public $attribute_count = 0;

	/**
	 * Number of attributes marked "used for variations".
	 *
	 * @var int
	 */
	public $variation_attribute_count = 0;

	/**
	 * Whether the product is virtual.
	 *
	 * @var bool
	 */
	public $is_virtual = false;

	/**
	 * Whether the product is downloadable.
	 *
	 * @var bool
	 */
	public $is_downloadable = false;

	/**
	 * Number of attached downloadable files.
	 *
	 * @var int
	 */
	public $download_count = 0;

	/**
	 * External/affiliate product URL.
	 *
	 * @var string
	 */
	public $external_url = '';

	/**
	 * Number of children: variations for variable, grouped products for grouped.
	 *
	 * @var int
	 */
	public $child_count = 0;

	/**
	 * Number of gallery images, not counting the featured image.
	 *
	 * @since 1.1.0
	 * @var int
	 */
	public $gallery_image_count = 0;

	/**
	 * Whether this WooCommerce version has the built-in GTIN field (9.1+).
	 *
	 * @since 1.1.0
	 * @var bool
	 */
	public $gtin_supported = false;

	/**
	 * GTIN, UPC, EAN or ISBN from WooCommerce's built-in field.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	public $global_unique_id = '';

	/**
	 * Whether WooCommerce's Brands taxonomy is registered.
	 *
	 * @since 1.1.0
	 * @var bool
	 */
	public $brand_supported = false;

	/**
	 * Assigned brand term IDs.
	 *
	 * @since 1.1.0
	 * @var int[]
	 */
	public $brand_ids = array();

	/**
	 * Data added by extensions through the catavio_snapshot_data filter.
	 *
	 * Keyed by whatever the extension chooses, ideally its own slug, so two
	 * extensions never overwrite each other. Extensions should read everything
	 * their checks need here, once per product, so the checks stay pure
	 * functions of the snapshot.
	 *
	 * @since 1.1.0
	 * @var array<string, mixed>
	 */
	public $extra = array();

	/**
	 * Build a snapshot from a WooCommerce product.
	 *
	 * Only WooCommerce CRUD getters are used, so this works for every product
	 * type without touching the database directly.
	 *
	 * @param \WC_Product $product WooCommerce product object.
	 * @return ProductSnapshot
	 */
	public static function from_product( $product ) {
		$snapshot = new self();

		$snapshot->id                  = (int) $product->get_id();
		$snapshot->type                = (string) $product->get_type();
		$snapshot->name                = (string) $product->get_name();
		$snapshot->description         = (string) $product->get_description();
		$snapshot->short_description   = (string) $product->get_short_description();
		$snapshot->featured_image_id   = (int) $product->get_image_id();
		$snapshot->price               = (string) $product->get_price();
		$snapshot->sku                 = (string) $product->get_sku();
		$snapshot->category_ids        = array_map( 'intval', (array) $product->get_category_ids() );
		$snapshot->default_category_id = (int) get_option( 'default_product_cat', 0 );
		$snapshot->manages_stock       = (bool) $product->get_manage_stock();
		$snapshot->stock_status        = (string) $product->get_stock_status();
		$snapshot->is_virtual          = (bool) $product->get_virtual();
		$snapshot->is_downloadable     = (bool) $product->get_downloadable();
		$snapshot->download_count      = count( (array) $product->get_downloads() );
		$snapshot->child_count         = count( (array) $product->get_children() );
		$snapshot->gallery_image_count = count( (array) $product->get_gallery_image_ids() );

		// method_exists() rather than is_callable(): see the note on
		// get_product_url() below.
		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			$snapshot->gtin_supported   = true;
			$snapshot->global_unique_id = trim( (string) $product->get_global_unique_id() );
		}

		if ( taxonomy_exists( 'product_brand' ) ) {
			$snapshot->brand_supported = true;

			// get_the_terms() reads the term cache the scan query primes, so
			// this costs no query per product.
			$brands = get_the_terms( $snapshot->id, 'product_brand' );

			if ( is_array( $brands ) ) {
				$snapshot->brand_ids = array_map( 'intval', wp_list_pluck( $brands, 'term_id' ) );
			}
		}

		$quantity                 = $product->get_stock_quantity();
		$snapshot->stock_quantity = ( null === $quantity ) ? null : (int) $quantity;

		if ( $snapshot->featured_image_id > 0 ) {
			$snapshot->featured_image_alt = trim( (string) get_post_meta( $snapshot->featured_image_id, '_wp_attachment_image_alt', true ) );
		}

		// Only WC_Product_External defines get_product_url(). Guard on the
		// product type rather than on is_callable() alone, because is_callable()
		// reports true for any class implementing __call(), which would call the
		// method on product types that do not have it.
		if ( $snapshot->is_external() && is_callable( array( $product, 'get_product_url' ) ) ) {
			$snapshot->external_url = (string) $product->get_product_url();
		}

		$snapshot->apply_attributes( (array) $product->get_attributes() );

		/**
		 * Filters the extension data attached to a product snapshot.
		 *
		 * Runs once per product per scan, in the admin or a REST request, never
		 * on the storefront. Return an array keyed by your extension's slug.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, mixed> $extra    Extension data, empty by default.
		 * @param \WC_Product          $product  The product being scanned.
		 * @param ProductSnapshot      $snapshot The snapshot built so far.
		 */
		$extra = apply_filters( 'catavio_snapshot_data', array(), $product, $snapshot );

		$snapshot->extra = is_array( $extra ) ? $extra : array();

		return $snapshot;
	}

	/**
	 * Count total and variation attributes.
	 *
	 * @param array $attributes WooCommerce product attributes.
	 * @return void
	 */
	private function apply_attributes( array $attributes ) {
		$this->attribute_count = count( $attributes );

		foreach ( $attributes as $attribute ) {
			if ( is_object( $attribute ) && is_callable( array( $attribute, 'get_variation' ) ) && $attribute->get_variation() ) {
				++$this->variation_attribute_count;
			}
		}
	}

	/**
	 * Read one entry of the extension data.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key           Entry key.
	 * @param mixed  $default_value Returned when the entry is missing.
	 * @return mixed
	 */
	public function get_extra( $key, $default_value = null ) {
		return array_key_exists( $key, $this->extra ) ? $this->extra[ $key ] : $default_value;
	}

	/**
	 * Get the categories that are not the site's default bucket.
	 *
	 * @return int[]
	 */
	public function get_real_category_ids() {
		if ( $this->default_category_id <= 0 ) {
			return $this->category_ids;
		}

		return array_values( array_diff( $this->category_ids, array( $this->default_category_id ) ) );
	}

	/**
	 * Whether the product is a variable product.
	 *
	 * @return bool
	 */
	public function is_variable() {
		return 'variable' === $this->type;
	}

	/**
	 * Whether the product is a grouped product.
	 *
	 * @return bool
	 */
	public function is_grouped() {
		return 'grouped' === $this->type;
	}

	/**
	 * Whether the product is an external/affiliate product.
	 *
	 * @return bool
	 */
	public function is_external() {
		return 'external' === $this->type;
	}
}
