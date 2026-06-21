<?php
/**
 * Block registration.
 *
 * @package WMPB
 */

namespace WMPB;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the three blocks from their compiled metadata.
 *
 * Each block ships a `block.json` (the canonical, modern way to define a block).
 * `register_block_type()` reads that file and automatically enqueues the right
 * editor script, view module, styles and server `render.php` callback. The PHP
 * here therefore stays tiny — the contract lives in each block.json.
 */
final class Blocks {

	/**
	 * Register every compiled block found in /build.
	 *
	 * @return void
	 */
	public static function register() {
		$blocks = array( 'posts-grid', 'posts-grid-pagination', 'posts-filter' );

		foreach ( $blocks as $block ) {
			$metadata = WMPB_DIR . 'build/' . $block;

			// Only register what the build step has actually produced, so a
			// missing `npm run build` fails loudly in the admin rather than
			// silently breaking the front end.
			if ( file_exists( $metadata . '/block.json' ) ) {
				register_block_type( $metadata );
			}
		}
	}
}
