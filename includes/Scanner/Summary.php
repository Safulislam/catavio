<?php
/**
 * Catalog-wide scan summary.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Scanner;

use Catavio\HealthScore;

defined( 'ABSPATH' ) || exit;

/**
 * The aggregate figures the dashboard reads.
 *
 * Accumulated during the scan and written once when a job completes, rather
 * than computed when the dashboard is opened. That is what lets the dashboard
 * render from a single option read, and it is why opening an admin screen never
 * touches the catalog.
 *
 * A cancelled or abandoned job writes nothing, so the figures on screen always
 * describe a scan that actually finished.
 */
class Summary {

	/**
	 * Option name. Deliberately not autoloaded.
	 *
	 * @var string
	 */
	const OPTION = 'catavio_summary';

	/**
	 * Number of issue types reported as "top issues".
	 *
	 * @var int
	 */
	const TOP_ISSUE_LIMIT = 10;

	/**
	 * Read the stored summary.
	 *
	 * @return array<string, mixed> Empty array when no scan has completed.
	 */
	public function get() {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) || array() === $stored ) {
			return array();
		}

		return $this->normalize( $stored );
	}

	/**
	 * Determine whether any scan has ever completed.
	 *
	 * @return bool
	 */
	public function exists() {
		return array() !== $this->get();
	}

	/**
	 * Store a summary.
	 *
	 * @param array<string, mixed> $summary Summary data.
	 * @return void
	 */
	public function save( array $summary ) {
		update_option( self::OPTION, $this->normalize( $summary ), false );
	}

	/**
	 * Remove the summary.
	 *
	 * @return void
	 */
	public function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * Build a summary from a completed job's accumulated totals.
	 *
	 * @param array<string, mixed> $state Completed scan state.
	 * @return array<string, mixed>
	 */
	public function build_from_state( array $state ) {
		$scanned = isset( $state['processed'] ) ? (int) $state['processed'] : 0;
		$buckets = isset( $state['buckets'] ) && is_array( $state['buckets'] ) ? $state['buckets'] : array();
		$issues  = isset( $state['issue_counts'] ) && is_array( $state['issue_counts'] ) ? $state['issue_counts'] : array();

		$average = 0;

		if ( $scanned > 0 ) {
			$average = (int) round( ( (int) $state['score_total'] ) / $scanned );
		}

		arsort( $issues );

		return $this->normalize(
			array(
				'generated_at'   => time(),
				'products_total' => isset( $state['total'] ) ? (int) $state['total'] : 0,
				'scanned'        => $scanned,
				'skipped'        => isset( $state['skipped'] ) ? (int) $state['skipped'] : 0,
				'average_score'  => $average,
				'healthy'        => isset( $buckets[ HealthScore::BUCKET_HEALTHY ] ) ? (int) $buckets[ HealthScore::BUCKET_HEALTHY ] : 0,
				'attention'      => isset( $buckets[ HealthScore::BUCKET_ATTENTION ] ) ? (int) $buckets[ HealthScore::BUCKET_ATTENTION ] : 0,
				'critical'       => isset( $buckets[ HealthScore::BUCKET_CRITICAL ] ) ? (int) $buckets[ HealthScore::BUCKET_CRITICAL ] : 0,
				'issues'         => array_slice( $issues, 0, self::TOP_ISSUE_LIMIT, true ),
			)
		);
	}

	/**
	 * Coerce a stored summary into the expected shape.
	 *
	 * @param array<string, mixed> $summary Raw summary.
	 * @return array<string, mixed>
	 */
	private function normalize( array $summary ) {
		$issues = array();

		if ( isset( $summary['issues'] ) && is_array( $summary['issues'] ) ) {
			foreach ( $summary['issues'] as $check => $count ) {
				$issues[ (string) $check ] = max( 0, (int) $count );
			}
		}

		$average = isset( $summary['average_score'] ) ? (int) $summary['average_score'] : 0;

		return array(
			'generated_at'   => isset( $summary['generated_at'] ) ? (int) $summary['generated_at'] : 0,
			'products_total' => isset( $summary['products_total'] ) ? max( 0, (int) $summary['products_total'] ) : 0,
			'scanned'        => isset( $summary['scanned'] ) ? max( 0, (int) $summary['scanned'] ) : 0,
			'skipped'        => isset( $summary['skipped'] ) ? max( 0, (int) $summary['skipped'] ) : 0,
			'average_score'  => min( HealthScore::STARTING_SCORE, max( HealthScore::MINIMUM_SCORE, $average ) ),
			'healthy'        => isset( $summary['healthy'] ) ? max( 0, (int) $summary['healthy'] ) : 0,
			'attention'      => isset( $summary['attention'] ) ? max( 0, (int) $summary['attention'] ) : 0,
			'critical'       => isset( $summary['critical'] ) ? max( 0, (int) $summary['critical'] ) : 0,
			'issues'         => $issues,
		);
	}
}
