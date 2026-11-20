<?php
/**
 * Optional contract: a check that is off until the store owner turns it on.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a check start turned off.
 *
 * Some information only matters to some stores. A shop selling its own goods
 * has no GTINs, and a single-brand shop does not assign brands. Flagging every
 * product in those shops would bury their real gaps, and adding such a check in
 * an update would lower every existing store's scores on the next scan without
 * anything about their products having changed. So these checks wait to be
 * turned on in Settings.
 *
 * @since 1.1.0
 */
interface OptionalCheckInterface {

	/**
	 * Whether the check runs before the store owner has chosen.
	 *
	 * @return bool
	 */
	public function is_enabled_by_default();
}
