/**
 * One product's health report.
 */

import apiFetch from '@wordpress/api-fetch';
import { render, screen, waitFor, within } from '@testing-library/react';

import ProductDetail from '../../src/screens/ProductDetail';

function report( overrides = {} ) {
	return {
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
			{
				check: 'missing_image_alt_text',
				label: 'Featured image alt text',
				severity: 'info',
				severityLabel: 'Info',
				message: 'The featured image has no alt text.',
				penalty: 5,
			},
		],
		passed: [
			{ check: 'missing_title', label: 'Product title' },
			{ check: 'missing_description', label: 'Description' },
		],
		notApplicable: [
			{ check: 'missing_external_url', label: 'Product URL' },
		],
		...overrides,
	};
}

beforeEach( () => {
	apiFetch.mockReset();
} );

describe( 'ProductDetail', () => {
	it( 'identifies the product and when it was checked', async () => {
		apiFetch.mockResolvedValue( report() );

		render( <ProductDetail productId={ 101 } /> );

		expect(
			await screen.findByText( 'Nike Air Max 270' )
		).toBeInTheDocument();
		expect( screen.getByText( 'Variable product' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Last checked 5 mins ago' )
		).toBeInTheDocument();
	} );

	it( 'states the score with its status in words', async () => {
		apiFetch.mockResolvedValue( report() );

		render( <ProductDetail productId={ 101 } /> );

		await screen.findByText( 'Nike Air Max 270' );

		expect(
			screen.getByText( 'Health score 65 out of 100, Critical' )
		).toBeInTheDocument();
	} );

	it( 'shows the arithmetic, and it reconciles with the score', async () => {
		apiFetch.mockResolvedValue( report() );

		render( <ProductDetail productId={ 101 } /> );

		await screen.findByText( 'Nike Air Max 270' );

		const table = screen.getByRole( 'table' );
		const rows = within( table ).getAllByRole( 'row' );

		// header, starting score, three deductions, total
		expect( rows ).toHaveLength( 6 );

		// 100 - 20 - 10 - 5 = 65, shown as a running total at each step.
		expect( within( rows[ 1 ] ).getByText( '100' ) ).toBeInTheDocument();
		expect( within( rows[ 2 ] ).getByText( '80' ) ).toBeInTheDocument();
		expect( within( rows[ 3 ] ).getByText( '70' ) ).toBeInTheDocument();
		expect( within( rows[ 4 ] ).getByText( '65' ) ).toBeInTheDocument();
		expect( within( rows[ 5 ] ).getByText( '65' ) ).toBeInTheDocument();
	} );

	it( 'never shows a negative running score', async () => {
		const critical = ( id ) => ( {
			check: id,
			label: id.toUpperCase(),
			severity: 'critical',
			severityLabel: 'Critical',
			message: 'Something is missing.',
			penalty: 20,
		} );

		apiFetch.mockResolvedValue(
			report( {
				score: 0,
				issues: [ 'a', 'b', 'c', 'd', 'e', 'f' ].map( critical ),
			} )
		);

		render( <ProductDetail productId={ 101 } /> );

		await screen.findByText( 'Nike Air Max 270' );

		const rows = within( screen.getByRole( 'table' ) ).getAllByRole(
			'row'
		);

		// Six criticals would reach -20 unchecked. The running total floors at
		// zero, matching the scoring rule, and never shows a negative number.
		const runningTotals = rows
			.slice( 1 )
			.map( ( row ) => row.lastElementChild.textContent );

		expect( runningTotals ).toEqual( [
			'100',
			'80',
			'60',
			'40',
			'20',
			'0',
			'0',
			'0',
		] );
		runningTotals.forEach( ( value ) => {
			expect( parseInt( value, 10 ) ).toBeGreaterThanOrEqual( 0 );
		} );
	} );

	it( 'groups issues under severity headings', async () => {
		apiFetch.mockResolvedValue( report() );

		render( <ProductDetail productId={ 101 } /> );

		await screen.findByText( 'Nike Air Max 270' );

		// h1 is the page title, h2 the product, h3 each report section, h4 each
		// severity group. Severity is part of the structure, not just styling.
		const headings = screen
			.getAllByRole( 'heading', { level: 4 } )
			.map( ( h ) => h.textContent );

		expect( headings ).toEqual(
			expect.arrayContaining( [ 'Critical', 'Warning', 'Info' ] )
		);

		expect(
			screen.getByText( 'The short description is missing.' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'The product has no SKU.' )
		).toBeInTheDocument();
	} );

	it( 'nests its headings under the product, not beside it', async () => {
		apiFetch.mockResolvedValue( report() );

		render( <ProductDetail productId={ 101 } /> );

		await screen.findByText( 'Nike Air Max 270' );

		// The product name is the report; everything else is part of it.
		expect( screen.getByRole( 'heading', { level: 2 } ).textContent ).toBe(
			'Nike Air Max 270'
		);

		expect(
			screen
				.getAllByRole( 'heading', { level: 3 } )
				.map( ( h ) => h.textContent )
		).toEqual( [ 'How this score was reached', 'Issues', 'Checks' ] );
	} );

	it( 'lists what passed and what did not apply', async () => {
		apiFetch.mockResolvedValue( report() );

		render( <ProductDetail productId={ 101 } /> );

		await screen.findByText( 'Nike Air Max 270' );

		expect( screen.getByText( 'Product title' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Product URL' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				'These checks do not apply to this product type, so they did not affect the score.'
			)
		).toBeInTheDocument();
	} );

	it( 'offers the standard WooCommerce editor as the primary action', async () => {
		apiFetch.mockResolvedValue( report() );

		render( <ProductDetail productId={ 101 } /> );

		const edit = await screen.findByRole( 'link', {
			name: 'Edit product',
		} );

		expect( edit ).toHaveAttribute(
			'href',
			'http://example.test/wp-admin/post.php?post=101&action=edit'
		);

		expect(
			screen.getByRole( 'link', { name: 'Back to product health' } )
		).toBeInTheDocument();
	} );

	it( 'moves focus to the report heading on arrival', async () => {
		apiFetch.mockResolvedValue( report() );

		render( <ProductDetail productId={ 101 } /> );

		const heading = await screen.findByText( 'Nike Air Max 270' );

		await waitFor( () => {
			expect( heading ).toHaveFocus();
		} );
	} );

	it( 'says so plainly when a product has never been scanned', async () => {
		apiFetch.mockResolvedValue(
			report( { scanned: false, score: 0, issues: [], passed: [] } )
		);

		render( <ProductDetail productId={ 101 } /> );

		expect(
			await screen.findByText(
				'This product has not been scanned yet. Run a scan to see its health report.'
			)
		).toBeInTheDocument();

		// No score table is shown for a product with no result.
		expect( screen.queryByRole( 'table' ) ).not.toBeInTheDocument();
	} );

	it( 'reports a failure to load', async () => {
		apiFetch.mockRejectedValue( { message: 'Product not found.' } );

		render( <ProductDetail productId={ 999 } /> );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
			'Product not found.'
		);
	} );
} );
