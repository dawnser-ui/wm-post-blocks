<?php
/**
 * Plugin Name:       WM Posts Blocks
 * Description:        Two Gutenberg blocks — a dynamic Posts Grid and a Posts Filter — that stay in sync across the page via the WordPress Interactivity API. Seeds all demo content (custom post type, taxonomies, posts, featured images and a demo page) automatically on activation.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Shachar Srebrenik
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wm-posts-blocks
 *
 * @package WMPB
 */

defined( 'ABSPATH' ) || exit;

/*
 * ---------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------------
 * A single, unique prefix (WMPB = "WM Posts Blocks") is used for every public
 * identifier the plugin introduces: PHP constants, the custom post type, the
 * taxonomies, option keys, the block namespace and the REST namespace. This
 * guarantees the plugin can never collide with core or another plugin.
 */
define( 'WMPB_VERSION', '1.0.0' );
define( 'WMPB_FILE', __FILE__ );
define( 'WMPB_DIR', plugin_dir_path( __FILE__ ) );
define( 'WMPB_URL', plugin_dir_url( __FILE__ ) );

/*
 * ---------------------------------------------------------------------------
 * Manual class loading
 * ---------------------------------------------------------------------------
 * The plugin is small and dependency-free, so a hand-written require list is
 * clearer (and easier to explain) than pulling in Composer. Each class lives
 * in its own file and owns exactly one responsibility.
 */
require_once WMPB_DIR . 'includes/class-content-model.php';
require_once WMPB_DIR . 'includes/class-settings.php';
require_once WMPB_DIR . 'includes/class-posts-query.php';
require_once WMPB_DIR . 'includes/class-rest-controller.php';
require_once WMPB_DIR . 'includes/class-blocks.php';
require_once WMPB_DIR . 'includes/class-seeder.php';
require_once WMPB_DIR . 'includes/class-plugin.php';

/*
 * ---------------------------------------------------------------------------
 * Lifecycle hooks
 * ---------------------------------------------------------------------------
 * Activation seeds the demo environment; deactivation only cleans up rewrite
 * rules (it intentionally keeps the seeded content so re-activating is cheap).
 * Full teardown lives in uninstall.php and runs only when the plugin is deleted.
 */
register_activation_hook( __FILE__, array( 'WMPB\\Seeder', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WMPB\\Seeder', 'deactivate' ) );

/*
 * Boot the plugin once all other plugins are loaded. Plugin::init() is the
 * single place that wires every class to its WordPress hooks.
 */
add_action( 'plugins_loaded', array( 'WMPB\\Plugin', 'init' ) );
