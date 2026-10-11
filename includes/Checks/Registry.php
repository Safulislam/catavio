<?php
/**
 * The ordered set of checks the scanner runs.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Holds every check in a fixed, documented order.
 *
 * The order is the order results are displayed in, so it is deliberate rather
 * than incidental: the checks a store owner is most likely to act on come
 * first. The filter at the end of get_checks() is the single extension seam;
 * nothing in the free plugin uses it.
 */
class Registry {

	/**
	 * Resolved check instances.
	 *
	 * @var CheckInterface[]|null
	 */
	private $checks = null;

	/**
	 * Get every registered check.
	 *
	 * @return CheckInterface[]
	 */
	public function get_checks() {
		if ( null !== $this->checks ) {
			return $this->checks;
		}

		$checks = array(
			new TitleCheck(),
			new DescriptionCheck(),
			new ShortDescriptionCheck(),
			new PriceCheck(),
			new FeaturedImageCheck(),
			new ImageAltTextCheck(),
			new CategoryCheck(),
			new SkuCheck(),
			new StockCheck(),
			new VariationsCheck(),
			new VariationAttributesCheck(),
			new ExternalUrlCheck(),
			new DownloadableFilesCheck(),
		);

		/**
		 * Filters the checks Catavio runs against each product.
		 *
		 * Every entry must implement CheckInterface. Entries that do not are
		 * discarded, so a faulty extension cannot break a scan.
		 *
		 * @since 1.0.0
		 *
		 * @param CheckInterface[] $checks Ordered list of checks.
		 */
		$filtered = apply_filters( 'catavio_checks', $checks );

		$this->checks = $this->sanitize( $filtered );

		return $this->checks;
	}

	/**
	 * Get a single check by identifier.
	 *
	 * @param string $id Check identifier.
	 * @return CheckInterface|null
	 */
	public function get_check( $id ) {
		foreach ( $this->get_checks() as $check ) {
			if ( $check->get_id() === $id ) {
				return $check;
			}
		}

		return null;
	}

	/**
	 * Get every registered check identifier.
	 *
	 * Used to validate the issue filter on the product list, so that only known
	 * values ever reach a query.
	 *
	 * @return string[]
	 */
	public function get_check_ids() {
		$ids = array();

		foreach ( $this->get_checks() as $check ) {
			$ids[] = $check->get_id();
		}

		return $ids;
	}

	/**
	 * Reset the memoized checks.
	 *
	 * @return void
	 */
	public function reset() {
		$this->checks = null;
	}

	/**
	 * Discard anything that is not a usable check, and any duplicate id.
	 *
	 * @param mixed $checks Filtered value.
	 * @return CheckInterface[]
	 */
	private function sanitize( $checks ) {
		$valid = array();
		$seen  = array();

		foreach ( (array) $checks as $check ) {
			if ( ! $check instanceof CheckInterface ) {
				continue;
			}

			$id = $check->get_id();

			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}

			$seen[ $id ] = true;
			$valid[]     = $check;
		}

		return $valid;
	}
}
