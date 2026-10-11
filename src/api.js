/**
 * REST wrappers.
 *
 * apiFetch is configured by WordPress core: it already knows the REST root and
 * attaches the X-WP-Nonce header, which is why no nonce is handled here. The
 * root it is given is correct whether the site uses pretty permalinks or the
 * ?rest_route= form.
 */

import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const NAMESPACE = '/catavio/v1';

export function getSummary() {
	return apiFetch( { path: `${ NAMESPACE }/summary` } );
}

export function getProducts( query ) {
	return apiFetch( {
		path: addQueryArgs( `${ NAMESPACE }/products`, query ),
	} );
}

export function getProduct( id ) {
	return apiFetch( { path: `${ NAMESPACE }/products/${ id }` } );
}

export function startScan() {
	return apiFetch( { path: `${ NAMESPACE }/scan`, method: 'POST' } );
}

export function runScanBatch() {
	return apiFetch( { path: `${ NAMESPACE }/scan/batch`, method: 'POST' } );
}

export function getScanStatus() {
	return apiFetch( { path: `${ NAMESPACE }/scan` } );
}

export function cancelScan() {
	return apiFetch( { path: `${ NAMESPACE }/scan`, method: 'DELETE' } );
}

/**
 * Turn any thrown value into a message worth showing a person.
 *
 * @param {unknown} error    Thrown value.
 * @param {string}  fallback Message to use when nothing better is available.
 * @return {string} A human readable message.
 */
export function errorMessage( error, fallback ) {
	if (
		error &&
		typeof error === 'object' &&
		typeof error.message === 'string'
	) {
		return error.message;
	}

	return fallback;
}
