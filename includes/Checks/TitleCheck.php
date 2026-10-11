<?php
/**
 * Product title check.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * Every product must have a title. Without one it cannot be found or ordered.
 */
class TitleCheck extends AbstractCheck {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_id() {
		return 'missing_title';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Product title', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_severity( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return Issue::SEVERITY_CRITICAL;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_message( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return __( 'The product title is missing.', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot ) {
		return '' !== trim( $snapshot->name );
	}
}
