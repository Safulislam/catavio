/**
 * Test setup for the admin UI.
 *
 * Three things are substituted here:
 *
 * - @wordpress/api-fetch, because these tests are about interface behaviour.
 *   The REST layer is verified against a real WooCommerce catalog separately.
 * - @wordpress/a11y, so announcements can be asserted on.
 * - @wordpress/components, replaced by minimal stand-ins. The real package
 *   resolves to TypeScript source under Jest and pulls in its whole dependency
 *   tree, and its behaviour is not ours to test. The stand-ins keep the
 *   semantics the tests actually assert on: Button renders a real <button>, so
 *   it still has a button role and an accessible name.
 */

import '@testing-library/jest-dom';
import { toHaveNoViolations } from 'jest-axe';

expect.extend( toHaveNoViolations );

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

jest.mock( '@wordpress/a11y', () => ( {
	__esModule: true,
	speak: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => {
	const React = require( 'react' );

	const box = ( name ) =>
		function Box( { children, ...rest } ) {
			return React.createElement(
				'div',
				{ 'data-component': name, ...rest },
				children
			);
		};

	return {
		__esModule: true,
		// Matches the real component: given an href it renders an anchor, so a
		// link is queried as a link and a button as a button.
		Button: function Button( { children, variant, href, ...rest } ) {
			if ( href ) {
				return React.createElement( 'a', { href, ...rest }, children );
			}

			return React.createElement(
				'button',
				{ type: 'button', ...rest },
				children
			);
		},
		Notice: function Notice( {
			children,
			status,
			isDismissible,
			...rest
		} ) {
			return React.createElement(
				'div',
				{ role: 'status', 'data-status': status, ...rest },
				children
			);
		},
		Spinner: function Spinner() {
			return React.createElement( 'span', {
				'data-component': 'spinner',
			} );
		},
		Card: box( 'card' ),
		CardBody: box( 'card-body' ),
		CardHeader: box( 'card-header' ),
	};
} );
