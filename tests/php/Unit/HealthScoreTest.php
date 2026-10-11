<?php
/**
 * The scoring rules Phase 4 specifies.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Checks\Registry;
use Catavio\HealthScore;
use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;
use Catavio\Scanner\Repository;
use Catavio\Scanner\Result;
use Catavio\Scanner\ScanState;
use Catavio\Scanner\Scanner;
use Catavio\Scanner\Summary;

/**
 * Every score must be reproducible from the issues shown beside it.
 */
class HealthScoreTest extends TestCase {

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
	 * Build a result directly from a list of severities.
	 *
	 * @param string[] $severities Severities to include.
	 * @return Result
	 */
	private function result_with( array $severities ) {
		$issues = array();
		$index  = 0;

		foreach ( $severities as $severity ) {
			++$index;
			$issues[] = new Issue( 'check_' . $index, $severity, 'Message.', 1 );
		}

		return new Result( 1, 'simple', $issues, array(), 1700000000 );
	}

	/**
	 * A complete product scores 100.
	 *
	 * @return void
	 */
	public function test_complete_product_scores_one_hundred() {
		$result = $this->scan( SnapshotFactory::simple() );

		$this->assertSame( 100, $result->get_score() );
		$this->assertSame( HealthScore::BUCKET_HEALTHY, $result->get_bucket() );
	}

	/**
	 * A single critical issue costs 20 points.
	 *
	 * @return void
	 */
	public function test_single_critical_issue_scores_eighty() {
		$result = $this->scan( SnapshotFactory::simple( array( 'name' => '' ) ) );

		$this->assertSame( 80, $result->get_score() );
		$this->assertSame( HealthScore::BUCKET_CRITICAL, $result->get_bucket() );
	}

