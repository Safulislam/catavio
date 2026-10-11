/**
 * Loads one page of the product health list.
 *
 * The query lives in the page's own address bar rather than in component state,
 * so a filtered view can be linked to, bookmarked, and reached with the browser
 * back button. That is also why there is no router: the admin URL already is the
 * state.
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { errorMessage, getProducts } from '../api';

export default function useProducts( query ) {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	// Guards against an older request resolving after a newer one and
	// overwriting the results the user is actually looking at.
	const requestId = useRef( 0 );

	const key = JSON.stringify( query );

	const load = useCallback( async () => {
		requestId.current += 1;
		const current = requestId.current;

		setLoading( true );

		try {
			const result = await getProducts( JSON.parse( key ) );

			if ( current !== requestId.current ) {
				return;
			}

			setData( result );
			setError( '' );
		} catch ( thrown ) {
			if ( current !== requestId.current ) {
				return;
			}

			setError(
				errorMessage(
					thrown,
					__(
						'The product list could not be loaded.',
						'catavio'
					)
				)
			);
		} finally {
			if ( current === requestId.current ) {
				setLoading( false );
			}
		}
	}, [ key ] );

	useEffect( () => {
		load();
	}, [ load ] );

	return { data, loading, error, reload: load };
}
