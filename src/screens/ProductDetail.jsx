/**
 * Health report for one product.
 *
 * Shows the arithmetic rather than asserting the total: a store owner can add
 * up the deductions listed here and arrive at the score displayed. The only
 * action offered is a link into the standard WooCommerce product editor. This
 * plugin reports problems; the editor that fixes them already exists.
 */

import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Spinner,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { errorMessage, getProduct } from '../api';
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
	const heading = useRef( null );

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
	// by keyboard lands on the content rather than at the top of the page.
	useEffect( () => {
		if ( product && heading.current ) {
			heading.current.focus();
		}
	}, [ product ] );

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
						{ __(
							'Loading product report.',
							'catavio'
						) }
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
						{ __(
							'This product has not been scanned yet. Run a scan to see its health report.',
							'catavio'
						) }
					</p>
					{ editButton }
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
											__(
												'Last checked %s',
												'catavio'
											),
											product.lastCheckedText
										) }
									</span>
								) : null }
							</p>
						</div>

						{ editButton }
					</div>

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
						{ __(
							'How this score was reached',
							'catavio'
						) }
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
								<th scope="col" className="catavio-table__number">
									{ __( 'Points', 'catavio' ) }
								</th>
								<th scope="col" className="catavio-table__number">
									{ __(
										'Running score',
										'catavio'
									) }
								</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<th scope="row">
									{ __(
										'Starting score',
										'catavio'
									) }
								</th>
								<td className="catavio-table__number">&mdash;</td>
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
												__(
													'−%d',
													'catavio'
												),
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
									{ __(
										'Health score',
										'catavio'
									) }
								</th>
								<td className="catavio-table__number">&mdash;</td>
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
				</CardBody>
			</Card>
		</div>
	);
}
