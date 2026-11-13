<?php
/**
 * Turned-off checks.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Checks\Registry;
use Catavio\HealthScore;
use Catavio\Scanner\Repository;
use Catavio\Scanner\ScanState;
use Catavio\Scanner\Scanner;
use Catavio\Scanner\Summary;
use Catavio\Settings;

/**
 * Covers storing the turned-off checks and how a scan honours them.
 */
class SettingsTest extends TestCase {

	/**
	 * Every registered check identifier.
	 *
	 * @var string[]
	 */
	private $known = array();

	/**
	 * Reset the stubbed option store.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['catavio_test_options'] = array();

		$this->known = ( new Registry() )->get_check_ids();
	}

	/**
	 * Nothing is turned off until the store owner says so.
	 *
	 * @return void
	 */
	public function test_every_check_is_on_by_default() {
		$settings = new Settings();

		$this->assertSame( array(), $settings->get_disabled_checks() );
		$this->assertTrue( $settings->is_enabled( 'missing_sku' ) );
		$this->assertSame( 0, $settings->get_updated_at() );
	}

	/**
	 * Only identifiers the server knows are stored, once each.
	 *
	 * @return void
	 */
	public function test_stores_only_known_checks() {
		$settings = new Settings();

		$stored = $settings->set_disabled_checks(
			array( 'missing_sku', 'missing_sku', 'not_a_check', '<script>' ),
			$this->known
		);

		$this->assertSame( array( 'missing_sku' ), $stored );
		$this->assertSame( array( 'missing_sku' ), $settings->get_disabled_checks() );
		$this->assertFalse( $settings->is_enabled( 'missing_sku' ) );
		$this->assertTrue( $settings->is_enabled( 'missing_title' ) );
		$this->assertGreaterThan( 0, $settings->get_updated_at() );
	}

	/**
	 * Saving the same choice again does not count as a change.
	 *
	 * @return void
	 */
	public function test_unchanged_choice_keeps_its_timestamp() {
		$settings = new Settings();

		$settings->set_disabled_checks( array( 'missing_sku', 'missing_short_description' ), $this->known );

		$GLOBALS['catavio_test_options'][ Settings::OPTION ]['updated_at'] = 1000;

		$settings->set_disabled_checks( array( 'missing_short_description', 'missing_sku' ), $this->known );

		$this->assertSame( 1000, $settings->get_updated_at() );
	}

	/**
	 * A malformed stored value reads as "nothing turned off".
	 *
	 * @return void
	 */
	public function test_malformed_option_is_ignored() {
		$GLOBALS['catavio_test_options'][ Settings::OPTION ] = 'garbage';

		$this->assertSame( array(), ( new Settings() )->get_disabled_checks() );

		$GLOBALS['catavio_test_options'][ Settings::OPTION ] = array( 'disabled_checks' => array( 3, '', array() ) );

		$this->assertSame( array(), ( new Settings() )->get_disabled_checks() );
	}

	/**
	 * A turned-off check neither fails nor passes, and costs no points.
	 *
	 * @return void
	 */
	public function test_scan_leaves_turned_off_checks_out() {
		$settings = new Settings();
		$settings->set_disabled_checks( array( 'missing_sku' ), $this->known );

		$scanner = new Scanner( new Registry(), new Repository(), new ScanState(), new Summary(), $settings );

		$result = $scanner->scan_snapshot( SnapshotFactory::simple( array( 'sku' => '' ) ), 1700000000 );

		$this->assertNotContains( 'missing_sku', $result->get_issue_codes() );
		$this->assertNotContains( 'missing_sku', $result->get_passed() );
		$this->assertSame( HealthScore::STARTING_SCORE, $result->get_score() );
		$this->assertSame( HealthScore::BUCKET_HEALTHY, $result->get_bucket() );
	}

	/**
	 * The same product still fails the check while it is on.
	 *
	 * @return void
	 */
	public function test_scan_reports_the_check_while_it_is_on() {
		$scanner = new Scanner( new Registry(), new Repository(), new ScanState(), new Summary(), new Settings() );

		$result = $scanner->scan_snapshot( SnapshotFactory::simple( array( 'sku' => '' ) ), 1700000000 );

		$this->assertContains( 'missing_sku', $result->get_issue_codes() );
	}
}
