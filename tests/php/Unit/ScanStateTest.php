<?php
/**
 * Scan job state.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Scanner\ScanState;

/**
 * The job must survive a reload and refuse to mislead.
 */
class ScanStateTest extends TestCase {

	/**
	 * State under test.
	 *
	 * @var ScanState
	 */
	private $state;

	/**
	 * Reset the stubbed option store.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['catavio_test_options'] = array();

		$this->state = new ScanState();
	}

	/**
	 * No job means no job, not an empty one.
	 *
	 * @return void
	 */
	public function test_reports_no_job_when_there_is_none() {
		$this->assertSame( array(), $this->state->get() );
		$this->assertFalse( $this->state->is_running() );
		$this->assertFalse( $this->state->is_stale() );
	}

	/**
	 * Starting a job records the work to be done.
	 *
	 * @return void
	 */
	public function test_starting_a_job_records_the_work() {
		$started = $this->state->start( 250, 50 );

		$this->assertSame( ScanState::STATUS_RUNNING, $started['status'] );
		$this->assertSame( 250, $started['total'] );
		$this->assertSame( 50, $started['batch_size'] );
		$this->assertSame( 0, $started['processed'] );
		$this->assertSame( 0, $started['cursor'] );
		$this->assertTrue( $this->state->is_running() );
	}

	/**
	 * Starting again discards the previous job rather than resuming it.
	 *
	 * @return void
	 */
	public function test_starting_again_discards_the_previous_job() {
		$this->state->start( 250, 50 );

		$running              = $this->state->get();
		$running['processed'] = 120;
		$this->state->save( $running );

		$this->assertSame( 120, $this->state->get()['processed'] );

		$this->state->start( 250, 50 );

		$this->assertSame( 0, $this->state->get()['processed'] );
	}

	/**
	 * A finished job is no longer running.
	 *
	 * @return void
	 */
	public function test_finishing_ends_the_job() {
		$this->state->start( 10, 5 );

		$finished = $this->state->finish( ScanState::STATUS_COMPLETE );

		$this->assertSame( ScanState::STATUS_COMPLETE, $finished['status'] );
		$this->assertFalse( $this->state->is_running() );
	}

	/**
	 * Cancelling is distinguishable from completing.
	 *
	 * The summary is only written on completion, so the two must never be
	 * confused.
	 *
	 * @return void
	 */
	public function test_cancelling_is_not_completing() {
		$this->state->start( 10, 5 );

		$this->assertSame(
			ScanState::STATUS_CANCELLED,
			$this->state->finish( ScanState::STATUS_CANCELLED )['status']
		);
	}

	/**
	 * Finishing a job that does not exist does nothing.
	 *
	 * @return void
	 */
	public function test_finishing_nothing_is_harmless() {
		$this->assertSame( array(), $this->state->finish( ScanState::STATUS_COMPLETE ) );
	}

	/**
	 * A job untouched for long enough is reported as abandoned.
	 *
	 * A tab closed mid scan leaves a running job that nothing will ever advance;
	 * without this the user would be blocked forever.
	 *
	 * @return void
	 */
	public function test_an_abandoned_job_is_reported_as_stale() {
		$this->state->start( 10, 5 );

		$this->assertFalse( $this->state->is_stale() );

		$stored               = $this->state->get();
		$stored['updated_at'] = time() - ( ScanState::STALE_AFTER + 60 );

		// Written directly: save() refreshes updated_at by design.
		$GLOBALS['catavio_test_options'][ ScanState::OPTION ] = $stored;

		$this->assertTrue( $this->state->is_stale() );
	}

	/**
	 * A finished job is never stale, however old.
	 *
	 * @return void
	 */
	public function test_a_finished_job_is_never_stale() {
		$this->state->start( 10, 5 );
		$this->state->finish( ScanState::STATUS_COMPLETE );

		$stored               = $this->state->get();
		$stored['updated_at'] = time() - ( ScanState::STALE_AFTER * 10 );

		$GLOBALS['catavio_test_options'][ ScanState::OPTION ] = $stored;

		$this->assertFalse( $this->state->is_stale() );
	}

	/**
	 * Corrupt stored state cannot put the scanner into an impossible job.
	 *
	 * @return void
	 */
	public function test_corrupt_state_is_coerced() {
		$GLOBALS['catavio_test_options'][ ScanState::OPTION ] = array(
			'status'     => 'nonsense',
			'total'      => '-40',
			'processed'  => 'abc',
			'batch_size' => 0,
			'cursor'     => -9,
		);

		$state = $this->state->get();

		$this->assertSame( ScanState::STATUS_RUNNING, $state['status'] );
		$this->assertSame( 0, $state['total'] );
		$this->assertSame( 0, $state['processed'] );
		$this->assertGreaterThanOrEqual( 1, $state['batch_size'] );
		$this->assertSame( 0, $state['cursor'] );
	}

	/**
	 * Clearing removes the job entirely.
	 *
	 * @return void
	 */
	public function test_clearing_removes_the_job() {
		$this->state->start( 10, 5 );
		$this->state->clear();

		$this->assertSame( array(), $this->state->get() );
		$this->assertArrayNotHasKey( ScanState::OPTION, $GLOBALS['catavio_test_options'] );
	}
}