	/**
	 * Warnings cost 10 points each.
	 *
	 * @return void
	 */
	public function test_multiple_warnings_accumulate() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'short_description' => '',
					'sku'               => '',
					'category_ids'      => array(),
				)
			)
		);

		$this->assertSame( 3, $result->count_issues( Issue::SEVERITY_WARNING ) );
		$this->assertSame( 70, $result->get_score() );
		$this->assertSame( HealthScore::BUCKET_ATTENTION, $result->get_bucket() );
	}

	/**
	 * An informational finding costs 5 points.
	 *
	 * @return void
	 */
	public function test_informational_finding_costs_five() {
		$result = $this->scan( SnapshotFactory::simple( array( 'featured_image_alt' => '' ) ) );

		$this->assertSame( 95, $result->get_score() );
		$this->assertSame( HealthScore::BUCKET_ATTENTION, $result->get_bucket() );
	}

	/**
	 * The worked example from the specification.
	 *
	 * 100 - 20 (description) - 10 (short description) - 10 (featured image) = 60
	 *
	 * @return void
	 */
	public function test_specification_worked_example() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'description'        => '',
					'short_description'  => '',
					'featured_image_id'  => 0,
					'featured_image_alt' => '',
				)
			)
		);

		$codes = $result->get_issue_codes();
		sort( $codes );

		$this->assertSame(
			array( 'missing_description', 'missing_featured_image', 'missing_short_description' ),
			$codes
		);
		$this->assertSame( 60, $result->get_score() );
	}

	/**
	 * The score never falls below zero.
	 *
	 * @return void
	 */
	public function test_score_is_floored_at_zero() {
		$result = $this->result_with(
			array_fill( 0, 6, Issue::SEVERITY_CRITICAL )
		);

		// Six criticals would be 100 - 120 = -20 before the floor applies.
		$this->assertSame( 0, $result->get_score() );
	}

	/**
	 * A catastrophic real product also floors at zero rather than going negative.
	 *
	 * @return void
	 */
	public function test_an_entirely_empty_variable_product_floors_at_zero() {
		$result = $this->scan(
			SnapshotFactory::variable(
				array(
					'name'                      => '',
					'description'               => '',
					'short_description'         => '',
					'price'                     => '',
					'sku'                       => '',
					'category_ids'              => array(),
					'featured_image_id'         => 0,
					'featured_image_alt'        => '',
					'stock_status'              => '',
					'child_count'               => 0,
					'variation_attribute_count' => 0,
				)
			)
		);

		$raw = HealthScore::STARTING_SCORE;

		foreach ( $result->get_issues() as $issue ) {
			$raw -= HealthScore::penalty_for( $issue->get_severity() );
		}

		$this->assertLessThan( 0, $raw );
		$this->assertSame( 0, $result->get_score() );
	}

	/**
	 * Checks excluded by product type cannot move the score.
	 *
	 * A grouped product carries no price of its own, so it must not be
	 * penalised for the price it does not have.
	 *
	 * @return void
	 */
	public function test_inapplicable_checks_do_not_affect_the_score() {
		$grouped = $this->scan( SnapshotFactory::grouped() );

		$this->assertSame( 100, $grouped->get_score() );

		$simple = $this->scan( SnapshotFactory::simple( array( 'price' => '' ) ) );

		$this->assertSame( 80, $simple->get_score() );
	}

	/**
	 * A simple product is never penalised for having no attributes.
	 *
	 * @return void
	 */
	public function test_simple_product_is_not_penalised_for_attributes() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'attribute_count'           => 0,
					'variation_attribute_count' => 0,
				)
			)
		);

		$this->assertSame( 100, $result->get_score() );
	}

	/**
	 * The score equals 100 minus the sum of the published penalties.
	 *
	 * This is the property the product detail screen relies on: a store owner
	 * can add up the deductions shown and arrive at the number displayed.
	 *
	 * @dataProvider provide_scoring_combinations
	 *
	 * @param string[] $severities Issue severities.
	 * @param int      $expected   Expected score.
	 * @return void
	 */
	public function test_score_is_the_sum_of_published_penalties( array $severities, $expected ) {
		$this->assertSame( $expected, $this->result_with( $severities )->get_score() );
	}

	/**
	 * Severity combinations and their scores.
	 *
	 * @return array<string, array{0: string[], 1: int}>
	 */
	public function provide_scoring_combinations() {
		return array(
			'nothing'                 => array( array(), 100 ),
			'one info'                => array( array( Issue::SEVERITY_INFO ), 95 ),
			'one warning'             => array( array( Issue::SEVERITY_WARNING ), 90 ),
			'one critical'            => array( array( Issue::SEVERITY_CRITICAL ), 80 ),
			'critical plus warning'   => array( array( Issue::SEVERITY_CRITICAL, Issue::SEVERITY_WARNING ), 70 ),
			'one of each'             => array( array( Issue::SEVERITY_CRITICAL, Issue::SEVERITY_WARNING, Issue::SEVERITY_INFO ), 65 ),
			'five criticals hit zero' => array( array_fill( 0, 5, Issue::SEVERITY_CRITICAL ), 0 ),
			'ten warnings hit zero'   => array( array_fill( 0, 10, Issue::SEVERITY_WARNING ), 0 ),
		);
	}

	/**
	 * The explanation lists one deduction per issue, and they sum correctly.
	 *
	 * @return void
	 */
	public function test_explanation_accounts_for_the_whole_score() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'description'        => '',
					'sku'                => '',
					'featured_image_alt' => '',
				)
			)
		);

		$lines = HealthScore::explain( $result );

		$this->assertCount( 3, $lines );

		$total = HealthScore::STARTING_SCORE;

		foreach ( $lines as $line ) {
			$total -= $line['penalty'];
		}

		$this->assertSame( $result->get_score(), $total );
		$this->assertSame( 65, $total );
	}

	/**
	 * Buckets are decided by severity, not by a score threshold.
	 *
	 * @return void
	 */
	public function test_buckets_follow_severity_not_score() {
		$two_warnings = $this->result_with( array( Issue::SEVERITY_WARNING, Issue::SEVERITY_WARNING ) );
		$one_critical = $this->result_with( array( Issue::SEVERITY_CRITICAL ) );

		// The lower scoring result is not the critical one.
		$this->assertSame( 80, $two_warnings->get_score() );
		$this->assertSame( 80, $one_critical->get_score() );

		$this->assertSame( HealthScore::BUCKET_ATTENTION, $two_warnings->get_bucket() );
		$this->assertSame( HealthScore::BUCKET_CRITICAL, $one_critical->get_bucket() );
	}

	/**
	 * An unknown severity deducts nothing rather than corrupting the score.
	 *
	 * @return void
	 */
	public function test_unknown_severity_deducts_nothing() {
		$this->assertSame( 0, HealthScore::penalty_for( 'not-a-severity' ) );
	}

	/**
	 * The result exposes everything Phase 4 requires of it.
	 *
	 * @return void
	 */
	public function test_result_exposes_the_required_output() {
		$result = $this->scan(
			SnapshotFactory::simple(
				array(
					'name' => '',
					'sku'  => '',
				)
			)
		);

		$this->assertIsInt( $result->get_score() );
		$this->assertSame( 2, $result->count_issues() );
		$this->assertSame(
			array(
				Issue::SEVERITY_CRITICAL => 1,
				Issue::SEVERITY_WARNING  => 1,
				Issue::SEVERITY_INFO     => 0,
			),
			$result->get_severity_counts()
		);
		$this->assertNotEmpty( $result->get_passed() );
		$this->assertSame( array( 'missing_title', 'missing_sku' ), $result->get_issue_codes() );
	}
}
