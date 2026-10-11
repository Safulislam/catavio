<?php
/**
 * Admin asset registration.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio;

use Catavio\Admin\Menu;
use Catavio\Admin\Screen;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the admin bundle, and only on this plugin's screens.
 *
 * Script dependencies are read from the generated build/index.asset.php rather
 * than hard coded, because that list grows as screens are built. The bundle
 * itself contains no framework: React and the @wordpress packages are webpack
 * externals mapped to the globals WordPress already loads in wp-admin.
 */
class Assets {

	/**
	 * Script and style handle.
	 *
	 * @var string
	 */
	const HANDLE = 'catavio-admin';

	/**
	 * Menu instance, used to resolve the current screen.
	 *
	 * @var Menu
	 */
	private $menu;

	/**
	 * Constructor.
	 *
	 * @param Menu $menu Menu instance.
	 */
	public function __construct( Menu $menu ) {
		$this->menu = $menu;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the admin bundle on this plugin's screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		$view = $this->menu->get_view_for_hook( (string) $hook_suffix );

		if ( '' === $view ) {
			return;
		}

		if ( ! Capabilities::current_user_can_manage() ) {
			return;
		}

		$asset = $this->get_manifest();

		if ( array() === $asset ) {
			add_action( 'admin_notices', array( $this, 'render_missing_build_notice' ) );
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			CATAVIO_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			array( 'in_footer' => true )
		);

		wp_enqueue_style(
			self::HANDLE,
			CATAVIO_PLUGIN_URL . 'build/style-index.css',
			array( 'wp-components' ),
			$asset['version']
		);

		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

		wp_set_script_translations( self::HANDLE, 'catavio', CATAVIO_PLUGIN_DIR . 'languages' );

		wp_add_inline_script(
			self::HANDLE,
			'window.catavioAdmin = ' . wp_json_encode( $this->get_bootstrap_data( $view ) ) . ';',
			'before'
		);
	}

	/**
	 * Warn administrators when the compiled bundle is missing.
	 *
	 * This happens when the repository is used directly without running the
	 * build, never in a packaged release.
	 *
	 * @return void
	 */
	public function render_missing_build_notice() {
		if ( ! Capabilities::current_user_can_manage() ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'The Catavio admin assets have not been built. Run "npm install && npm run build" in the plugin directory.', 'catavio' )
		);
	}

	/**
	 * Read the generated asset manifest.
	 *
	 * @return array{dependencies: string[], version: string}|array<empty, empty>
	 */
	private function get_manifest() {
		$path = CATAVIO_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! is_readable( $path ) ) {
			return array();
		}

		$asset = require $path;

		if ( ! is_array( $asset ) || ! isset( $asset['dependencies'], $asset['version'] ) ) {
			return array();
		}

		return array(
			'dependencies' => (array) $asset['dependencies'],
			'version'      => (string) $asset['version'],
		);
	}

	/**
	 * Build the data handed to the admin bundle.
	 *
	 * @param string $view The view being rendered.
	 * @return array<string, mixed>
	 */
	private function get_bootstrap_data( $view ) {
		return array(
			'screen'   => $view,
			'screens'  => array(
				'dashboard' => Screen::VIEW_DASHBOARD,
				'products'  => Screen::VIEW_PRODUCTS,
			),
			'page'     => Screen::PAGE,
			'tabArg'   => Screen::TAB,
			'adminUrl' => admin_url( 'admin.php' ),
			'version'  => CATAVIO_VERSION,
		);
	}
}
