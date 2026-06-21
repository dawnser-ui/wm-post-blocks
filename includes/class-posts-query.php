<?php
/**
 * The single source of truth for querying and shaping demo posts.
 *
 * @package WMPB
 */

namespace WMPB;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the filtered, paginated posts query and normalises the result.
 *
 * Both the server-side block render (first paint / no-JS) and the REST endpoint
 * (every subsequent filter or pagination action) call this one method, so the
 * filtering rules live in exactly one place and can never drift apart.
 */
final class Posts_Query {

	/**
	 * Fetch a page of demo posts matching the given category/tag selection.
	 *
	 * Filtering rule required by the brief:
	 *   - OR  *within* a filter type  → an `IN` term clause (match ANY selected term).
	 *   - AND *across* filter types    → `relation => 'AND'` between the two clauses.
	 *
	 * So a post is returned only if it matches at least one selected category
	 * AND at least one selected tag.
	 *
	 * @param array $args {
	 *     @type int[] $categories Selected category term IDs.
	 *     @type int[] $tags       Selected tag term IDs.
	 *     @type int   $per_page   Posts per page (clamped 1..50).
	 *     @type int   $page       1-based page number.
	 * }
	 * @return array{posts:array,page:int,totalPages:int,total:int} Normalised payload.
	 */
	public static function get_posts( array $args ) {
		$categories = self::sanitize_ids( $args['categories'] ?? array() );
		$tags       = self::sanitize_ids( $args['tags'] ?? array() );
		$per_page   = max( 1, min( 50, (int) ( $args['per_page'] ?? 6 ) ) );
		$page       = max( 1, (int) ( $args['page'] ?? 1 ) );

		// Build the tax_query only from the filters that are actually active.
		$tax_query = array( 'relation' => 'AND' );

		if ( $categories ) {
			$tax_query[] = array(
				'taxonomy' => Content_Model::TAX_CATEGORY,
				'field'    => 'term_id',
				'terms'    => $categories,
				'operator' => 'IN', // OR within the category filter.
			);
		}

		if ( $tags ) {
			$tax_query[] = array(
				'taxonomy' => Content_Model::TAX_TAG,
				'field'    => 'term_id',
				'terms'    => $tags,
				'operator' => 'IN', // OR within the tag filter.
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'           => Content_Model::POST_TYPE,
				'post_status'         => 'publish',
				'posts_per_page'      => $per_page,
				'paged'               => $page,
				// If no filters are active, pass no tax_query at all.
				'tax_query'           => count( $tax_query ) > 1 ? $tax_query : array(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'ignore_sticky_posts' => true,
			)
		);

		return array(
			'posts'      => array_map( array( self::class, 'shape_post' ), $query->posts ),
			'page'       => $page,
			'totalPages' => (int) $query->max_num_pages,
			'total'      => (int) $query->found_posts,
		);
	}

	/**
	 * Reduce a WP_Post to the render-ready shape the cards need.
	 *
	 * The same shape is produced for the server-seeded first paint and for every
	 * REST response, so the grid template renders identically either way.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Card data (id, title, excerpt, image, link, meta labels).
	 */
	private static function shape_post( $post ) {
		$categories = get_the_terms( $post, Content_Model::TAX_CATEGORY );
		$tags       = get_the_terms( $post, Content_Model::TAX_TAG );
		$categories = is_array( $categories ) ? $categories : array();
		$tags       = is_array( $tags ) ? $tags : array();

		$excerpt_words = (int) Settings::get( 'excerpt_words' );

		return array(
			'id'            => (int) $post->ID,
			'title'         => get_the_title( $post ),
			'excerpt'       => wp_trim_words( wp_strip_all_tags( get_the_excerpt( $post ) ), $excerpt_words ),
			'image'         => (string) get_the_post_thumbnail_url( $post, 'medium_large' ),
			'link'          => (string) get_permalink( $post ),
			// Primary category, shown as a chip over the image.
			'categoryLabel' => $categories ? $categories[0]->name : '',
			// Tags joined into one label (avoids a fragile nested client loop).
			'tagsLabel'     => implode( ' · ', wp_list_pluck( $tags, 'name' ) ),
			// "Jun 16, 2026 · 3 min read".
			'metaLabel'     => sprintf(
				'%s · %s',
				get_the_date( 'M j, Y', $post ),
				self::reading_time( $post )
			),
		);
	}

	/**
	 * Estimate reading time from the post content (~200 words/minute).
	 *
	 * @param \WP_Post $post Post object.
	 * @return string e.g. "3 min read".
	 */
	private static function reading_time( $post ) {
		$words   = str_word_count( wp_strip_all_tags( $post->post_content ) );
		$minutes = max( 1, (int) round( $words / 200 ) );
		/* translators: %d: number of minutes. */
		return sprintf( _n( '%d min read', '%d min read', $minutes, 'wm-posts-blocks' ), $minutes );
	}

	/**
	 * Coerce a mixed list of term IDs into a clean array of positive integers.
	 *
	 * @param mixed $ids Raw IDs (array or comma string).
	 * @return int[]
	 */
	private static function sanitize_ids( $ids ) {
		if ( is_string( $ids ) ) {
			$ids = explode( ',', $ids );
		}

		$ids = array_map( 'absint', (array) $ids );

		return array_values( array_filter( $ids ) );
	}
}
