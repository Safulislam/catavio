/**
 * Accessibility of the admin screens.
 *
 * Two kinds of check: axe for the rules a machine can decide, and keyboard
 * interaction for the ones it cannot. Neither replaces a person using the
 * screen, but both catch regressions that a person would otherwise have to
 * notice by hand.
 *
 * Two axe rules are switched off, and only these two:
 *
 * - "region": every element must sit inside a landmark. These tests render the
 *   plugin's own subtree, not the whole admin page; WordPress supplies the
 *   landmarks around it.
 * - "page-has-heading-one": the h1 is rendered server side by Admin\Screen, so
 *   it is deliberately outside the React tree.
 */

import apiFetch from '@wordpress/api-fetch';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';

import Dashboard from '../../src/screens/Dashboard';
import ProductDetail from '../../src/screens/ProductDetail';
import ProductList from '../../src/screens/ProductList';
import ScanPanel from '../../src/components/ScanPanel';

const AXE_CONFIG = {
	rules: {
		region: { enabled: false },
		'page-has-heading-one': { enabled: false },
	},
};

const settings = {
	screen: 'products',
	screens: { dashboard: 'dashboard', products: 'products' },
	page: 'catavio',
	tabArg: 'tab',
	adminUrl: 'http://example.test/wp-admin/admin.php',
};

const summary = {
	hasProducts: true,
	productsTotal: 87,
	hasScan: true,
	averageScore: 93,
	scanned: 87,
	skipped: 0,
	healthy: 6,
	attention: 76,
	critical: 5,
	lastScan: 1700000000,
	lastScanText: '5 mins ago',
	topIssues: [
		{
			check: 'missing_image_alt_text',
			label: 'Featured image alt text',
			severity: 'info',
			severityLabel: 'Info',
			count: 76,
		},
		{
			check: 'missing_price',
			label: 'Price',
			severity: 'critical',
			severityLabel: 'Critical',
			count: 3,
		},
	],
	scan: { status: 'idle', total: 0, processed: 0, done: true },
};

const listPage = {
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
			editUrl:
				'http://example.test/wp-admin/post.php?post=101&action=edit',
			detailUrl: 'http://example.test/wp-admin/admin.php?product=101',
		},
	],
	total: 45,
	pages: 3,
	page: 2,
	filters: {
		health: [ { value: 'critical', label: 'Critical' } ],
		issues: [ { value: 'missing_sku', label: 'SKU' } ],
		types: [ { value: 'simple', label: 'Simple product' } ],
	},
};

const detail = {
	id: 101,
	name: 'Nike Air Max 270',
	type: 'variable',
	typeLabel: 'Variable product',
	scanned: true,
	lastChecked: 1700000000,
	lastCheckedText: '5 mins ago',
	editUrl: 'http://example.test/wp-admin/post.php?post=101&action=edit',
	listUrl:
		'http://example.test/wp-admin/admin.php?page=catavio&tab=products',
	startingScore: 100,
	score: 65,
	bucket: 'critical',
	bucketLabel: 'Critical',
	issues: [
		{
			check: 'missing_short_description',
			label: 'Short description',
			severity: 'critical',
			severityLabel: 'Critical',
			message: 'The short description is missing.',
			penalty: 20,
		},
		{
			check: 'missing_sku',
			label: 'SKU',
			severity: 'warning',
			severityLabel: 'Warning',
			message: 'The product has no SKU.',
			penalty: 10,
		},
	],
	passed: [ { check: 'missing_title', label: 'Product title' } ],
	notApplicable: [ { check: 'missing_external_url', label: 'Product URL' } ],
};

beforeEach( () => {
	apiFetch.mockReset();
	window.history.replaceState( {}, '', '/wp-admin/admin.php' );
} );

describe( 'accessibility: axe', () => {
	it( 'dashboard has no violations', async () => {
		apiFetch.mockResolvedValue( summary );

		const { container } = render( <Dashboard /> );
		await screen.findByText( '93%' );

		expect( await axe( container, AXE_CONFIG ) ).toHaveNoViolations();
	} );

	it( 'dashboard empty state has no violations', async () => {
		apiFetch.mockResolvedValue( {
			...summary,
			hasProducts: false,
			hasScan: false,
		} );

		const { container } = render( <Dashboard /> );
		await screen.findByText( 'No WooCommerce products were found.' );

		expect( await axe( container, AXE_CONFIG ) ).toHaveNoViolations();
	} );

	it( 'product list has no violations', async () => {
		apiFetch.mockResolvedValue( listPage );

		const { container } = render( <ProductList settings={ settings } /> );
		await screen.findByText( 'Merino Wool Scarf' );

		expect( await axe( container, AXE_CONFIG ) ).toHaveNoViolations();
	} );

	it( 'product detail has no violations', async () => {
		apiFetch.mockResolvedValue( detail );

		const { container } = render( <ProductDetail productId={ 101 } /> );
		await screen.findByText( 'Nike Air Max 270' );

		expect( await axe( container, AXE_CONFIG ) ).toHaveNoViolations();
	} );

	it( 'scan progress has no violations', async () => {
		const { container } = render(
			<ScanPanel
				scan={ {
					status: 'running',
					total: 87,
					processed: 20,
					done: false,
				} }
				running={ true }
				error=""
			/>
		);

		expect( await axe( container, AXE_CONFIG ) ).toHaveNoViolations();
	} );

	it( 'an error notice has no violations', async () => {
		const { container } = render(
			<ScanPanel
				scan={ { status: 'idle', total: 0, processed: 0, done: true } }
				running={ false }
				error="The scan could not be completed."
			/>
		);

		expect( await axe( container, AXE_CONFIG ) ).toHaveNoViolations();
	} );
} );

