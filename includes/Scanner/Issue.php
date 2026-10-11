<?php
/**
 * A single product issue.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * One failed check against one product.
 */
class Issue {

	/**
	 * Blocks the product from being sold or listed correctly.
	 *
	 * @var string
	 */
	const SEVERITY_CRITICAL = 'critical';

	/**
	 * Incomplete information that should be corrected.
	 *
	 * @var string
	 */
	const SEVERITY_WARNING = 'warning';

	/**
	 * Informational finding, safe to ignore in many stores.
	 *
	 * @var string
	 */
	const SEVERITY_INFO = 'info';

	/**
	 * Check identifier, for example "missing_sku".
	 *
	 * @var string
	 */
	private $check_id;

	/**
	 * Severity, one of the SEVERITY_* constants.
	 *
	 * @var string
	 */
	private $severity;

	/**
	 * Translated, human readable explanation.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Product this issue belongs to.
	 *
	 * @var int
	 */
	private $product_id;

	/**
	 * Optional supporting data for the UI.
	 *
	 * @var array<string, scalar>
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @param string                $check_id   Check identifier.
	 * @param string                $severity   One of the SEVERITY_* constants.
	 * @param string                $message    Translated message.
	 * @param int                   $product_id Product ID.
	 * @param array<string, scalar> $data       Optional supporting data.
	 */
	public function __construct( $check_id, $severity, $message, $product_id, array $data = array() ) {
		$this->check_id   = (string) $check_id;
		$this->severity   = self::is_valid_severity( $severity ) ? (string) $severity : self::SEVERITY_INFO;
		$this->message    = (string) $message;
		$this->product_id = (int) $product_id;
		$this->data       = $data;
	}

	/**
	 * Get every valid severity, most severe first.
	 *
	 * @return string[]
	 */
	public static function severities() {
		return array( self::SEVERITY_CRITICAL, self::SEVERITY_WARNING, self::SEVERITY_INFO );
	}

	/**
	 * Determine whether a severity is recognised.
	 *
	 * @param string $severity Severity to test.
	 * @return bool
	 */
	public static function is_valid_severity( $severity ) {
		return in_array( $severity, self::severities(), true );
	}

	/**
	 * Get the check identifier.
	 *
	 * @return string
	 */
	public function get_check_id() {
		return $this->check_id;
	}

	/**
	 * Get the severity.
	 *
	 * @return string
	 */
	public function get_severity() {
		return $this->severity;
	}

	/**
	 * Get the translated message.
	 *
	 * @return string
	 */
	public function get_message() {
		return $this->message;
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
	 * Get the supporting data.
	 *
	 * @return array<string, scalar>
	 */
	public function get_data() {
		return $this->data;
	}

	/**
	 * Convert to the array shape persisted in post meta.
	 *
	 * Messages are deliberately not stored: they are translated at read time so
	 * that switching site language does not require a rescan.
	 *
	 * @return array<string, mixed>
	 */
	public function to_storage() {
		$stored = array(
			'check'    => $this->check_id,
			'severity' => $this->severity,
		);

		if ( array() !== $this->data ) {
			$stored['data'] = $this->data;
		}

		return $stored;
	}
}
