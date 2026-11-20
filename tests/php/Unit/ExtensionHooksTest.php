<?php
/**
 * The 1.1 extension seams: scan actions, penalties, admin views, abilities.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Catavio\Abilities;
use Catavio\Admin\Screen;
use Catavio\Checks\Registry;
use Catavio\HealthScore;
use Catavio\Scanner\Issue;
use Catavio\Scanner\Repository;
use Catavio\Scanner\Result;
use Catavio\Scanner\ScanState;
use Catavio\Scanner\Scanner;
use Catavio\Scanner\Summary;

/**
 * Covers the hooks extensions rely on.
 */
class ExtensionHooksTest extends TestCase {

	/**
	 * Reset stubbed state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['catavio_test_options']    = array();
		$GLOBALS['catavio_test_meta']       = array();
		$GLOBALS['catavio_test_hooks']      = array();
		$GLOBALS['catavio_test_actions']    = array();
		$GLOBALS['catavio_test_taxonomies'] = array();

		HealthScore::reset_penalties();
	}

	/**
	 * Leave no filtered penalties behind for other tests.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['catavio_test_hooks'] = array();

		HealthScore::reset_penalties();

		parent::tearDown();
	}

	/**
	 * Names of the actions fired so far.
	 *
	 * @return string[]
	 */
	private function fired() {
		return array_column( $GLOBALS['catavio_test_actions'], 'tag' );
	}

	/**
	 * Build a scanner.
	 *
	 * @return Scanner
	 */
	private function scanner() {
		return new Scanner( new Registry(), new Repository(), new ScanState(), new Summary() );
	}

	/**
	 * Checking one product again announces the stored result.
	 *
	 * @return void
	 */
	public function test_recheck_fires_product_scanned() {
		$this->scanner()->recheck_product( new FakeProduct( array( 'description' => '' ) ) );

		$this->assertSame( array( 'catavio_product_scanned' ), $this->fired() );

		$args = $GLOBALS['catavio_test_actions'][0]['args'];

		$this->assertSame( 77, $args[0] );
		$this->assertInstanceOf( Result::class, $args[1] );
		$this->assertContains( 'missing_description', $args[1]->get_issue_codes() );
	}

	/**
	 * Stopping a scan is announced only when there was one.
	 *
	 * @return void
	 */
	public function test_cancel_fires_only_for_a_job() {
		$scanner = $this->scanner();

		$scanner->cancel();

		$this->assertSame( array(), $this->fired() );

		( new ScanState() )->start( 10, 5 );
		$scanner->cancel();

		$this->assertSame( array( 'catavio_scan_cancelled' ), $this->fired() );
	}

	/**
	 * Penalties can be changed, and every value is kept in range.
	 *
	 * @return void
	 */
	public function test_penalties_can_be_filtered_within_range() {
		add_filter(
			'catavio_severity_penalties',
			static function () {
				return array(
					Issue::SEVERITY_CRITICAL => 500,
					Issue::SEVERITY_WARNING  => 'lots',
					Issue::SEVERITY_INFO     => -3,
				);
			}
		);

		$this->assertSame( 100, HealthScore::penalty_for( Issue::SEVERITY_CRITICAL ) );
		$this->assertSame( HealthScore::PENALTY_WARNING, HealthScore::penalty_for( Issue::SEVERITY_WARNING ) );
		$this->assertSame( 0, HealthScore::penalty_for( Issue::SEVERITY_INFO ) );
		$this->assertSame( 0, HealthScore::penalty_for( 'unknown' ) );
	}

	/**
	 * Scores and their explanation use the same filtered penalties.
	 *
	 * @return void
	 */
	public function test_score_and_explanation_agree_under_filtered_penalties() {
		add_filter(
			'catavio_severity_penalties',
			static function ( $penalties ) {
				$penalties[ Issue::SEVERITY_WARNING ] = 15;

				return $penalties;
			}
		);

		$result = $this->scanner()->scan_snapshot( SnapshotFactory::simple( array( 'short_description' => '' ) ), 1 );

		$this->assertSame( 85, $result->get_score() );
		$this->assertSame( 15, HealthScore::explain( $result )[0]['penalty'] );
	}

	/**
	 * Extensions add views after the built-in ones and cannot replace them.
	 *
	 * @return void
	 */
	public function test_extensions_can_add_admin_views() {
		add_filter(
			'catavio_admin_views',
			static function ( $views ) {
				$views['seo']       = 'SEO & AI';
				$views['dashboard'] = 'Hijacked';
				$views['empty']     = '';

				return $views;
			}
		);

		$this->assertSame( array( 'dashboard', 'products', 'settings', 'seo' ), Screen::views() );
		$this->assertSame( 'SEO & AI', Screen::menu_title( 'seo' ) );
		$this->assertSame( 'SEO & AI', Screen::page_title( 'seo' ) );
		$this->assertSame( 'Dashboard', Screen::menu_title( Screen::VIEW_DASHBOARD ) );

		$_GET[ Screen::TAB ] = 'seo';
		$this->assertSame( 'seo', Screen::current_view() );
		unset( $_GET[ Screen::TAB ] );
	}

	/**
	 * Abilities are read-only, permission checked, and point at real methods.
	 *
	 * @return void
	 */
	public function test_abilities_are_read_only() {
		$abilities   = new Abilities();
		$definitions = $abilities->definitions();

		$this->assertSame(
			array( 'catavio/get-catalog-health', 'catavio/list-products-by-health', 'catavio/get-product-health' ),
			array_keys( $definitions )
		);

		foreach ( $definitions as $name => $definition ) {
			$this->assertSame( Abilities::CATEGORY, $definition['category'], $name );
			$this->assertTrue( $definition['meta']['annotations']['readonly'], $name );
			$this->assertFalse( $definition['meta']['annotations']['destructive'], $name );
			$this->assertTrue( is_callable( $definition['execute_callback'] ), $name );
			$this->assertTrue( is_callable( $definition['permission_callback'] ), $name );
		}

		$this->assertSame( array( 'id' ), $definitions['catavio/get-product-health']['input_schema']['required'] );
	}

	/**
	 * Without the Abilities API, nothing is hooked.
	 *
	 * @return void
	 */
	public function test_abilities_do_nothing_without_the_api() {
		$this->assertFalse( Abilities::is_supported() );

		( new Abilities() )->register();

		$this->assertArrayNotHasKey( 'wp_abilities_api_init', $GLOBALS['catavio_test_hooks'] );
	}
}
