<?php
/**
 * Product short description check.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * The short description is used in the cart, in listings and in feeds.
 */
class ShortDescriptionCheck extends AbstractCheck {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_id() {
		return 'missing_short_description';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Short description', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_message( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return __( 'The short description is missing.', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot ) {
		return ! $this->is_blank( $snapshot->short_description );
	}
}
