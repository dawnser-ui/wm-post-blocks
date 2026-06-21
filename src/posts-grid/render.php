<?php
/**
 * Server render for the Posts Grid block.
 *
 * Responsibilities:
 *   1. Resolve the effective layout (block attribute, else global setting).
 *   2. Seed the Interactivity store with the initial result set + labels so the
 *      grid is correct the moment it hydrates (no extra request, no flash).
 *   3. Render the card template once; the runtime clones it per post in state.
 *   4. Apply the site-wide style (accent colour, card style, image ratio) and
 *      the "which fields show" settings.
 *   5. Output the inner pagination block ($content) inside the same scope.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Serialised inner blocks (the pagination block).
 * @var WP_Block $block      Block instance.
 *
 * @package WMPB
 */

defined( 'ABSPATH' ) || exit;

use WMPB\Settings;
use WMPB\Posts_Query;

// Effective layout: a block attribute of 0 means "inherit the global default".
$wmpb_columns  = ! empty( $attributes['columns'] ) ? (int) $attributes['columns'] : (int) Settings::get( 'columns' );
$wmpb_per_page = ! empty( $attributes['perPage'] ) ? (int) $attributes['perPage'] : (int) Settings::get( 'per_page' );

// Site-wide presentation settings.
$wmpb_show_image   = (bool) Settings::get( 'show_image' );
$wmpb_show_excerpt = (bool) Settings::get( 'show_excerpt' );
$wmpb_show_meta    = (bool) Settings::get( 'show_meta' );
$wmpb_card_style   = (string) Settings::get( 'card_style' );
$wmpb_accent       = (string) Settings::get( 'accent_color' );

// Initial, unfiltered page of posts.
$wmpb_result = Posts_Query::get_posts(
	array(
		'categories' => array(),
		'tags'       => array(),
		'per_page'   => $wmpb_per_page,
		'page'       => 1,
	)
);

// Seed the shared store (single initial state for the page).
wp_interactivity_state(
	'wmpb/posts',
	array(
		'posts'              => $wmpb_result['posts'],
		'columns'            => $wmpb_columns,
		'perPage'            => $wmpb_per_page,
		'page'               => $wmpb_result['page'],
		'totalPages'         => $wmpb_result['totalPages'],
		'total'              => $wmpb_result['total'],
		'selectedCategories' => array(),
		'selectedTags'       => array(),
		'loading'            => false,
	)
);

// Read-only config: REST URL + translatable labels used by store getters.
wp_interactivity_config(
	'wmpb/posts',
	array(
		'restUrl' => esc_url_raw( rest_url( 'wmpb/v1/posts' ) ),
		'labels'  => array(
			'showing' => __( 'Showing', 'wm-posts-blocks' ),
			'post'    => __( 'post', 'wm-posts-blocks' ),
			'posts'   => __( 'posts', 'wm-posts-blocks' ),
		),
	)
);

$wmpb_wrapper = get_block_wrapper_attributes(
	array(
		'class'               => 'wmpb-posts-grid wmpb-style-' . sanitize_html_class( $wmpb_card_style ),
		'data-wp-interactive' => 'wmpb/posts',
		'style'              => sprintf(
			'--wmpb-columns:%d;--wmpb-accent:%s;--wmpb-ratio:%s;',
			$wmpb_columns,
			esc_attr( $wmpb_accent ),
			esc_attr( Settings::css_ratio() )
		),
	)
);
?>
<div <?php echo wp_kses_data( $wmpb_wrapper ); ?>>

	<?php // Toolbar: live result count + a Clear-filters button. ?>
	<div class="wmpb-posts-grid__toolbar">
		<span class="wmpb-posts-grid__count" data-wp-text="state.resultLabel"></span>
		<button
			type="button"
			class="wmpb-posts-grid__clear"
			data-wp-on--click="actions.clearFilters"
			data-wp-bind--hidden="!state.hasFilters"
		>
			<?php esc_html_e( 'Clear filters', 'wm-posts-blocks' ); ?>
		</button>
	</div>

	<?php // Thin progress bar shown while a request is in flight. ?>
	<div class="wmpb-posts-grid__progress" data-wp-bind--hidden="!state.loading" aria-hidden="true"></div>

	<div
		class="wmpb-posts-grid__items"
		data-wp-class--is-loading="state.loading"
	>
		<?php
		/*
		 * Single rendering path: one card per item in `state.posts`, keyed by ID.
		 * The first page is seeded server-side (above), so the grid populates on
		 * hydration with no request. We do NOT also print static cards: data-wp-each
		 * appends rather than reconciling, which would render every card twice.
		 */
		?>
		<template
			data-wp-each--post="state.posts"
			data-wp-each-key="context.post.id"
		>
			<article class="wmpb-card">
				<?php if ( $wmpb_show_image ) : ?>
					<a class="wmpb-card__media" data-wp-bind--href="context.post.link">
						<img
							class="wmpb-card__image"
							data-wp-bind--src="context.post.image"
							data-wp-bind--alt="context.post.title"
							loading="lazy"
						/>
						<?php if ( $wmpb_show_meta ) : ?>
							<span
								class="wmpb-card__chip"
								data-wp-text="context.post.categoryLabel"
								data-wp-bind--hidden="!context.post.categoryLabel"
							></span>
						<?php endif; ?>
					</a>
				<?php endif; ?>

				<div class="wmpb-card__body">
					<?php if ( $wmpb_show_meta ) : ?>
						<p class="wmpb-card__meta" data-wp-text="context.post.metaLabel"></p>
					<?php endif; ?>

					<h3 class="wmpb-card__title">
						<a data-wp-bind--href="context.post.link" data-wp-text="context.post.title"></a>
					</h3>

					<?php if ( $wmpb_show_excerpt ) : ?>
						<p class="wmpb-card__excerpt" data-wp-text="context.post.excerpt"></p>
					<?php endif; ?>

					<?php if ( $wmpb_show_meta ) : ?>
						<p
							class="wmpb-card__tags"
							data-wp-text="context.post.tagsLabel"
							data-wp-bind--hidden="!context.post.tagsLabel"
						></p>
					<?php endif; ?>
				</div>
			</article>
		</template>
	</div>

	<?php // Empty state, shown only when a finished request returned nothing. ?>
	<p class="wmpb-posts-grid__empty" data-wp-bind--hidden="!state.isEmpty">
		<?php esc_html_e( 'No posts match the selected filters.', 'wm-posts-blocks' ); ?>
	</p>

	<?php
	// The pagination inner block, rendered inside the same interactive scope.
	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted inner block markup.
	?>
</div>
