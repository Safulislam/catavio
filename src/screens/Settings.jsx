/**
 * Settings screen: which checks run.
 *
 * Not every store uses every field WooCommerce offers. A shop that never
 * assigns SKUs should not see every product flagged for it. Turning a check off
 * leaves it out of future scans and out of every score; it never edits a
 * product, and it never starts a scan on its own.
 *
 * Plain checkboxes in a fieldset per topic, such as "Content" or "Images". The
 * topic is the fieldset's name; each check's severity, and whether it is an
 * optional check that starts turned off, is its description. Both are
 * announced with the choice rather than inferred from colour.
 *
 * Extensions add sections below the checks with the "catavio.settings.sections"
 * filter.
 */

import { speak } from '@wordpress/a11y';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

import { errorMessage, getSettings, saveSettings } from '../api';
import { ExtensionEntry, getExtensions } from '../extensions';

const SEVERITIES = [ 'critical', 'warning', 'info' ];

/**
 * Arrange checks into fieldsets.
 *
 * By topic when the server describes topics, otherwise by severity.
 *
 * @param {Object[]}      checks Checks as the server describes them.
 * @param {Object[]|null} groups Topics as the server describes them.
 * @return {Object[]} Fieldsets, each `{ key, legend, items }`.
 */
function arrange( checks, groups ) {
	if ( Array.isArray( groups ) && groups.length > 0 ) {
		return groups
			.map( ( group ) => ( {
				key: group.group,
				legend: group.label,
				items: checks.filter( ( item ) => item.group === group.group ),
			} ) )
			.filter( ( group ) => group.items.length > 0 );
	}

	return SEVERITIES.map( ( severity ) => {
		const items = checks.filter( ( item ) => item.severity === severity );

		return {
			key: severity,
			legend: items.length > 0 ? items[ 0 ].severityLabel : '',
			items,
		};
	} ).filter( ( group ) => group.items.length > 0 );
}

/**
 * The identifiers of the checks that are turned off.
 *
 * @param {Object[]} checks Checks as the server describes them.
 * @return {string[]} Turned-off identifiers.
 */
function disabledIn( checks ) {
	return checks
		.filter( ( check ) => ! check.enabled )
		.map( ( check ) => check.check );
}

