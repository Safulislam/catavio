<?php
/**
 * Gallery images check.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Checks;

use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;

defined( 'ABSPATH' ) || exit;

/**
 * At least one gallery image beyond the featured image, so a shopper can see
 * the product from more than one side.
 *
 * Off until turned on in Settings; see OptionalCheckInterface for why.
 *
 * @since 1.1.0
 */
class GalleryImagesCheck extends AbstractCheck {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_id() {
		return 'missing_gallery_images';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_group() {
		return Registry::GROUP_MEDIA;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label() {
		return __( 'Gallery images', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_severity( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return Issue::SEVERITY_INFO;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_message( ProductSnapshot $snapshot ) {
		unset( $snapshot );

		return __( 'The product has no gallery images besides the featured image.', 'catavio' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return bool
	 */
	public function is_enabled_by_default() {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Not grouped products, which show the products they group, and not virtual
	 * products, which often have nothing physical to photograph.
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function applies_to( ProductSnapshot $snapshot ) {
		return ! $snapshot->is_grouped() && ! $snapshot->is_virtual;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot ) {
		return $snapshot->gallery_image_count > 0;
	}
}
