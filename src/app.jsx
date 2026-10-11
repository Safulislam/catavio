/**
 * Screen router.
 *
 * Navigation uses admin query arguments rather than a router library, so deep
 * links, browser history and WordPress menu highlighting all keep working.
 */

import { __ } from '@wordpress/i18n';

import Dashboard from './screens/Dashboard';
import Products from './screens/Products';

export default function App( { settings } ) {
	const { screen, screens } = settings;

	if ( screens && screen === screens.products ) {
		return <Products settings={ settings } />;
	}

	if ( screens && screen === screens.dashboard ) {
		return <Dashboard settings={ settings } />;
	}

	return <p>{ __( 'Unknown screen.', 'catavio' ) }</p>;
}
