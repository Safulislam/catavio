/**
 * Severity indicator.
 *
 * The severity word is always present in the text. Colour is decoration only,
 * never the sole carrier of meaning, so the component reads identically to a
 * screen reader, in high contrast mode, and in print.
 */

export default function SeverityTag( { severity, label } ) {
	return (
		<span className={ `catavio-severity catavio-severity--${ severity }` }>
			<span className="catavio-severity__dot" aria-hidden="true" />
			{ label }
		</span>
	);
}
