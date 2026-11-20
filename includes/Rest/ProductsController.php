<?php
/**
 * Product health list REST route.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Rest;

use Catavio\Admin\Screen;
use Catavio\Checks\Registry;
use Catavio\HealthScore;
use Catavio\Scanner\Issue;
use Catavio\Scanner\ProductSnapshot;
use Catavio\Scanner\Repository;
use Catavio\Scanner\ScanState;
use Catavio\Scanner\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Serves the paginated product health list.
 *
 * Deliberately never loads a WC_Product. Everything a row shows comes from the
 * post, its terms, and this plugin's own meta, all of which WP_Query primes in
 * bulk for the whole page. Hydrating twenty WooCommerce product objects per page
 * would cost far more and buy nothing.
 */
class ProductsController extends Controller {

	/**
	 * Rows per page when the client does not say.
	 *
	 * @var int
	 */
	const DEFAULT_PER_PAGE = 20;

	/**
	 * Hard ceiling on rows per page, whatever the client asks for.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Scanner, used for the product statuses in scope.
	 *
	 * @var Scanner
	 */
	private $scanner;

	/**
	 * Check registry, used to label and validate issue filters.
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
	 * Register the products route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/products',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_products' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $this->get_collection_args(),
				),
			)
		);

		$this->register_single_route();
	}

	/**
	 * Register the single product route.
	 *
	 * @return void
	 */
	private function register_single_route() {
		$id_arg = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/products/(?P<id>[0-9]+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_product' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $id_arg,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/products/(?P<id>[0-9]+)/scan',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'recheck_product' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => $id_arg,
				),
			)
		);
	}

	/**
	 * Check one product again and return its fresh report.
	 *
	 * Runs the same checks a full scan would, for this product only, and
	 * stores the result. It reads the product and writes nothing but this
	 * plugin's own meta, exactly like a full scan.
	 *
	 * Refused while a full scan is being driven: that scan will reach this
	 * product anyway, and its totals are being accumulated as it goes.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function recheck_product( $request ) {
		$error = $this->require_woocommerce();

		if ( null !== $error ) {
			return $error;
		}

		$product_id = (int) $request['id'];
		$product    = $this->load_product( $product_id );

		if ( is_wp_error( $product ) ) {
			return $product;
		}

		if ( ! $this->scanner->is_scanned_status( (string) get_post_status( $product_id ) ) ) {
			return new \WP_Error(
				'catavio_product_not_scanned',
				__( 'Products with this status are not included in scans, so this product cannot be checked.', 'catavio' ),
				array( 'status' => 409 )
			);
		}

		$state = $this->scanner->get_state();

		if ( array() !== $state && ScanState::STATUS_RUNNING === $state['status'] && ! $this->scanner->is_stale() ) {
			return new \WP_Error(
				'catavio_scan_running',
				__( 'A full scan is in progress. Finish or stop it before checking a single product.', 'catavio' ),
				array( 'status' => 409 )
			);
		}

		$this->scanner->recheck_product( $product );

		return rest_ensure_response( $this->build_report( $product_id, $product ) );
	}

	/**
	 * Load a product by ID, or explain why it cannot be.
	 *
	 * @param int $product_id Product ID.
	 * @return \WC_Product|\WP_Error
	 */
	private function load_product( $product_id ) {
		$post = get_post( $product_id );

		if ( $post instanceof \WP_Post && 'product' === $post->post_type ) {
			$product = wc_get_product( $product_id );

			if ( $product instanceof \WC_Product ) {
				return $product;
			}
		}

		return new \WP_Error(
			'catavio_product_not_found',
			__( 'That product could not be found.', 'catavio' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Return one product's full health report.
	 *
	 * Reports what the last scan found; it does not rescan. The stored result is
	 * the authority on what applied and what failed, so the numbers on screen
	 * always match the scan the dashboard is reporting.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_product( $request ) {
		$error = $this->require_woocommerce();

		if ( null !== $error ) {
			return $error;
		}

		$product_id = (int) $request['id'];
		$product    = $this->load_product( $product_id );

		if ( is_wp_error( $product ) ) {
			return $product;
		}

		return rest_ensure_response( $this->build_report( $product_id, $product ) );
	}

	/**
	 * Build one product's full health report from its stored result.
	 *
	 * @param int         $product_id Product ID.
	 * @param \WC_Product $product    The loaded product.
	 * @return array<string, mixed>
	 */
	private function build_report( $product_id, $product ) {
		$post       = get_post( $product_id );
		$scanned_at = (int) get_post_meta( $product_id, Repository::META_SCANNED_AT, true );

		$response = array(
			'id'              => $product_id,
			'name'            => get_the_title( $post ),
			'type'            => (string) $product->get_type(),
			'typeLabel'       => $this->get_product_type_label( (string) $product->get_type() ),
			'scanned'         => $scanned_at > 0,
			'lastChecked'     => $scanned_at,
			'lastCheckedText' => $this->describe_time( $scanned_at ),
			'changedSince'    => $this->changed_since_check( $post, $scanned_at ),
			'canRecheck'      => $this->scanner->is_scanned_status( (string) get_post_status( $product_id ) ),
			'editUrl'         => (string) get_edit_post_link( $product_id, 'raw' ),
			'listUrl'         => $this->get_list_url(),
			'startingScore'   => HealthScore::STARTING_SCORE,
			'score'           => (int) get_post_meta( $product_id, Repository::META_SCORE, true ),
			'bucket'          => '',
			'bucketLabel'     => '',
			'issues'          => array(),
			'passed'          => array(),
			'notApplicable'   => array(),
			'turnedOff'       => array(),
		);

		if ( ! $response['scanned'] ) {
			return $this->filter_report( $response, $product, null );
		}

		$bucket                  = (string) get_post_meta( $product_id, Repository::META_BUCKET, true );
		$response['bucket']      = $bucket;
		$response['bucketLabel'] = SummaryController::bucket_label( $bucket );

		$snapshot = ProductSnapshot::from_product( $product );

		$stored = get_post_meta( $product_id, Repository::META_ISSUES, true );
		$stored = is_array( $stored ) ? $stored : array();

		$failed = array();

		foreach ( $stored as $entry ) {
			$check_id = isset( $entry['check'] ) ? (string) $entry['check'] : '';
			$check    = $this->registry->get_check( $check_id );

			if ( null === $check ) {
				continue;
			}

			$severity = isset( $entry['severity'] ) ? (string) $entry['severity'] : Issue::SEVERITY_INFO;
			$failed[] = $check_id;

			$response['issues'][] = array(
				'check'         => $check_id,
				'label'         => $check->get_label(),
				'severity'      => $severity,
				'severityLabel' => SummaryController::severity_label( $severity ),
				'message'       => $check->get_message( $snapshot ),
				'penalty'       => HealthScore::penalty_for( $severity ),
				'group'         => $this->registry->get_group_id( $check ),
				'groupLabel'    => $this->registry->get_group_label( $check ),
			);
		}

		$passed = get_post_meta( $product_id, Repository::META_PASSED, true );
		$passed = is_array( $passed ) ? array_map( 'strval', $passed ) : array();

		foreach ( $passed as $check_id ) {
			$check = $this->registry->get_check( $check_id );

			if ( null === $check ) {
				continue;
			}

			$response['passed'][] = array(
				'check' => $check_id,
				'label' => $check->get_label(),
				'group' => $this->registry->get_group_id( $check ),
			);
		}

		// Whatever neither failed nor passed did not apply to this product type,
		// unless the store owner has turned that check off, which is a
		// different reason and is said separately. Deriving both from the
		// stored result, rather than re-running applies_to() against the
		// product as it is now, keeps the lists consistent with each other and
		// with the score.
		$applicable = array_merge( $failed, $passed );
		$disabled   = $this->scanner->get_disabled_checks();

		foreach ( $this->registry->get_checks() as $check ) {
			if ( in_array( $check->get_id(), $applicable, true ) ) {
				continue;
			}

			$group = in_array( $check->get_id(), $disabled, true ) ? 'turnedOff' : 'notApplicable';

			$response[ $group ][] = array(
				'check' => $check->get_id(),
				'label' => $check->get_label(),
				'group' => $this->registry->get_group_id( $check ),
			);
		}

		return $this->filter_report( $response, $product, $snapshot );
	}

	/**
	 * Let extensions add to one product's report.
	 *
	 * The fields Catavio itself sets cannot be changed or removed, so the
	 * report screen can rely on them.
	 *
	 * @param array<string, mixed> $response Report built so far.
	 * @param \WC_Product          $product  The loaded product.
	 * @param ProductSnapshot|null $snapshot Snapshot of the product, null when
	 *                                       it has not been scanned.
	 * @return array<string, mixed>
	 */
	private function filter_report( array $response, $product, $snapshot ) {
		/**
		 * Filters one product's health report, as returned by
		 * GET /catavio/v1/products/<id> and POST /catavio/v1/products/<id>/scan.
		 *
		 * Add fields under your own key. Existing fields cannot be changed.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, mixed> $response Report.
		 * @param \WC_Product          $product  The product.
		 * @param ProductSnapshot|null $snapshot Snapshot, or null when the
		 *                                       product has not been scanned.
		 */
		$filtered = apply_filters( 'catavio_rest_product_response', $response, $product, $snapshot );

		return is_array( $filtered ) ? array_merge( $filtered, $response ) : $response;
	}

	/**
	 * Determine whether a product was saved after it was last checked.
	 *
	 * Read from the post row that is already loaded, so it costs no query.
	 * WooCommerce updates the modified time on every product save, which makes
	 * this a reliable sign that the stored result may no longer describe the
	 * product.
	 *
	 * @param \WP_Post|null $post       Product post.
	 * @param int           $scanned_at When the product was last checked.
	 * @return bool
	 */
	private function changed_since_check( $post, $scanned_at ) {
		if ( ! $post instanceof \WP_Post || $scanned_at <= 0 ) {
			return false;
		}

		$modified = (int) get_post_modified_time( 'U', true, $post );

		return $modified > $scanned_at;
	}

	/**
	 * Build the admin URL of the product health list.
	 *
	 * @return string
	 */
	private function get_list_url() {
		return Screen::url( Screen::VIEW_PRODUCTS );
	}

	/**
	 * Describe and validate every accepted query argument.
	 *
	 * Each one declares both a sanitize and a validate callback, and every
	 * free-form value is constrained to a known set, so nothing reaches a query
	 * without having been checked against something the server defined.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_collection_args() {
		return array(
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page' => array(
				'type'              => 'integer',
				'default'           => self::DEFAULT_PER_PAGE,
				'minimum'           => 1,
				'maximum'           => self::MAX_PER_PAGE,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'search'   => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'health'   => array(
				'type'              => 'string',
				'default'           => '',
				'enum'              => array_merge( array( '' ), HealthScore::buckets() ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'issue'    => array(
				'type'              => 'string',
				'default'           => '',
				'enum'              => array_merge( array( '' ), $this->registry->get_check_ids() ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'type'     => array(
				'type'              => 'string',
				'default'           => '',
				'enum'              => array_merge( array( '' ), $this->get_product_type_slugs() ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'orderby'  => array(
				'type'              => 'string',
				'default'           => 'score',
				'enum'              => array( 'score', 'title', 'checked' ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'order'    => array(
				'type'              => 'string',
				'default'           => 'asc',
				'enum'              => array( 'asc', 'desc' ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}

	/**
	 * Return one page of scanned products.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_products( $request ) {
		$error = $this->require_woocommerce();

		if ( null !== $error ) {
			return $error;
		}

		$query = new \WP_Query( $this->build_query_args( $request ) );

		$rows = array();

		foreach ( $query->posts as $post ) {
			$rows[] = $this->shape_row( $post );
		}

		$response = rest_ensure_response(
			array(
				'products' => $rows,
				'total'    => (int) $query->found_posts,
				'pages'    => (int) $query->max_num_pages,
				'page'     => (int) $request['page'],
				'filters'  => $this->describe_filters(),
			)
		);

		$response->header( 'X-WP-Total', (string) (int) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) (int) $query->max_num_pages );

		return $response;
	}

	/**
	 * Translate the request into WP_Query arguments.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function build_query_args( $request ) {
		$args = array(
			'post_type'              => 'product',
			'post_status'            => $this->scanner->get_scanned_statuses(),
			'posts_per_page'         => min( self::MAX_PER_PAGE, (int) $request['per_page'] ),
			'paged'                  => max( 1, (int) $request['page'] ),
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Filtering by stored health meta is this route's purpose. The keys are indexed by WordPress's own postmeta meta_key index, the result set is always paginated, and the timings are recorded in docs/ARCHITECTURE.md.
			'meta_query'             => array(),
		);

		// Only products that have actually been scanned appear here: a row with
		// no result has nothing to report and no score to sort by.
		$args['meta_query'][] = array(
			'key'     => Repository::META_SCANNED_AT,
			'compare' => 'EXISTS',
		);

		$search = (string) $request['search'];

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$health = (string) $request['health'];

		if ( '' !== $health ) {
			$args['meta_query'][] = array(
				'key'     => Repository::META_BUCKET,
				'value'   => $health,
				'compare' => '=',
			);
		}

		$issue = (string) $request['issue'];

		if ( '' !== $issue ) {
			$args['meta_query'][] = array(
				'key'     => Repository::META_ISSUE_CODE,
				'value'   => $issue,
				'compare' => '=',
			);
		}

		$type = (string) $request['type'];

		if ( '' !== $type ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Product type is a taxonomy in WooCommerce, so a tax_query is the only way to filter by it. Terms are indexed and the result set is paginated.
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => $type,
				),
			);
		}

		return array_merge( $args, $this->build_order_args( $request ) );
	}

	/**
	 * Translate the requested ordering into WP_Query arguments.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	private function build_order_args( $request ) {
		$order   = 'desc' === $request['order'] ? 'DESC' : 'ASC';
		$orderby = (string) $request['orderby'];

		if ( 'title' === $orderby ) {
			return array(
				'orderby' => 'title',
				'order'   => $order,
			);
		}

		if ( 'checked' === $orderby ) {
			return array(
				'orderby'  => 'meta_value_num',
				'meta_key' => Repository::META_SCANNED_AT, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting by the stored scan time is the point of this route; the key is indexed by WordPress's postmeta index.
				'order'    => $order,
			);
		}

		// Worst first by default, so the products needing work are on page one.
		return array(
			'orderby'  => 'meta_value_num',
			'meta_key' => Repository::META_SCORE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Sorting by the stored health score is the primary purpose of this route.
			'order'    => $order,
		);
	}

	/**
	 * Build one row of the list.
	 *
	 * @param \WP_Post $post Product post.
	 * @return array<string, mixed>
	 */
	private function shape_row( $post ) {
		$product_id = (int) $post->ID;
		$issues     = get_post_meta( $product_id, Repository::META_ISSUES, true );
		$issues     = is_array( $issues ) ? $issues : array();
		$bucket     = (string) get_post_meta( $product_id, Repository::META_BUCKET, true );
		$scanned_at = (int) get_post_meta( $product_id, Repository::META_SCANNED_AT, true );
		$type       = $this->get_product_type( $product_id );

		$critical = 0;

		foreach ( $issues as $issue ) {
			if ( isset( $issue['severity'] ) && 'critical' === $issue['severity'] ) {
				++$critical;
			}
		}

		$row = array(
			'id'              => $product_id,
			'name'            => get_the_title( $post ),
			'type'            => $type,
			'typeLabel'       => $this->get_product_type_label( $type ),
			'score'           => (int) get_post_meta( $product_id, Repository::META_SCORE, true ),
			'bucket'          => $bucket,
			'bucketLabel'     => SummaryController::bucket_label( $bucket ),
			'issueCount'      => count( $issues ),
			'criticalCount'   => $critical,
			'lastChecked'     => $scanned_at,
			'lastCheckedText' => $this->describe_time( $scanned_at ),
			'changedSince'    => $this->changed_since_check( $post, $scanned_at ),
			'editUrl'         => (string) get_edit_post_link( $product_id, 'raw' ),
			'detailUrl'       => $this->get_detail_url( $product_id ),
		);

		/**
		 * Filters one row of the product health list, as returned by
		 * GET /catavio/v1/products.
		 *
		 * Add fields under your own key. Existing fields cannot be changed.
		 * Runs once per row, so keep it cheap: the post, its meta and its terms
		 * are already cached.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, mixed> $row  List row.
		 * @param \WP_Post             $post Product post.
		 */
		$filtered = apply_filters( 'catavio_rest_product_row', $row, $post );

		return is_array( $filtered ) ? array_merge( $filtered, $row ) : $row;
	}

	/**
	 * Get a product's type slug from the cached term data.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function get_product_type( $product_id ) {
		$terms = get_the_terms( $product_id, 'product_type' );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) || array() === $terms ) {
			return 'simple';
		}

		$term = reset( $terms );

		return isset( $term->slug ) ? (string) $term->slug : 'simple';
	}

	/**
	 * Get the registered WooCommerce product type slugs.
	 *
	 * @return string[]
	 */
	private function get_product_type_slugs() {
		if ( ! function_exists( 'wc_get_product_types' ) ) {
			return array( 'simple', 'variable', 'grouped', 'external' );
		}

		return array_keys( wc_get_product_types() );
	}

	/**
	 * Get the translated label for a product type.
	 *
	 * @param string $type Product type slug.
	 * @return string
	 */
	private function get_product_type_label( $type ) {
		if ( ! function_exists( 'wc_get_product_types' ) ) {
			return $type;
		}

		$types = wc_get_product_types();

		return isset( $types[ $type ] ) ? (string) $types[ $type ] : $type;
	}

	/**
	 * Build the admin URL of this plugin's detail view for a product.
	 *
	 * Built here rather than in the browser so the client never assembles an
	 * admin URL of its own.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function get_detail_url( $product_id ) {
		return add_query_arg( 'product', $product_id, Screen::url( Screen::VIEW_PRODUCTS ) );
	}

	/**
	 * Describe the values the filters accept.
	 *
	 * Returned with the list so the browser never has to know, or guess, which
	 * values the server considers valid.
	 *
	 * @return array<string, mixed>
	 */
	private function describe_filters() {
		$issues = array();

		foreach ( $this->registry->get_checks() as $check ) {
			$issues[] = array(
				'value' => $check->get_id(),
				'label' => $check->get_label(),
			);
		}

		$types = array();

		foreach ( $this->get_product_type_slugs() as $slug ) {
			$types[] = array(
				'value' => $slug,
				'label' => $this->get_product_type_label( $slug ),
			);
		}

		$health = array();

		foreach ( HealthScore::buckets() as $bucket ) {
			$health[] = array(
				'value' => $bucket,
				'label' => SummaryController::bucket_label( $bucket ),
			);
		}

		return array(
			'health' => $health,
			'issues' => $issues,
			'types'  => $types,
		);
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
