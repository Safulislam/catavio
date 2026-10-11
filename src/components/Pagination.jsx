/**
 * List pagination.
 *
 * Buttons rather than links, because moving between pages updates the query in
 * place. Each control carries its own accessible name, and the current position
 * is stated in text so it is not conveyed by the disabled state alone.
 */

import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

export default function Pagination( { page, pages, total, onChange } ) {
	if ( pages <= 1 ) {
		return null;
	}

	return (
		<nav
			className="catavio-pagination"
			aria-label={ __( 'Product list pages', 'catavio' ) }
		>
			<span className="catavio-pagination__count">
				{ sprintf(
					/* translators: %d: total number of products found. */
					__( '%d products', 'catavio' ),
					total
				) }
			</span>
			<Button
				variant="secondary"
				disabled={ page <= 1 }
				onClick={ () => onChange( page - 1 ) }
			>
				{ __( 'Previous page', 'catavio' ) }
			</Button>
			<span className="catavio-pagination__position">
				{ sprintf(
					/* translators: 1: current page number, 2: total number of pages. */
					__( 'Page %1$d of %2$d', 'catavio' ),
					page,
					pages
				) }
			</span>
			<Button
				variant="secondary"
				disabled={ page >= pages }
				onClick={ () => onChange( page + 1 ) }
			>
				{ __( 'Next page', 'catavio' ) }
			</Button>
		</nav>
	);
}
