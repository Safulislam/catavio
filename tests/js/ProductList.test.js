/**
 * The product health list.
 */

import apiFetch from '@wordpress/api-fetch';
import {
	fireEvent,
	render,
	screen,
	waitFor,
	within,
} from '@testing-library/react';

import ProductList from '../../src/screens/ProductList';

const filters = {
	health: [
		{ value: 'critical', label: 'Critical' },
		{ value: 'attention', label: 'Needs attention' },
		{ value: 'healthy', label: 'Healthy' },
	],
	issues: [
		{ value: 'missing_sku', label: 'SKU' },
		{ value: 'missing_price', label: 'Price' },
	],
	types: [
		{ value: 'simple', label: 'Simple product' },
		{ value: 'variable', label: 'Variable product' },
	],
};

function product( overrides = {} ) {
	return {
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
		editUrl: 'http://example.test/wp-admin/post.php?post=101&action=edit',
		detailUrl:
			'http://example.test/wp-admin/admin.php?page=catavio&tab=products&product=101',
		...overrides,
	};
}

function page( overrides = {} ) {
	return {
		products: [ product() ],
		total: 1,
		pages: 1,
		page: 1,
		filters,
		...overrides,
	};
}

const settings = {
	screen: 'products',
	screens: { dashboard: 'dashboard', products: 'products' },
	page: 'catavio',
	tabArg: 'tab',
	adminUrl: 'http://example.test/wp-admin/admin.php',
};

beforeEach( () => {
	apiFetch.mockReset();
	window.history.replaceState( {}, '', '/wp-admin/admin.php' );
} );

