/**
 * Empty state message.
 *
 * A heading plus one plain sentence, optionally with a single action. Deliberately
 * plain: an empty catalog is a normal situation, not an error to dress up.
 */

export default function EmptyState( { title, description, children } ) {
	return (
		<div className="catavio-empty">
			<h3 className="catavio-empty__title">{ title }</h3>
			<p className="catavio-empty__description">{ description }</p>
			{ children }
		</div>
	);
}
