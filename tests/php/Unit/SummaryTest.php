<?php
/**
 * Catalog summary aggregation.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\HealthScore;
use Catavio\Scanner\Summary;

/**
 * The figures the dashboard renders from.
 */
class SummaryTest extends TestCase {

	/**
	 * Summary under test.
	 *
	 * @var Summary
	 */
	private $summary;

	/**
	 * Reset the stubbed option store.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['catavio_test_options'] = array();

		$this->summary = new Summary();
	}

	/**
	 * A completed job's totals become the dashboard figures.
	 *
	 * @return void
	 */
	public function test_builds_figures_from_a_completed_job() {
		$built = $this->summary->build_from_state(
			array(
				'total'        => 10,
				'processed'    => 8,
				'skipped'      => 2,
				'score_total'  => 600,
				'buckets'      => array(
					HealthScore::BUCKET_HEALTHY   => 3,
					HealthScore::BUCKET_ATTENTION => 4,
					HealthScore::BUCKET_CRITICAL  => 1,
				),
				'issue_counts' => array(
					'missing_sku'            => 5,
					'missing_featured_image' => 2,
				),
			)
		);

		$this->assertSame( 10, $built['products_total'] );
		$this->assertSame( 8, $built['scanned'] );
		$this->assertSame( 2, $built['skipped'] );
		$this->assertSame( 75, $built['average_score'] );
		$this->assertSame( 3, $built['healthy'] );
		$this->assertSame( 4, $built['attention'] );
		$this->assertSame( 1, $built['critical'] );
		$this->assertSame( 5, $built['issues']['missing_sku'] );
	}

	/**
	 * Top issues are ordered by how many products they affect.
	 *
	 * @return void
	 */
	public function test_top_issues_are_ordered_by_frequency() {
		$built = $this->summary->build_from_state(
			array(
				'processed'    => 1,
				'score_total'  => 100,
				'issue_counts' => array(
					'missing_sku'            => 3,
					'missing_category'       => 31,
					'missing_featured_image' => 12,
				),
			)
		);

		$this->assertSame(
			array( 'missing_category', 'missing_featured_image', 'missing_sku' ),
			array_keys( $built['issues'] )
		);
	}

	/**
	 * Scanning nothing does not divide by zero.
	 *
	 * @return void
	 */
	public function test_an_empty_catalog_scores_zero_without_dividing_by_zero() {
		$built = $this->summary->build_from_state(
			array(
				'total'       => 0,
				'processed'   => 0,
				'score_total' => 0,
			)
		);

		$this->assertSame( 0, $built['average_score'] );
		$this->assertSame( 0, $built['scanned'] );
	}

	/**
	 * The average is clamped to the valid score range.
	 *
	 * @return void
	 */
	public function test_average_is_clamped_to_the_score_range() {
		$high = $this->summary->build_from_state(
			array(
				'processed'   => 1,
				'score_total' => 5000,
			)
		);

		$this->assertSame( 100, $high['average_score'] );
	}

	/**
	 * Nothing is reported until a scan has completed.
	 *
	 * @return void
	 */
	public function test_reports_nothing_before_the_first_scan() {
		$this->assertFalse( $this->summary->exists() );
		$this->assertSame( array(), $this->summary->get() );
	}

	/**
	 * A saved summary round trips.
	 *
	 * @return void
	 */
	public function test_a_saved_summary_round_trips() {
		$this->summary->save(
			$this->summary->build_from_state(
				array(
					'total'       => 4,
					'processed'   => 4,
					'score_total' => 340,
					'buckets'     => array( HealthScore::BUCKET_HEALTHY => 4 ),
				)
			)
		);

		$this->assertTrue( $this->summary->exists() );

		$read = $this->summary->get();

		$this->assertSame( 85, $read['average_score'] );
		$this->assertSame( 4, $read['healthy'] );

		$this->summary->clear();

		$this->assertFalse( $this->summary->exists() );
	}

	/**
	 * Corrupt stored data cannot produce a nonsensical dashboard.
	 *
	 * @return void
	 */
	public function test_corrupt_stored_data_is_coerced() {
		$GLOBALS['catavio_test_options'][ Summary::OPTION ] = array(
			'scanned'       => '-5',
			'average_score' => '900',
			'healthy'       => 'nonsense',
			'issues'        => 'not-an-array',
		);

		$read = $this->summary->get();

		$this->assertSame( 0, $read['scanned'] );
		$this->assertSame( 100, $read['average_score'] );
		$this->assertSame( 0, $read['healthy'] );
		$this->assertSame( array(), $read['issues'] );
	}
}
