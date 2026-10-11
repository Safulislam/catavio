/**
 * Drives the scan from the browser, one batch per request.
 *
 * The loop lives here rather than on the server so that no single request has
 * to process the whole catalog. Each batch is its own short request, progress
 * is reported between them, and closing the tab simply stops the loop: the job
 * state is already persisted, so the next page load can resume it.
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { speak } from '@wordpress/a11y';
import { __, sprintf } from '@wordpress/i18n';

import { cancelScan, errorMessage, runScanBatch, startScan } from '../api';

// Stops a bug in the batch loop from hammering the server forever.
const MAX_BATCHES = 10000;

export default function useScan( { serverScan, onFinished } ) {
	const [ scan, setScan ] = useState(
		serverScan || { status: 'idle', total: 0, processed: 0, done: true }
	);
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );

	// Lets the loop be stopped from outside without it capturing stale state.
	const cancelled = useRef( false );
	const mounted = useRef( true );

	useEffect( () => {
		// Reset on every mount, not just the first. React can mount, unmount and
		// remount a component with the same refs, and a mounted flag that is
		// only ever set false would leave the scan unable to report anything
		// after that.
		mounted.current = true;

		return () => {
			mounted.current = false;
		};
	}, [] );

	// Adopt whatever the server reports, but never while this tab is driving a
	// scan of its own: doing so would replace live progress with a stale
	// snapshot. This is what lets a job interrupted by a page reload reappear,
	// since the server state outlives the tab that started it.
	useEffect( () => {
		if ( serverScan && ! busy ) {
			setScan( serverScan );
		}
	}, [ serverScan, busy ] );

	const loop = useCallback( async ( startingState ) => {
		let state = startingState;
		let guard = 0;

		while (
			state &&
			! state.done &&
			! cancelled.current &&
			guard < MAX_BATCHES
		) {
			guard++;

			// eslint-disable-next-line no-await-in-loop -- Batches are sequential by design: each one depends on the cursor the previous one advanced.
			state = await runScanBatch();

			if ( ! mounted.current ) {
				return state;
			}

			setScan( state );
		}

		return state;
	}, [] );

	/**
	 * Continue a job that is already running on the server.
	 *
	 * Used after a page reload: the job state outlives the tab, so the work can
	 * be picked up where it stopped rather than started again.
	 */
	const resume = useCallback( async () => {
		cancelled.current = false;
		setError( '' );
		setBusy( true );

		try {
			speak( __( 'Resuming scan.', 'catavio' ), 'polite' );

			await loop( scan );

			if ( onFinished ) {
				await onFinished();
			}
		} catch ( thrown ) {
			if ( mounted.current ) {
				setError(
					errorMessage(
						thrown,
						__(
							'The scan could not be resumed.',
							'catavio'
						)
					)
				);
			}
		} finally {
			if ( mounted.current ) {
				setBusy( false );
			}
		}
	}, [ loop, onFinished, scan ] );

	const run = useCallback( async () => {
		cancelled.current = false;
		setError( '' );
		setBusy( true );

		try {
			const started = await startScan();

			if ( ! mounted.current ) {
				return;
			}

			setScan( started );
			speak( __( 'Scan started.', 'catavio' ), 'polite' );

			const finished = await loop( started );

			if ( ! mounted.current ) {
				return;
			}

			if ( cancelled.current ) {
				speak(
					__( 'Scan cancelled.', 'catavio' ),
					'polite'
				);
			} else {
				speak(
					sprintf(
						/* translators: %d: number of products scanned. */
						__(
							'Scan complete. %d products checked.',
							'catavio'
						),
						finished && finished.processed ? finished.processed : 0
					),
					'polite'
				);
			}

			if ( onFinished ) {
				await onFinished();
			}
		} catch ( thrown ) {
			if ( mounted.current ) {
				setError(
					errorMessage(
						thrown,
						__(
							'The scan could not be completed.',
							'catavio'
						)
					)
				);
			}
		} finally {
			if ( mounted.current ) {
				setBusy( false );
			}
		}
	}, [ loop, onFinished ] );

	const stop = useCallback( async () => {
		cancelled.current = true;

		try {
			const state = await cancelScan();

			if ( mounted.current ) {
				setScan( state );
			}
		} catch ( thrown ) {
			if ( mounted.current ) {
				setError(
					errorMessage(
						thrown,
						__(
							'The scan could not be cancelled.',
							'catavio'
						)
					)
				);
			}
		}
	}, [] );

	// "running" means this tab is actively driving batches. A job the server
	// still calls running while no tab is driving it is interrupted, not
	// running, and the two need different controls.
	const running = busy;
	const interrupted =
		! busy && !! scan && ! scan.done && 'idle' !== scan.status;

	return { scan, error, running, interrupted, run, resume, stop, setScan };
}
