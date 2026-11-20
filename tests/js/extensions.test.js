/**
 * Extension points: other plugins adding to Catavio's screens.
 */

import apiFetch from '@wordpress/api-fetch';
import { addFilter, removeAllFilters } from '@wordpress/hooks';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import App from '../../src/app';
import { getExtensions } from '../../src/extensions';
import Dashboard from '../../src/screens/Dashboard';
import ProductDetail from '../../src/screens/ProductDetail';
import ProductList from '../../src/screens/ProductList';
import Settings from '../../src/screens/Settings';

const HOOKS = [
	'catavio.views',
	'catavio.dashboard.cards',
	'catavio.dashboard.scoreDetails',
	'catavio.productList.columns',
	'catavio.productReport.issueActions',
	'catavio.productReport.panels',
	'catavio.settings.sections',
	'test.hook',
];

const NS = 'catavio/tests';

function report( overrides = {} ) {
	return {
		id: 101,
		name: 'Merino Wool Scarf',
		type: 'simple',
		typeLabel: 'Simple product',
		scanned: true,
		lastChecked: 1700000000,
		lastCheckedText: '5 mins ago',
		editUrl: 'http://example.test/wp-admin/post.php?post=101&action=edit',
		listUrl:
			'http://example.test/wp-admin/admin.php?page=catavio&tab=products',
		startingScore: 100,
		score: 90,
		bucket: 'attention',
		bucketLabel: 'Needs attention',
		issues: [
			{
				check: 'missing_sku',
				label: 'SKU',
				severity: 'warning',
				severityLabel: 'Warning',
				message: 'The product has no SKU.',
				penalty: 10,
				group: 'identifiers',
				groupLabel: 'Brand and identifiers',
			},
		],
		passed: [],
		notApplicable: [],
		turnedOff: [],
		changedSince: false,
		canRecheck: true,
		...overrides,
	};
}

beforeEach( () => {
	apiFetch.mockReset();
	window.history.replaceState( {}, '', '/wp-admin/admin.php' );
} );

afterEach( () => {
	HOOKS.forEach( ( hook ) => removeAllFilters( hook ) );
} );

describe( 'getExtensions', () => {
	it( 'keeps only well-formed entries, each key once', () => {
		addFilter( 'test.hook', NS, ( entries ) => [
			...entries,
			{ key: 'good', label: 'Good', render: () => null },
			{ key: 'good', label: 'Duplicate', render: () => null },
			{ key: 'no-render', label: 'Broken' },
			{ key: '', render: () => null },
			null,
			{ key: 'no-label', render: () => null },
		] );

		expect(
			getExtensions( 'test.hook' ).map( ( entry ) => entry.key )
		).toEqual( [ 'good', 'no-label' ] );
		expect(
			getExtensions( 'test.hook', true ).map( ( entry ) => entry.key )
		).toEqual( [ 'good' ] );
	} );

	it( 'treats a filter that returns nonsense, or throws, as no entries', () => {
		addFilter( 'test.hook', NS, () => 'nonsense' );
		expect( getExtensions( 'test.hook' ) ).toEqual( [] );

		removeAllFilters( 'test.hook' );
		addFilter( 'test.hook', NS, () => {
			throw new Error( 'boom' );
		} );
		expect( getExtensions( 'test.hook' ) ).toEqual( [] );
	} );
} );

describe( 'App', () => {
	it( 'renders a view an extension registered', () => {
		addFilter( 'catavio.views', NS, ( views ) => ( {
			...views,
			seo: ( { settings } ) => <p>SEO view for { settings.page }</p>,
		} ) );

		render(
			<App
				settings={ {
					screen: 'seo',
					screens: { dashboard: 'dashboard' },
					page: 'catavio',
				} }
			/>
		);

		expect(
			screen.getByText( 'SEO view for catavio' )
		).toBeInTheDocument();
	} );

	it( 'says so when no extension renders the view', () => {
		render(
			<App settings={ { screen: 'seo', screens: { dashboard: 'x' } } } />
		);

		expect( screen.getByText( 'Unknown screen.' ) ).toBeInTheDocument();
	} );
} );

