/**
 * Health report for one product.
 *
 * Shows the arithmetic rather than asserting the total: a store owner can add
 * up the deductions listed here and arrive at the score displayed. The main
 * action is a link into the standard WooCommerce product editor. This plugin
 * reports problems; the editor that fixes them already exists.
 *
 * The second action checks this one product again, so a fix made in the editor
 * can be confirmed without rescanning the whole catalog. It reads the product
 * and stores a fresh result, exactly as a full scan would; it never edits it.
 */

import { speak } from '@wordpress/a11y';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { errorMessage, getProduct, recheckProduct } from '../api';
import HealthMeter from '../components/HealthMeter';
import IssueList from '../components/IssueList';
import ScoreBadge from '../components/ScoreBadge';

/**
 * One group of checks.
 *
 * The tone only sets the marker each item carries. It is never the thing that
 * says what the group is: the heading above already does that in words, and the
 * markers are drawn shapes rather than characters, so nothing here is read out
 * twice or read out as punctuation.
 *
 * @param {Object}   props               Component props.
 * @param {string}   props.title         Group heading.
 * @param {string}   [props.description] Optional sentence under the heading.
 * @param {Object[]} props.items         Checks in this group.
 * @param {string}   props.tone          Either "passed" or "skipped".
 */
function CheckList( { title, description, items, tone } ) {
	if ( items.length === 0 ) {
		return null;
	}

	return (
		<section className="catavio-detail__group">
			<h4 className="catavio-detail__group-title">{ title }</h4>
			{ description ? (
				<p className="catavio-detail__group-note">{ description }</p>
			) : null }
			{ /* eslint-disable-next-line jsx-a11y/no-redundant-roles -- Redundant by the specification, but not in practice: Safari drops the list role from a list whose markers are removed in CSS, which is what these lists do. */ }
			<ul
				role="list"
				className={ `catavio-detail__checks catavio-detail__checks--${ tone }` }
			>
				{ items.map( ( item ) => (
					<li className="catavio-detail__check" key={ item.check }>
						{ item.label }
					</li>
				) ) }
			</ul>
		</section>
	);
}

