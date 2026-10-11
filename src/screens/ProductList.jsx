/**
 * Product health list.
 *
 * One page of scanned products at a time, never the whole catalog. Every row
 * ends in a link to the standard WooCommerce product editor: this plugin reports
 * problems, it does not replace the editor that fixes them.
 */

import { Card, CardBody, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs, getQueryArgs } from '@wordpress/url';

import EmptyState from '../components/EmptyState';
import HealthMeter from '../components/HealthMeter';
import Filters from '../components/Filters';
import Pagination from '../components/Pagination';
import ScoreBadge from '../components/ScoreBadge';
import useProducts from '../hooks/useProducts';

const DEFAULT_QUERY = {
	page: 1,
	search: '',
	health: '',
	issue: '',
	type: '',
	orderby: 'score',
	order: 'asc',
};

/**
 * Read the query out of the page's address bar.
 *
 * Anything unexpected falls back to the default, so a hand-edited URL cannot
 * put the screen into a state the server would reject.
 *
 * @return {Object} The current query.
 */
function queryFromUrl() {
	const args = getQueryArgs( window.location.href );

	return {
		...DEFAULT_QUERY,
		page: Math.max( 1, parseInt( args.paged, 10 ) || 1 ),
		search: args.s || '',
		health: args.health || '',
		issue: args.issue || '',
		type: args.type || '',
		orderby: DEFAULT_QUERY.orderby,
		order: DEFAULT_QUERY.order,
	};
}

export default function ProductList( { settings } ) {
	const [ query, setQuery ] = useState( queryFromUrl );

	// Keep the address bar in step, so a filtered view can be linked to and the
	// browser back button behaves as expected.
	useEffect( () => {
		// The page and tab arguments are already in the address bar. They are
		// restated so that rewriting the query can never drop the view this
		// screen belongs to, and both come from the server rather than being
		// written out here, so renaming either cannot strand the browser on a
		// URL the plugin no longer answers.
		const view = {};

		if ( settings && settings.page ) {
			view.page = settings.page;
		}

		if ( settings && settings.screens && settings.screens.products ) {
			view[ settings.tabArg || 'tab' ] = settings.screens.products;
		}

		const url = addQueryArgs(
			window.location.pathname + window.location.search,
			{
				...view,
				paged: query.page > 1 ? query.page : undefined,
				s: query.search || undefined,
				health: query.health || undefined,
				issue: query.issue || undefined,
				type: query.type || undefined,
			}
		);

		window.history.replaceState( {}, '', url );
	}, [ query, settings ] );

	const { data, loading, error } = useProducts( query );

	const update = useCallback( ( changes ) => {
		setQuery( ( current ) => ( { ...current, ...changes, page: 1 } ) );
	}, [] );

	const goToPage = useCallback( ( page ) => {
		setQuery( ( current ) => ( { ...current, page } ) );
	}, [] );

	if ( error ) {
		return (
			<Card className="catavio-card">
				<CardBody className="catavio-card__body">
					<p role="alert">{ error }</p>
				</CardBody>
			</Card>
		);
	}

	if ( ! data ) {
		return (
			<Card className="catavio-card">
				<CardBody className="catavio-card__body">
					<Spinner />
					<span className="screen-reader-text">
						{ __( 'Loading products.', 'catavio' ) }
					</span>
				</CardBody>
			</Card>
		);
	}

	const filtered =
		!! query.search || !! query.health || !! query.issue || !! query.type;

	return (
		<div className="catavio-products">
			<Card className="catavio-card">
				<Filters
					query={ query }
					filters={ data.filters }
					onChange={ update }
					onSearch={ ( search ) => update( { search } ) }
				/>

				{ data.products.length === 0 ? (
					<CardBody className="catavio-card__body">
						{ filtered ? (
							<EmptyState
								title={ __(
									'No matching products',
									'catavio'
								) }
								description={ __(
									'No products match the filters you selected.',
									'catavio'
								) }
							/>
						) : (
							<EmptyState
								title={ __(
									'Nothing scanned yet',
									'catavio'
								) }
								description={ __(
									'Run a scan to check your product catalog.',
									'catavio'
								) }
							/>
						) }
					</CardBody>
				) : (
					<CardBody className="catavio-card__body">
						<table
							className="catavio-table catavio-table--products widefat striped"
							aria-busy={ loading }
						>
							<caption className="screen-reader-text">
								{ __(
									'Scanned products and their health',
									'catavio'
								) }
							</caption>
							<thead>
								<tr>
									<th scope="col">
										{ __(
											'Product',
											'catavio'
										) }
									</th>
									<th scope="col">
										{ __( 'Type', 'catavio' ) }
									</th>
									<th scope="col">
										{ __(
											'Health',
											'catavio'
										) }
									</th>
									<th
										scope="col"
										className="catavio-table__number"
									>
										{ __(
											'Issues',
											'catavio'
										) }
									</th>
									<th scope="col">
										{ __(
											'Last checked',
											'catavio'
										) }
									</th>
									<th scope="col">
										{ __(
											'Actions',
											'catavio'
										) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ data.products.map( ( product ) => (
									<tr key={ product.id }>
										<td>
											<a
												className="catavio-products__name"
												href={ product.detailUrl }
											>
												{ product.name }
											</a>
										</td>
										<td>
											<span className="catavio-products__type">
												{ product.typeLabel }
											</span>
										</td>
										<td>
											<span className="catavio-products__health">
												<ScoreBadge
													score={ product.score }
													bucket={ product.bucket }
													bucketLabel={
														product.bucketLabel
													}
												/>
												{ /* The badge already announces the status; this repeats it
											     visually only. */ }
												<span
													className="catavio-products__bucket"
													aria-hidden="true"
												>
													{ product.bucketLabel }
												</span>
											</span>
											<HealthMeter
												value={ product.score }
												tone={ product.bucket }
											/>
										</td>
										<td className="catavio-table__number">
											<span className="catavio-products__issues">
												{ product.issueCount }
											</span>
										</td>
										<td className="catavio-products__checked">
											{ product.lastCheckedText }
										</td>
										<td>
											{ /* get_edit_post_link() returns nothing when the viewer
										     cannot edit that particular product. A link with an
										     empty href would look like a working control and do
										     nothing at all. */ }
											{ product.editUrl ? (
												<a href={ product.editUrl }>
													{ __(
														'Edit product',
														'catavio'
													) }
												</a>
											) : (
												<span className="catavio-products__no-action">
													{ __(
														'View only',
														'catavio'
													) }
												</span>
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>

						<Pagination
							page={ data.page }
							pages={ data.pages }
							total={ data.total }
							onChange={ goToPage }
						/>
					</CardBody>
				) }
			</Card>
		</div>
	);
}
