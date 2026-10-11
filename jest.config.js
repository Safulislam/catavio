/**
 * Jest configuration for the admin UI tests.
 *
 * Extends the WordPress preset, which supplies the jsdom environment, the Babel
 * transform for JSX and the @wordpress/* module mapping.
 */

module.exports = {
	...require( '@wordpress/scripts/config/jest-unit.config.js' ),
	testEnvironment: 'jsdom',
	setupFilesAfterEnv: [ '<rootDir>/tests/js/setup.js' ],
	testMatch: [ '<rootDir>/tests/js/**/*.test.js' ],
};
