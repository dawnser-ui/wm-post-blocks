<?php
/**
 * Plugin bootstrapper.
 *
 * @package WMPB
 */

namespace WMPB;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every component of the plugin to its WordPress hooks.
 *
 * This class is deliberately thin: it does not contain business logic, it only
 * decides *when* each collaborator runs. Keeping the wiring in one place makes
 * the plugin's startup sequence easy to read top-to-bottom.
 */
final class Plugin {

	/**
	 * Register all hooks for the plugin's runtime (non-activation) behaviour.
	 *
	 * @return void
	 */
	public static function init() {
		// Register the custom post type + taxonomies on `init`.
		add_action( 'init', array( Content_Model::class, 'register' ) );

		// Register the three blocks on `init` (after their post type exists).
		add_action( 'init', array( Blocks::class, 'register' ) );

		// Expose the custom REST endpoint used by the front-end filter/grid.
		add_action( 'rest_api_init', array( REST_Controller::class, 'register_routes' ) );

		// Register the admin settings page (Settings → WM Posts Blocks).
		Settings::register_hooks();

		// Load translations.
		add_action( 'init', array( self::class, 'load_textdomain' ) );
	}

	/**
	 * Load the plugin text domain so all strings are translatable.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'wm-posts-blocks', false, dirname( plugin_basename( WMPB_FILE ) ) . '/languages' );
	}
}
