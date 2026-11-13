/**
 * Product list filters.
 *
 * Plain labelled form controls rather than custom widgets: they are reachable
 * by keyboard, announced correctly, and familiar from the rest of wp-admin.
 * Every control is a label stacked over its input, search included, so the row
 * lines up on one baseline instead of the search sitting half a line low.
 * Changing any control resets to page one, because staying on page nine of a
 * result set that no longer has nine pages is never what anyone wants.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

function Select( { id, label, value, options, onChange, anyLabel } ) {
	return (
		<p className="catavio-filters__field">
			<label className="catavio-filters__label" htmlFor={ id }>
				{ label }
			</label>
			<select
				id={ id }
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			>
				<option value="">{ anyLabel }</option>
				{ options.map( ( option ) => (
					<option key={ option.value } value={ option.value }>
						{ option.label }
					</option>
				) ) }
			</select>
		</p>
	);
}

/**
 * The orderings the list offers, as orderby and order pairs.
 *
 * Every pair is one the server's REST schema already accepts; the labels are
 * the only thing defined here.
 *
 * @return {Object[]} Sort options, the default first.
 */
export function sortOptions() {
	return [
		{
			value: 'score:asc',
			label: __( 'Lowest score first', 'catavio' ),
		},
		{
			value: 'score:desc',
			label: __( 'Highest score first', 'catavio' ),
		},
		{ value: 'title:asc', label: __( 'Name, A to Z', 'catavio' ) },
		{ value: 'title:desc', label: __( 'Name, Z to A', 'catavio' ) },
		{
			value: 'checked:desc',
			label: __( 'Most recently checked', 'catavio' ),
		},
		{
			value: 'checked:asc',
			label: __( 'Least recently checked', 'catavio' ),
		},
	];
}

export default function Filters( { query, filters, onChange, onSearch } ) {
	const hasFilters =
		!! query.search || !! query.health || !! query.issue || !! query.type;

	return (
		<div className="catavio-filters">
			<form
				className="catavio-filters__field catavio-filters__search"
				onSubmit={ ( event ) => {
					event.preventDefault();
					onSearch( event.target.elements[ 'catavio-search' ].value );
				} }
			>
				<label
					className="catavio-filters__label"
					htmlFor="catavio-search"
				>
					{ __( 'Search products', 'catavio' ) }
				</label>
				<span className="catavio-filters__control">
					<input
						type="search"
						id="catavio-search"
						name="catavio-search"
						defaultValue={ query.search }
						key={ query.search }
					/>
					<Button variant="secondary" type="submit">
						{ __( 'Search', 'catavio' ) }
					</Button>
				</span>
			</form>

			<Select
				id="catavio-filter-health"
				label={ __( 'Health', 'catavio' ) }
				anyLabel={ __( 'Any health', 'catavio' ) }
				value={ query.health }
				options={ filters.health }
				onChange={ ( value ) => onChange( { health: value } ) }
			/>

			<Select
				id="catavio-filter-issue"
				label={ __( 'Issue', 'catavio' ) }
				anyLabel={ __( 'Any issue', 'catavio' ) }
				value={ query.issue }
				options={ filters.issues }
				onChange={ ( value ) => onChange( { issue: value } ) }
			/>

			<Select
				id="catavio-filter-type"
				label={ __( 'Product type', 'catavio' ) }
				anyLabel={ __( 'Any type', 'catavio' ) }
				value={ query.type }
				options={ filters.types }
				onChange={ ( value ) => onChange( { type: value } ) }
			/>

			<p className="catavio-filters__field">
				<label
					className="catavio-filters__label"
					htmlFor="catavio-sort"
				>
					{ __( 'Sort by', 'catavio' ) }
				</label>
				<select
					id="catavio-sort"
					value={ `${ query.orderby }:${ query.order }` }
					onChange={ ( event ) => {
						const [ orderby, order ] =
							event.target.value.split( ':' );
						onChange( { orderby, order } );
					} }
				>
					{ sortOptions().map( ( option ) => (
						<option key={ option.value } value={ option.value }>
							{ option.label }
						</option>
					) ) }
				</select>
			</p>

			{ hasFilters ? (
				<span className="catavio-filters__clear">
					<Button
						variant="link"
						onClick={ () =>
							onChange( {
								search: '',
								health: '',
								issue: '',
								type: '',
							} )
						}
					>
						{ __( 'Clear filters', 'catavio' ) }
					</Button>
				</span>
			) : null }
		</div>
	);
}
