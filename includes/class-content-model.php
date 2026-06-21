<?php
/**
 * Custom content model: the demo post type and its two taxonomies.
 *
 * @package WMPB
 */

namespace WMPB;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a self-contained content model for the demo.
 *
 * Why a custom post type instead of core "post"?
 * The brief asks for a unique prefix on the post type and taxonomy names, and
 * seeding ten posts into the site's real blog would pollute it. A dedicated
 * `wmpb_post` type with its own `wmpb_category` / `wmpb_tag` taxonomies keeps
 * all demo data isolated, namespaced and trivially removable on uninstall.
 */
final class Content_Model {

	/** Custom post type slug. */
	const POST_TYPE = 'wmpb_post';

	/** Category-like (hierarchical) taxonomy slug. */
	const TAX_CATEGORY = 'wmpb_category';

	/** Tag-like (flat) taxonomy slug. */
	const TAX_TAG = 'wmpb_tag';

	/**
	 * Register the post type and taxonomies.
	 *
	 * Runs on every `init` so the content model exists for the editor, the REST
	 * API and the front end alike — not only during activation.
	 *
	 * @return void
	 */
	public static function register() {
		self::register_taxonomies();
		self::register_post_type();
	}

	/**
	 * Register the hierarchical (category) and flat (tag) taxonomies.
	 *
	 * `show_in_rest` is required so the block editor and our front-end scripts
	 * can read the terms.
	 *
	 * @return void
	 */
	private static function register_taxonomies() {
		register_taxonomy(
			self::TAX_CATEGORY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Demo Categories', 'wm-posts-blocks' ),
					'singular_name' => __( 'Demo Category', 'wm-posts-blocks' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
			)
		);

		register_taxonomy(
			self::TAX_TAG,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Demo Tags', 'wm-posts-blocks' ),
					'singular_name' => __( 'Demo Tag', 'wm-posts-blocks' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_in_rest'      => true,
				'show_admin_column' => true,
			)
		);
	}

	/**
	 * Register the demo post type.
	 *
	 * `public` is true so each card can link to a real single view, and
	 * `show_in_rest` enables Gutenberg editing plus the core entity store the
	 * editor previews rely on.
	 *
	 * @return void
	 */
	private static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Demo Posts', 'wm-posts-blocks' ),
					'singular_name' => __( 'Demo Post', 'wm-posts-blocks' ),
				),
				'public'       => true,
				'has_archive'  => false,
				'menu_icon'    => 'dashicons-grid-view',
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'taxonomies'   => array( self::TAX_CATEGORY, self::TAX_TAG ),
			)
		);
	}
}
