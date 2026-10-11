/**
 * Confirms the accessibility tooling actually detects problems.
 *
 * A checker that silently passes everything is worse than no checker, so this
 * asserts that axe fails on markup that is genuinely inaccessible.
 */

import { render } from '@testing-library/react';
import { axe } from 'jest-axe';

const AXE_CONFIG = {
	rules: {
		region: { enabled: false },
		'page-has-heading-one': { enabled: false },
	},
};

describe( 'axe sanity', () => {
	it( 'fails an input with no label', async () => {
		const { container } = render(
			<div>
				<input type="text" name="unlabelled" />
			</div>
		);

		const results = await axe( container, AXE_CONFIG );

		expect( results.violations.length ).toBeGreaterThan( 0 );
		expect( results.violations.map( ( v ) => v.id ) ).toContain( 'label' );
	} );

	it( 'fails a button with no accessible name', async () => {
		const { container } = render(
			<div>
				<button type="button" />
			</div>
		);

		const results = await axe( container, AXE_CONFIG );

		expect( results.violations.map( ( v ) => v.id ) ).toContain(
			'button-name'
		);
	} );

	it( 'fails an image with no alt text', async () => {
		const { container } = render(
			<div>
				{ /* eslint-disable-next-line jsx-a11y/alt-text -- Deliberately broken. */ }
				<img src="x.png" />
			</div>
		);

		const results = await axe( container, AXE_CONFIG );

		expect( results.violations.map( ( v ) => v.id ) ).toContain(
			'image-alt'
		);
	} );
} );
