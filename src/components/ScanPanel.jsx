/**
 * Scan control and progress.
 *
 * Progress is reported three ways at once: a native <progress> element, a text
 * count, and a polite live region. The live region is what makes a long scan
 * followable without sight, and the text count is what makes it followable
 * without the progress bar rendering.
 *
 * The panel is three stacked rows — count and percentage, then the bar, then
 * the actions — each its own element. Laying them out by flex ordering alone
 * would leave the order of what is read and what is seen free to disagree.
 */

import { Button, Notice } from '@wordpress/components';
import { __, _x, sprintf } from '@wordpress/i18n';

export default function ScanPanel( {
	scan,
	running,
	interrupted,
	error,
	onRun,
	onResume,
	onStop,
} ) {
	const total = scan && scan.total ? scan.total : 0;
	const processed = scan && scan.processed ? scan.processed : 0;
	const percent = total > 0 ? Math.round( ( processed / total ) * 100 ) : 0;

	const progressText = sprintf(
		/* translators: 1: products scanned so far, 2: total products to scan. */
		__( 'Scanning product %1$d of %2$d', 'catavio' ),
		processed,
		total
	);

	return (
		<div className="catavio-scan">
			{ error ? (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) : null }

			{ interrupted ? (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'A scan was interrupted before it finished. You can continue it, or stop it and start again.',
						'catavio'
					) }
				</Notice>
			) : null }

			{ running || interrupted ? (
				<div className="catavio-scan__progress">
					<div className="catavio-scan__status">
						<label
							className="catavio-scan__label"
							htmlFor="catavio-scan-progress"
						>
							{ progressText }
						</label>
						<p className="catavio-scan__percent" aria-hidden="true">
							{ sprintf(
								/* translators: %d: how much of the scan is complete, as a percentage. */
								_x(
									'%d%%',
									'scan progress',
									'catavio'
								),
								percent
							) }
						</p>
					</div>
					<progress
						id="catavio-scan-progress"
						className="catavio-scan__bar"
						max={ total || 1 }
						value={ processed }
					/>
					<div aria-live="polite" className="screen-reader-text">
						{ progressText }
					</div>
					<div className="catavio-scan__actions">
						{ interrupted ? (
							<Button variant="primary" onClick={ onResume }>
								{ __(
									'Continue scan',
									'catavio'
								) }
							</Button>
						) : null }
						<Button variant="secondary" onClick={ onStop }>
							{ __( 'Stop scan', 'catavio' ) }
						</Button>
					</div>
				</div>
			) : (
				<Button variant="primary" onClick={ onRun }>
					{ __( 'Run scan', 'catavio' ) }
				</Button>
			) }
		</div>
	);
}