describe( 'ProductList', () => {
	it( 'shows every column the specification asks for', async () => {
		apiFetch.mockResolvedValue( page() );

		render( <ProductList settings={ settings } /> );

		await screen.findByText( 'Merino Wool Scarf' );

		const headers = screen.getAllByRole( 'columnheader' );
		expect( headers.map( ( h ) => h.textContent ) ).toEqual( [
			'Product',
			'Type',
			'Health',
			'Issues',
			'Last checked',
			'Actions',
		] );
	} );

	it( 'states the health in words, not only as a number', async () => {
		apiFetch.mockResolvedValue( page() );

		render( <ProductList settings={ settings } /> );

		await screen.findByText( 'Merino Wool Scarf' );

		const row = screen.getAllByRole( 'row' )[ 1 ];

		expect( within( row ).getByText( '60' ) ).toBeInTheDocument();
		expect( within( row ).getByText( 'Critical' ) ).toBeInTheDocument();
		expect(
			within( row ).getByText( 'Health score 60 out of 100, Critical' )
		).toBeInTheDocument();
	} );

	it( 'links to the standard WooCommerce editor and to the detail view', async () => {
		apiFetch.mockResolvedValue( page() );

		render( <ProductList settings={ settings } /> );

		const edit = await screen.findByRole( 'link', {
			name: 'Edit product',
		} );
		expect( edit ).toHaveAttribute(
			'href',
			'http://example.test/wp-admin/post.php?post=101&action=edit'
		);

		const detail = screen.getByRole( 'link', {
			name: 'Merino Wool Scarf',
		} );
		expect( detail.getAttribute( 'href' ) ).toContain(
			'page=catavio&tab=products&product=101'
		);
	} );

	it( 'does not render a dead link when the product cannot be edited', async () => {
		apiFetch.mockResolvedValue(
			page( { products: [ product( { editUrl: '' } ) ] } )
		);

		render( <ProductList settings={ settings } /> );

		await screen.findByText( 'Merino Wool Scarf' );

		expect(
			screen.queryByRole( 'link', { name: 'Edit product' } )
		).not.toBeInTheDocument();
		expect( screen.getByText( 'View only' ) ).toBeInTheDocument();
	} );

	it( 'invites a scan when nothing has been scanned', async () => {
		apiFetch.mockResolvedValue(
			page( { products: [], total: 0, pages: 0 } )
		);

		render( <ProductList settings={ settings } /> );

		expect(
			await screen.findByText(
				'Run a scan to check your product catalog.'
			)
		).toBeInTheDocument();
	} );

	it( 'distinguishes an empty filter result from an empty catalog', async () => {
		apiFetch.mockResolvedValue(
			page( { products: [], total: 0, pages: 0 } )
		);

		window.history.replaceState(
			{},
			'',
			'/wp-admin/admin.php?health=critical'
		);

		render( <ProductList settings={ settings } /> );

		expect(
			await screen.findByText(
				'No products match the filters you selected.'
			)
		).toBeInTheDocument();
	} );

	it( 'reads its filters from the address bar', async () => {
		apiFetch.mockResolvedValue( page() );

		window.history.replaceState(
			{},
			'',
			'/wp-admin/admin.php?health=critical&issue=missing_sku&type=variable&s=scarf&paged=2'
		);

		render( <ProductList settings={ settings } /> );

		await screen.findByText( 'Merino Wool Scarf' );

		const requested = apiFetch.mock.calls[ 0 ][ 0 ].path;

		expect( requested ).toContain( 'health=critical' );
		expect( requested ).toContain( 'issue=missing_sku' );
		expect( requested ).toContain( 'type=variable' );
		expect( requested ).toContain( 'search=scarf' );
		expect( requested ).toContain( 'page=2' );
	} );

	it( 'returns to page one when a filter changes', async () => {
		apiFetch.mockResolvedValue( page( { total: 40, pages: 2, page: 2 } ) );

		window.history.replaceState( {}, '', '/wp-admin/admin.php?paged=2' );

		render( <ProductList settings={ settings } /> );

		await screen.findByText( 'Merino Wool Scarf' );
		expect( apiFetch.mock.calls[ 0 ][ 0 ].path ).toContain( 'page=2' );

		fireEvent.change( screen.getByLabelText( 'Health' ), {
			target: { value: 'critical' },
		} );

		// Staying on page two of a result set that may have only one page is
		// never what anyone wants.
		await waitFor( () => {
			const last =
				apiFetch.mock.calls[ apiFetch.mock.calls.length - 1 ][ 0 ].path;
			expect( last ).toContain( 'health=critical' );
			expect( last ).toContain( 'page=1' );
		} );
	} );

	it( 'hides pagination when everything fits on one page', async () => {
		apiFetch.mockResolvedValue( page() );

		render( <ProductList settings={ settings } /> );

		await screen.findByText( 'Merino Wool Scarf' );

		expect(
			screen.queryByRole( 'navigation', { name: 'Product list pages' } )
		).not.toBeInTheDocument();
	} );

	it( 'pages through a larger result set', async () => {
		apiFetch.mockResolvedValue( page( { total: 45, pages: 3, page: 2 } ) );

		render( <ProductList settings={ settings } /> );

		await screen.findByText( 'Merino Wool Scarf' );

		expect( screen.getByText( 'Page 2 of 3' ) ).toBeInTheDocument();
		expect( screen.getByText( '45 products' ) ).toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button', { name: 'Next page' } ) );

		await waitFor( () => {
			const last =
				apiFetch.mock.calls[ apiFetch.mock.calls.length - 1 ][ 0 ].path;
			expect( last ).toContain( 'page=3' );
		} );
	} );

	it( 'gives every filter control a real label', async () => {
		apiFetch.mockResolvedValue( page() );

		render( <ProductList settings={ settings } /> );

		await screen.findByText( 'Merino Wool Scarf' );

		expect(
			screen.getByLabelText( 'Search products' )
		).toBeInTheDocument();
		expect( screen.getByLabelText( 'Health' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Issue' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Product type' ) ).toBeInTheDocument();
	} );

	it( 'reports a failure to load rather than showing an empty table', async () => {
		apiFetch.mockRejectedValue( { message: 'Server unavailable.' } );

		render( <ProductList settings={ settings } /> );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
			'Server unavailable.'
		);
	} );
} );
