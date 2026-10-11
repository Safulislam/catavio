/**
 * Product Health screen.
 *
 * Chooses between the list and one product's report based on the "product"
 * query argument. The detail view is a view of this screen rather than a
 * separate admin page, which is what keeps the WordPress menu highlighting
 * correct while reading a report.
 */

import { getQueryArgs } from '@wordpress/url';

import ProductDetail from './ProductDetail';
import ProductList from './ProductList';

export default function Products( { settings } ) {
	const args = getQueryArgs( window.location.href );
	const productId = parseInt( args.product, 10 );

	if ( productId > 0 ) {
		return <ProductDetail productId={ productId } />;
	}

	return <ProductList settings={ settings } />;
}
