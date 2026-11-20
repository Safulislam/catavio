<?php
/**
 * Optional contract: a check that belongs to a named group.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Places a check under a heading, such as "Content" or "Images".
 *
 * Kept separate from CheckInterface so that checks written against 1.0 keep
 * working unchanged: a check that does not implement this lands in the "other"
 * group.
 *
 * @since 1.1.0
 */
interface GroupedCheckInterface {

	/**
	 * Group identifier, one of the keys Registry::get_groups() returns.
	 *
	 * @return string
	 */
	public function get_group();
}
