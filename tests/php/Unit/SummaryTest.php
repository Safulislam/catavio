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
use Catavio\Rest\SummaryController;
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

	/**
	 * Rechecking a product marks the summary but leaves its figures alone.
	 *
	 * @return void
	 */
	public function test_recheck_marks_without_changing_figures() {
		$this->summary->mark_rechecked();

		// No summary yet: there is nothing to mark, and nothing is created.
		$this->assertFalse( $this->summary->exists() );

		$this->summary->save(
			$this->summary->build_from_state(
				array(
					'processed'   => 2,
					'score_total' => 150,
				)
			)
		);

		$this->assertFalse( $this->summary->has_rechecks() );

		$this->summary->mark_rechecked();

		$this->assertTrue( $this->summary->has_rechecks() );
		$this->assertSame( 75, $this->summary->get()['average_score'] );
		$this->assertSame( 2, $this->summary->get()['scanned'] );

		// The next full scan starts the figures, and the mark, afresh.
		$this->summary->save(
			$this->summary->build_from_state(
				array(
					'processed'   => 2,
					'score_total' => 200,
				)
			)
		);

		$this->assertFalse( $this->summary->has_rechecks() );
	}

	/**
	 * Each topic keeps how many products it affected and its most common issue.
	 *
	 * @return void
	 */
	public function test_topics_record_affected_products_and_most_common_issue() {
		$built = $this->summary->build_from_state(
			array(
				'processed'    => 10,
				'score_total'  => 900,
				'issue_counts' => array(
					'missing_sku'               => 2,
					'missing_image_alt_text'    => 7,
					'missing_short_description' => 4,
					'missing_description'       => 5,
				),
				'group_counts' => array(
					'media'       => 7,
					'content'     => 6,
					'identifiers' => 2,
				),
			),
			array(
				'missing_sku'               => 'identifiers',
				'missing_image_alt_text'    => 'media',
				'missing_short_description' => 'content',
				'missing_description'       => 'content',
			)
		);

		$this->assertSame(
			array(
				'affected'  => 6,
				'top_check' => 'missing_description',
				'top_count' => 5,
			),
			$built['topics']['content']
		);
		$this->assertSame( 'missing_image_alt_text', $built['topics']['media']['top_check'] );
		$this->assertSame( 2, $built['topics']['identifiers']['affected'] );
		$this->assertArrayNotHasKey( 'commerce', $built['topics'] );
	}

	/**
	 * A summary saved before topics existed reads as "not recorded", not "none".
	 *
	 * @return void
	 */
	public function test_summary_from_before_topics_has_no_topic_figures() {
		$GLOBALS['catavio_test_options'][ Summary::OPTION ] = array(
			'generated_at' => 1700000000,
			'scanned'      => 5,
		);

		$this->assertNull( $this->summary->get()['topics'] );

		$this->summary->save(
			$this->summary->build_from_state(
				array(
					'processed'   => 5,
					'score_total' => 500,
				)
			)
		);

		$this->assertSame( array(), $this->summary->get()['topics'] );
	}

	/**
	 * The share never contradicts the count beside it.
	 *
	 * @return void
	 */
	public function test_topic_share_never_rounds_away_an_issue() {
		$this->assertSame( 100, SummaryController::complete_share( 1000, 0 ) );
		$this->assertSame( 99, SummaryController::complete_share( 1000, 1 ) );
		$this->assertSame( 1, SummaryController::complete_share( 1000, 999 ) );
		$this->assertSame( 0, SummaryController::complete_share( 1000, 1000 ) );
		$this->assertSame( 0, SummaryController::complete_share( 1000, 1200 ) );
		$this->assertSame( 75, SummaryController::complete_share( 4, 1 ) );
		$this->assertSame( 0, SummaryController::complete_share( 0, 0 ) );
	}
}
