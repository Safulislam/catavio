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
 * first. The filter at the end of get_checks() is how extensions add checks;
 * nothing in the free plugin uses it.
 */
class Registry {

	/**
	 * Titles, descriptions and categories.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const GROUP_CONTENT = 'content';

	/**
	 * Featured image, gallery and alt text.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const GROUP_MEDIA = 'media';

	/**
	 * Price and stock.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const GROUP_COMMERCE = 'commerce';

	/**
	 * SKU, GTIN and brand.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const GROUP_IDENTIFIERS = 'identifiers';

	/**
	 * Setup that depends on the product type: variations, URLs, files.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const GROUP_STRUCTURE = 'structure';

	/**
	 * Where a check without a known group is listed.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const GROUP_OTHER = 'other';

	/**
	 * Resolved check instances.
	 *
	 * @var CheckInterface[]|null
	 */
	private $checks = null;

	/**
	 * Resolved groups.
	 *
	 * @var array<string, string>|null
	 */
	private $groups = null;

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
			new GalleryImagesCheck(),
			new CategoryCheck(),
			new SkuCheck(),
			new GtinCheck(),
			new BrandCheck(),
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
	 * Get the check groups, in display order.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string> Labels keyed by group identifier.
	 */
	public function get_groups() {
		if ( null !== $this->groups ) {
			return $this->groups;
		}

		$groups = array(
			self::GROUP_CONTENT     => __( 'Content', 'catavio' ),
			self::GROUP_MEDIA       => __( 'Images', 'catavio' ),
			self::GROUP_COMMERCE    => __( 'Price and stock', 'catavio' ),
			self::GROUP_IDENTIFIERS => __( 'Brand and identifiers', 'catavio' ),
			self::GROUP_STRUCTURE   => __( 'Product type setup', 'catavio' ),
		);

		/**
		 * Filters the groups checks are listed under.
		 *
		 * Add a group by adding a key and a translated label. Built-in groups
		 * cannot be removed or renamed, and "other" is always listed last.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, string> $groups Labels keyed by group identifier.
		 */
		$filtered = apply_filters( 'catavio_check_groups', $groups );
		$resolved = $groups;

		foreach ( (array) $filtered as $id => $label ) {
			$id = sanitize_key( (string) $id );

			if ( '' === $id || isset( $resolved[ $id ] ) || self::GROUP_OTHER === $id || ! is_string( $label ) || '' === trim( $label ) ) {
				continue;
			}

			$resolved[ $id ] = $label;
		}

		$resolved[ self::GROUP_OTHER ] = __( 'Other checks', 'catavio' );

		$this->groups = $resolved;

		return $this->groups;
	}

	/**
	 * Get the group a check is listed under.
	 *
	 * @since 1.1.0
	 *
	 * @param CheckInterface $check Check.
	 * @return string A key of get_groups().
	 */
	public function get_group_id( CheckInterface $check ) {
		if ( ! $check instanceof GroupedCheckInterface ) {
			return self::GROUP_OTHER;
		}

		$group = (string) $check->get_group();

		return array_key_exists( $group, $this->get_groups() ) ? $group : self::GROUP_OTHER;
	}

	/**
	 * Get the label of the group a check is listed under.
	 *
	 * @since 1.1.0
	 *
	 * @param CheckInterface $check Check.
	 * @return string
	 */
	public function get_group_label( CheckInterface $check ) {
		$groups = $this->get_groups();

		return $groups[ $this->get_group_id( $check ) ];
	}

	/**
	 * Get the identifiers of checks that are off until turned on.
	 *
	 * @since 1.1.0
	 *
	 * @return string[]
	 */
	public function get_default_off_ids() {
		$ids = array();

		foreach ( $this->get_checks() as $check ) {
			if ( $check instanceof OptionalCheckInterface && ! $check->is_enabled_by_default() ) {
				$ids[] = $check->get_id();
			}
		}

		return $ids;
	}

	/**
	 * Reset the memoized checks and groups.
	 *
	 * @return void
	 */
	public function reset() {
		$this->checks = null;
		$this->groups = null;
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
