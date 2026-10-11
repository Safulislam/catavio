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

import SeverityTag from './SeverityTag';

export default function IssueList( { issues } ) {
	if ( issues.length === 0 ) {
		return (
			<p className="catavio-detail__none">
				{ __(
					'No issues were found for this product.',
					'catavio'
				) }
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
							</li>
						) ) }
					</ul>
				</section>
			) ) }
		</>
	);
}
