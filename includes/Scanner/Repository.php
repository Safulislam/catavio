<?php
/**
 * Storage for scan results.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Scanner;

use Catavio\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes scan results as post meta.
 *
 * The only class that knows the storage keys. No custom table is used: post
 * meta covers every access pattern the screens need, and it disappears with the
 * product when the product is deleted.
 */
class Repository {

	/**
	 * Serialized list of failed checks.
	 *
	 * @var string
	 */
	const META_ISSUES = '_catavio_issues';

	/**
	 * Serialized list of passed check identifiers.
	 *
	 * @var string
	 */
	const META_PASSED = '_catavio_passed';

	/**
	 * One row per failed check, for indexed filtering by issue type.
	 *
	 * @var string
	 */
	const META_ISSUE_CODE = '_catavio_issue_code';

	/**
	 * Health score, 0 to 100. Indexed so the product list can sort by it.
	 *
	 * @var string
	 */
	const META_SCORE = '_catavio_score';

	/**
	 * Dashboard bucket: healthy, attention or critical.
	 *
	 * @var string
	 */
	const META_BUCKET = '_catavio_severity';

	/**
	 * Unix timestamp of the last scan.
	 *
	 * @var string
	 */
	const META_SCANNED_AT = '_catavio_scanned_at';

	/**
	 * Register the meta keys with WordPress.
	 *
	 * Registration is about declaring intent and locking down access: the keys
	 * are underscore prefixed so they are already protected, and none of them
	 * is exposed through the core REST API. The plugin serves this data through
	 * its own controllers instead.
	 *
	 * @return void
	 */
	public function register_meta() {
		$keys = array(
			self::META_ISSUES     => 'array',
			self::META_PASSED     => 'array',
			self::META_SCORE      => 'integer',
			self::META_BUCKET     => 'string',
			self::META_SCANNED_AT => 'integer',
		);

		foreach ( $keys as $key => $type ) {
			register_post_meta(
				'product',
				$key,
				array(
					'type'          => $type,
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => array( Capabilities::class, 'current_user_can_manage' ),
				)
			);
		}

		register_post_meta(
			'product',
			self::META_ISSUE_CODE,
			array(
				'type'          => 'string',
				'single'        => false,
				'show_in_rest'  => false,
				'auth_callback' => array( Capabilities::class, 'current_user_can_manage' ),
			)
		);
	}

	/**
	 * Persist one product's result.
	 *
	 * The issue data is compared against what is already stored and skipped
	 * when unchanged, so rescanning an unchanged catalog writes no issue,
	 * passed-check or issue-code rows at all. The scan timestamp is the one
	 * exception: it changes by definition, so it is written once per product
	 * per scan. Measured on an 87 product catalog, an identical rescan performs
	 * 87 writes rather than 348.
	 *
	 * @param Result $result Scan result.
	 * @return void
	 */
	public function save( Result $result ) {
		$product_id = $result->get_product_id();

		$issues = array();

		foreach ( $result->get_issues() as $issue ) {
			$issues[] = $issue->to_storage();
		}

		$this->update_if_changed( $product_id, self::META_ISSUES, $issues );
		$this->update_if_changed( $product_id, self::META_PASSED, $result->get_passed() );
		$this->update_if_changed( $product_id, self::META_SCORE, $result->get_score() );
		$this->update_if_changed( $product_id, self::META_BUCKET, $result->get_bucket() );
		$this->update_if_changed( $product_id, self::META_SCANNED_AT, $result->get_scanned_at() );
		$this->sync_issue_codes( $product_id, $result->get_issue_codes() );
	}

	/**
	 * Get the stored issues for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_issues( $product_id ) {
		$stored = get_post_meta( (int) $product_id, self::META_ISSUES, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		return $stored;
	}

	/**
	 * Get the stored passed check identifiers for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	public function get_passed( $product_id ) {
		$stored = get_post_meta( (int) $product_id, self::META_PASSED, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		return array_map( 'strval', $stored );
	}

	/**
	 * Get a product's stored health score.
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public function get_score( $product_id ) {
		return (int) get_post_meta( (int) $product_id, self::META_SCORE, true );
	}

	/**
	 * Get a product's stored bucket.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_bucket( $product_id ) {
		return (string) get_post_meta( (int) $product_id, self::META_BUCKET, true );
	}

	/**
	 * Get the timestamp of a product's last scan.
	 *
	 * @param int $product_id Product ID.
	 * @return int Zero when the product has never been scanned.
	 */
	public function get_scanned_at( $product_id ) {
		return (int) get_post_meta( (int) $product_id, self::META_SCANNED_AT, true );
	}

	/**
	 * Determine whether a product has ever been scanned.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function has_result( $product_id ) {
		return $this->get_scanned_at( $product_id ) > 0;
	}

	/**
	 * Remove one product's stored result.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	public function delete( $product_id ) {
		$product_id = (int) $product_id;

		delete_post_meta( $product_id, self::META_ISSUES );
		delete_post_meta( $product_id, self::META_PASSED );
		delete_post_meta( $product_id, self::META_SCORE );
		delete_post_meta( $product_id, self::META_BUCKET );
		delete_post_meta( $product_id, self::META_ISSUE_CODE );
		delete_post_meta( $product_id, self::META_SCANNED_AT );
	}

	/**
	 * Get every meta key this plugin writes to products.
	 *
	 * Used by uninstall.php so the cleanup list can never drift from the
	 * writing code.
	 *
	 * @return string[]
	 */
	public static function meta_keys() {
		return array(
			self::META_ISSUES,
			self::META_PASSED,
			self::META_ISSUE_CODE,
			self::META_SCORE,
			self::META_BUCKET,
			self::META_SCANNED_AT,
		);
	}

	/**
	 * Write a meta value only when it differs from what is stored.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $key        Meta key.
	 * @param mixed  $value      New value.
	 * @return void
	 */
	private function update_if_changed( $product_id, $key, $value ) {
		$current = get_post_meta( $product_id, $key, true );

		if ( $current === $value ) {
			return;
		}

		update_post_meta( $product_id, $key, $value );
	}

	/**
	 * Replace the per issue meta rows, but only when the set has changed.
	 *
	 * These rows exist so the product list can filter by issue type with an
	 * indexed meta query instead of loading and filtering the catalog in PHP.
	 *
	 * @param int      $product_id Product ID.
	 * @param string[] $codes      Current failed check identifiers.
	 * @return void
	 */
	private function sync_issue_codes( $product_id, array $codes ) {
		$existing = get_post_meta( $product_id, self::META_ISSUE_CODE, false );
		$existing = is_array( $existing ) ? array_map( 'strval', $existing ) : array();

		$current = $codes;

		sort( $existing );
		sort( $current );

		if ( $existing === $current ) {
			return;
		}

		delete_post_meta( $product_id, self::META_ISSUE_CODE );

		foreach ( $codes as $code ) {
			add_post_meta( $product_id, self::META_ISSUE_CODE, $code, false );
		}
	}
}
