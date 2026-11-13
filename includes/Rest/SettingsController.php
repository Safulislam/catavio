<?php
/**
 * Settings REST route.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Rest;

use Catavio\Checks\Registry;
use Catavio\Scanner\ProductSnapshot;
use Catavio\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and saves which checks a store has turned off.
 *
 * Saving changes no product and starts no scan. The choice applies from the
 * next scan, or the next time a product is rechecked, and the dashboard says
 * so in the meantime.
 */
class SettingsController extends Controller {

	/**
	 * Store owner preferences.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Check registry.
	 *
	 * @var Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Store owner preferences.
	 * @param Registry $registry Check registry.
	 */
	public function __construct( Settings $settings, Registry $registry ) {
		$this->settings = $settings;
		$this->registry = $registry;
	}

	/**
	 * Register the settings route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'disabledChecks' => array(
							'type'              => 'array',
							'required'          => true,
							'items'             => array(
								'type' => 'string',
								'enum' => $this->registry->get_check_ids(),
							),
							'sanitize_callback' => array( $this, 'sanitize_check_ids' ),
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Sanitize a list of check identifiers.
	 *
	 * The schema's enum has already rejected anything unknown; this only
	 * normalises what passed.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	public function sanitize_check_ids( $value ) {
		return array_values( array_unique( array_map( 'sanitize_key', (array) $value ) ) );
	}

	/**
	 * Return every check and whether it is turned on.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( $this->describe() );
	}

	/**
	 * Store which checks are turned off.
	 *
	 * Turning every check off is refused: a scan would then report every
	 * product as healthy, which is never true and never useful.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( $request ) {
		$known    = $this->registry->get_check_ids();
		$disabled = array_values( array_intersect( (array) $request['disabledChecks'], $known ) );

		if ( count( $disabled ) >= count( $known ) ) {
			return new \WP_Error(
				'catavio_no_checks',
				__( 'Keep at least one check turned on.', 'catavio' ),
				array( 'status' => 400 )
			);
		}

		$this->settings->set_disabled_checks( $disabled, $known );

		return rest_ensure_response( $this->describe() );
	}

	/**
	 * Build the response shared by both methods.
	 *
	 * @return array<string, mixed>
	 */
	private function describe() {
		$disabled = $this->settings->get_disabled_checks();
		$checks   = array();

		// Severity is read from a blank snapshot, as on the dashboard: this
		// describes the check itself rather than any one product's result.
		$blank = new ProductSnapshot();

		foreach ( $this->registry->get_checks() as $check ) {
			$severity = $check->get_severity( $blank );

			$checks[] = array(
				'check'         => $check->get_id(),
				'label'         => $check->get_label(),
				'severity'      => $severity,
				'severityLabel' => SummaryController::severity_label( $severity ),
				'enabled'       => ! in_array( $check->get_id(), $disabled, true ),
			);
		}

		return array(
			'checks'    => $checks,
			'updatedAt' => $this->settings->get_updated_at(),
		);
	}
}
