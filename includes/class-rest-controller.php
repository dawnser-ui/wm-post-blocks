<?php
/**
 * Custom REST endpoint that powers live filtering and pagination.
 *
 * @package WMPB
 */

namespace WMPB;

defined( 'ABSPATH' ) || exit;

/**
 * Registers `GET /wp-json/wmpb/v1/posts`.
 *
 * Why a custom endpoint instead of the core `/wp/v2/wmpb_post` route?
 *   1. We return exactly the four fields the cards need (title, excerpt, image,
 *      link) instead of the full, heavy post object.
 *   2. We control the OR/AND taxonomy logic and the pagination shape ourselves.
 *   3. The response shape is identical to what the server seeds into the
 *      Interactivity store, so the front end never has to reshape data.
 */
final class REST_Controller {

	/** REST namespace. */
	const NAMESPACE = 'wmpb/v1';

	/** REST route. */
	const ROUTE = '/posts';

	/**
	 * Register the route and document its arguments.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_posts' ),
				// Public, read-only data — no authentication required.
				'permission_callback' => '__return_true',
				'args'                => array(
					'categories' => array(
						'description'       => __( 'Comma-separated category term IDs.', 'wm-posts-blocks' ),
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'tags'       => array(
						'description'       => __( 'Comma-separated tag term IDs.', 'wm-posts-blocks' ),
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page'   => array(
						'description'       => __( 'Number of posts per page.', 'wm-posts-blocks' ),
						'type'              => 'integer',
						'default'           => 6,
						'sanitize_callback' => 'absint',
					),
					'page'       => array(
						'description'       => __( 'Page number (1-based).', 'wm-posts-blocks' ),
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Handle the request by delegating to the shared query class.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public static function get_posts( \WP_REST_Request $request ) {
		$payload = Posts_Query::get_posts(
			array(
				'categories' => $request->get_param( 'categories' ),
				'tags'       => $request->get_param( 'tags' ),
				'per_page'   => $request->get_param( 'per_page' ),
				'page'       => $request->get_param( 'page' ),
			)
		);

		return rest_ensure_response( $payload );
	}
}
