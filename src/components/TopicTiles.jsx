/**
 * Health by topic.
 *
 * One tile per topic (Content, Images, Price and stock, …): the share of
 * scanned products with no issue in that topic, how many products have one,
 * and the most common issue, linked to the product list filtered to it.
 *
 * Tiles come from the server, one per topic with at least one check turned
 * on. A plugin that registers its own topic and checks (catavio_check_groups,
 * catavio_checks) gets a tile here like any of Catavio's own, with no
 * JavaScript of its own.
 */

import { __, _n, _x, sprintf } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import HealthMeter from './HealthMeter';

/**
 * Colour of a tile: green when the topic has no issue, otherwise the colour
 * of its most common issue's severity, the same colours severities have on
 * every other screen. Never chosen from the share alone: a topic whose only
 * issue is informational is not shown as an emergency.
 */
const SEVERITY_TONE = {
	critical: 'critical',
	warning: 'attention',
	info: 'info',
};

/**
 * One topic.
 *
 * @param {Object} props             Component props.
 * @param {Object} props.topic       Topic as the server describes it.
 * @param {string} props.productsUrl Product list URL, or empty.
 */
function TopicTile( { topic, productsUrl } ) {
	const top = topic.topIssue;
	let tone = 'healthy';

	if ( topic.affected > 0 ) {
		tone = ( top && SEVERITY_TONE[ top.severity ] ) || 'info';
	}

	return (
		<li className={ `catavio-topic catavio-topic--${ tone }` }>
			<h3 className="catavio-topic__title">{ topic.label }</h3>

			<p className="catavio-topic__figure">
				<span className="catavio-topic__value">
					{ sprintf(
						/* translators: %d: share of scanned products with no issue in this topic, as a percentage. */
						_x(
							'%d%%',
							'share of products with no issue in a topic',
							'catavio'
						),
						topic.complete
					) }
				</span>
				<span className="catavio-topic__unit">
					{ _x( 'complete', 'topic share', 'catavio' ) }
				</span>
			</p>

			<HealthMeter
				value={ topic.complete }
				tone={ 'info' === tone ? undefined : tone }
			/>

			<p className="catavio-topic__count">
				{ topic.affected === 0
					? __( 'No issues', 'catavio' )
					: sprintf(
							/* translators: %d: number of products with at least one issue in this topic. */
							_n(
								'%d product with issues',
								'%d products with issues',
								topic.affected,
								'catavio'
							),
							topic.affected
					  ) }
			</p>

			{ top && topic.affected > 0 ? (
				<p className="catavio-topic__top">
					<span className="catavio-topic__top-label">
						{ __( 'Most common', 'catavio' ) }
					</span>
					{ productsUrl ? (
						<a
							href={ addQueryArgs( productsUrl, {
								issue: top.check,
							} ) }
						>
							{ top.label }
						</a>
					) : (
						<span>{ top.label }</span>
					) }
				</p>
			) : null }

			<p className="catavio-topic__checks">
				{ sprintf(
					/* translators: %d: number of checks turned on in this topic. */
					_n( '%d check', '%d checks', topic.checks, 'catavio' ),
					topic.checks
				) }
			</p>
		</li>
	);
}

/**
 * @param {Object}   props             Component props.
 * @param {Object[]} props.topics      Topics as the server describes them.
 * @param {boolean}  props.recorded    Whether the last scan recorded topics.
 * @param {string}   props.productsUrl Product list URL, or empty.
 */
export default function TopicTiles( { topics, recorded, productsUrl } ) {
	return (
		<section
			className="catavio-topics"
			aria-labelledby="catavio-topics-title"
		>
			<div className="catavio-topics__header">
				<h2 id="catavio-topics-title" className="catavio-topics__title">
					{ __( 'By topic', 'catavio' ) }
				</h2>
				<p className="catavio-topics__note">
					{ __(
						'The share of scanned products with no issue in each topic.',
						'catavio'
					) }
				</p>
			</div>

			{ recorded ? (
				/* eslint-disable-next-line jsx-a11y/no-redundant-roles -- Safari drops the list role when markers are removed in CSS. */
				<ul role="list" className="catavio-topics__grid">
					{ topics.map( ( topic ) => (
						<TopicTile
							key={ topic.group }
							topic={ topic }
							productsUrl={ productsUrl }
						/>
					) ) }
				</ul>
			) : (
				<p className="catavio-topics__pending">
					{ __(
						'Figures by topic appear after the next scan.',
						'catavio'
					) }
				</p>
			) }
		</section>
	);
}
