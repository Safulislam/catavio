<?php
/**
 * Resumable scan job state.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * The state of the one scan job a site can have in flight.
 *
 * Persisted after every batch, so closing the tab mid-scan loses at most one
 * batch of work and the next page load can offer to resume. The option is not
 * autoloaded: it is only ever read by this plugin's own screens.
 */
class ScanState {

	/**
	 * Option name holding the job.
	 *
	 * @var string
	 */
	const OPTION = 'catavio_scan_state';

	/**
	 * A job that has not finished.
	 *
	 * @var string
	 */
	const STATUS_RUNNING = 'running';

	/**
	 * A job that processed every product.
	 *
	 * @var string
	 */
	const STATUS_COMPLETE = 'complete';

	/**
	 * A job the user stopped.
	 *
	 * @var string
	 */
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Seconds after which an unfinished job is treated as abandoned.
	 *
	 * @var int
	 */
	const STALE_AFTER = 3600;

	/**
	 * Begin a new job, discarding any previous one.
	 *
	 * @param int $total      Number of products to process.
	 * @param int $batch_size Products per batch.
	 * @return array<string, mixed>
	 */
	public function start( $total, $batch_size ) {
		$now = time();

		$state = array(
			'status'       => self::STATUS_RUNNING,
			'total'        => max( 0, (int) $total ),
			'processed'    => 0,
			'skipped'      => 0,
			'cursor'       => 0,
			'batch_size'   => max( 1, (int) $batch_size ),
			'score_total'  => 0,
			'buckets'      => array(),
			'issue_counts' => array(),
			'started_at'   => $now,
			'updated_at'   => $now,
		);

		$this->save( $state );

		return $state;
	}

	/**
	 * Get the current job, or an empty array when there is none.
	 *
	 * @return array<string, mixed>
	 */
	public function get() {
		$state = get_option( self::OPTION, array() );

		if ( ! is_array( $state ) || array() === $state ) {
			return array();
		}

		return $this->normalize( $state );
	}

	/**
	 * Persist a job.
	 *
	 * @param array<string, mixed> $state Job state.
	 * @return void
	 */
	public function save( array $state ) {
		$state['updated_at'] = time();

		update_option( self::OPTION, $this->normalize( $state ), false );
	}

	/**
	 * Remove the job entirely.
	 *
	 * @return void
	 */
	public function clear() {
		delete_option( self::OPTION );
	}

	/**
	 * Mark the current job as finished.
	 *
	 * @param string $status One of the STATUS_* constants.
	 * @return array<string, mixed>
	 */
	public function finish( $status ) {
		$state = $this->get();

		if ( array() === $state ) {
			return array();
		}

		$state['status'] = ( self::STATUS_CANCELLED === $status ) ? self::STATUS_CANCELLED : self::STATUS_COMPLETE;

		$this->save( $state );

		return $state;
	}

	/**
	 * Determine whether a job is currently running.
	 *
	 * @return bool
	 */
	public function is_running() {
		$state = $this->get();

		if ( array() === $state ) {
			return false;
		}

		return self::STATUS_RUNNING === $state['status'];
	}

	/**
	 * Determine whether an unfinished job has been abandoned.
	 *
	 * A browser tab closed mid-scan leaves a running job that nothing will ever
	 * advance. Rather than block the user forever, a job untouched for an hour
	 * is reported as stale so the UI can offer to start again.
	 *
	 * @return bool
	 */
	public function is_stale() {
		$state = $this->get();

		if ( array() === $state || self::STATUS_RUNNING !== $state['status'] ) {
			return false;
		}

		return ( time() - (int) $state['updated_at'] ) > self::STALE_AFTER;
	}

	/**
	 * Coerce a map of counters into non negative integers keyed by string.
	 *
	 * @param mixed $counts Raw counter map.
	 * @return array<string, int>
	 */
	private function normalize_counts( $counts ) {
		$clean = array();

		foreach ( (array) $counts as $key => $count ) {
			$clean[ (string) $key ] = max( 0, (int) $count );
		}

		return $clean;
	}

	/**
	 * Coerce a stored job into the expected shape.
	 *
	 * @param array<string, mixed> $state Raw job state.
	 * @return array<string, mixed>
	 */
	private function normalize( array $state ) {
		$status = isset( $state['status'] ) ? (string) $state['status'] : self::STATUS_RUNNING;

		if ( ! in_array( $status, array( self::STATUS_RUNNING, self::STATUS_COMPLETE, self::STATUS_CANCELLED ), true ) ) {
			$status = self::STATUS_RUNNING;
		}

		return array(
			'status'       => $status,
			'total'        => isset( $state['total'] ) ? max( 0, (int) $state['total'] ) : 0,
			'processed'    => isset( $state['processed'] ) ? max( 0, (int) $state['processed'] ) : 0,
			'skipped'      => isset( $state['skipped'] ) ? max( 0, (int) $state['skipped'] ) : 0,
			'cursor'       => isset( $state['cursor'] ) ? max( 0, (int) $state['cursor'] ) : 0,
			'batch_size'   => isset( $state['batch_size'] ) ? max( 1, (int) $state['batch_size'] ) : Scanner::DEFAULT_BATCH_SIZE,
			'score_total'  => isset( $state['score_total'] ) ? max( 0, (int) $state['score_total'] ) : 0,
			'buckets'      => $this->normalize_counts( isset( $state['buckets'] ) ? $state['buckets'] : array() ),
			'issue_counts' => $this->normalize_counts( isset( $state['issue_counts'] ) ? $state['issue_counts'] : array() ),
			'started_at'   => isset( $state['started_at'] ) ? (int) $state['started_at'] : 0,
			'updated_at'   => isset( $state['updated_at'] ) ? (int) $state['updated_at'] : 0,
		);
	}
}