export default function ProductDetail( { productId } ) {
	const [ product, setProduct ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ rechecking, setRechecking ] = useState( false );
	const [ recheckError, setRecheckError ] = useState( '' );
	const [ rechecked, setRechecked ] = useState( false );
	const heading = useRef( null );
	const arrived = useRef( false );

	useEffect( () => {
		let cancelled = false;

		getProduct( productId )
			.then( ( data ) => {
				if ( ! cancelled ) {
					setProduct( data );
				}
			} )
			.catch( ( thrown ) => {
				if ( ! cancelled ) {
					setError(
						errorMessage(
							thrown,
							__(
								'This product report could not be loaded.',
								'catavio'
							)
						)
					);
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ productId ] );

	// Move focus to the report heading once it exists, so someone arriving here
	// by keyboard lands on the content rather than at the top of the page. Only
	// on arrival: a recheck replaces the report in place, and pulling focus away
	// from the button just pressed would lose the person's place.
	useEffect( () => {
		if ( product && heading.current && ! arrived.current ) {
			arrived.current = true;
			heading.current.focus();
		}
	}, [ product ] );

	const recheck = useCallback( async () => {
		setRechecking( true );
		setRecheckError( '' );
		setRechecked( false );

		try {
			const data = await recheckProduct( productId );

			setProduct( data );
			setRechecked( true );
			speak(
				sprintf(
					/* translators: 1: health score, out of 100. 2: health group, for example "Critical". */
					__(
						'Product checked again. Health score %1$d out of 100, %2$s.',
						'catavio'
					),
					data.score,
					data.bucketLabel
				),
				'polite'
			);
		} catch ( thrown ) {
			setRecheckError(
				errorMessage(
					thrown,
					__( 'This product could not be checked.', 'catavio' )
				)
			);
		} finally {
			setRechecking( false );
		}
	}, [ productId ] );

	if ( error ) {
		return (
			<Card className="catavio-card">
				<CardBody className="catavio-card__body">
					<p role="alert">{ error }</p>
				</CardBody>
			</Card>
		);
	}

	if ( ! product ) {
		return (
			<Card className="catavio-card">
				<CardBody className="catavio-card__body">
					<Spinner />
					<span className="screen-reader-text">
						{ __( 'Loading product report.', 'catavio' ) }
					</span>
				</CardBody>
			</Card>
		);
	}

	/*
	 * The Product Health tab is marked current while this report is open, since
	 * the report is a view within that tab. A current tab is not a way back, so
	 * this link is the only one, and it leads rather than trails the heading.
	 */
	const backLink = (
		<p className="catavio-detail__breadcrumb">
			<a className="catavio-detail__back" href={ product.listUrl }>
				{ __( 'Back to product health', 'catavio' ) }
			</a>
		</p>
	);

	const editButton = product.editUrl ? (
		<Button variant="primary" href={ product.editUrl }>
			{ __( 'Edit product', 'catavio' ) }
		</Button>
	) : null;

	let recheckLabel = __( 'Check now', 'catavio' );

	if ( rechecking ) {
		recheckLabel = __( 'Checking…', 'catavio' );
	} else if ( product.scanned ) {
		recheckLabel = __( 'Check again', 'catavio' );
	}

	const recheckButton = product.canRecheck ? (
		// aria-disabled rather than disabled while busy: disabling the button
		// that was just pressed would drop keyboard focus to the page body.
		<Button
			variant="secondary"
			onClick={ rechecking ? undefined : recheck }
			isBusy={ rechecking }
			aria-disabled={ rechecking }
		>
			{ recheckLabel }
		</Button>
	) : null;

	const actions = (
		<div className="catavio-detail__actions">
			{ editButton }
			{ recheckButton }
		</div>
	);

	const recheckNotices = (
		<>
			{ recheckError ? (
				<Notice status="error" isDismissible={ false }>
					{ recheckError }
				</Notice>
			) : null }
			{ rechecked && ! recheckError ? (
				<Notice status="success" isDismissible={ false }>
					{ __(
						'Checked again. This report now reflects the product as it is saved.',
						'catavio'
					) }
				</Notice>
			) : null }
		</>
	);

	if ( ! product.scanned ) {
		return (
			<Card className="catavio-card">
				<CardBody className="catavio-card__body">
					{ backLink }
					<h2
						tabIndex={ -1 }
						ref={ heading }
						className="catavio-detail__title"
					>
						{ product.name }
					</h2>
					<p className="catavio-detail__unscanned">
						{ product.canRecheck
							? __(
									'This product has not been checked yet. Check it now, or run a scan to check the whole catalog.',
									'catavio'
							  )
							: __(
									'This product has not been scanned yet. Run a scan to see its health report.',
									'catavio'
							  ) }
					</p>
					{ recheckNotices }
					{ actions }
				</CardBody>
			</Card>
		);
	}

	let running = product.startingScore;

	return (
		<div className="catavio-detail">
			<Card className="catavio-card catavio-card--hero">
				<CardBody className="catavio-card__body">
					{ backLink }

					<div className="catavio-detail__identity">
						<div className="catavio-detail__naming">
							<h2
								tabIndex={ -1 }
								ref={ heading }
								className="catavio-detail__title"
							>
								{ product.name }
							</h2>

							<p className="catavio-meta">
								<span className="catavio-meta__item">
									{ product.typeLabel }
								</span>
								{ product.lastCheckedText ? (
									<span className="catavio-meta__item">
										{ sprintf(
											/* translators: %s: time since the last check, for example "5 mins ago". */
											__( 'Last checked %s', 'catavio' ),
											product.lastCheckedText
										) }
									</span>
								) : null }
							</p>
						</div>

						{ actions }
					</div>

					{ recheckNotices }

					{ product.changedSince ? (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'This product was edited after it was last checked, so this report may be out of date.',
								'catavio'
							) }
						</Notice>
					) : null }

					<div
						className={ `catavio-detail__scoreboard catavio-detail__scoreboard--${ product.bucket }` }
					>
						<p className="catavio-detail__score">
							<ScoreBadge
								size="large"
								score={ product.score }
								bucket={ product.bucket }
								bucketLabel={ product.bucketLabel }
							/>
							{ /* The badge already announces the status; this repeats it
							     visually only. */ }
							<span
								className="catavio-detail__bucket"
								aria-hidden="true"
							>
								{ product.bucketLabel }
							</span>
						</p>
						<HealthMeter
							value={ product.score }
							tone={ product.bucket }
						/>
					</div>
				</CardBody>
			</Card>

			<Card className="catavio-card">
				<CardHeader className="catavio-card__header">
					<h3 className="catavio-card__title">
						{ __( 'How this score was reached', 'catavio' ) }
					</h3>
				</CardHeader>
				<CardBody className="catavio-card__body">
					<table className="catavio-table catavio-detail__maths widefat striped">
						<caption className="screen-reader-text">
							{ __(
								'Each deduction applied to this product, and the resulting score',
								'catavio'
							) }
						</caption>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Check', 'catavio' ) }
								</th>
								<th
									scope="col"
									className="catavio-table__number"
								>
									{ __( 'Points', 'catavio' ) }
								</th>
								<th
									scope="col"
									className="catavio-table__number"
								>
									{ __( 'Running score', 'catavio' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th scope="row">
									{ __( 'Starting score', 'catavio' ) }
								</th>
								<td className="catavio-table__number">
									&mdash;
								</td>
								<td className="catavio-table__number">
									{ product.startingScore }
								</td>
							</tr>
							{ product.issues.map( ( issue ) => {
								running = Math.max(
									0,
									running - issue.penalty
								);

								return (
									<tr key={ issue.check }>
										<th scope="row">{ issue.label }</th>
										<td className="catavio-table__number catavio-detail__penalty">
											{ sprintf(
												/* translators: %d: points deducted. */
												__( '−%d', 'catavio' ),
												issue.penalty
											) }
										</td>
										<td className="catavio-table__number">
											{ running }
										</td>
									</tr>
								);
							} ) }
							<tr className="catavio-detail__maths-total">
								<th scope="row">
									{ __( 'Health score', 'catavio' ) }
								</th>
								<td className="catavio-table__number">
									&mdash;
								</td>
								<td className="catavio-table__number">
									{ product.score }
								</td>
							</tr>
						</tbody>
					</table>
				</CardBody>
			</Card>

			<Card className="catavio-card">
				<CardHeader className="catavio-card__header">
					<h3 className="catavio-card__title">
						{ __( 'Issues', 'catavio' ) }
					</h3>
				</CardHeader>
				<CardBody className="catavio-card__body">
					<IssueList issues={ product.issues } />
				</CardBody>
			</Card>

			<Card className="catavio-card catavio-card--checks">
				<CardHeader className="catavio-card__header">
					<h3 className="catavio-card__title">
						{ __( 'Checks', 'catavio' ) }
					</h3>
				</CardHeader>
				<CardBody className="catavio-card__body">
					<CheckList
						tone="passed"
						title={ __( 'Passed', 'catavio' ) }
						items={ product.passed }
					/>
					<CheckList
						tone="skipped"
						title={ __( 'Not checked', 'catavio' ) }
						description={ __(
							'These checks do not apply to this product type, so they did not affect the score.',
							'catavio'
						) }
						items={ product.notApplicable }
					/>
					<CheckList
						tone="skipped"
						title={ __( 'Turned off', 'catavio' ) }
						description={ __(
							'These checks are turned off in Settings, so they did not affect the score.',
							'catavio'
						) }
						items={ product.turnedOff || [] }
					/>
				</CardBody>
			</Card>
		</div>
	);
}
