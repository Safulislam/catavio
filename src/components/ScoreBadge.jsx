/**
 * Health score display.
 *
 * Always pairs the number with the bucket name, so the meaning survives without
 * colour. Screen readers get one sentence rather than a bare number.
 */

import { __, sprintf } from '@wordpress/i18n';

export default function ScoreBadge( { score, bucket, bucketLabel, size } ) {
	const spoken = sprintf(
		/* translators: 1: health score out of 100, 2: health status such as "Healthy". */
		__( 'Health score %1$d out of 100, %2$s', 'catavio' ),
		score,
		bucketLabel
	);

	return (
		<span
			className={ `catavio-score catavio-score--${ bucket } ${
				'large' === size ? 'catavio-score--large' : ''
			}` }
		>
			<span className="screen-reader-text">{ spoken }</span>
			<span aria-hidden="true" className="catavio-score__value">
				{ score }
			</span>
			<span aria-hidden="true" className="catavio-score__out-of">
				{ __( '/100', 'catavio' ) }
			</span>
		</span>
	);
}
