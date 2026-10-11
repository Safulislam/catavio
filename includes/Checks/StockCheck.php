<?php
/**
 * Stock configuration check.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * Stock configuration, where stock is a meaningful concept.
 *
 * External products are fulfilled on another site and grouped products hold no
 * stock of their own, so neither is judged here. Virtual and downloadable
 * products are included, because they may still manage stock, but they are
 * never judged on shipping configuration anywhere in this plugin.
 */
class StockCheck extends AbstractCheck {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_id() {
		return 'incomplete_stock_configuration';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Stock configuration', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_message( ProductSnapshot $snapshot ) {
		if ( $snapshot->manages_stock ) {
			return __( 'Stock management is enabled but no stock quantity is set.', 'catavio' );
		}

		return __( 'The product has no stock status.', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function applies_to( ProductSnapshot $snapshot ) {
		return ! $snapshot->is_external() && ! $snapshot->is_grouped();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot ) {
		if ( '' === trim( $snapshot->stock_status ) ) {
			return false;
		}

		if ( $snapshot->manages_stock && null === $snapshot->stock_quantity ) {
			return false;
		}

		return true;
	}
}
