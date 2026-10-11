<?php
/**
 * The batched product scanner.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio\Scanner;

use Catavio\Checks\Registry;

defined( 'ABSPATH' ) || exit;

/**
 * Walks the catalog in batches and stores a result per product.
 *
 * The scanner never runs on its own. It advances one batch per explicit
 * request, so a catalog of any size is processed without a long running
 * request, and nothing is scanned while an admin screen is merely being
 * rendered.
 */
class Scanner {

	/**
	 * Products processed per batch.
	 *
	 * @var int
	 */
	const DEFAULT_BATCH_SIZE = 50;

	/**
	 * Query variable carrying the keyset cursor.
	 *
	 * @var string
	 */
	const CURSOR_QUERY_VAR = 'catavio_after_id';

	/**
	 * Check registry.
	 *
	 * @var Registry
	 */
	private $registry;

	/**
	 * Result storage.
	 *
	 * @var Repository
	 */
	private $repository;

	/**
	 * Job state.
	 *
	 * @var ScanState
	 */
	private $state;

	/**
	 * Catalog summary storage.
	 *
	 * @var Summary
	 */
	private $summary;

	/**
	 * Constructor.
	 *
	 * @param Registry   $registry   Check registry.
	 * @param Repository $repository Result storage.
	 * @param ScanState  $state      Job state.
	 * @param Summary    $summary    Catalog summary storage.
	 */
	public function __construct( Registry $registry, Repository $repository, ScanState $state, Summary $summary ) {
		$this->registry   = $registry;
		$this->repository = $repository;
		$this->state      = $state;
		$this->summary    = $summary;
	}

