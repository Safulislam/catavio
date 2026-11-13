/**
 * Choosing which checks run.
 */

import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { axe } from 'jest-axe';

import Settings from '../../src/screens/Settings';

const AXE_CONFIG = {
	rules: {
		region: { enabled: false },
		'page-has-heading-one': { enabled: false },
	},
};

function check( id, label, severity, enabled = true ) {
	const severityLabel = {
		critical: 'Critical',
		warning: 'Warning',
		info: 'Info',
	}[ severity ];

	return { check: id, label, severity, severityLabel, enabled };
}

function described( overrides = {} ) {
	return {
		checks: [
			check( 'missing_title', 'Product title', 'critical' ),
			check( 'missing_sku', 'SKU', 'warning' ),
			check( 'missing_image_alt_text', 'Image alt text', 'info' ),
		],
		updatedAt: 0,
		...overrides,
	};
}

const settings = {
	screen: 'settings',
	page: 'catavio',
	tabArg: 'tab',
	adminUrl: 'http://example.test/wp-admin/admin.php',
};

beforeEach( () => {
	apiFetch.mockReset();
	speak.mockClear();
} );

describe( 'Settings', () => {
	it( 'groups every check under its severity, each with a real label', async () => {
		apiFetch.mockResolvedValue( described() );

		render( <Settings settings={ settings } /> );

		expect(
			await screen.findByRole( 'group', { name: 'Critical' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'group', { name: 'Warning' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'group', { name: 'Info' } )
		).toBeInTheDocument();

		expect( screen.getByLabelText( 'SKU' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Product title' ) ).toBeChecked();
	} );

	it( 'shows a turned-off check as unticked', async () => {
		apiFetch.mockResolvedValue(
			described( {
				checks: [
					check( 'missing_title', 'Product title', 'critical' ),
					check( 'missing_sku', 'SKU', 'warning', false ),
				],
			} )
		);

		render( <Settings settings={ settings } /> );

		expect( await screen.findByLabelText( 'SKU' ) ).not.toBeChecked();
	} );

	it( 'saves only what changed, and says when it applies', async () => {
		const user = userEvent.setup();

		apiFetch.mockResolvedValueOnce( described() ).mockResolvedValueOnce(
			described( {
				checks: [
					check( 'missing_title', 'Product title', 'critical' ),
					check( 'missing_sku', 'SKU', 'warning', false ),
					check( 'missing_image_alt_text', 'Image alt text', 'info' ),
				],
				updatedAt: 1700000000,
			} )
		);

		render( <Settings settings={ settings } /> );

		const save = await screen.findByRole( 'button', {
			name: 'Save changes',
		} );

		// Nothing to save yet.
		expect( save ).toHaveAttribute( 'aria-disabled', 'true' );
		await user.click( save );
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );

		await user.click( screen.getByLabelText( 'SKU' ) );
		expect( save ).toHaveAttribute( 'aria-disabled', 'false' );

		await user.click( save );

		await waitFor( () => {
			expect( apiFetch ).toHaveBeenCalledTimes( 2 );
		} );

		const request = apiFetch.mock.calls[ 1 ][ 0 ];
		expect( request.path ).toBe( '/catavio/v1/settings' );
		expect( request.method ).toBe( 'POST' );
		expect( request.data ).toEqual( { disabledChecks: [ 'missing_sku' ] } );

		expect(
			await screen.findByText(
				'Settings saved. They apply from the next scan, or when a product is checked again.'
			)
		).toBeInTheDocument();
		expect( speak ).toHaveBeenCalledWith( 'Settings saved.', 'polite' );
		expect(
			screen.getByRole( 'link', { name: 'Go to the dashboard' } )
		).toHaveAttribute(
			'href',
			'http://example.test/wp-admin/admin.php?page=catavio'
		);
	} );

	it( 'will not turn every check off', async () => {
		const user = userEvent.setup();

		apiFetch.mockResolvedValue(
			described( {
				checks: [ check( 'missing_sku', 'SKU', 'warning' ) ],
			} )
		);

		render( <Settings settings={ settings } /> );

		await user.click( await screen.findByLabelText( 'SKU' ) );

		expect(
			screen.getByText( 'Keep at least one check turned on.' )
		).toBeInTheDocument();

		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);

		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'reports a failed save', async () => {
		const user = userEvent.setup();

		apiFetch
			.mockResolvedValueOnce( described() )
			.mockRejectedValueOnce( { message: 'Sorry, not allowed.' } );

		render( <Settings settings={ settings } /> );

		await user.click( await screen.findByLabelText( 'SKU' ) );
		await user.click(
			screen.getByRole( 'button', { name: 'Save changes' } )
		);

		expect(
			await screen.findByText( 'Sorry, not allowed.' )
		).toBeInTheDocument();
	} );

	it( 'reports a failure to load', async () => {
		apiFetch.mockRejectedValue( { message: 'Network is down.' } );

		render( <Settings settings={ settings } /> );

		expect( await screen.findByRole( 'alert' ) ).toHaveTextContent(
			'Network is down.'
		);
	} );

	it( 'has no accessibility violations', async () => {
		apiFetch.mockResolvedValue( described() );

		const { container } = render( <Settings settings={ settings } /> );

		await screen.findByLabelText( 'SKU' );

		expect( await axe( container, AXE_CONFIG ) ).toHaveNoViolations();
	} );
} );
