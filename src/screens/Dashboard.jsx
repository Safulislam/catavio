/**
 * Dashboard screen.
 *
 * Renders entirely from the stored summary: opening this page never scans and
 * never walks the catalog. Every number shown comes from a scan that actually
 * completed, so nothing here is ever an estimate or a placeholder.
 *
 * The page reads top down as one sentence: how complete the catalog is, how it
 * divides up, what is most worth fixing, and how to refresh the figures. The
 * bar, the meter and the row of figures are three views of the same stored
 * numbers — none of them is a separate source of truth.
 */

import { Card, CardBody, CardHeader, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, _x, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { errorMessage, getSummary } from '../api';
import DistributionBar from '../components/DistributionBar';
import EmptyState from '../components/EmptyState';
import FirstRunWizard from '../components/FirstRunWizard';
import HealthMeter from '../components/HealthMeter';
import ScanPanel from '../components/ScanPanel';
import SeverityTag from '../components/SeverityTag';
import useScan from '../hooks/useScan';

/**
 * One bucket of the catalog, as a figure.
 *
 * One row of the legend under the breakdown bar. The coloured key repeats that
 * segment's colour so the two can be matched by eye, but the bucket name is
 * always spelled out beside it: the colour is the shortcut, never the
 * information.
 *
 * @param {Object} props       Component props.
 * @param {string} props.label Bucket name.
 * @param {number} props.value Number of products in the bucket.
 * @param {string} props.tone  Bucket key: healthy, attention or critical.
 * @param {string} props.share Share of the scanned catalog, already phrased.
 */
function Figure( { label, value, tone, share } ) {
	return (
		<li className={ `catavio-figure catavio-figure--${ tone }` }>
			<span className="catavio-figure__key" aria-hidden="true" />
			<span className="catavio-figure__label">{ label }</span>
			<span className="catavio-figure__value">{ value }</span>
			{ share ? (
				<span className="catavio-figure__share">{ share }</span>
			) : null }
		</li>
	);
}

/**
 * A count, with a bar showing how it compares to the largest count in the table.
 *
 * One hue, because the bar's job is magnitude and nothing else; severity is
 * already carried by its own column. The bar is decoration over a number that
 * is right there, so it is hidden from assistive technology.
 *
 * @param {Object} props       Component props.
 * @param {number} props.value This row's count.
 * @param {number} props.max   The largest count in the table.
 */
function CountBar( { value, max } ) {
	const percent = max > 0 ? Math.round( ( value / max ) * 100 ) : 0;

	return (
		<span className="catavio-count">
			<span className="catavio-count__track" aria-hidden="true">
				<span
					className="catavio-count__fill"
					style={ { width: `${ percent }%` } }
				/>
			</span>
			<span className="catavio-count__value">{ value }</span>
		</span>
	);
}

export default function Dashboard( { settings } ) {
	const [ summary, setSummary ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ loadError, setLoadError ] = useState( '' );
	const [ welcomeHidden, setWelcomeHidden ] = useState( () => {
		try {
			return (
				'1' === window.localStorage.getItem( 'catavio-welcome-hidden' )
			);
		} catch ( error ) {
			return false;
		}
	} );

	const load = useCallback( async () => {
		try {
			const data = await getSummary();
			setSummary( data );
			setLoadError( '' );
		} catch ( thrown ) {
			setLoadError(
				errorMessage(
					thrown,
					__(
						'Product health could not be loaded.',
						'catavio'
					)
				)
			);
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const { scan, error, running, interrupted, run, resume, stop } = useScan( {
		serverScan: summary ? summary.scan : null,
		onFinished: load,
	} );

	if ( loading ) {
		return (
			<Card className="catavio-card">
				<CardBody className="catavio-card__body catavio-card__body--centred">
					<Spinner />
					<span className="screen-reader-text">
						{ __(
							'Loading product health.',
							'catavio'
						) }
					</span>
				</CardBody>
			</Card>
		);
	}

	if ( loadError ) {
		return (
			<Card className="catavio-card">
				<CardBody className="catavio-card__body">
					<p role="alert">{ loadError }</p>
				</CardBody>
			</Card>
		);
	}

	// No catalog at all. Nothing to scan and nothing to explain.
	if ( ! summary.hasProducts ) {
		return (
			<Card className="catavio-card">
				<CardBody className="catavio-card__body">
					<EmptyState
						title={ __(
							'No products yet',
							'catavio'
						) }
						description={ __(
							'No WooCommerce products were found.',
							'catavio'
						) }
					/>
				</CardBody>
			</Card>
		);
	}

	// Products exist, but nothing has been scanned yet.
	if ( ! summary.hasScan ) {
		if ( welcomeHidden ) {
			return (
				<Card className="catavio-card">
					<CardBody className="catavio-card__body">
						<EmptyState
							title={ __( 'No scan yet', 'catavio' ) }
							description={ __(
								'Run a scan to check your product catalog.',
								'catavio'
							) }
						>
							<ScanPanel
								scan={ scan }
								running={ running }
								interrupted={ interrupted }
								error={ error }
								onRun={ run }
								onResume={ resume }
								onStop={ stop }
							/>
						</EmptyState>
					</CardBody>
				</Card>
			);
		}

		return (
			<FirstRunWizard
				scan={ scan }
				running={ running }
				interrupted={ interrupted }
				error={ error }
				onRun={ run }
				onResume={ resume }
				onStop={ stop }
				onDismiss={ () => {
					try {
						window.localStorage.setItem(
							'catavio-welcome-hidden',
							'1'
						);
					} catch ( storageError ) {
						// Keep the dismissal for this view even when storage is unavailable.
					}
					setWelcomeHidden( true );
				} }
			/>
		);
	}

	// Shares are taken against the buckets themselves rather than the catalog
	// count, so the three figures always add up to the bar they sit under.
	const bucketTotal = summary.healthy + summary.attention + summary.critical;

	/**
	 * Phrase a bucket's share of the scanned catalog.
	 *
	 * @param {number} count Products in the bucket.
	 * @return {string} A short sentence, or an empty string when there is no
	 *                  catalog to take a share of.
	 */
	const shareOf = ( count ) => {
		if ( bucketTotal <= 0 ) {
			return '';
		}

		return sprintf(
			/* translators: %d: share of the scanned products, as a percentage. */
			__( '%d%% of scanned products', 'catavio' ),
			Math.round( ( count / bucketTotal ) * 100 )
		);
	};

	// The largest count sets the scale for every bar in the issues table.
	const maxIssueCount = summary.topIssues.reduce(
		( largest, issue ) => Math.max( largest, issue.count ),
		0
	);

	// Deep links into the product list, filtered to one issue. The tab bar
	// above already reaches the unfiltered list, so nothing here repeats it.
	// Only available when the screen was booted by WordPress; the component
	// renders perfectly well without them.
	const productsUrl =
		settings && settings.adminUrl && settings.page && settings.screens
			? addQueryArgs( settings.adminUrl, {
					page: settings.page,
					[ settings.tabArg || 'tab' ]: settings.screens.products,
			  } )
			: '';

	return (
		<div className="catavio-dashboard">
			<Card className="catavio-card catavio-card--hero">
				<CardHeader className="catavio-card__header">
					<h2 className="catavio-card__title">
						{ __( 'Product health', 'catavio' ) }
					</h2>
				</CardHeader>
				<CardBody className="catavio-card__body">
					<div className="catavio-hero">
						<div className="catavio-hero__score">
							<p className="catavio-headline">
								<span className="catavio-headline__value">
									{ sprintf(
										/* translators: %d: average health score, out of 100. */
										_x(
											'%d%%',
											'average health score',
											'catavio'
										),
										summary.averageScore
									) }
								</span>
								<span className="catavio-headline__label">
									{ __(
										'Average product information completeness',
										'catavio'
									) }
								</span>
							</p>
							<HealthMeter value={ summary.averageScore } />
						</div>

						<div className="catavio-hero__breakdown">
							<h3 className="catavio-breakdown__title">
								{ __(
									'Catalog breakdown',
									'catavio'
								) }
							</h3>

							<DistributionBar
								segments={ [
									{ tone: 'healthy', value: summary.healthy },
									{
										tone: 'attention',
										value: summary.attention,
									},
									{
										tone: 'critical',
										value: summary.critical,
									},
								] }
							/>

							{ /* eslint-disable-next-line jsx-a11y/no-redundant-roles -- Redundant by the specification, but not in practice: Safari drops the list role from a list whose markers are removed in CSS, which is what this list does. */ }
							<ul role="list" className="catavio-figures">
								<Figure
									tone="healthy"
									value={ summary.healthy }
									share={ shareOf( summary.healthy ) }
									label={ __(
										'Healthy',
										'catavio'
									) }
								/>
								<Figure
									tone="attention"
									value={ summary.attention }
									share={ shareOf( summary.attention ) }
									label={ __(
										'Needs attention',
										'catavio'
									) }
								/>
								<Figure
									tone="critical"
									value={ summary.critical }
									share={ shareOf( summary.critical ) }
									label={ __(
										'Critical',
										'catavio'
									) }
								/>
							</ul>
						</div>
					</div>
				</CardBody>
			</Card>

			<Card className="catavio-card catavio-card--issues">
				<CardHeader className="catavio-card__header">
					<h2 className="catavio-card__title">
						{ __( 'Top issues', 'catavio' ) }
					</h2>
				</CardHeader>
				<CardBody className="catavio-card__body">
					{ summary.topIssues.length === 0 ? (
						<p className="catavio-card__note">
							{ __(
								'No issues were found in your catalog.',
								'catavio'
							) }
						</p>
					) : (
						<table className="catavio-table catavio-table--issues widefat striped">
							<caption className="screen-reader-text">
								{ __(
									'Issues found, by number of products affected',
									'catavio'
								) }
							</caption>
							<thead>
								<tr>
									<th scope="col">
										{ __(
											'Issue',
											'catavio'
										) }
									</th>
									<th scope="col">
										{ __(
											'Severity',
											'catavio'
										) }
									</th>
									<th
										scope="col"
										className="catavio-table__number"
									>
										{ __(
											'Products',
											'catavio'
										) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ summary.topIssues.map( ( issue ) => (
									<tr key={ issue.check }>
										<td>
											{ productsUrl ? (
												<a
													href={ addQueryArgs(
														productsUrl,
														{
															issue: issue.check,
														}
													) }
												>
													{ issue.label }
												</a>
											) : (
												issue.label
											) }
										</td>
										<td>
											<SeverityTag
												severity={ issue.severity }
												label={ issue.severityLabel }
											/>
										</td>
										<td className="catavio-table__number">
											<CountBar
												value={ issue.count }
												max={ maxIssueCount }
											/>
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					) }
				</CardBody>
			</Card>

			<Card className="catavio-card catavio-card--scan">
				<CardHeader className="catavio-card__header">
					<h2 className="catavio-card__title">
						{ __( 'Scan', 'catavio' ) }
					</h2>
				</CardHeader>
				<CardBody className="catavio-card__body">
					<p className="catavio-meta catavio-scan__facts">
						<span className="catavio-meta__item">
							{ sprintf(
								/* translators: %d: number of products scanned. */
								__(
									'Based on %d products scanned',
									'catavio'
								),
								summary.scanned
							) }
						</span>
						{ summary.skipped > 0 ? (
							<span className="catavio-meta__item">
								{ sprintf(
									/* translators: %d: number of products left out of the scan. */
									__( '%d skipped', 'catavio' ),
									summary.skipped
								) }
							</span>
						) : null }
						{ summary.lastScanText ? (
							<span className="catavio-meta__item">
								{ sprintf(
									/* translators: %s: time since the last scan, for example "5 mins ago". */
									__(
										'Last scan %s',
										'catavio'
									),
									summary.lastScanText
								) }
							</span>
						) : null }
					</p>
					<p className="catavio-card__note">
						{ __(
							'Run a scan again after editing products to bring these figures up to date.',
							'catavio'
						) }
					</p>
					<ScanPanel
						scan={ scan }
						running={ running }
						interrupted={ interrupted }
						error={ error }
						onRun={ run }
						onResume={ resume }
						onStop={ stop }
					/>
				</CardBody>
			</Card>
		</div>
	);
}
