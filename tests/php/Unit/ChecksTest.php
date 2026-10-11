<?php
/**
 * The twelve scenarios Phase 3 requires, plus the type awareness rules.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Checks\Registry;
use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;
use Catavio\Scanner\Repository;
use Catavio\Scanner\ScanState;
use Catavio\Scanner\Result;
use Catavio\Scanner\Scanner;
use Catavio\Scanner\Summary;

/**
 * Exercises the rule set against each supported product type.
 */
class ChecksTest extends TestCase {

	/**
	 * Scanner under test.
	 *
	 * @var Scanner
	 */
	private $scanner;

	/**
	 * Set up the scanner.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->scanner = new Scanner( new Registry(), new Repository(), new ScanState(), new Summary() );
	}

	/**
	 * Scan a snapshot.
	 *
	 * @param ProductSnapshot $snapshot Snapshot to scan.
	 * @return Result
	 */
	private function scan( ProductSnapshot $snapshot ) {
		return $this->scanner->scan_snapshot( $snapshot, 1700000000 );
	}

	/**
	 * Assert a result reports exactly the given failed check identifiers.
	 *
	 * @param string[] $expected Expected failed check identifiers.
	 * @param Result   $result   Result under test.
	 * @return void
	 */
	private function assertIssueCodes( array $expected, Result $result ) {
		$actual = $result->get_issue_codes();

		sort( $expected );
		sort( $actual );

		$this->assertSame( $expected, $actual );
	}

	// -------------------------------------------------------------- scenario 1

	/**
	 * A complete product reports nothing.
	 *
	 * @return void
	 */
	public function test_complete_product_has_no_issues() {
		$result = $this->scan( SnapshotFactory::simple() );

		$this->assertIssueCodes( array(), $result );
		$this->assertSame( 0, $result->count_issues() );
		$this->assertNotEmpty( $result->get_passed() );
	}

	// ----------------------------------------------------------- scenarios 2-7

	/**
	 * Each missing field is reported on its own, with the documented severity.
	 *
	 * @dataProvider provide_missing_fields
	 *
	 * @param array<string, mixed> $overrides Snapshot properties to blank out.
	 * @param string               $check_id  Expected failing check.
	 * @param string               $severity  Expected severity.
	 * @return void
	 */
	public function test_missing_field_is_reported( array $overrides, $check_id, $severity ) {
		$result = $this->scan( SnapshotFactory::simple( $overrides ) );

		$this->assertIssueCodes( array( $check_id ), $result );

		$issues = $result->get_issues();

		$this->assertSame( $severity, $issues[0]->get_severity() );
		$this->assertNotSame( '', $issues[0]->get_message() );
		$this->assertSame( 101, $issues[0]->get_product_id() );
		$this->assertNotContains( $check_id, $result->get_passed() );
	}

	/**
	 * The single field scenarios Phase 3 lists.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
	 */
	public function provide_missing_fields() {
		return array(
			'missing title'             => array( array( 'name' => '' ), 'missing_title', Issue::SEVERITY_CRITICAL ),
			'missing description'       => array( array( 'description' => '' ), 'missing_description', Issue::SEVERITY_CRITICAL ),
			'missing short description' => array( array( 'short_description' => '' ), 'missing_short_description', Issue::SEVERITY_WARNING ),
			'missing price'             => array( array( 'price' => '' ), 'missing_price', Issue::SEVERITY_CRITICAL ),
			'missing SKU'               => array( array( 'sku' => '' ), 'missing_sku', Issue::SEVERITY_WARNING ),
			'missing category'          => array( array( 'category_ids' => array() ), 'missing_category', Issue::SEVERITY_WARNING ),
		);
	}