	/**
	 * Get the catalog summary storage.
	 *
	 * @return Summary
	 */
	public function get_summary() {
		return $this->summary;
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'posts_where', array( $this, 'filter_posts_where' ), 10, 2 );
	}

	/**
	 * Get the post statuses that are scanned.
	 *
	 * Drafts and products in the trash are excluded: incomplete information in
	 * an unpublished draft is expected, and reporting it as a catalog problem
	 * would bury the real ones.
	 *
	 * @return string[]
	 */
	public function get_scanned_statuses() {
		/**
		 * Filters the product post statuses included in a scan.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $statuses Post statuses.
		 */
		$statuses = apply_filters( 'catavio_scanned_statuses', array( 'publish', 'private' ) );

		return array_values( array_filter( array_map( 'strval', (array) $statuses ) ) );
	}

	/**
	 * Get the batch size.
	 *
	 * @return int
	 */
	public function get_batch_size() {
		/**
		 * Filters the number of products processed per scan batch.
		 *
		 * @since 1.0.0
		 *
		 * @param int $batch_size Products per batch.
		 */
		$batch_size = (int) apply_filters( 'catavio_batch_size', self::DEFAULT_BATCH_SIZE );

		return max( 1, min( 200, $batch_size ) );
	}

	/**
	 * Count the products a scan would cover.
	 *
	 * @return int
	 */
	public function count_products() {
		$counts = wp_count_posts( 'product' );
		$total  = 0;

		foreach ( $this->get_scanned_statuses() as $status ) {
			if ( isset( $counts->$status ) ) {
				$total += (int) $counts->$status;
			}
		}

		return $total;
	}

	/**
	 * Begin a new scan.
	 *
	 * @return array<string, mixed> The new job state.
	 */
	public function start() {
		return $this->state->start( $this->count_products(), $this->get_batch_size() );
	}

	/**
	 * Process the next batch of the running job.
	 *
	 * @return array<string, mixed> The updated job state.
	 */
	public function run_batch() {
		$state = $this->state->get();

		if ( array() === $state || ScanState::STATUS_RUNNING !== $state['status'] ) {
			return $state;
		}

		$posts = $this->get_next_posts( (int) $state['cursor'], (int) $state['batch_size'] );

		if ( array() === $posts ) {
			$finished = $this->state->finish( ScanState::STATUS_COMPLETE );

			// The summary is written only here, when a job has genuinely run to
			// the end, so the dashboard never shows figures from a half
			// finished scan.
			$this->summary->save( $this->summary->build_from_state( $finished ) );

			return $finished;
		}

		$scanned_at = time();

		foreach ( $posts as $post ) {
			$state['cursor'] = (int) $post->ID;

			// Resolved by ID rather than by passing the WP_Post, purely because
			// it states the intent: load the product that this row identifies.
			// Both forms behave identically, including returning false once the
			// post is gone.
			$product = wc_get_product( (int) $post->ID );

			// A product that cannot be loaded yields false here. It is counted
			// and skipped rather than aborting the whole scan.
			//
			// Note on a product deleted mid scan: this branch does not reliably
			// catch that case, and cannot. WP_Query primes the object cache with
			// the rows it has already read, after the_posts has run, so a post
			// deleted between the query and this loop is still served from cache
			// and still scans successfully. The consequence is bounded and
			// harmless: a handful of _catavio_ meta rows pointing at a post that no
			// longer exists. Nothing reads them, because every query that
			// consumes them joins against the posts table. See
			// docs/ARCHITECTURE.md, "Known limitations".
			if ( ! $product instanceof \WC_Product ) {
				++$state['skipped'];
				continue;
			}

			$result = $this->scan_product( $product, $scanned_at );

			$this->repository->save( $result );

			$bucket = $result->get_bucket();

			$state['score_total']        = (int) $state['score_total'] + $result->get_score();
			$state['buckets'][ $bucket ] = ( isset( $state['buckets'][ $bucket ] ) ? (int) $state['buckets'][ $bucket ] : 0 ) + 1;

			foreach ( $result->get_issue_codes() as $code ) {
				$state['issue_counts'][ $code ] = ( isset( $state['issue_counts'][ $code ] ) ? (int) $state['issue_counts'][ $code ] : 0 ) + 1;
			}

			++$state['processed'];
		}

		$this->state->save( $state );

		return $this->state->get();
	}

	/**
	 * Run every applicable check against one product.
	 *
	 * @param \WC_Product $product    WooCommerce product.
	 * @param int         $scanned_at Unix timestamp to record.
	 * @return Result
	 */
	public function scan_product( $product, $scanned_at = 0 ) {
		$snapshot = ProductSnapshot::from_product( $product );

		return $this->scan_snapshot( $snapshot, $scanned_at );
	}

	/**
	 * Run every applicable check against a snapshot.
	 *
	 * Kept separate from scan_product() so the rule set can be exercised
	 * without WooCommerce present.
	 *
	 * @param ProductSnapshot $snapshot   Product snapshot.
	 * @param int             $scanned_at Unix timestamp to record.
	 * @return Result
	 */
	public function scan_snapshot( ProductSnapshot $snapshot, $scanned_at = 0 ) {
		$issues = array();
		$passed = array();

		foreach ( $this->registry->get_checks() as $check ) {
			if ( ! $check->applies_to( $snapshot ) ) {
				continue;
			}

			if ( $check->passes( $snapshot ) ) {
				$passed[] = $check->get_id();
				continue;
			}

			$issues[] = new Issue(
				$check->get_id(),
				$check->get_severity( $snapshot ),
				$check->get_message( $snapshot ),
				$snapshot->id
			);
		}

		if ( $scanned_at <= 0 ) {
			$scanned_at = time();
		}

		return new Result( $snapshot->id, $snapshot->type, $issues, $passed, $scanned_at );
	}

	/**
	 * Cancel the running job.
	 *
	 * @return array<string, mixed>
	 */
	public function cancel() {
		return $this->state->finish( ScanState::STATUS_CANCELLED );
	}

	/**
	 * Get the current job state.
	 *
	 * @return array<string, mixed>
	 */
	public function get_state() {
		return $this->state->get();
	}

	/**
	 * Determine whether a running job has been abandoned.
	 *
	 * @return bool
	 */
	public function is_stale() {
		return $this->state->is_stale();
	}

	/**
	 * Add the keyset cursor to our own product query.
	 *
	 * Keyset paging is used instead of an offset because an offset shifts when
	 * a product is deleted mid scan, which silently skips a product. Comparing
	 * against the last processed ID is stable under concurrent edits and stays
	 * fast on large catalogs.
	 *
	 * @param string    $where Current WHERE clause.
	 * @param \WP_Query $query Query being filtered.
	 * @return string
	 */
	public function filter_posts_where( $where, $query ) {
		if ( ! $query instanceof \WP_Query ) {
			return $where;
		}

		$after = (int) $query->get( self::CURSOR_QUERY_VAR );

		if ( $after <= 0 ) {
			return $where;
		}

		global $wpdb;

		$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", $after );

		return $where;
	}

	/**
	 * Fetch the next page of product posts.
	 *
	 * Full post objects are requested, rather than IDs, so that WordPress
	 * primes the post, meta and term caches for the whole batch in bulk. That
	 * is what keeps the per product checks from turning into N+1 queries.
	 *
	 * @param int $after      Return products with an ID greater than this.
	 * @param int $batch_size Maximum products to return.
	 * @return \WP_Post[]
	 */
	private function get_next_posts( $after, $batch_size ) {
		$query = new \WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => $this->get_scanned_statuses(),
				'posts_per_page'         => $batch_size,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
				'cache_results'          => true,
				self::CURSOR_QUERY_VAR   => $after,
			)
		);

		return $query->posts;
	}
}
