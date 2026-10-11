/**
 * Score meter.
 *
 * A ratio against a fixed limit of 100, so the form is a meter rather than a
 * chart: one fill, one track, no axis. The number it illustrates is always
 * stated beside it in text, which is why the meter itself is hidden from
 * assistive technology — it would only repeat what has just been read out.
 *
 * The fill takes a tone only when the caller actually has one. A single
 * product's bucket is decided by the severity of what the scan found, so
 * colouring its meter repeats a fact the server already established. The
 * dashboard's average score has no bucket at all, so its meter stays in the
 * one accent hue rather than inventing a threshold the plugin does not have.
 *
 * @param {Object} props        Component props.
 * @param {number} props.value  Score out of 100.
 * @param {string} [props.tone] Health bucket, when the caller has one.
 */

export default function HealthMeter( { value, tone } ) {
	const percent = Math.max( 0, Math.min( 100, Math.round( value ) ) );

	return (
		<div
			className={ `catavio-meter${ tone ? ` catavio-meter--${ tone }` : '' }` }
			aria-hidden="true"
		>
			<div className="catavio-meter__track">
				<div
					className="catavio-meter__fill"
					style={ { width: `${ percent }%` } }
				/>
			</div>
		</div>
	);
}