	/**
	 * A missing featured image is reported, and alt text is then not judged.
	 *
	 * Reporting both would penalise one underlying problem twice.
	 *
	 * @return void
	 */
	public function test_missing_featured_image_suppresses_the_alt_text_check() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'featured_image_id'  => 0,
					'featured_image_alt' => '',
				)
			)
		);

		$this->assertIssueCodes( array( 'missing_featured_image' ), $result );
		$this->assertNotContains( 'missing_image_alt_text', $result->get_passed() );
	}

	/**
	 * Alt text is reported only when there is an image to describe.
	 *
	 * @return void
	 */
	public function test_missing_alt_text_is_informational() {
		$result = $this->scan( SnapshotFactory::simple( array( 'featured_image_alt' => '' ) ) );

		$this->assertIssueCodes( array( 'missing_image_alt_text' ), $result );
		$this->assertSame( Issue::SEVERITY_INFO, $result->get_issues()[0]->get_severity() );
	}

	/**
	 * Markup that renders as nothing counts as missing.
	 *
	 * @dataProvider provide_blank_descriptions
	 *
	 * @param string $value Description value.
	 * @return void
	 */
	public function test_visually_empty_description_counts_as_missing( $value ) {
		$result = $this->scan( SnapshotFactory::simple( array( 'description' => $value ) ) );

		$this->assertContains( 'missing_description', $result->get_issue_codes() );
	}

	/**
	 * Values that look like content but render as nothing.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_blank_descriptions() {
		return array(
			'empty paragraph'    => array( '<p></p>' ),
			'non breaking space' => array( '<p>&nbsp;</p>' ),
			'whitespace only'    => array( "   \n\t " ),
			'empty div'          => array( '<div><span></span></div>' ),
		);
	}

	/**
	 * A product left in the site's default category counts as uncategorised.
	 *
	 * WooCommerce assigns its default category to every product saved without
	 * one, so a check for an empty list alone would never report anything.
	 *
	 * @return void
	 */
	public function test_default_category_only_counts_as_uncategorised() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'category_ids'        => array( 15 ),
					'default_category_id' => 15,
				)
			)
		);

		$this->assertIssueCodes( array( 'missing_category' ), $result );
	}

	/**
	 * A real category alongside the default one still passes.
	 *
	 * @return void
	 */
	public function test_real_category_alongside_the_default_passes() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'category_ids'        => array( 15, 22 ),
					'default_category_id' => 15,
				)
			)
		);

		$this->assertIssueCodes( array(), $result );
	}

	// ------------------------------------------------------------- scenario 8

	/**
	 * A complete variable product reports nothing.
	 *
	 * @return void
	 */
	public function test_complete_variable_product_has_no_issues() {
		$this->assertIssueCodes( array(), $this->scan( SnapshotFactory::variable() ) );
	}

	/**
	 * A variable product with no variations is critical.
	 *
	 * @return void
	 */
	public function test_variable_product_without_variations_is_critical() {
		$result = $this->scan( SnapshotFactory::variable( array( 'child_count' => 0 ) ) );

		$this->assertContains( 'no_variations', $result->get_issue_codes() );
	}

	/**
	 * A variable product needs an attribute marked for variations.
	 *
	 * @return void
	 */
	public function test_variable_product_without_variation_attributes_is_critical() {
		$result = $this->scan( SnapshotFactory::variable( array( 'variation_attribute_count' => 0 ) ) );

		$this->assertIssueCodes( array( 'missing_variation_attributes' ), $result );
		$this->assertSame( Issue::SEVERITY_CRITICAL, $result->get_issues()[0]->get_severity() );
	}

	/**
	 * A variable product whose variations carry no price is reported.
	 *
	 * WooCommerce exposes the lowest variation price through get_price(), so an
	 * empty value means no variation is priced.
	 *
	 * @return void
	 */
	public function test_variable_product_without_any_variation_price_is_reported() {
		$result = $this->scan( SnapshotFactory::variable( array( 'price' => '' ) ) );

		$this->assertContains( 'missing_price', $result->get_issue_codes() );
	}

	/**
	 * Attributes are never demanded of a simple product.
	 *
	 * This is the rule the specification calls out explicitly.
	 *
	 * @return void
	 */
	public function test_simple_product_is_not_judged_on_attributes() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'attribute_count'           => 0,
					'variation_attribute_count' => 0,
				)
			)
		);

		$this->assertIssueCodes( array(), $result );
		$this->assertNotContains( 'missing_variation_attributes', $result->get_passed() );
		$this->assertNotContains( 'no_variations', $result->get_passed() );
	}

	// ------------------------------------------------------------- scenario 9

	/**
	 * A grouped product is not judged on price or stock.
	 *
	 * WooCommerce derives both from the children, so demanding them on the
	 * parent would report a defect that does not exist.
	 *
	 * @return void
	 */
	public function test_grouped_product_is_not_judged_on_price_or_stock() {
		$result = $this->scan( SnapshotFactory::grouped() );

		$this->assertIssueCodes( array(), $result );
		$this->assertNotContains( 'missing_price', $result->get_passed() );
		$this->assertNotContains( 'incomplete_stock_configuration', $result->get_passed() );
	}

	// ------------------------------------------------------------ scenario 10

	/**
	 * A complete external product reports nothing.
	 *
	 * @return void
	 */
	public function test_complete_external_product_has_no_issues() {
		$this->assertIssueCodes( array(), $this->scan( SnapshotFactory::external() ) );
	}

	/**
	 * An external product without a URL is critical.
	 *
	 * @return void
	 */
	public function test_external_product_without_url_is_critical() {
		$result = $this->scan( SnapshotFactory::external( array( 'external_url' => '' ) ) );

		$this->assertIssueCodes( array( 'missing_external_url' ), $result );
		$this->assertSame( Issue::SEVERITY_CRITICAL, $result->get_issues()[0]->get_severity() );
	}

	/**
	 * Only external products are asked for a product URL.
	 *
	 * @return void
	 */
	public function test_non_external_products_are_not_asked_for_a_url() {
		$result = $this->scan( SnapshotFactory::simple() );

		$this->assertNotContains( 'missing_external_url', $result->get_passed() );
	}

	/**
	 * An external product is not judged on stock configuration.
	 *
	 * @return void
	 */
	public function test_external_product_is_not_judged_on_stock() {
		$result = $this->scan( SnapshotFactory::external( array( 'stock_status' => '' ) ) );

		$this->assertIssueCodes( array(), $result );
	}

	// ------------------------------------------------------------ scenario 11

	/**
	 * A complete virtual product reports nothing.
	 *
	 * @return void
	 */
	public function test_complete_virtual_product_has_no_issues() {
		$this->assertIssueCodes( array(), $this->scan( SnapshotFactory::virtual() ) );
	}

	/**
	 * A virtual product is never asked for downloadable files.
	 *
	 * @return void
	 */
	public function test_virtual_product_is_not_asked_for_files() {
		$result = $this->scan( SnapshotFactory::virtual( array( 'download_count' => 0 ) ) );

		$this->assertIssueCodes( array(), $result );
		$this->assertNotContains( 'missing_downloadable_files', $result->get_passed() );
	}

	// ------------------------------------------------------------ scenario 12

	/**
	 * A complete downloadable product reports nothing.
	 *
	 * @return void
	 */
	public function test_complete_downloadable_product_has_no_issues() {
		$this->assertIssueCodes( array(), $this->scan( SnapshotFactory::downloadable() ) );
	}

	/**
	 * A downloadable product with no files is critical.
	 *
	 * @return void
	 */
	public function test_downloadable_product_without_files_is_critical() {
		$result = $this->scan( SnapshotFactory::downloadable( array( 'download_count' => 0 ) ) );

		$this->assertIssueCodes( array( 'missing_downloadable_files' ), $result );
		$this->assertSame( Issue::SEVERITY_CRITICAL, $result->get_issues()[0]->get_severity() );
	}

	// ---------------------------------------------------------- stock handling

	/**
	 * Managed stock without a quantity is incomplete configuration.
	 *
	 * @return void
	 */
	public function test_managed_stock_without_quantity_is_reported() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'manages_stock'  => true,
					'stock_quantity' => null,
				)
			)
		);

		$this->assertIssueCodes( array( 'incomplete_stock_configuration' ), $result );
	}

	/**
	 * Managed stock with a quantity, including zero, is complete.
	 *
	 * Zero is a legitimate quantity and must not be confused with unset.
	 *
	 * @return void
	 */
	public function test_managed_stock_with_zero_quantity_passes() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'manages_stock'  => true,
					'stock_quantity' => 0,
				)
			)
		);

		$this->assertIssueCodes( array(), $result );
	}

	// ----------------------------------------------------------- result shape

	/**
	 * Severity counts are reported per severity.
	 *
	 * @return void
	 */
	public function test_result_reports_severity_counts() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'name'               => '',
					'sku'                => '',
					'featured_image_alt' => '',
				)
			)
		);

		$counts = $result->get_severity_counts();

		$this->assertSame( 1, $counts[ Issue::SEVERITY_CRITICAL ] );
		$this->assertSame( 1, $counts[ Issue::SEVERITY_WARNING ] );
		$this->assertSame( 1, $counts[ Issue::SEVERITY_INFO ] );
		$this->assertSame( 3, $result->count_issues() );
	}

	/**
	 * Scanning is deterministic: the same input always gives the same output.
	 *
	 * @return void
	 */
	public function test_scanning_is_deterministic() {
		$first  = $this->scan( SnapshotFactory::variable( array( 'sku' => '' ) ) );
		$second = $this->scan( SnapshotFactory::variable( array( 'sku' => '' ) ) );

		$this->assertSame( $first->get_issue_codes(), $second->get_issue_codes() );
		$this->assertSame( $first->get_passed(), $second->get_passed() );
	}

	/**
	 * A check that does not apply is neither failed nor passed.
	 *
	 * @return void
	 */
	public function test_inapplicable_checks_are_excluded_entirely() {
		$result = $this->scan( SnapshotFactory::grouped() );

		$applicable = array_merge( $result->get_passed(), $result->get_issue_codes() );

		$this->assertNotContains( 'missing_price', $applicable );
		$this->assertNotContains( 'incomplete_stock_configuration', $applicable );
		$this->assertNotContains( 'missing_external_url', $applicable );

		// Checks that do apply to a grouped product must still be present.
		$this->assertContains( 'missing_title', $applicable );
		$this->assertContains( 'missing_description', $applicable );
	}
}
