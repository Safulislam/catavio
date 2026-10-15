<?php
/**
 * Plugin container and bootstrap.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio;

use Catavio\Admin\Menu;
use Catavio\Checks\Registry;
use Catavio\Rest\ProductsController;
use Catavio\Rest\ScanController;
use Catavio\Rest\SummaryController;
use Catavio\Scanner\Repository;
use Catavio\Scanner\ScanState;
use Catavio\Scanner\Summary;
use Catavio\Scanner\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin together.
 *
 * Boot order matters: nothing that depends on WooCommerce is constructed until
 * WooCommerce has been confirmed present and supported.
 *
 * Translations load on init: WordPress.org serves language packs for the
 * plugin slug, and the German translation bundled in /languages covers sites
 * that are offline or ahead of the pack. The .mo files are small enough that
 * loading them here costs less than a plugin-locale switch mid-request, which
 * is what loading any earlier would risk.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Menu instance.
	 *
	 * @var Menu|null
	 */
	private $menu = null;

	/**
	 * Check registry.
	 *
	 * @var Registry|null
	 */
	private $registry = null;

	/**
	 * Result storage.
	 *
	 * @var Repository|null
	 */
	private $repository = null;

	/**
	 * Scanner.
	 *
	 * @var Scanner|null
	 */
	private $scanner = null;

	/**
	 * Get the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( ! Requirements::are_met() ) {
			Requirements::register_notices();
			return;
		}

		$this->registry   = new Registry();
		$this->repository = new Repository();
		$this->scanner    = new Scanner( $this->registry, $this->repository, new ScanState(), new Summary() );

		$this->scanner->register();

		add_action( 'init', array( $this->repository, 'register_meta' ) );

		// REST requests are not admin requests, so this must sit outside the
		// is_admin() guard below or the routes never register.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( ! is_admin() ) {
			return;
		}

		$this->menu = new Menu();
		$this->menu->register();

		$assets = new Assets( $this->menu );
		$assets->register();
	}

	/**
	 * Load the plugin's text domain.
	 *
	 * Bundled translations live in /languages; WordPress.org language packs
	 * take precedence for locales this plugin does not ship, because the
	 * pack's .mo is loaded first and the bundled file only fills the gaps.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'catavio',
			false,
			dirname( CATAVIO_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register the REST controllers.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		if ( null === $this->scanner ) {
			return;
		}

		$controllers = array(
			new ScanController( $this->scanner ),
			new SummaryController( $this->scanner, $this->registry ),
			new ProductsController( $this->scanner, $this->registry ),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Get the menu instance.
	 *
	 * @return Menu|null
	 */
	public function get_menu() {
		return $this->menu;
	}

	/**
	 * Get the check registry.
	 *
	 * @return Registry|null
	 */
	public function get_registry() {
		return $this->registry;
	}

	/**
	 * Get the result storage.
	 *
	 * @return Repository|null
	 */
	public function get_repository() {
		return $this->repository;
	}

	/**
	 * Get the scanner.
	 *
	 * @return Scanner|null
	 */
	public function get_scanner() {
		return $this->scanner;
	}
}
