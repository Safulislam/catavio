/**
 * The dashboard a store owner actually sees.
 */

import apiFetch from '@wordpress/api-fetch';
import { render, screen, waitFor, within } from '@testing-library/react';

import Dashboard from '../../src/screens/Dashboard';

const idleScan = {
	status: 'idle',
	total: 0,
	processed: 0,
	done: true,
	stale: false,
};

function summary( overrides = {} ) {
	return {
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
		scan: idleScan,
		...overrides,
	};
}

beforeEach( () => {
	apiFetch.mockReset();
} );

describe( 'Dashboard', () => {
	it( 'shows the catalog figures once loaded', async () => {
		apiFetch.mockResolvedValue( summary() );

		render( <Dashboard /> );

		expect( await screen.findByText( '93%' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Healthy' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Needs attention' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Based on 87 products scanned' )
		).toBeInTheDocument();
		expect(
			screen.getByText( /Last scan 5 mins ago/ )
		).toBeInTheDocument();
	} );

	it( 'labels every top issue with its severity in words', async () => {
		apiFetch.mockResolvedValue( summary() );

		render( <Dashboard /> );

		expect(
			await screen.findByText( 'Featured image alt text' )
		).toBeInTheDocument();

		// Severity is carried by text, not only by the colour of a dot.
		// Scoped to the table: "Critical" is also a bucket label higher up the
		// page, and both uses are intentional.
		const table = screen.getByRole( 'table' );
		const rows = within( table ).getAllByRole( 'row' ).slice( 1 );

		expect( within( rows[ 0 ] ).getByText( 'Info' ) ).toBeInTheDocument();
		expect( within( rows[ 0 ] ).getByText( '76' ) ).toBeInTheDocument();
		expect(
			within( rows[ 1 ] ).getByText( 'Critical' )
		).toBeInTheDocument();
	} );

	it( 'gives the issues table a real header row', async () => {
		apiFetch.mockResolvedValue( summary() );

		render( <Dashboard /> );

		await screen.findByText( 'Featured image alt text' );

		const headers = screen.getAllByRole( 'columnheader' );
		expect( headers.map( ( h ) => h.textContent ) ).toEqual( [
			'Issue',
			'Severity',
			'Products',
		] );
	} );

	it( 'states plainly when there are no products', async () => {
		apiFetch.mockResolvedValue(
			summary( { hasProducts: false, productsTotal: 0, hasScan: false } )
		);

		render( <Dashboard /> );

		expect(
			await screen.findByText( 'No WooCommerce products were found.' )
		).toBeInTheDocument();

		// Nothing to scan, so no scan action is offered.
		expect(
			screen.queryByRole( 'button', { name: 'Run scan' } )
		).not.toBeInTheDocument();
	} );

	it( 'invites a first scan when products exist but none has run', async () => {
		apiFetch.mockResolvedValue( summary( { hasScan: false } ) );

		render( <Dashboard /> );

		expect(
			await screen.findByText(
				'Run a scan to check your product catalog.'
			)
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Run scan' } )
		).toBeInTheDocument();

		// No invented figures before a scan has happened.
		expect( screen.queryByText( '93%' ) ).not.toBeInTheDocument();
	} );

	it( 'reports a failure to load rather than showing nothing', async () => {
		apiFetch.mockRejectedValue( { message: 'Network is down.' } );

		render( <Dashboard /> );

		const alert = await screen.findByRole( 'alert' );
		expect( alert ).toHaveTextContent( 'Network is down.' );
	} );

	it( 'resumes reporting a scan that was already running', async () => {
		apiFetch.mockResolvedValue(
			summary( {
				scan: {
					status: 'running',
					total: 87,
					processed: 20,
					done: false,
					stale: false,
				},
			} )
		);

		render( <Dashboard /> );

		await waitFor( () => {
			expect( screen.getByText( '93%' ) ).toBeInTheDocument();
		} );

		// The panel picks up the in-flight job rather than offering a fresh run.
		await waitFor( () => {
			expect(
				screen.getAllByText( 'Scanning product 20 of 87' ).length
			).toBeGreaterThan( 0 );
		} );

		// An interrupted job offers to continue, not to start over.
		expect(
			screen.getByRole( 'button', { name: 'Continue scan' } )
		).toBeInTheDocument();
	} );
} );
