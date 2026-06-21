<?php
/**
 * Demo content seeder — runs on plugin activation.
 *
 * @package WMPB
 */

namespace WMPB;

defined( 'ABSPATH' ) || exit;

/**
 * Creates everything the assessment needs to be usable immediately after
 * activation: taxonomy terms, 12 posts (each with a featured image, excerpt and
 * overlapping category/tag assignments), and a demo page containing both blocks.
 *
 * The whole process is idempotent: a one-time option flag means re-activating
 * the plugin never duplicates content.
 */
final class Seeder {

	/** Option flag that records a completed seed. */
	const SEEDED_OPTION = 'wmpb_seeded';

	/** Option that stores the demo page ID (also used by uninstall). */
	const DEMO_PAGE_OPTION = 'wmpb_demo_page_id';

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		// The post type / taxonomies are normally registered on `init`, which has
		// not fired during activation — register them now so we can insert terms
		// and posts against them.
		Content_Model::register();

		if ( ! get_option( self::SEEDED_OPTION ) ) {
			self::seed();
			update_option( self::SEEDED_OPTION, WMPB_VERSION );
		}

		// Pretty permalinks for the new post type need the rewrite rules rebuilt.
		flush_rewrite_rules();
	}

	/**
	 * Deactivation callback. Keeps content; only clears rewrite rules.
	 *
	 * @return void
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Seed taxonomy terms, posts and the demo page.
	 *
	 * @return void
	 */
	private static function seed() {
		$category_ids = self::seed_terms(
			Content_Model::TAX_CATEGORY,
			array( 'Engineering', 'Design', 'Product', 'Marketing' )
		);

		$tag_ids = self::seed_terms(
			Content_Model::TAX_TAG,
			array( 'Featured', 'Tutorial', 'Opinion', 'Case Study', 'Quick Read', 'Deep Dive' )
		);

		self::seed_posts( $category_ids, $tag_ids );
		self::seed_demo_page();
	}

	/**
	 * Create terms for a taxonomy and return a name => term_id map.
	 *
	 * @param string   $taxonomy Taxonomy slug.
	 * @param string[] $names    Term names to create.
	 * @return array<string,int>
	 */
	private static function seed_terms( $taxonomy, array $names ) {
		$map = array();

		foreach ( $names as $name ) {
			$existing = get_term_by( 'name', $name, $taxonomy );
			if ( $existing ) {
				$map[ $name ] = (int) $existing->term_id;
				continue;
			}

			$result = wp_insert_term( $name, $taxonomy );
			if ( ! is_wp_error( $result ) ) {
				$map[ $name ] = (int) $result['term_id'];
			}
		}

		return $map;
	}

	/**
	 * Create 12 demo posts with overlapping category/tag assignments.
	 *
	 * The assignments are crafted so that filter combinations produce meaningful,
	 * non-empty results (e.g. "Engineering" AND "Tutorial" matches several posts,
	 * while "Marketing" AND "Deep Dive" narrows to one).
	 *
	 * @param array<string,int> $categories Name => term_id map.
	 * @param array<string,int> $tags       Name => term_id map.
	 * @return void
	 */
	private static function seed_posts( array $categories, array $tags ) {
		// title, [category names], [tag names], hex colour for the featured image.
		$blueprint = array(
			array( 'Scaling WordPress to a Million Requests', array( 'Engineering' ), array( 'Featured', 'Deep Dive' ), '2563eb' ),
			array( 'A Practical Guide to Gutenberg Blocks', array( 'Engineering', 'Product' ), array( 'Tutorial', 'Quick Read' ), '7c3aed' ),
			array( 'Designing Accessible Color Systems', array( 'Design' ), array( 'Tutorial', 'Deep Dive' ), '059669' ),
			array( 'Why We Chose the Interactivity API', array( 'Engineering' ), array( 'Opinion', 'Featured' ), '0891b2' ),
			array( 'From Figma to Production in a Day', array( 'Design', 'Product' ), array( 'Case Study' ), 'db2777' ),
			array( 'The Anatomy of a Great Changelog', array( 'Product' ), array( 'Opinion', 'Quick Read' ), 'ea580c' ),
			array( 'Growth Loops That Actually Work', array( 'Marketing' ), array( 'Deep Dive', 'Case Study' ), 'd97706' ),
			array( 'Writing Copy That Converts', array( 'Marketing', 'Design' ), array( 'Tutorial', 'Quick Read' ), 'dc2626' ),
			array( 'Measuring Developer Experience', array( 'Engineering', 'Product' ), array( 'Opinion', 'Deep Dive' ), '4f46e5' ),
			array( 'Onboarding That Users Remember', array( 'Product', 'Marketing' ), array( 'Case Study', 'Featured' ), '0d9488' ),
			array( 'Refactoring Without Fear', array( 'Engineering' ), array( 'Tutorial' ), '9333ea' ),
			array( 'The Quiet Power of Whitespace', array( 'Design' ), array( 'Opinion', 'Quick Read' ), '16a34a' ),
		);

		foreach ( $blueprint as $index => $row ) {
			list( $title, $cat_names, $tag_names, $color ) = $row;

			$post_id = wp_insert_post(
				array(
					'post_type'    => Content_Model::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => self::dummy_content( $title, ( $index % 4 ) + 1 ),
					'post_excerpt' => sprintf(
						/* translators: %s: post title. */
						__( 'A short, hand-written excerpt introducing "%s" so the grid always has summary text to show.', 'wm-posts-blocks' ),
						$title
					),
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			// Map the human-readable names back to term IDs and assign them.
			wp_set_object_terms( $post_id, self::names_to_ids( $cat_names, $categories ), Content_Model::TAX_CATEGORY );
			wp_set_object_terms( $post_id, self::names_to_ids( $tag_names, $tags ), Content_Model::TAX_TAG );

			// Generate and attach a featured image locally (no network needed).
			$attachment_id = self::create_featured_image( $post_id, $title, $color, $index + 1 );
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}
	}

	/**
	 * Create the demo page with both blocks already placed.
	 *
	 * The grid block contains the pagination inner block, and the filter block
	 * sits above it — demonstrating that the two top-level blocks stay in sync
	 * without being nested inside one another.
	 *
	 * @return void
	 */
	private static function seed_demo_page() {
		$content = implode(
			"\n\n",
			array(
				'<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Browse the demo posts', 'wm-posts-blocks' ) . '</h2><!-- /wp:heading -->',
				'<!-- wp:wmpb/posts-filter /-->',
				// No attributes → the grid inherits the global plugin settings.
				'<!-- wp:wmpb/posts-grid --><!-- wp:wmpb/posts-grid-pagination /--><!-- /wp:wmpb/posts-grid -->',
			)
		);

		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'WM Posts Blocks Demo', 'wm-posts-blocks' ),
				'post_content' => $content,
			)
		);

		if ( ! is_wp_error( $page_id ) ) {
			update_option( self::DEMO_PAGE_OPTION, (int) $page_id );
		}
	}

	/**
	 * Generate an attractive diagonal-gradient image and store it as an
	 * attachment in the media library.
	 *
	 * The gradient is drawn on a tiny canvas (fast) and smoothly upscaled, then a
	 * couple of soft translucent circles add depth — a modern, on-brand look with
	 * no external HTTP request (which would be fragile on locked-down hosts).
	 *
	 * @param int    $post_id Parent post.
	 * @param string $title   Used for the alt text.
	 * @param string $hex     Base brand colour (6-digit hex, no #).
	 * @param int    $number  Used for the file name.
	 * @return int|null Attachment ID, or null on failure.
	 */
	private static function create_featured_image( $post_id, $title, $hex, $number ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return null; // GD unavailable — skip gracefully.
		}

		$width  = 1200;
		$height = 800;

		// Two harmonious endpoints: the brand colour and a lightened variant.
		list( $r, $g, $b ) = array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
		$r2 = (int) min( 255, $r + 60 );
		$g2 = (int) min( 255, $g + 40 );
		$b2 = (int) min( 255, $b + 80 );

		// Draw the diagonal gradient cheaply on a small canvas...
		$small = 64;
		$grad  = imagecreatetruecolor( $small, $small );
		for ( $y = 0; $y < $small; $y++ ) {
			for ( $x = 0; $x < $small; $x++ ) {
				$t   = ( $x + $y ) / ( 2 * $small );
				$col = imagecolorallocate(
					$grad,
					(int) ( $r + ( $r2 - $r ) * $t ),
					(int) ( $g + ( $g2 - $g ) * $t ),
					(int) ( $b + ( $b2 - $b ) * $t )
				);
				imagesetpixel( $grad, $x, $y, $col );
			}
		}

		// ...then upscale smoothly to the final size.
		$image = imagecreatetruecolor( $width, $height );
		imagecopyresampled( $image, $grad, 0, 0, 0, 0, $width, $height, $small, $small );
		imagedestroy( $grad );

		// Soft translucent circles for a tasteful "mesh gradient" feel.
		imagealphablending( $image, true );
		$light = imagecolorallocatealpha( $image, 255, 255, 255, 110 );
		$dark  = imagecolorallocatealpha( $image, 0, 0, 0, 115 );
		imagefilledellipse( $image, (int) ( $width * 0.78 ), (int) ( $height * 0.22 ), 520, 520, $light );
		imagefilledellipse( $image, (int) ( $width * 0.15 ), (int) ( $height * 0.85 ), 420, 420, $dark );

		// Write the JPEG into the uploads directory.
		$uploads  = wp_upload_dir();
		$filename = sprintf( 'wmpb-demo-%d.jpg', $number );
		$filepath = trailingslashit( $uploads['path'] ) . $filename;

		imagejpeg( $image, $filepath, 90 );
		imagedestroy( $image );

		// Register the file as a WordPress attachment.
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => $title,
				'post_status'    => 'inherit',
			),
			$filepath,
			$post_id
		);

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return null;
		}

		// Generate the standard set of intermediate image sizes.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $filepath );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return (int) $attachment_id;
	}

	/**
	 * Translate term names into their seeded IDs.
	 *
	 * @param string[]          $names Term names.
	 * @param array<string,int> $map   Name => ID map.
	 * @return int[]
	 */
	private static function names_to_ids( array $names, array $map ) {
		$ids = array();
		foreach ( $names as $name ) {
			if ( isset( $map[ $name ] ) ) {
				$ids[] = $map[ $name ];
			}
		}
		return $ids;
	}

	/**
	 * Build block markup for the post body.
	 *
	 * The body length varies with $length so the demo posts show a range of
	 * reading times (1–4 min) on the cards rather than all reading the same.
	 *
	 * @param string $title  Post title.
	 * @param int    $length Number of filler paragraphs (1–4).
	 * @return string
	 */
	private static function dummy_content( $title, $length = 2 ) {
		$intro = sprintf(
			/* translators: %s: post title. */
			__( 'This is demo content for "%s", created automatically when the plugin was activated so the grid, filter and pagination have realistic data to work with.', 'wm-posts-blocks' ),
			$title
		);

		$filler = __( 'Use the category and tag filters to see how OR-within / AND-across filtering narrows the results, and the pagination controls to move between pages. Posts are loaded dynamically over a custom REST endpoint, and the two blocks stay in sync through a shared Interactivity API store without being nested inside each other.', 'wm-posts-blocks' );

		$blocks = array( sprintf( '<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->', esc_html( $intro ) ) );
		for ( $i = 0; $i < max( 1, (int) $length ); $i++ ) {
			$blocks[] = sprintf( '<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->', esc_html( $filler ) );
		}

		return implode( "\n\n", $blocks );
	}
}
