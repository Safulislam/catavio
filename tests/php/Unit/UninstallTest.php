<?php
/**
 * Uninstall coverage.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Catavio\Lifecycle;
use Catavio\Scanner\Repository;
use Catavio\Scanner\ScanState;
use Catavio\Scanner\Summary;

/**
 * Checks that uninstall.php still names every key the plugin writes.
 *
 * The uninstall file cannot import these classes, because WordPress includes it
 * with the plugin unloaded, so the keys are duplicated there as literals. This
 * test is what stops the duplicate drifting: add a meta key or rename an
 * option, and it fails until uninstall.php is updated to match.
 *
 * The file is read as text rather than executed, because running it would
 * delete data.
 */
class UninstallTest extends TestCase {

	/**
	 * Contents of uninstall.php.
	 *
	 * @var string
	 */
	private $source = '';

	/**
	 * Read the uninstall file once per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$path = dirname( __DIR__, 3 ) . '/uninstall.php';

		$this->assertFileExists( $path, 'uninstall.php is missing: deleting the plugin would leave its data behind.' );

		$this->source = (string) file_get_contents( $path );
	}

	/**
	 * Every option the plugin writes must be deleted on uninstall.
	 *
	 * @return void
	 */
	public function test_every_option_is_listed() {
		$options = array(
			Lifecycle::VERSION_OPTION,
			Summary::OPTION,
			ScanState::OPTION,
		);

		foreach ( $options as $option ) {
			$this->assertStringContainsString(
				"'" . $option . "'",
				$this->source,
				sprintf( 'uninstall.php does not delete the "%s" option.', $option )
			);
		}
	}

	/**
	 * Every meta key the plugin writes must be deleted on uninstall.
	 *
	 * Read by reflection rather than listed here, so a new META_* constant is
	 * covered the moment it is added.
	 *
	 * @return void
	 */
	public function test_every_meta_key_is_listed() {
		$keys = $this->meta_keys();

		$this->assertNotEmpty( $keys, 'No META_* constants were found on the repository.' );

		foreach ( $keys as $key ) {
			$this->assertStringContainsString(
				"'" . $key . "'",
				$this->source,
				sprintf( 'uninstall.php does not delete the "%s" meta key.', $key )
			);
		}
	}

	/**
	 * The file must not name a key the plugin no longer writes.
	 *
	 * A stale key is harmless to run but misleading to read, and it hides the
	 * fact that the real key was renamed.
	 *
	 * @return void
	 */
	public function test_no_unknown_keys_are_listed() {
		$known = array_merge(
			array( Lifecycle::VERSION_OPTION, Summary::OPTION, ScanState::OPTION ),
			$this->meta_keys()
		);

		preg_match_all( "/'(_?catavio_[a-z_]+)'/", $this->source, $matches );

		foreach ( array_unique( $matches[1] ) as $listed ) {
			$this->assertContains(
				$listed,
				$known,
				sprintf( 'uninstall.php names "%s", which the plugin no longer writes.', $listed )
			);
		}
	}

	/**
	 * Uninstall must refuse to run outside of WordPress's uninstall request.
	 *
	 * @return void
	 */
	public function test_guards_on_the_uninstall_constant() {
		$this->assertStringContainsString(
			"defined( 'WP_UNINSTALL_PLUGIN' ) || exit;",
			$this->source,
			'uninstall.php must exit when it is not being run by WordPress.'
		);
	}

	/**
	 * Collect the repository's meta key constants.
	 *
	 * @return string[]
	 */
	private function meta_keys() {
		$constants = ( new ReflectionClass( Repository::class ) )->getConstants();
		$keys      = array();

		foreach ( $constants as $name => $value ) {
			if ( 0 === strpos( $name, 'META_' ) ) {
				$keys[] = (string) $value;
			}
		}

		return $keys;
	}
}
