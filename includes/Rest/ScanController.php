<?php
/**
 * Scan REST routes.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Rest;

use Catavio\Scanner\ScanState;
use Catavio\Scanner\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Starts, advances, reports and cancels the scan job.
 *
 * The client drives the loop: it starts a job, then posts to the batch route
 * until the response reports the job is done. Splitting it this way keeps every
 * request short regardless of catalog size, and makes progress reportable.
 */
class ScanController extends Controller {

	/**
	 * Scanner.
	 *
	 * @var Scanner
	 */
	private $scanner;

	/**
	 * Constructor.
	 *
	 * @param Scanner $scanner Scanner.
	 */
	public function __construct( Scanner $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Register the scan routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/scan',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'start' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'status' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'cancel' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/scan/batch',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'batch' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Start a scan.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function start() {
		$error = $this->require_woocommerce();

		if ( null !== $error ) {
			return $error;
		}

		$state = $this->scanner->start();

		return rest_ensure_response( $this->shape( $state ) );
	}

	/**
	 * Process one batch.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function batch() {
		$error = $this->require_woocommerce();

		if ( null !== $error ) {
			return $error;
		}

		if ( ! $this->scanner->get_state() ) {
			return new \WP_Error(
				'catavio_no_scan',
				__( 'There is no scan in progress.', 'catavio' ),
				array( 'status' => 409 )
			);
		}

		$state = $this->scanner->run_batch();

		return rest_ensure_response( $this->shape( $state ) );
	}

	/**
	 * Report the current job.
	 *
	 * @return \WP_REST_Response
	 */
	public function status() {
		return rest_ensure_response( $this->shape( $this->scanner->get_state() ) );
	}

	/**
	 * Cancel the current job.
	 *
	 * @return \WP_REST_Response
	 */
	public function cancel() {
		return rest_ensure_response( $this->shape( $this->scanner->cancel() ) );
	}

	/**
	 * Convert job state into the response shape.
	 *
	 * @param array<string, mixed> $state Job state.
	 * @return array<string, mixed>
	 */
	private function shape( array $state ) {
		if ( array() === $state ) {
			return array(
				'status'    => 'idle',
				'total'     => 0,
				'processed' => 0,
				'skipped'   => 0,
				'done'      => true,
				'stale'     => false,
			);
		}

		return array(
			'status'    => (string) $state['status'],
			'total'     => (int) $state['total'],
			'processed' => (int) $state['processed'],
			'skipped'   => (int) $state['skipped'],
			'done'      => ScanState::STATUS_RUNNING !== $state['status'],
			'stale'     => $this->scanner->is_stale(),
			'startedAt' => (int) $state['started_at'],
			'updatedAt' => (int) $state['updated_at'],
		);
	}
}
