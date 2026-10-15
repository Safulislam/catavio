/**
 * Fold stray Jed JSON translation files into the bundle's file.
 *
 * `wp i18n make-json` names each JSON file after the first source reference of
 * its strings. Strings that live in src/ but whose first reference happens to
 * be a src file, not the compiled bundle, end up in files named after src/
 * paths. WordPress never looks for those: at runtime it hashes the script path
 * relative to the plugin folder, which is always build/index.js. Anything not
 * in the build/index.js file is silently untranslated.
 *
 * This script merges every catavio-<locale>-*.json whose `source` points into
 * src/ into the file whose `source` is build/index.js, then removes the stray
 * files. Run it after `wp i18n make-json`.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const languagesDir = path.join( __dirname, '..', 'languages' );

const isCatavioJson = ( name ) =>
	/^catavio-.+\.json$/.test( name );

const jsonFiles = fs
	.readdirSync( languagesDir )
	.filter( isCatavioJson );

const read = ( name ) =>
	JSON.parse(
		fs.readFileSync( path.join( languagesDir, name ), 'utf8' )
	);

const targets = jsonFiles.filter( ( name ) => {
	const json = read( name );
	return json.source === 'build/index.js';
} );

if ( targets.length !== 1 ) {
	process.stderr.write(
		`Expected exactly one build/index.js translation file, found ${ targets.length }.\n` +
			'Rebuild the language files with: npm run i18n\n'
	);
	process.exit( 1 );
}

const targetName = targets[ 0 ];
const target = read( targetName );
const targetEntries = target.locale_data[ target.domain ];

let merged = 0;

for ( const name of jsonFiles ) {
	if ( name === targetName ) {
		continue;
	}

	const json = read( name );

	if ( ! json.source || ! json.source.startsWith( 'src/' ) ) {
		continue;
	}

	const entries = json.locale_data[ json.domain ];

	for ( const [ msgid, entry ] of Object.entries( entries ) ) {
		if ( msgid === '' ) {
			continue; // The domain header, not a translation.
		}

		if ( targetEntries[ msgid ] ) {
			process.stderr.write(
				`"${ msgid }" exists in both ${ targetName } and ${ name }; keeping the bundle copy.\n`
			);
			continue;
		}

		targetEntries[ msgid ] = entry;
		merged++;
	}

	fs.unlinkSync( path.join( languagesDir, name ) );
}

fs.writeFileSync(
	path.join( languagesDir, targetName ),
	JSON.stringify( target, null, '\t' ) + '\n'
);

process.stdout.write(
	`Merged ${ merged } string(s) into ${ targetName }.\n`
);
