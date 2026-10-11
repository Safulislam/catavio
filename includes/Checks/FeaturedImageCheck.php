<?php
/**
 * Featured image check.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * A product without a featured image shows a placeholder everywhere.
 */
class FeaturedImageCheck extends AbstractCheck {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_id() {
		return 'missing_featured_image';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Featured image', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_message( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return __( 'The product has no featured image.', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot ) {
		return $snapshot->featured_image_id > 0;
	}
}
