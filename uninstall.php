<?php
/**
 * Uninstall routine.
 *
 * Runs when the plugin is deleted from the Plugins screen, with the plugin
 * itself not loaded: WordPress includes this file directly and nothing else.
 * That is why the keys below are written out as literals rather than read from
 * the classes that own them — requiring the autoloader here would make deleting
 * the plugin depend on a vendor directory that may already be gone.
 *
 * The literals are covered by UninstallTest, which fails if a class constant
 * changes without this file changing with it.
 *
 * Deactivation clears only the running job. This removes everything: the
 * options, and the per-product results stored as post meta.
 *
 * @package Catavio
 */

declare( strict_types = 1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Options written by the plugin.
 *
 * Mirrors Lifecycle::VERSION_OPTION, Summary::OPTION and ScanState::OPTION.
 *
 * @return string[]
 */
function catavio_uninstall_option_names() {
	return array(
		'catavio_version',
		'catavio_summary',
		'catavio_scan_state',
	);
}

/**
 * Post meta keys written by the plugin.
 *
 * Mirrors the Repository::META_* constants.
 *
 * @return string[]
 */
function catavio_uninstall_meta_keys() {
	return array(
		'_catavio_issues',
		'_catavio_passed',
		'_catavio_issue_code',
		'_catavio_score',
		'_catavio_severity',
		'_catavio_scanned_at',
	);
}

/**
 * Remove every trace of the plugin from the current site.
 *
 * Clearing a meta key across all posts is done in one statement per key, which
 * matters on a large catalog: walking products one at a time would be thousands
 * of queries for the same result.
 *
 * @return void
 */
function catavio_uninstall_site() {
	foreach ( catavio_uninstall_option_names() as $option ) {
		delete_option( $option );
	}

	foreach ( catavio_uninstall_meta_keys() as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}
}

if ( ! is_multisite() ) {
	catavio_uninstall_site();

	return;
}

/*
 * On a network the plugin's data is per site, so every site has to be visited.
 * Sites are fetched in pages rather than all at once: a large network would
 * otherwise load every site object into memory for a routine that only needs
 * one ID at a time.
 */
$catavio_offset = 0;
$catavio_limit  = 100;

do {
	$catavio_site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => $catavio_limit,
			'offset'                 => $catavio_offset,
			'update_site_meta_cache' => false,
			'update_site_cache'      => false,
		)
	);

	$catavio_found = count( $catavio_site_ids );

	foreach ( $catavio_site_ids as $catavio_site_id ) {
		switch_to_blog( (int) $catavio_site_id );
		catavio_uninstall_site();
		restore_current_blog();
	}

	$catavio_offset += $catavio_limit;
} while ( $catavio_found === $catavio_limit );
