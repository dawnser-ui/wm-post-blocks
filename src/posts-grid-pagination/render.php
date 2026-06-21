<?php
/**
 * Server render for the Posts Grid Pagination block.
 *
 * This block is always rendered inside the Posts Grid, so it sits within the
 * grid's `data-wp-interactive="wmpb/posts"` scope and reads the very same shared
 * store. It therefore needs no state of its own — it just binds buttons to the
 * `prevPage` / `nextPage` actions and reflects `state.page` / `state.totalPages`.
 *
 * @package WMPB
 */

defined( 'ABSPATH' ) || exit;

$wmpb_wrapper = get_block_wrapper_attributes(
	array(
		'class'               => 'wmpb-pagination',
		'data-wp-interactive' => 'wmpb/posts',
		// Hide the whole control when there are no results to paginate.
		'data-wp-bind--hidden' => '!state.hasPosts',
		'aria-label'          => esc_attr__( 'Posts pagination', 'wm-posts-blocks' ),
	)
);
?>
<nav <?php echo wp_kses_data( $wmpb_wrapper ); ?>>
	<button
		type="button"
		class="wmpb-pagination__btn"
		data-wp-on--click="actions.prevPage"
		data-wp-bind--disabled="!state.hasPrev"
	>
		<?php esc_html_e( 'Previous', 'wm-posts-blocks' ); ?>
	</button>

	<span class="wmpb-pagination__status">
		<?php esc_html_e( 'Page', 'wm-posts-blocks' ); ?>
		<span data-wp-text="state.page"></span>
		<?php esc_html_e( 'of', 'wm-posts-blocks' ); ?>
		<span data-wp-text="state.totalPages"></span>
	</span>

	<button
		type="button"
		class="wmpb-pagination__btn"
		data-wp-on--click="actions.nextPage"
		data-wp-bind--disabled="!state.hasNext"
	>
		<?php esc_html_e( 'Next', 'wm-posts-blocks' ); ?>
	</button>
</nav>