describe( 'accessibility: keyboard', () => {
	it( 'reaches the scan action by keyboard alone', async () => {
		apiFetch.mockResolvedValue( summary );
		const user = userEvent.setup();

		render( <Dashboard /> );
		await screen.findByText( '93%' );

		const run = screen.getByRole( 'button', { name: 'Run scan' } );

		// Tab until the action is reached, rather than focusing it directly.
		let guard = 0;
		while ( document.activeElement !== run && guard < 30 ) {
			// eslint-disable-next-line no-await-in-loop -- Tabbing is sequential.
			await user.tab();
			guard++;
		}

		expect( run ).toHaveFocus();
	} );

	it( 'reaches every product list control by keyboard', async () => {
		apiFetch.mockResolvedValue( listPage );
		const user = userEvent.setup();

		render( <ProductList settings={ settings } /> );
		await screen.findByText( 'Merino Wool Scarf' );

		const wanted = [
			screen.getByLabelText( 'Search products' ),
			screen.getByLabelText( 'Health' ),
			screen.getByLabelText( 'Issue' ),
			screen.getByLabelText( 'Product type' ),
			screen.getByRole( 'link', { name: 'Edit product' } ),
			screen.getByRole( 'button', { name: 'Next page' } ),
		];

		const reached = new Set();

		for ( let i = 0; i < 40; i++ ) {
			// eslint-disable-next-line no-await-in-loop -- Tabbing is sequential.
			await user.tab();

			if ( wanted.includes( document.activeElement ) ) {
				reached.add( document.activeElement );
			}
		}

		expect( reached.size ).toBe( wanted.length );
	} );

	it( 'operates the page controls with the keyboard', async () => {
		apiFetch.mockResolvedValue( listPage );
		const user = userEvent.setup();

		render( <ProductList settings={ settings } /> );
		await screen.findByText( 'Merino Wool Scarf' );

		const next = screen.getByRole( 'button', { name: 'Next page' } );
		next.focus();
		await user.keyboard( '{Enter}' );

		await waitFor( () => {
			const last =
				apiFetch.mock.calls[ apiFetch.mock.calls.length - 1 ][ 0 ].path;
			expect( last ).toContain( 'page=3' );
		} );
	} );

	it( 'reaches the editor link on the detail screen by keyboard', async () => {
		apiFetch.mockResolvedValue( detail );
		const user = userEvent.setup();

		render( <ProductDetail productId={ 101 } /> );
		await screen.findByText( 'Nike Air Max 270' );

		const edit = screen.getByRole( 'link', { name: 'Edit product' } );

		let guard = 0;
		while ( document.activeElement !== edit && guard < 30 ) {
			// eslint-disable-next-line no-await-in-loop -- Tabbing is sequential.
			await user.tab();
			guard++;
		}

		expect( edit ).toHaveFocus();
	} );
} );

describe( 'accessibility: status without colour', () => {
	it( 'states every dashboard figure in words', async () => {
		apiFetch.mockResolvedValue( summary );

		render( <Dashboard /> );
		await screen.findByText( '93%' );

		// Removing every stylesheet must not remove any meaning.
		expect( screen.getByText( 'Healthy' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Needs attention' ) ).toBeInTheDocument();
		expect( screen.getAllByText( 'Critical' ).length ).toBeGreaterThan( 0 );
		expect( screen.getByText( 'Info' ) ).toBeInTheDocument();
	} );

	it( 'gives the score a spoken form, not just a number', async () => {
		apiFetch.mockResolvedValue( detail );

		render( <ProductDetail productId={ 101 } /> );
		await screen.findByText( 'Nike Air Max 270' );

		expect(
			screen.getByText( 'Health score 65 out of 100, Critical' )
		).toBeInTheDocument();
	} );

	it( 'announces a product status once, not twice', async () => {
		apiFetch.mockResolvedValue( detail );

		render( <ProductDetail productId={ 101 } /> );
		await screen.findByText( 'Nike Air Max 270' );

		// The badge carries the spoken form. The word beside it is the same
		// information for sighted readers, so it is marked decorative rather
		// than read out a second time.
		expect(
			screen.getByText( 'Health score 65 out of 100, Critical' )
		).toBeInTheDocument();

		const visible = document.querySelector( '.catavio-detail__bucket' );
		expect( visible ).toHaveTextContent( 'Critical' );
		expect( visible ).toHaveAttribute( 'aria-hidden', 'true' );
	} );

	it( 'hides the decorative severity dot from assistive technology', async () => {
		apiFetch.mockResolvedValue( summary );

		const { container } = render( <Dashboard /> );
		await screen.findByText( '93%' );

		const dots = container.querySelectorAll( '.catavio-severity__dot' );

		expect( dots.length ).toBeGreaterThan( 0 );
		dots.forEach( ( dot ) => {
			expect( dot ).toHaveAttribute( 'aria-hidden', 'true' );
		} );
	} );
} );
