<?php
/**
 * The 1.1 optional checks, check groups, and how Settings stores opt-ins.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Checks\BrandCheck;
use Catavio\Checks\CheckInterface;
use Catavio\Checks\GalleryImagesCheck;
use Catavio\Checks\GtinCheck;
use Catavio\Checks\Registry;
use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;
use Catavio\Scanner\Repository;
use Catavio\Scanner\ScanState;
use Catavio\Scanner\Scanner;
use Catavio\Scanner\Summary;
use Catavio\Settings;

/**
 * A check written against the 1.0 interface only.
 */
class LegacyCheck implements CheckInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_id() {
		return 'legacy_check';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_label() {
		return 'Legacy';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_severity( ProductSnapshot $snapshot ) {
		return Issue::SEVERITY_INFO;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return string
	 */
	public function get_message( ProductSnapshot $snapshot ) {
		return 'Legacy message.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function applies_to( ProductSnapshot $snapshot ) {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ProductSnapshot $snapshot Product under test.
	 * @return bool
	 */
	public function passes( ProductSnapshot $snapshot ) {
		return false;
	}
}

/**
 * Covers GTIN, brand and gallery checks, groups, and opt-in storage.
 */
class OptionalChecksTest extends TestCase {

	/**
	 * Reset stubbed state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['catavio_test_options'] = array();
		$GLOBALS['catavio_test_hooks']   = array();
	}

	/**
	 * Leave no filters behind for other tests.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['catavio_test_hooks'] = array();

		parent::tearDown();
	}

	/**
	 * A complete product on a store with GTIN and Brands support.
	 *
	 * @param array<string, mixed> $overrides Field overrides.
	 * @return ProductSnapshot
	 */
	private function product( array $overrides = array() ) {
		return SnapshotFactory::simple(
			array_merge(
				array(
					'gtin_supported'      => true,
					'global_unique_id'    => '4006381333931',
					'brand_supported'     => true,
					'brand_ids'           => array( 9 ),
					'gallery_image_count' => 2,
				),
				$overrides
			)
		);
	}

	/**
	 * Build a scanner over the given settings.
	 *
	 * @param Settings|null $settings Settings.
	 * @return Scanner
	 */
	private function scanner( ?Settings $settings = null ) {
		return new Scanner( new Registry(), new Repository(), new ScanState(), new Summary(), $settings );
	}

	/**
	 * All three new checks are off until turned on.
	 *
	 * @return void
	 */
	public function test_new_checks_are_off_by_default() {
		$registry = new Registry();

		$this->assertSame( array( 'missing_gallery_images', 'missing_gtin', 'missing_brand' ), $registry->get_default_off_ids() );

		$result = $this->scanner()->scan_snapshot(
			$this->product(
				array(
					'global_unique_id'    => '',
					'brand_ids'           => array(),
					'gallery_image_count' => 0,
				)
			),
			1
		);

		$this->assertSame( array(), $result->get_issue_codes() );
		$this->assertNotContains( 'missing_gtin', $result->get_passed() );
	}

	/**
	 * Turned on, each check reports what is missing.
	 *
	 * @return void
	 */
	public function test_turned_on_checks_report_missing_data() {
		$registry = new Registry();
		$settings = new Settings();
		$settings->set_disabled_checks( array(), $registry->get_check_ids(), $registry->get_default_off_ids() );

		$scanner = $this->scanner( $settings );

		$missing = $scanner->scan_snapshot(
			$this->product(
				array(
					'global_unique_id'    => '  ',
					'brand_ids'           => array(),
					'gallery_image_count' => 0,
				)
			),
			1
		);

		$codes = $missing->get_issue_codes();
		sort( $codes );

		$this->assertSame( array( 'missing_brand', 'missing_gallery_images', 'missing_gtin' ), $codes );

		$complete = $scanner->scan_snapshot( $this->product(), 1 );

		$this->assertSame( array(), $complete->get_issue_codes() );
		$this->assertContains( 'missing_gtin', $complete->get_passed() );
	}

	/**
	 * Each new check is informational.
	 *
	 * @return void
	 */
	public function test_new_checks_are_informational() {
		foreach ( array( new GtinCheck(), new BrandCheck(), new GalleryImagesCheck() ) as $check ) {
			$this->assertSame( Issue::SEVERITY_INFO, $check->get_severity( new ProductSnapshot() ) );
		}
	}

	/**
	 * GTIN applies only where WooCommerce has the field, and not to variable
	 * or grouped products.
	 *
	 * @return void
	 */
	public function test_gtin_applies_only_where_supported() {
		$check = new GtinCheck();

		$this->assertTrue( $check->applies_to( $this->product() ) );
		$this->assertTrue( $check->applies_to( $this->product( array( 'type' => 'external' ) ) ) );
		$this->assertFalse( $check->applies_to( $this->product( array( 'gtin_supported' => false ) ) ) );
		$this->assertFalse( $check->applies_to( $this->product( array( 'type' => 'variable' ) ) ) );
		$this->assertFalse( $check->applies_to( $this->product( array( 'type' => 'grouped' ) ) ) );
	}

	/**
	 * Brand applies only when the taxonomy exists, and not to grouped products.
	 *
	 * @return void
	 */
	public function test_brand_applies_only_where_supported() {
		$check = new BrandCheck();

		$this->assertTrue( $check->applies_to( $this->product( array( 'type' => 'variable' ) ) ) );
		$this->assertFalse( $check->applies_to( $this->product( array( 'brand_supported' => false ) ) ) );
		$this->assertFalse( $check->applies_to( $this->product( array( 'type' => 'grouped' ) ) ) );
	}

	/**
	 * Gallery images are not expected of grouped or virtual products.
	 *
	 * @return void
	 */
	public function test_gallery_skips_grouped_and_virtual_products() {
		$check = new GalleryImagesCheck();

		$this->assertTrue( $check->applies_to( $this->product() ) );
		$this->assertFalse( $check->applies_to( $this->product( array( 'type' => 'grouped' ) ) ) );
		$this->assertFalse( $check->applies_to( $this->product( array( 'is_virtual' => true ) ) ) );
	}

	/**
	 * Every built-in check sits in a named group, never in "other".
	 *
	 * @return void
	 */
	public function test_every_built_in_check_has_a_group() {
		$registry = new Registry();
		$groups   = $registry->get_groups();

		foreach ( $registry->get_checks() as $check ) {
			$group = $registry->get_group_id( $check );

			$this->assertNotSame( Registry::GROUP_OTHER, $group, $check->get_id() );
			$this->assertSame( $groups[ $group ], $registry->get_group_label( $check ) );
		}

		$this->assertSame( Registry::GROUP_OTHER, array_key_last( $groups ) );
	}

	/**
	 * Extensions add groups but cannot rename built-in ones or "other".
	 *
	 * @return void
	 */
	public function test_extensions_can_add_groups_only() {
		add_filter(
			'catavio_check_groups',
			static function ( $groups ) {
				$groups['seo']      = 'SEO';
				$groups['content']  = 'Renamed';
				$groups['other']    = 'Renamed';
				$groups['Bad Key!'] = 'Sanitized';
				$groups['empty']    = '  ';

				return $groups;
			}
		);

		$groups = ( new Registry() )->get_groups();

		$this->assertSame( 'SEO', $groups['seo'] );
		$this->assertSame( 'Content', $groups['content'] );
		$this->assertSame( 'Other checks', $groups['other'] );
		$this->assertSame( 'Sanitized', $groups['badkey'] );
		$this->assertArrayNotHasKey( 'empty', $groups );
		$this->assertSame( Registry::GROUP_OTHER, array_key_last( $groups ) );
	}

	/**
	 * A check written for 1.0 still runs, is on, and is listed under "other".
	 *
	 * @return void
	 */
	public function test_legacy_checks_keep_working() {
		add_filter(
			'catavio_checks',
			static function ( $checks ) {
				$checks[] = new LegacyCheck();

				return $checks;
			}
		);

		$registry = new Registry();
		$legacy   = $registry->get_check( 'legacy_check' );

		$this->assertNotNull( $legacy );
		$this->assertSame( Registry::GROUP_OTHER, $registry->get_group_id( $legacy ) );
		$this->assertNotContains( 'legacy_check', $registry->get_default_off_ids() );

		$result = $this->scanner()->scan_snapshot( $this->product(), 1 );

		$this->assertContains( 'legacy_check', $result->get_issue_codes() );
	}

	/**
	 * Only differences from each default are stored.
	 *
	 * @return void
	 */
	public function test_settings_store_differences_from_defaults() {
		$known       = array( 'missing_sku', 'missing_title', 'missing_gtin', 'missing_brand' );
		$default_off = array( 'missing_gtin', 'missing_brand' );
		$settings    = new Settings();

		$this->assertSame( array( 'missing_gtin', 'missing_brand' ), $settings->get_disabled_checks( $default_off ) );
		$this->assertFalse( $settings->is_enabled( 'missing_gtin', $default_off ) );

		// Turn SKU off and GTIN on; brand stays at its default, off.
		$result = $settings->set_disabled_checks( array( 'missing_sku', 'missing_brand' ), $known, $default_off );

		sort( $result );

		$this->assertSame( array( 'missing_brand', 'missing_sku' ), $result );
		$this->assertTrue( $settings->is_enabled( 'missing_gtin', $default_off ) );
		$this->assertFalse( $settings->is_enabled( 'missing_brand', $default_off ) );

		$stored = $GLOBALS['catavio_test_options'][ Settings::OPTION ];

		$this->assertSame( array( 'missing_sku' ), $stored['disabled_checks'] );
		$this->assertSame( array( 'missing_gtin' ), $stored['enabled_checks'] );
	}

	/**
	 * Settings saved by 1.0, without an opt-in list, read the same in 1.1.
	 *
	 * @return void
	 */
	public function test_settings_from_1_0_still_read() {
		$GLOBALS['catavio_test_options'][ Settings::OPTION ] = array(
			'disabled_checks' => array( 'missing_sku' ),
			'updated_at'      => 1000,
		);

		$settings = new Settings();

		$this->assertSame( array( 'missing_sku', 'missing_gtin' ), $settings->get_disabled_checks( array( 'missing_gtin' ) ) );
		$this->assertSame( 1000, $settings->get_updated_at() );
	}

	/**
	 * Saving the defaults again changes nothing and fires nothing.
	 *
	 * @return void
	 */
	public function test_unchanged_opt_in_choice_keeps_its_timestamp() {
		$known       = array( 'missing_sku', 'missing_gtin' );
		$default_off = array( 'missing_gtin' );
		$settings    = new Settings();

		$GLOBALS['catavio_test_actions'] = array();

		$settings->set_disabled_checks( array( 'missing_gtin' ), $known, $default_off );

		$this->assertSame( 0, $settings->get_updated_at() );
		$this->assertSame( array(), $GLOBALS['catavio_test_actions'] );

		$settings->set_disabled_checks( array(), $known, $default_off );

		$this->assertGreaterThan( 0, $settings->get_updated_at() );
		$this->assertSame( 'catavio_settings_saved', $GLOBALS['catavio_test_actions'][0]['tag'] );
		$this->assertSame( array(), $GLOBALS['catavio_test_actions'][0]['args'][0] );
	}
}
