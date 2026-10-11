/**
 * Catalog breakdown bar.
 *
 * Part to whole, in one bar: how the scanned catalog divides between healthy,
 * needs attention and critical. Segments are separated by a gap in the surface
 * colour rather than by a border, so neighbouring colours stay distinct without
 * adding ink that is not data.
 *
 * Nothing here is labelled, and nothing here needs to be: the figures below the
 * bar name every segment and state its count, and this element is hidden from
 * assistive technology so the same three numbers are not announced twice.
 *
 * @param {Object}   props          Component props.
 * @param {Object[]} props.segments Segments, each with a `tone` and a `value`.
 */

export default function DistributionBar( { segments } ) {
	const drawable = segments.filter( ( segment ) => segment.value > 0 );
	const total = drawable.reduce( ( sum, segment ) => sum + segment.value, 0 );

	// Nothing to divide up. The figures below still report the zeroes.
	if ( total <= 0 ) {
		return null;
	}

	return (
		<div className="catavio-distribution" aria-hidden="true">
			{ drawable.map( ( segment ) => (
				<span
					key={ segment.tone }
					className={ `catavio-distribution__segment catavio-distribution__segment--${ segment.tone }` }
					style={ { flexGrow: segment.value } }
				/>
			) ) }
		</div>
	);
}
