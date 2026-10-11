<?php
/**
 * Dashboard summary REST route.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Rest;

use Catavio\Checks\Registry;
use Catavio\HealthScore;
use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;
use Catavio\Scanner\ScanState;
use Catavio\Scanner\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Serves everything the dashboard renders.
 *
 * Reads the stored summary and the current job state. It never scans, and never
 * walks the catalog: the only catalog query is a cached post count, needed to
 * tell "no products yet" apart from "products, but no scan yet".
 */
class SummaryController extends Controller {

	/**
	 * Scanner.
	 *
	 * @var Scanner
	 */
	private $scanner;

	/**
	 * Check registry, used to label issues.
	 *
	 * @var Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param Scanner  $scanner  Scanner.
	 * @param Registry $registry Check registry.
	 */
	public function __construct( Scanner $scanner, Registry $registry ) {
		$this->scanner  = $scanner;
		$this->registry = $registry;
	}

	/**
	 * Register the summary route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/summary',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_summary' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Return the dashboard summary.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_summary() {
		$error = $this->require_woocommerce();

		if ( null !== $error ) {
			return $error;
		}

		$summary        = $this->scanner->get_summary()->get();
		$products_total = $this->scanner->count_products();
		$has_scan       = array() !== $summary;

		$response = array(
			'hasProducts'   => $products_total > 0,
			'productsTotal' => $products_total,
			'hasScan'       => $has_scan,
			'averageScore'  => $has_scan ? (int) $summary['average_score'] : 0,
			'scanned'       => $has_scan ? (int) $summary['scanned'] : 0,
			'skipped'       => $has_scan ? (int) $summary['skipped'] : 0,
			'healthy'       => $has_scan ? (int) $summary['healthy'] : 0,
			'attention'     => $has_scan ? (int) $summary['attention'] : 0,
			'critical'      => $has_scan ? (int) $summary['critical'] : 0,
			'lastScan'      => $has_scan ? (int) $summary['generated_at'] : 0,
			'lastScanText'  => $has_scan ? $this->describe_time( (int) $summary['generated_at'] ) : '',
			'topIssues'     => $has_scan ? $this->describe_issues( $summary['issues'] ) : array(),
			'scan'          => $this->describe_scan(),
		);

		return rest_ensure_response( $response );
	}

	/**
	 * Describe the current scan job.
	 *
	 * @return array<string, mixed>
	 */
	private function describe_scan() {
		$state = $this->scanner->get_state();

		if ( array() === $state ) {
			return array(
				'status'    => 'idle',
				'total'     => 0,
				'processed' => 0,
				'done'      => true,
				'stale'     => false,
			);
		}

		return array(
			'status'    => (string) $state['status'],
			'total'     => (int) $state['total'],
			'processed' => (int) $state['processed'],
			'done'      => ScanState::STATUS_RUNNING !== $state['status'],
			'stale'     => $this->scanner->is_stale(),
		);
	}

	/**
	 * Turn stored issue counts into labelled, ordered rows.
	 *
	 * Labels and severity names are translated here rather than in the browser,
	 * so that no runtime value is ever interpolated into a translated string.
	 *
	 * @param array<string, int> $counts Issue counts keyed by check id.
	 * @return array<int, array<string, mixed>>
	 */
	private function describe_issues( array $counts ) {
		$rows = array();

		// Severity is read from a blank snapshot. No current check varies its
		// severity by product, and this list describes the check itself rather
		// than any one product's result.
		$blank = new ProductSnapshot();

		foreach ( $counts as $check_id => $count ) {
			$check = $this->registry->get_check( (string) $check_id );

			if ( null === $check ) {
				continue;
			}

			$severity = $check->get_severity( $blank );

			$rows[] = array(
				'check'         => $check->get_id(),
				'label'         => $check->get_label(),
				'severity'      => $severity,
				'severityLabel' => self::severity_label( $severity ),
				'count'         => (int) $count,
			);
		}

		return $rows;
	}

	/**
	 * Get the translated name of a severity.
	 *
	 * @param string $severity One of the Issue::SEVERITY_* constants.
	 * @return string
	 */
	public static function severity_label( $severity ) {
		if ( Issue::SEVERITY_CRITICAL === $severity ) {
			return __( 'Critical', 'catavio' );
		}

		if ( Issue::SEVERITY_WARNING === $severity ) {
			return __( 'Warning', 'catavio' );
		}

		return __( 'Info', 'catavio' );
	}

	/**
	 * Get the translated name of a health bucket.
	 *
	 * @param string $bucket One of the HealthScore::BUCKET_* constants.
	 * @return string
	 */
	public static function bucket_label( $bucket ) {
		if ( HealthScore::BUCKET_CRITICAL === $bucket ) {
			return __( 'Critical', 'catavio' );
		}

		if ( HealthScore::BUCKET_ATTENTION === $bucket ) {
			return __( 'Needs attention', 'catavio' );
		}

		return __( 'Healthy', 'catavio' );
	}

	/**
	 * Describe a timestamp in words.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	private function describe_time( $timestamp ) {
		if ( $timestamp <= 0 ) {
			return '';
		}

		return sprintf(
			/* translators: %s: human readable time difference, for example "5 mins". */
			__( '%s ago', 'catavio' ),
			human_time_diff( $timestamp, time() )
		);
	}
}
