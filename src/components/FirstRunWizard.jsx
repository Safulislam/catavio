import { Button, Card, CardBody } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import ScanPanel from './ScanPanel';

/**
 * A small, optional introduction shown while a catalog has no scan results.
 *
 * @param {Object} props Wizard actions and current scan state.
 * @return {JSX.Element} The first-run guide.
 */
export default function FirstRunWizard( props ) {
	const [ step, setStep ] = useState( 0 );
	const {
		scan,
		running,
		interrupted,
		error,
		onRun,
		onResume,
		onStop,
		onDismiss,
	} = props;

	return (
		<Card className="catavio-card catavio-welcome">
			<CardBody className="catavio-card__body">
				<div className="catavio-welcome__layout">
					<div className="catavio-welcome__art" aria-hidden="true">
						<span className="catavio-welcome__orbit catavio-welcome__orbit--one" />
						<span className="catavio-welcome__orbit catavio-welcome__orbit--two" />
						<span className="catavio-welcome__spark catavio-welcome__spark--one" />
						<span className="catavio-welcome__spark catavio-welcome__spark--two" />
						<span className="catavio-welcome__lens" />
					</div>
					<div className="catavio-welcome__content">
						<p className="catavio-welcome__eyebrow">
							{ __( 'A clear view of your catalog', 'catavio' ) }
						</p>
						<h2 className="catavio-welcome__title">
							{ 0 === step
								? __( 'Let’s take a first look.', 'catavio' )
								: __( 'Your first catalog check', 'catavio' ) }
						</h2>
						<p className="catavio-welcome__description">
							{ 0 === step
								? __(
										'Catavio checks product details for common gaps and saves health findings for a report. It won’t edit product titles, descriptions, prices, or other storefront content.',
										'catavio'
								  )
								: __(
										'This check reads your product details and records findings for the dashboard. It won’t edit the content shoppers see.',
										'catavio'
								  ) }
						</p>
						<ol
							className="catavio-welcome__steps"
							aria-label={ __( 'Setup steps', 'catavio' ) }
						>
							<li
								className={
									0 === step ? 'is-current' : 'is-complete'
								}
							>
								<span>1</span>
								{ __( 'Review', 'catavio' ) }
							</li>
							<li className={ 1 === step ? 'is-current' : '' }>
								<span>2</span>
								{ __( 'Choose to scan', 'catavio' ) }
							</li>
						</ol>
						{ 0 === step ? (
							<div className="catavio-welcome__actions">
								<Button
									variant="primary"
									onClick={ () => setStep( 1 ) }
								>
									{ __( 'Continue', 'catavio' ) }
								</Button>
								<Button
									variant="tertiary"
									onClick={ onDismiss }
								>
									{ __( 'Hide this guide', 'catavio' ) }
								</Button>
							</div>
						) : (
							<div className="catavio-welcome__scan">
								<ScanPanel
									scan={ scan }
									running={ running }
									interrupted={ interrupted }
									error={ error }
									onRun={ onRun }
									onResume={ onResume }
									onStop={ onStop }
								/>
								<Button
									variant="tertiary"
									onClick={ () => setStep( 0 ) }
								>
									{ __( 'Back', 'catavio' ) }
								</Button>
							</div>
						) }
					</div>
				</div>
			</CardBody>
		</Card>
	);
}
