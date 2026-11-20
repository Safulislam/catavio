/**
 * Score ring.
 *
 * A circular gauge around the headline score. Purely visual: the number it
 * draws is printed in text inside it, so the ring is hidden from assistive
 * technology, as the bar meters are.
 *
 * Neutral by default. The average score is not a health group, and a green
 * ring over a catalog with no healthy product would say something untrue.
 */

const SIZE = 132;
const STROKE = 10;
const RADIUS = ( SIZE - STROKE ) / 2;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

/**
 * @param {Object}           props          Component props.
 * @param {number}           props.value    Score, 0–100.
 * @param {*}                props.children Content centred inside the ring.
 * @param {string|undefined} props.tone     healthy, attention or critical; the
 *                                          neutral accent colour when omitted.
 */
export default function ScoreRing( { value, children, tone } ) {
	const percent = Math.max( 0, Math.min( 100, Math.round( value ) ) );
	const offset = CIRCUMFERENCE * ( 1 - percent / 100 );

	return (
		<div
			className={ `catavio-ring${
				tone ? ` catavio-ring--${ tone }` : ''
			}` }
		>
			<svg
				className="catavio-ring__svg"
				width={ SIZE }
				height={ SIZE }
				viewBox={ `0 0 ${ SIZE } ${ SIZE }` }
				aria-hidden="true"
				focusable="false"
			>
				<circle
					className="catavio-ring__track"
					cx={ SIZE / 2 }
					cy={ SIZE / 2 }
					r={ RADIUS }
					strokeWidth={ STROKE }
					fill="none"
				/>
				<circle
					className="catavio-ring__fill"
					cx={ SIZE / 2 }
					cy={ SIZE / 2 }
					r={ RADIUS }
					strokeWidth={ STROKE }
					fill="none"
					strokeLinecap="round"
					strokeDasharray={ CIRCUMFERENCE }
					strokeDashoffset={ offset }
					transform={ `rotate(-90 ${ SIZE / 2 } ${ SIZE / 2 })` }
				/>
			</svg>
			<div className="catavio-ring__content">{ children }</div>
		</div>
	);
}
