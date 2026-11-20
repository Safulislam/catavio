/**
 * Issues for one product, grouped by severity.
 *
 * Each group is a real heading with its own list, so the structure is
 * navigable by heading and the severity is announced as part of the group
 * rather than inferred from the colour of a row.
 *
 * Both lists carry an explicit role because their markers are removed in CSS,
 * and Safari stops treating such a list as a list.
 */

import { __ } from '@wordpress/i18n';

import { ExtensionEntry, getExtensions } from '../extensions';
import SeverityTag from './SeverityTag';

/**
 * Actions extensions offer for one issue, such as a fix.
 *
 * @param {Object}   props         Component props.
 * @param {Object}   props.issue   The issue.
 * @param {Object}   props.product The product report.
 * @param {Function} props.reload  Reloads the report.
 */
function IssueActions( { issue, product, reload } ) {
	const actions = getExtensions(
		'catavio.productReport.issueActions',
		false,
		issue,
		product
	);

	if ( actions.length === 0 ) {
		return null;
	}

	return (
		<span className="catavio-detail__issue-actions">
			{ actions.map( ( action ) => (
				<ExtensionEntry
					key={ action.key }
					entry={ action }
					args={ { issue, product, reload } }
				/>
			) ) }
		</span>
	);
}

export default function IssueList( { issues, product = null, reload } ) {
	if ( issues.length === 0 ) {
		return (
			<p className="catavio-detail__none">
				{ __( 'No issues were found for this product.', 'catavio' ) }
			</p>
		);
	}

	// Severity order is fixed, worst first, so the same product always reads
	// the same way.
	const groups = [ 'critical', 'warning', 'info' ]
		.map( ( severity ) => ( {
			severity,
			items: issues.filter( ( issue ) => issue.severity === severity ),
		} ) )
		.filter( ( group ) => group.items.length > 0 );

	return (
		<>
			{ groups.map( ( group ) => (
				<section
					key={ group.severity }
					className={ `catavio-detail__group catavio-detail__group--${ group.severity }` }
				>
					<h4 className="catavio-detail__group-title">
						<SeverityTag
							severity={ group.severity }
							label={ group.items[ 0 ].severityLabel }
						/>
					</h4>
					{ /* eslint-disable-next-line jsx-a11y/no-redundant-roles -- Redundant by the specification, but not in practice: Safari drops the list role from a list whose markers are removed in CSS, which is what these lists do. */ }
					<ul role="list" className="catavio-detail__issues">
						{ group.items.map( ( issue ) => (
							<li
								key={ issue.check }
								className="catavio-detail__issue"
							>
								<span className="catavio-detail__issue-label">
									{ issue.label }
								</span>
								<span className="catavio-detail__issue-message">
									{ issue.message }
								</span>
								{ product ? (
									<IssueActions
										issue={ issue }
										product={ product }
										reload={ reload }
									/>
								) : null }
							</li>
						) ) }
					</ul>
				</section>
			) ) }
		</>
	);
}
