<?php
/**
 * Result storage.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Scanner\Issue;
use Catavio\Scanner\Repository;
use Catavio\Scanner\Result;

/**
 * Storage must be cheap to rewrite and complete to remove.
 */
class RepositoryTest extends TestCase {

	/**
	 * Repository under test.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Reset the stubbed meta store.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['catavio_test_meta']        = array();
		$GLOBALS['catavio_test_meta_writes'] = array();

		$this->repository = new Repository();
	}

	/**
	 * Build a result.
	 *
	 * @param string[] $issue_ids Check identifiers that failed.
	 * @return Result
	 */
	private function result( array $issue_ids ) {
		$issues = array();

		foreach ( $issue_ids as $id ) {
			$issues[] = new Issue( $id, Issue::SEVERITY_WARNING, 'Message.', 7 );
		}

		return new Result( 7, 'simple', $issues, array( 'missing_title' ), 1700000000 );
	}

	/**
	 * Count writes for one meta key.
	 *
	 * @param string $key Meta key.
	 * @return int
	 */
	private function writes_for( $key ) {
		$count = 0;

		foreach ( $GLOBALS['catavio_test_meta_writes'] as $write ) {
			if ( $write['key'] === $key ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * A result is stored in full.
	 *
	 * @return void
	 */
	public function test_stores_the_whole_result() {
		$this->repository->save( $this->result( array( 'missing_sku' ) ) );

		$this->assertSame( 90, $this->repository->get_score( 7 ) );
		$this->assertSame( 'attention', $this->repository->get_bucket( 7 ) );
		$this->assertSame( 1700000000, $this->repository->get_scanned_at( 7 ) );
		$this->assertTrue( $this->repository->has_result( 7 ) );
		$this->assertSame( array( 'missing_title' ), $this->repository->get_passed( 7 ) );
		$this->assertSame(
			array(
				array(
					'check'    => 'missing_sku',
					'severity' => 'warning',
				),
			),
			$this->repository->get_issues( 7 )
		);
	}

	/**
	 * Rescanning unchanged data rewrites only the timestamp.
	 *
	 * The issue data is the expensive part, and it is what stays put.
	 *
	 * @return void
	 */
	public function test_an_unchanged_rescan_rewrites_only_the_timestamp() {
		$this->repository->save( $this->result( array( 'missing_sku' ) ) );

		$GLOBALS['catavio_test_meta_writes'] = array();

		// Same findings, later clock.
		$later = new Result(
			7,
			'simple',
			array( new Issue( 'missing_sku', Issue::SEVERITY_WARNING, 'Message.', 7 ) ),
			array( 'missing_title' ),
			1700000999
		);

		$this->repository->save( $later );

		$this->assertSame( 0, $this->writes_for( Repository::META_ISSUES ) );
		$this->assertSame( 0, $this->writes_for( Repository::META_PASSED ) );
		$this->assertSame( 0, $this->writes_for( Repository::META_SCORE ) );
		$this->assertSame( 0, $this->writes_for( Repository::META_BUCKET ) );
		$this->assertSame( 0, $this->writes_for( Repository::META_ISSUE_CODE ) );
		$this->assertSame( 1, $this->writes_for( Repository::META_SCANNED_AT ) );
	}

	/**
	 * Issue code rows are replaced when the findings change.
	 *
	 * @return void
	 */
	public function test_issue_code_rows_follow_the_findings() {
		$this->repository->save( $this->result( array( 'missing_sku', 'missing_category' ) ) );

		$this->assertSame(
			array( 'missing_sku', 'missing_category' ),
			get_post_meta( 7, Repository::META_ISSUE_CODE, false )
		);

		$GLOBALS['catavio_test_meta_writes'] = array();

		$this->repository->save( $this->result( array( 'missing_sku' ) ) );

		$this->assertSame(
			array( 'missing_sku' ),
			get_post_meta( 7, Repository::META_ISSUE_CODE, false )
		);

		// Replaced, not accumulated.
		$this->assertGreaterThan( 0, $this->writes_for( Repository::META_ISSUE_CODE ) );
	}

	/**
	 * Reordered findings are not treated as a change.
	 *
	 * @return void
	 */
	public function test_reordered_issue_codes_are_not_rewritten() {
		$this->repository->save( $this->result( array( 'missing_sku', 'missing_category' ) ) );

		$GLOBALS['catavio_test_meta_writes'] = array();

		$this->repository->save( $this->result( array( 'missing_category', 'missing_sku' ) ) );

		$this->assertSame( 0, $this->writes_for( Repository::META_ISSUE_CODE ) );
	}

	/**
	 * Deleting removes every key this plugin writes.
	 *
	 * @return void
	 */
	public function test_delete_removes_every_key() {
		$this->repository->save( $this->result( array( 'missing_sku' ) ) );

		$this->repository->delete( 7 );

		foreach ( Repository::meta_keys() as $key ) {
			$this->assertArrayNotHasKey( '7:' . $key, $GLOBALS['catavio_test_meta'], $key . ' survived deletion' );
		}

		$this->assertFalse( $this->repository->has_result( 7 ) );
	}

	/**
	 * The published key list matches what is actually written.
	 *
	 * Cleanup in uninstall.php works from this list, so a key missing from it
	 * would silently be left behind on every site that removes the plugin.
	 *
	 * @return void
	 */
	public function test_the_published_key_list_covers_every_key_written() {
		$this->repository->save( $this->result( array( 'missing_sku' ) ) );

		$written = array();

		foreach ( array_keys( $GLOBALS['catavio_test_meta'] ) as $index ) {
			$written[] = substr( $index, strpos( $index, ':' ) + 1 );
		}

		foreach ( array_unique( $written ) as $key ) {
			$this->assertContains( $key, Repository::meta_keys(), $key . ' is written but not listed in meta_keys()' );
		}
	}

	/**
	 * Missing or corrupt stored data never produces a fatal.
	 *
	 * @return void
	 */
	public function test_corrupt_stored_data_is_tolerated() {
		$GLOBALS['catavio_test_meta'][ '9:' . Repository::META_ISSUES ] = 'not-an-array';
		$GLOBALS['catavio_test_meta'][ '9:' . Repository::META_PASSED ] = 42;

		$this->assertSame( array(), $this->repository->get_issues( 9 ) );
		$this->assertSame( array(), $this->repository->get_passed( 9 ) );
		$this->assertSame( 0, $this->repository->get_scanned_at( 9 ) );
		$this->assertFalse( $this->repository->has_result( 9 ) );
	}

	/**
	 * Every registered meta key is hidden from the core REST API.
	 *
	 * @return void
	 */
	public function test_registered_meta_is_not_exposed_through_core_rest() {
		$GLOBALS['catavio_test_registered_meta'] = array();

		$this->repository->register_meta();

		$this->assertNotEmpty( $GLOBALS['catavio_test_registered_meta'] );

		foreach ( $GLOBALS['catavio_test_registered_meta'] as $key => $args ) {
			$this->assertFalse( $args['show_in_rest'], $key . ' is exposed in REST' );
			$this->assertIsCallable( $args['auth_callback'], $key . ' has no auth callback' );
		}
	}
}
