<?php
/**
 * Product health scoring.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

namespace Catavio;

use Catavio\Scanner\Issue;
use Catavio\Scanner\Result;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a scan result into a score a store owner can reproduce by hand.
 *
 * WHAT THE SCORE MEASURES
 *
 * How completely a product's information and configuration have been filled in.
 * That is all. It is not a sales score, a conversion score, an SEO ranking, or
 * a revenue prediction, and nothing here should ever be presented as one.
 *
 * THE RULES, IN FULL
 *
 *   Start at 100.
 *   Subtract 20 for each critical issue.
 *   Subtract 10 for each warning.
 *   Subtract  5 for each informational finding.
 *   Never go below 0.
 *
 * Only checks that apply to the product take part. A check excluded by product
 * type is neither a pass nor a failure and cannot move the score, so a grouped
 * product is never penalised for having no price of its own.
 *
 * Worked example, a product missing its description, short description and
 * featured image:
 *
 *   100 - 20 (missing description, critical)
 *       - 10 (missing short description, warning)
 *       - 10 (missing featured image, warning)
 *   =  60
 *
 * Every score is therefore explainable from the issues shown beside it, which
 * is the property the product detail screen depends on.
 *
 * Extensions may change the three penalties through the
 * catavio_severity_penalties filter; see penalties().
 */
class HealthScore {

	/**
	 * The score a product with no issues receives.
	 *
	 * @var int
	 */
	const STARTING_SCORE = 100;

	/**
	 * Points deducted per critical issue.
	 *
	 * @var int
	 */
	const PENALTY_CRITICAL = 20;

	/**
	 * Points deducted per warning.
	 *
	 * @var int
	 */
	const PENALTY_WARNING = 10;

	/**
	 * Points deducted per informational finding.
	 *
	 * @var int
	 */
	const PENALTY_INFO = 5;

	/**
	 * The score can never fall below this.
	 *
	 * @var int
	 */
	const MINIMUM_SCORE = 0;

	/**
	 * Nothing was found.
	 *
	 * @var string
	 */
	const BUCKET_HEALTHY = 'healthy';

	/**
	 * Something was found, but nothing critical.
	 *
	 * @var string
	 */
	const BUCKET_ATTENTION = 'attention';

	/**
	 * At least one critical issue.
	 *
	 * @var string
	 */
	const BUCKET_CRITICAL = 'critical';

	/**
	 * Resolved penalties, see penalties().
	 *
	 * @var array<string, int>|null
	 */
	private static $penalties = null;

	/**
	 * Get every bucket, most severe first.
	 *
	 * @return string[]
	 */
	public static function buckets() {
		return array( self::BUCKET_CRITICAL, self::BUCKET_ATTENTION, self::BUCKET_HEALTHY );
	}

	/**
	 * Get the points deducted for one issue of a given severity.
	 *
	 * @param string $severity One of the Issue::SEVERITY_* constants.
	 * @return int
	 */
	public static function penalty_for( $severity ) {
		$penalties = self::penalties();

		return isset( $penalties[ $severity ] ) ? $penalties[ $severity ] : 0;
	}

	/**
	 * Get the points deducted per severity.
	 *
	 * The defaults are the documented rules above. An extension may change
	 * them; every score and every explanation then uses the changed values, so
	 * the product report still adds up. Scores already stored keep the values
	 * they were calculated with until the next scan, exactly as when checks are
	 * turned on or off.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, int> Points keyed by severity, each 0 to 100.
	 */
	public static function penalties() {
		if ( null !== self::$penalties ) {
			return self::$penalties;
		}

		$defaults = array(
			Issue::SEVERITY_CRITICAL => self::PENALTY_CRITICAL,
			Issue::SEVERITY_WARNING  => self::PENALTY_WARNING,
			Issue::SEVERITY_INFO     => self::PENALTY_INFO,
		);

		/**
		 * Filters the points deducted for each issue severity.
		 *
		 * Missing or invalid entries fall back to the default, and every value
		 * is kept between 0 and 100.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string, int> $penalties Points keyed by severity.
		 */
		$filtered = apply_filters( 'catavio_severity_penalties', $defaults );
		$filtered = is_array( $filtered ) ? $filtered : array();

		$penalties = array();

		foreach ( $defaults as $severity => $default ) {
			$value = isset( $filtered[ $severity ] ) && is_numeric( $filtered[ $severity ] ) ? (int) $filtered[ $severity ] : $default;

			$penalties[ $severity ] = max( 0, min( self::STARTING_SCORE, $value ) );
		}

		self::$penalties = $penalties;

		return self::$penalties;
	}

	/**
	 * Forget the resolved penalties, so the filter is read again.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function reset_penalties() {
		self::$penalties = null;
	}

	/**
	 * Score a scan result.
	 *
	 * @param Result $result Scan result.
	 * @return int A score between MINIMUM_SCORE and STARTING_SCORE.
	 */
	public static function for_result( Result $result ) {
		$score = self::STARTING_SCORE;

		foreach ( $result->get_issues() as $issue ) {
			$score -= self::penalty_for( $issue->get_severity() );
		}

		return max( self::MINIMUM_SCORE, $score );
	}

	/**
	 * Place a result in one of the three dashboard buckets.
	 *
	 * The buckets are defined by severity rather than by a score threshold, so
	 * that "why is this product in this group" is answerable from the issue list
	 * without knowing any cut-off number. Healthy means nothing was found at
	 * all, which is the only statement that stays true whatever the penalties
	 * are later tuned to.
	 *
	 * @param Result $result Scan result.
	 * @return string One of the BUCKET_* constants.
	 */
	public static function bucket_for( Result $result ) {
		if ( $result->count_issues( Issue::SEVERITY_CRITICAL ) > 0 ) {
			return self::BUCKET_CRITICAL;
		}

		if ( $result->count_issues() > 0 ) {
			return self::BUCKET_ATTENTION;
		}

		return self::BUCKET_HEALTHY;
	}

	/**
	 * Explain a score as an ordered list of deductions.
	 *
	 * Used by the product detail screen so the arithmetic is shown rather than
	 * asserted.
	 *
	 * @param Result $result Scan result.
	 * @return array<int, array{check: string, severity: string, penalty: int}>
	 */
	public static function explain( Result $result ) {
		$lines = array();

		foreach ( $result->get_issues() as $issue ) {
			$lines[] = array(
				'check'    => $issue->get_check_id(),
				'severity' => $issue->get_severity(),
				'penalty'  => self::penalty_for( $issue->get_severity() ),
			);
		}

		return $lines;
	}
}
