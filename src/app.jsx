/**
 * Screen router.
 *
 * Navigation uses admin query arguments rather than a router library, so deep
 * links, browser history and WordPress menu highlighting all keep working.
 *
 * Views added by extensions (PHP filter catavio_admin_views) are rendered by
 * the component registered for them with the JavaScript filter
 * "catavio.views", which receives and returns an object of view id → component.
 */

import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

import { ExtensionBoundary } from './extensions';
import Dashboard from './screens/Dashboard';
import Products from './screens/Products';
import Settings from './screens/Settings';

/**
 * Find the component an extension registered for a view.
 *
 * @param {string} screen View identifier.
 * @return {Function|null} Component, or null when none is registered.
 */
function extensionView( screen ) {
	let views;

	try {
		views = applyFilters( 'catavio.views', {} );
	} catch ( error ) {
		return null;
	}

	if ( ! views || typeof views !== 'object' ) {
		return null;
	}

	return Object.prototype.hasOwnProperty.call( views, screen ) &&
		typeof views[ screen ] === 'function'
		? views[ screen ]
		: null;
}

export default function App( { settings } ) {
	const { screen, screens } = settings;

	if ( screens && screen === screens.products ) {
		return <Products settings={ settings } />;
	}

	if ( screens && screen === screens.settings ) {
		return <Settings settings={ settings } />;
	}

	if ( screens && screen === screens.dashboard ) {
		return <Dashboard settings={ settings } />;
	}

	const View = screen ? extensionView( screen ) : null;

	if ( View ) {
		return (
			<ExtensionBoundary>
				<View settings={ settings } />
			</ExtensionBoundary>
		);
	}

	return <p>{ __( 'Unknown screen.', 'catavio' ) }</p>;
}