export default function Settings( { settings } ) {
	const [ checks, setChecks ] = useState( null );
	const [ groups, setGroups ] = useState( null );
	const [ saved, setSaved ] = useState( [] );
	const [ loadError, setLoadError ] = useState( '' );
	const [ saveError, setSaveError ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ justSaved, setJustSaved ] = useState( false );

	useEffect( () => {
		let cancelled = false;

		getSettings()
			.then( ( data ) => {
				if ( ! cancelled ) {
					setChecks( data.checks );
					setGroups( data.groups || null );
					setSaved( disabledIn( data.checks ) );
				}
			} )
			.catch( ( thrown ) => {
				if ( ! cancelled ) {
					setLoadError(
						errorMessage(
							thrown,
							__( 'Settings could not be loaded.', 'catavio' )
						)
					);
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	if ( loadError ) {
		return (
			<Card className="catavio-card">
				<CardBody className="catavio-card__body">
					<p role="alert">{ loadError }</p>
				</CardBody>
			</Card>
		);
	}

	if ( ! checks ) {
		return (
			<Card className="catavio-card">
				<CardBody className="catavio-card__body">
					<Spinner />
					<span className="screen-reader-text">
						{ __( 'Loading settings.', 'catavio' ) }
					</span>
				</CardBody>
			</Card>
		);
	}

	const disabled = disabledIn( checks );
	const changed =
		[ ...disabled ].sort().join( ',' ) !== [ ...saved ].sort().join( ',' );
	const noneEnabled = disabled.length >= checks.length;

	const toggle = ( id, enabled ) => {
		setJustSaved( false );
		setSaveError( '' );
		setChecks( ( current ) =>
			current.map( ( check ) =>
				check.check === id ? { ...check, enabled } : check
			)
		);
	};

	const save = async ( event ) => {
		event.preventDefault();

		if ( saving || noneEnabled || ! changed ) {
			return;
		}

		setSaving( true );
		setSaveError( '' );

		try {
			const data = await saveSettings( disabled );

			setChecks( data.checks );
			setSaved( disabledIn( data.checks ) );
			setJustSaved( true );
			speak( __( 'Settings saved.', 'catavio' ), 'polite' );
		} catch ( thrown ) {
			setSaveError(
				errorMessage(
					thrown,
					__( 'Settings could not be saved.', 'catavio' )
				)
			);
		} finally {
			setSaving( false );
		}
	};

	const dashboardUrl =
		settings && settings.adminUrl && settings.page
			? addQueryArgs( settings.adminUrl, { page: settings.page } )
			: '';

	const fieldsets = arrange( checks, groups );
	const sections = getExtensions( 'catavio.settings.sections' );

	return (
		<>
			<form className="catavio-settings" onSubmit={ save }>
				<Card className="catavio-card">
					<CardHeader className="catavio-card__header">
						<h2 className="catavio-card__title">
							{ __( 'Checks', 'catavio' ) }
						</h2>
					</CardHeader>
					<CardBody className="catavio-card__body">
						<p className="catavio-card__note">
							{ __(
								'Turn off checks for information your store does not use. A turned-off check is left out of scans and never affects a score. Products are never changed.',
								'catavio'
							) }
						</p>

						{ fieldsets.map( ( fieldset ) => (
							<fieldset
								key={ fieldset.key }
								className={ `catavio-settings__group catavio-settings__group--${ fieldset.key }` }
							>
								<legend className="catavio-settings__legend">
									{ fieldset.legend }
								</legend>
								{ fieldset.items.map( ( check ) => {
									const id = `catavio-check-${ check.check }`;
									const about = `${ id }-about`;

									return (
										<p
											key={ check.check }
											className="catavio-settings__check"
										>
											<input
												type="checkbox"
												id={ id }
												aria-describedby={ about }
												checked={ check.enabled }
												onChange={ ( event ) =>
													toggle(
														check.check,
														event.target.checked
													)
												}
											/>
											<label htmlFor={ id }>
												{ check.label }
											</label>
											<span
												id={ about }
												className="catavio-settings__about"
											>
												<span
													className={ `catavio-settings__severity catavio-settings__severity--${ check.severity }` }
												>
													{ check.severityLabel }
												</span>
												{ check.optional ? (
													<span className="catavio-settings__optional">
														{ __(
															'Optional, off until turned on',
															'catavio'
														) }
													</span>
												) : null }
											</span>
										</p>
									);
								} ) }
							</fieldset>
						) ) }

						{ noneEnabled ? (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'Keep at least one check turned on.',
									'catavio'
								) }
							</Notice>
						) : null }

						{ saveError ? (
							<Notice status="error" isDismissible={ false }>
								{ saveError }
							</Notice>
						) : null }

						{ justSaved ? (
							<Notice status="success" isDismissible={ false }>
								{ __(
									'Settings saved. They apply from the next scan, or when a product is checked again.',
									'catavio'
								) }{ ' ' }
								{ dashboardUrl ? (
									<a href={ dashboardUrl }>
										{ __(
											'Go to the dashboard',
											'catavio'
										) }
									</a>
								) : null }
							</Notice>
						) : null }

						<div className="catavio-settings__actions">
							<Button
								variant="primary"
								type="submit"
								isBusy={ saving }
								// aria-disabled rather than disabled: the button is
								// still focused once a save succeeds, and disabling
								// it would drop focus to the page body. save()
								// ignores a press while there is nothing to save.
								aria-disabled={
									saving || noneEnabled || ! changed
								}
							>
								{ saving
									? __( 'Saving…', 'catavio' )
									: __( 'Save changes', 'catavio' ) }
							</Button>
						</div>
					</CardBody>
				</Card>
			</form>

			{ /* Outside the form: a section with its own fields or form must
			     never submit, or be submitted with, the checks above. */ }
			{ sections.map( ( section ) => (
				<ExtensionEntry
					key={ section.key }
					entry={ section }
					args={ {} }
				/>
			) ) }
		</>
	);
}
