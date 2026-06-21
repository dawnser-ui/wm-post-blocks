<?php
/**
 * Uninstall routine — runs only when the plugin is deleted from wp-admin.
 *
 * Removes every artefact the plugin created (posts, attachments, terms, the demo
 * page and its options) so deleting the plugin leaves the site exactly as it was.
 *
 * @package WMPB
 */

// If uninstall is not called from WordPress, bail.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

const WMPB_POST_TYPE    = 'wmpb_post';
const WMPB_TAX_CATEGORY = 'wmpb_category';
const WMPB_TAX_TAG      = 'wmpb_tag';

/*
 * 1. Delete every demo post together with its attached featured images.
 */
$wmpb_posts = get_posts(
	array(
		'post_type'      => WMPB_POST_TYPE,
		'post_status'    => 'any',
		'numberposts'    => -1,
		'fields'         => 'ids',
		'suppress_filters' => true,
	)
);

foreach ( $wmpb_posts as $wmpb_post_id ) {
	// Remove child attachments first.
	$attachments = get_children(
		array(
			'post_parent' => $wmpb_post_id,
			'post_type'   => 'attachment',
			'fields'      => 'ids',
		)
	);
	foreach ( $attachments as $attachment_id ) {
		wp_delete_attachment( $attachment_id, true );
	}

	wp_delete_post( $wmpb_post_id, true );
}

/*
 * 2. Delete the taxonomy terms.
 */
foreach ( array( WMPB_TAX_CATEGORY, WMPB_TAX_TAG ) as $wmpb_taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $wmpb_taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( is_array( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( $term_id, $wmpb_taxonomy );
		}
	}
}

/*
 * 3. Delete the demo page and the plugin's options.
 */
$demo_page_id = (int) get_option( 'wmpb_demo_page_id' );
if ( $demo_page_id ) {
	wp_delete_post( $demo_page_id, true );
}

delete_option( 'wmpb_demo_page_id' );
delete_option( 'wmpb_seeded' );
