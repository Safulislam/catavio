/**
 * Scan control and progress reporting.
 */

import { render, screen } from '@testing-library/react';

import ScanPanel from '../../src/components/ScanPanel';

const idle = { status: 'idle', total: 0, processed: 0, done: true };

describe( 'ScanPanel', () => {
	it( 'offers a single clear action when idle', () => {
		render( <ScanPanel scan={ idle } running={ false } error="" /> );

		expect(
			screen.getByRole( 'button', { name: 'Run scan' } )
		).toBeInTheDocument();
		expect( screen.queryByRole( 'progressbar' ) ).not.toBeInTheDocument();
	} );

	it( 'reports progress as text as well as a bar', () => {
		render(
			<ScanPanel
				scan={ {
					status: 'running',
					total: 200,
					processed: 50,
					done: false,
				} }
				running={ true }
				error=""
			/>
		);

		// The count is stated in words, so progress is followable even if the
		// bar does not render.
		expect(
			screen.getAllByText( 'Scanning product 50 of 200' ).length
		).toBeGreaterThan( 0 );

		const bar = screen.getByRole( 'progressbar' );
		expect( bar ).toHaveAttribute( 'value', '50' );
		expect( bar ).toHaveAttribute( 'max', '200' );
	} );

	it( 'announces progress politely to assistive technology', () => {
		const { container } = render(
			<ScanPanel
				scan={ {
					status: 'running',
					total: 10,
					processed: 3,
					done: false,
				} }
				running={ true }
				error=""
			/>
		);

		const live = container.querySelector( '[aria-live="polite"]' );
		expect( live ).toBeInTheDocument();
		expect( live ).toHaveTextContent( 'Scanning product 3 of 10' );
	} );

	it( 'lets a running scan be stopped', () => {
		render(
			<ScanPanel
				scan={ {
					status: 'running',
					total: 10,
					processed: 1,
					done: false,
				} }
				running={ true }
				error=""
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Stop scan' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: 'Run scan' } )
		).not.toBeInTheDocument();
	} );

	it( 'shows an error without hiding the action', () => {
		render(
			<ScanPanel
				scan={ idle }
				running={ false }
				error="Something went wrong."
			/>
		);

		expect(
			screen.getByText( 'Something went wrong.' )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Run scan' } )
		).toBeInTheDocument();
	} );

	it( 'offers to continue a scan that was interrupted', () => {
		render(
			<ScanPanel
				scan={ {
					status: 'running',
					total: 87,
					processed: 40,
					done: false,
				} }
				running={ false }
				interrupted={ true }
				error=""
			/>
		);

		// An interrupted job is not a running one: nothing is driving it. The
		// user is told so, shown where it stopped, and given both ways forward.
		expect(
			screen.getByText( /scan was interrupted before it finished/i )
		).toBeInTheDocument();
		expect(
			screen.getAllByText( 'Scanning product 40 of 87' ).length
		).toBeGreaterThan( 0 );
		expect(
			screen.getByRole( 'button', { name: 'Continue scan' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Stop scan' } )
		).toBeInTheDocument();
	} );

	it( 'does not offer to continue while a scan is actively running', () => {
		render(
			<ScanPanel
				scan={ {
					status: 'running',
					total: 10,
					processed: 2,
					done: false,
				} }
				running={ true }
				interrupted={ false }
				error=""
			/>
		);

		expect(
			screen.queryByRole( 'button', { name: 'Continue scan' } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Stop scan' } )
		).toBeInTheDocument();
	} );
} );
