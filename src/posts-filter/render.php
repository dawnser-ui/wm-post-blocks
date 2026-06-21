<?php
/**
 * Server render for the Posts Filter block.
 *
 * Renders one checkbox per category and tag term. Each checkbox:
 *   - carries its own context: { termId, type } via data-wp-context,
 *   - calls actions.toggleTerm on change (the action reads that context),
 *   - reflects its selected state through the shared store's `isChecked` getter.
 *
 * Because every input lives in the `wmpb/posts` namespace, toggling one updates
 * the same store the grid reads from — no nesting, no custom events.
 *
 * @var array $attributes Block attributes.
 *
 * @package WMPB
 */

defined( 'ABSPATH' ) || exit;

$wmpb_show_categories = ! isset( $attributes['showCategories'] ) || $attributes['showCategories'];
$wmpb_show_tags       = ! isset( $attributes['showTags'] ) || $attributes['showTags'];

$wmpb_categories = get_terms(
	array(
		'taxonomy'   => \WMPB\Content_Model::TAX_CATEGORY,
		'hide_empty' => false,
	)
);
$wmpb_tags = get_terms(
	array(
		'taxonomy'   => \WMPB\Content_Model::TAX_TAG,
		'hide_empty' => false,
	)
);

/*
 * Seed the shared filter state so this block also works when placed on a page
 * WITHOUT a grid. These defaults match the grid's, so when both blocks are
 * present the merge is a no-op regardless of render order.
 */
wp_interactivity_state(
	'wmpb/posts',
	array(
		'selectedCategories' => array(),
		'selectedTags'       => array(),
	)
);

$wmpb_wrapper = get_block_wrapper_attributes(
	array(
		'class'               => 'wmpb-filter',
		'data-wp-interactive' => 'wmpb/posts',
		'style'               => '--wmpb-accent:' . esc_attr( \WMPB\Settings::get( 'accent_color' ) ) . ';',
	)
);

/**
 * Render a single taxonomy as a checkbox group.
 *
 * @param array  $terms  Term objects.
 * @param string $type   'category' or 'tag' (passed into each checkbox context).
 * @param string $legend Visible group label.
 * @return void
 */
$wmpb_render_group = static function ( $terms, $type, $legend ) {
	if ( empty( $terms ) || is_wp_error( $terms ) ) {
		return;
	}
	?>
	<fieldset class="wmpb-filter__group">
		<legend class="wmpb-filter__legend"><?php echo esc_html( $legend ); ?></legend>
		<div class="wmpb-filter__pills">
			<?php foreach ( $terms as $wmpb_term ) : ?>
				<label
					class="wmpb-filter__pill"
					<?php
					// Helper that prints a correctly-escaped data-wp-context attribute.
					echo wp_interactivity_data_wp_context(
						array(
							'termId' => (int) $wmpb_term->term_id,
							'type'   => $type,
						)
					);
					?>
				>
					<input
						type="checkbox"
						class="wmpb-filter__check"
						data-wp-on--change="actions.toggleTerm"
						data-wp-bind--checked="state.isChecked"
					/>
					<span class="wmpb-filter__pill-text"><?php echo esc_html( $wmpb_term->name ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
	</fieldset>
	<?php
};
?>
<div <?php echo wp_kses_data( $wmpb_wrapper ); ?>>
	<?php
	if ( $wmpb_show_categories ) {
		$wmpb_render_group( $wmpb_categories, 'category', __( 'Categories', 'wm-posts-blocks' ) );
	}
	if ( $wmpb_show_tags ) {
		$wmpb_render_group( $wmpb_tags, 'tag', __( 'Tags', 'wm-posts-blocks' ) );
	}
	?>
</div>
