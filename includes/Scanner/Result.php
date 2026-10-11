<?php
/**
 * The outcome of scanning one product.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Scanner;

use Catavio\HealthScore;

defined( 'ABSPATH' ) || exit;

/**
 * Structured result for one product: what failed, what passed, and when.
 */
class Result {

	/**
	 * Product ID.
	 *
	 * @var int
	 */
	private $product_id;

	/**
	 * Product type slug.
	 *
	 * @var string
	 */
	private $product_type;

	/**
	 * Failed checks.
	 *
	 * @var Issue[]
	 */
	private $issues;

	/**
	 * Identifiers of checks that applied and passed.
	 *
	 * @var string[]
	 */
	private $passed;

	/**
	 * Unix timestamp of the scan.
	 *
	 * @var int
	 */
	private $scanned_at;

	/**
	 * Constructor.
	 *
	 * @param int      $product_id   Product ID.
	 * @param string   $product_type Product type slug.
	 * @param Issue[]  $issues       Failed checks.
	 * @param string[] $passed       Passed check identifiers.
	 * @param int      $scanned_at   Unix timestamp.
	 */
	public function __construct( $product_id, $product_type, array $issues, array $passed, $scanned_at ) {
		$this->product_id   = (int) $product_id;
		$this->product_type = (string) $product_type;
		$this->issues       = $issues;
		$this->passed       = $passed;
		$this->scanned_at   = (int) $scanned_at;
	}

	/**
	 * Get the product ID.
	 *
	 * @return int
	 */
	public function get_product_id() {
		return $this->product_id;
	}

	/**
	 * Get the product type slug.
	 *
	 * @return string
	 */
	public function get_product_type() {
		return $this->product_type;
	}

	/**
	 * Get every issue.
	 *
	 * @return Issue[]
	 */
	public function get_issues() {
		return $this->issues;
	}

	/**
	 * Get the identifiers of checks that applied and passed.
	 *
	 * @return string[]
	 */
	public function get_passed() {
		return $this->passed;
	}

	/**
	 * Get the scan timestamp.
	 *
	 * @return int
	 */
	public function get_scanned_at() {
		return $this->scanned_at;
	}

	/**
	 * Count issues, optionally of one severity.
	 *
	 * @param string $severity Optional severity filter.
	 * @return int
	 */
	public function count_issues( $severity = '' ) {
		if ( '' === $severity ) {
			return count( $this->issues );
		}

		$total = 0;

		foreach ( $this->issues as $issue ) {
			if ( $issue->get_severity() === $severity ) {
				++$total;
			}
		}

		return $total;
	}

	/**
	 * Count issues per severity.
	 *
	 * @return array<string, int>
	 */
	public function get_severity_counts() {
		$counts = array();

		foreach ( Issue::severities() as $severity ) {
			$counts[ $severity ] = $this->count_issues( $severity );
		}

		return $counts;
	}

	/**
	 * Get this product's health score.
	 *
	 * The scoring rules live in HealthScore and are documented there in full.
	 *
	 * @return int
	 */
	public function get_score() {
		return HealthScore::for_result( $this );
	}

	/**
	 * Get the dashboard bucket this product falls into.
	 *
	 * @return string One of the HealthScore::BUCKET_* constants.
	 */
	public function get_bucket() {
		return HealthScore::bucket_for( $this );
	}

	/**
	 * Get the identifiers of every failed check.
	 *
	 * @return string[]
	 */
	public function get_issue_codes() {
		$codes = array();

		foreach ( $this->issues as $issue ) {
			$codes[] = $issue->get_check_id();
		}

		return array_values( array_unique( $codes ) );
	}
}