describe( 'Product report', () => {
	it( 'shows issue actions and panels, and lets them reload the report', async () => {
		const user = userEvent.setup();

		addFilter(
			'catavio.productReport.issueActions',
			NS,
			( actions, issue ) => [
				...actions,
				{
					key: 'fix',
					render: ( { reload } ) => (
						<button type="button" onClick={ reload }>
							{ `Fix ${ issue.label }` }
						</button>
					),
				},
			]
		);
		addFilter( 'catavio.productReport.panels', NS, ( panels ) => [
			...panels,
			{
				key: 'seo',
				render: ( { product } ) => (
					<section>Score seen by panel: { product.score }</section>
				),
			},
		] );

		apiFetch
			.mockResolvedValueOnce( report() )
			.mockResolvedValueOnce( report( { score: 100, issues: [] } ) );

		render( <ProductDetail productId={ 101 } /> );

		expect(
			await screen.findByText( 'Score seen by panel: 90' )
		).toBeInTheDocument();

		await user.click( screen.getByRole( 'button', { name: 'Fix SKU' } ) );

		expect(
			await screen.findByText( 'Score seen by panel: 100' )
		).toBeInTheDocument();
		expect( apiFetch ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'contains a broken extension to its own slot', async () => {
		addFilter( 'catavio.productReport.panels', NS, ( panels ) => [
			...panels,
			{
				key: 'broken',
				render: () => {
					throw new Error( 'boom' );
				},
			},
		] );

		// React logs caught render errors; keep the test output readable.
		const spy = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => {} );

		apiFetch.mockResolvedValue( report() );

		render( <ProductDetail productId={ 101 } /> );

		expect(
			await screen.findByText( 'This section could not be displayed.' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'The product has no SKU.' )
		).toBeInTheDocument();

		spy.mockRestore();
	} );
} );

describe( 'Product list', () => {
	it( 'adds labelled columns before Actions', async () => {
		addFilter( 'catavio.productList.columns', NS, ( columns ) => [
			...columns,
			{
				key: 'seo',
				label: 'SEO',
				render: ( { product } ) => `SEO ${ product.id }`,
			},
		] );

		apiFetch.mockResolvedValue( {
			products: [
				{
					id: 101,
					name: 'Merino Wool Scarf',
					type: 'simple',
					typeLabel: 'Simple product',
					score: 60,
					bucket: 'critical',
					bucketLabel: 'Critical',
					issueCount: 3,
					criticalCount: 1,
					lastChecked: 1700000000,
					lastCheckedText: '5 mins ago',
					editUrl: '',
					detailUrl: '',
				},
			],
			total: 1,
			pages: 1,
			page: 1,
			filters: { health: [], issues: [], types: [] },
		} );

		render(
			<ProductList
				settings={ {
					screen: 'products',
					page: 'catavio',
					tabArg: 'tab',
					adminUrl: 'http://example.test/wp-admin/admin.php',
				} }
			/>
		);

		const table = await screen.findByRole( 'table' );
		const headers = within( table )
			.getAllByRole( 'columnheader' )
			.map( ( cell ) => cell.textContent );

		expect( headers.slice( -2 ) ).toEqual( [ 'SEO', 'Actions' ] );
		expect( within( table ).getByText( 'SEO 101' ) ).toBeInTheDocument();
	} );
} );

describe( 'Dashboard', () => {
	it( 'adds cards that receive the summary', async () => {
		addFilter( 'catavio.dashboard.cards', NS, ( cards ) => [
			...cards,
			{
				key: 'ai',
				render: ( { summary } ) => (
					<p>{ `AI readiness for ${ summary.scanned } products` }</p>
				),
			},
		] );

		apiFetch.mockResolvedValue( {
			hasProducts: true,
			productsTotal: 2,
			hasScan: true,
			averageScore: 90,
			scanned: 2,
			skipped: 0,
			healthy: 1,
			attention: 1,
			critical: 0,
			lastScan: 1700000000,
			lastScanText: '5 mins ago',
			topIssues: [],
			scan: {
				status: 'idle',
				total: 0,
				processed: 0,
				done: true,
				stale: false,
			},
		} );

		render( <Dashboard /> );

		expect(
			await screen.findByText( 'AI readiness for 2 products' )
		).toBeInTheDocument();
	} );

	it( 'adds a line under the headline score, such as a trend', async () => {
		addFilter( 'catavio.dashboard.scoreDetails', NS, ( entries ) => [
			...entries,
			{
				key: 'trend',
				render: ( { summary } ) => (
					<p>{ `Up 4 points, now ${ summary.averageScore }` }</p>
				),
			},
		] );

		apiFetch.mockResolvedValue( {
			hasProducts: true,
			productsTotal: 2,
			hasScan: true,
			averageScore: 90,
			scanned: 2,
			skipped: 0,
			healthy: 1,
			attention: 1,
			critical: 0,
			lastScan: 1700000000,
			lastScanText: '5 mins ago',
			topIssues: [],
			topics: [],
			topicsRecorded: true,
			scan: {
				status: 'idle',
				total: 0,
				processed: 0,
				done: true,
				stale: false,
			},
		} );

		render( <Dashboard /> );

		const line = await screen.findByText( 'Up 4 points, now 90' );

		expect( line.closest( '.catavio-hero' ) ).not.toBeNull();
	} );
} );

describe( 'Settings', () => {
	it( 'groups checks by topic, describes severity and optional checks, and adds sections', async () => {
		addFilter( 'catavio.settings.sections', NS, ( sections ) => [
			...sections,
			{ key: 'license', render: () => <p>Licence section</p> },
		] );

		apiFetch.mockResolvedValue( {
			checks: [
				{
					check: 'missing_title',
					label: 'Product title',
					severity: 'critical',
					severityLabel: 'Critical',
					enabled: true,
					optional: false,
					group: 'content',
					groupLabel: 'Content',
				},
				{
					check: 'missing_gtin',
					label: 'GTIN, UPC, EAN or ISBN',
					severity: 'info',
					severityLabel: 'Info',
					enabled: false,
					optional: true,
					group: 'identifiers',
					groupLabel: 'Brand and identifiers',
				},
			],
			groups: [
				{ group: 'content', label: 'Content' },
				{ group: 'identifiers', label: 'Brand and identifiers' },
				{ group: 'other', label: 'Other checks' },
			],
			updatedAt: 0,
		} );

		render( <Settings settings={ { page: 'catavio' } } /> );

		const identifiers = await screen.findByRole( 'group', {
			name: 'Brand and identifiers',
		} );
		const gtin = within( identifiers ).getByRole( 'checkbox', {
			name: 'GTIN, UPC, EAN or ISBN',
		} );

		expect( gtin ).not.toBeChecked();
		expect( gtin ).toHaveAccessibleDescription(
			'Info Optional, off until turned on'
		);
		expect(
			screen.getByRole( 'group', { name: 'Content' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'group', { name: 'Other checks' } )
		).not.toBeInTheDocument();
		// Outside the checks form, so its own fields never submit it.
		expect( screen.getByText( 'Licence section' ).closest( 'form' ) ).toBe(
			null
		);

		await waitFor( () =>
			expect(
				screen.getByRole( 'checkbox', { name: 'Product title' } )
			).toHaveAccessibleDescription( 'Critical' )
		);
	} );
} );
