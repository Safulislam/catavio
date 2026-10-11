/**
 * Catavio — admin entry point.
 *
 * Mounts the admin application into the container rendered by
 * Catavio\Admin\Screen. The page heading is rendered server side and
 * is deliberately left outside the React tree.
 */

import { createRoot } from '@wordpress/element';

import App from './app';
import './style.scss';

const ROOT_ID = 'catavio-admin-root';

function mount() {
	const container = document.getElementById( ROOT_ID );

	if ( ! container ) {
		return;
	}

	const settings = window.catavioAdmin || {};

	createRoot( container ).render( <App settings={ settings } /> );
}

// The bundle is enqueued in the footer, so DOMContentLoaded may already have
// fired by the time it runs.
if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
