/**
 * Extension points for the admin screens.
 *
 * Other plugins add to Catavio's screens with @wordpress/hooks filters. Every
 * filter receives and returns a plain array of entries shaped
 * `{ key, label?, render }`, where `render` is a function returning React
 * content. Entries that are not shaped like that are dropped, and each one is
 * rendered inside its own error boundary, so a broken extension shows a short
 * notice in its own place instead of breaking the screen around it.
 *
 * Filters (the arguments after the array are passed to the filter and, where
 * noted, to `render`):
 *
 * - catavio.views                        Object of view id → component, see app.jsx.
 * - catavio.dashboard.cards              ( entries, summary )         render( { summary } )
 * - catavio.productList.columns          ( entries, data )            render( { product } ), label required
 * - catavio.productReport.issueActions   ( entries, issue, product )  render( { issue, product, reload } )
 * - catavio.productReport.panels         ( entries, product )         render( { product, reload } )
 * - catavio.settings.sections            ( entries )                  render()
 */

import { Component } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

/**
 * Read one extension filter.
 *
 * @param {string}  hookName Filter name.
 * @param {boolean} needs    Whether entries must carry a label.
 * @param {...*}    args     Extra arguments passed to the filter.
 * @return {Object[]} Valid entries, each key once.
 */
export function getExtensions( hookName, needs = false, ...args ) {
	let entries;

	try {
		entries = applyFilters( hookName, [], ...args );
	} catch ( error ) {
		return [];
	}

	if ( ! Array.isArray( entries ) ) {
		return [];
	}

	const seen = new Set();

	return entries.filter( ( entry ) => {
		const valid =
			entry &&
			typeof entry.key === 'string' &&
			entry.key !== '' &&
			typeof entry.render === 'function' &&
			( ! needs ||
				( typeof entry.label === 'string' && entry.label !== '' ) ) &&
			! seen.has( entry.key );

		if ( valid ) {
			seen.add( entry.key );
		}

		return valid;
	} );
}

/**
 * Contains a failing extension to its own slot.
 */
export class ExtensionBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { failed: false };
	}

	static getDerivedStateFromError() {
		return { failed: true };
	}

	render() {
		if ( this.state.failed ) {
			return (
				<p className="catavio-extension-error">
					{ __( 'This section could not be displayed.', 'catavio' ) }
				</p>
			);
		}

		return this.props.children;
	}
}

/**
 * Render one extension entry safely.
 *
 * @param {Object} props       Component props.
 * @param {Object} props.entry Extension entry.
 * @param {Object} props.args  Argument object handed to entry.render().
 */
export function ExtensionEntry( { entry, args } ) {
	return (
		<ExtensionBoundary>
			<RenderEntry entry={ entry } args={ args } />
		</ExtensionBoundary>
	);
}

// Calling render() inside a component of its own, rather than inline, is what
// lets the boundary above catch an error thrown by render() itself.
function RenderEntry( { entry, args } ) {
	return entry.render( args ) ?? null;
}
