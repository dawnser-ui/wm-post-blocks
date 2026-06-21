<?php
/**
 * Plugin settings: the admin control centre.
 *
 * @package WMPB
 */

namespace WMPB;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a single options page (Settings → WM Posts Blocks) that controls how
 * every Posts Grid looks and what it shows, site-wide.
 *
 * The model is deliberately simple to explain:
 *   - **Per-instance layout** (columns, posts-per-page) lives on the block, where
 *     editors expect it — and each grid can fall back to the global default.
 *   - **Site-wide presentation & content** (brand colour, card style, image ratio,
 *     which fields show, excerpt length) lives here, so a team enforces one
 *     consistent look without editing each block.
 */
final class Settings {

	/** Option key (single serialized array — one row in wp_options). */
	const OPTION = 'wmpb_settings';

	/** Settings group used by the Settings API. */
	const GROUP = 'wmpb_settings_group';

	/** Admin page slug. */
	const PAGE = 'wmpb-settings';

	/**
	 * Default values — also the single source of truth for valid option keys.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'columns'      => 3,
			'per_page'     => 6,
			'card_style'   => 'elevated', // elevated | bordered | minimal.
			'image_ratio'  => '3:2',      // 3:2 | 4:3 | 1:1 | 16:9.
			'accent_color' => '#2563eb',
			'excerpt_words' => 20,
			'show_image'   => true,
			'show_excerpt' => true,
			'show_meta'    => true, // Date, category chip and tag chips.
		);
	}

	/**
	 * Get a single setting, or the whole merged settings array.
	 *
	 * Saved values are merged over the defaults, so missing keys always resolve.
	 *
	 * @param string|null $key Setting key, or null for everything.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$settings = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		return null === $key ? $settings : ( $settings[ $key ] ?? null );
	}

	/**
	 * Map the chosen image ratio to a CSS `aspect-ratio` value.
	 *
	 * @return string e.g. "3 / 2".
	 */
	public static function css_ratio() {
		$map = array(
			'3:2'  => '3 / 2',
			'4:3'  => '4 / 3',
			'1:1'  => '1 / 1',
			'16:9' => '16 / 9',
		);
		$ratio = self::get( 'image_ratio' );
		return $map[ $ratio ] ?? '3 / 2';
	}

	/**
	 * Register hooks (called from Plugin::init).
	 *
	 * @return void
	 */
	public static function register_hooks() {
		add_action( 'admin_menu', array( self::class, 'add_menu' ) );
		add_action( 'admin_init', array( self::class, 'register_setting' ) );
	}

	/**
	 * Add the options page under the Settings menu.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_options_page(
			__( 'WM Posts Blocks', 'wm-posts-blocks' ),
			__( 'WM Posts Blocks', 'wm-posts-blocks' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'render_page' )
		);
	}

	/**
	 * Register the option and its sanitizer with the Settings API.
	 *
	 * @return void
	 */
	public static function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'object',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitize every field before it is stored.
	 *
	 * @param array $input Raw form input.
	 * @return array Clean settings.
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = array();

		$clean['columns']      = in_array( (int) ( $input['columns'] ?? 0 ), array( 2, 3, 4 ), true ) ? (int) $input['columns'] : $defaults['columns'];
		$clean['per_page']     = max( 1, min( 24, (int) ( $input['per_page'] ?? $defaults['per_page'] ) ) );
		$clean['card_style']   = in_array( $input['card_style'] ?? '', array( 'elevated', 'bordered', 'minimal' ), true ) ? $input['card_style'] : $defaults['card_style'];
		$clean['image_ratio']  = in_array( $input['image_ratio'] ?? '', array( '3:2', '4:3', '1:1', '16:9' ), true ) ? $input['image_ratio'] : $defaults['image_ratio'];
		$clean['accent_color'] = sanitize_hex_color( $input['accent_color'] ?? '' ) ?: $defaults['accent_color'];
		$clean['excerpt_words'] = max( 5, min( 60, (int) ( $input['excerpt_words'] ?? $defaults['excerpt_words'] ) ) );

		// Checkboxes: present means true.
		$clean['show_image']   = ! empty( $input['show_image'] );
		$clean['show_excerpt'] = ! empty( $input['show_excerpt'] );
		$clean['show_meta']    = ! empty( $input['show_meta'] );

		return $clean;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s         = self::get();
		$demo_page = (int) get_option( Seeder::DEMO_PAGE_OPTION );
		?>
		<div class="wrap wmpb-settings">
			<h1><?php esc_html_e( 'WM Posts Blocks', 'wm-posts-blocks' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'These settings control how every Posts Grid looks and what it shows across the site.', 'wm-posts-blocks' ); ?>
				<?php if ( $demo_page ) : ?>
					<a href="<?php echo esc_url( get_permalink( $demo_page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View the demo page →', 'wm-posts-blocks' ); ?></a>
				<?php endif; ?>
			</p>

			<form action="options.php" method="post">
				<?php settings_fields( self::GROUP ); ?>

				<h2 class="title"><?php esc_html_e( 'Layout', 'wm-posts-blocks' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wmpb_columns"><?php esc_html_e( 'Default columns per row', 'wm-posts-blocks' ); ?></label></th>
						<td>
							<select id="wmpb_columns" name="<?php echo esc_attr( self::OPTION ); ?>[columns]">
								<?php foreach ( array( 2, 3, 4 ) as $n ) : ?>
									<option value="<?php echo esc_attr( $n ); ?>" <?php selected( $s['columns'], $n ); ?>><?php echo esc_html( $n ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Individual grid blocks can override this in the editor.', 'wm-posts-blocks' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wmpb_per_page"><?php esc_html_e( 'Posts per page', 'wm-posts-blocks' ); ?></label></th>
						<td><input type="number" id="wmpb_per_page" min="1" max="24" name="<?php echo esc_attr( self::OPTION ); ?>[per_page]" value="<?php echo esc_attr( $s['per_page'] ); ?>" class="small-text" /></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Content', 'wm-posts-blocks' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Fields to display', 'wm-posts-blocks' ); ?></th>
						<td>
							<fieldset>
								<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_image]" value="1" <?php checked( $s['show_image'] ); ?> /> <?php esc_html_e( 'Featured image', 'wm-posts-blocks' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_excerpt]" value="1" <?php checked( $s['show_excerpt'] ); ?> /> <?php esc_html_e( 'Excerpt', 'wm-posts-blocks' ); ?></label><br />
								<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_meta]" value="1" <?php checked( $s['show_meta'] ); ?> /> <?php esc_html_e( 'Meta (date, category & tag chips)', 'wm-posts-blocks' ); ?></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wmpb_excerpt_words"><?php esc_html_e( 'Excerpt length (words)', 'wm-posts-blocks' ); ?></label></th>
						<td><input type="number" id="wmpb_excerpt_words" min="5" max="60" name="<?php echo esc_attr( self::OPTION ); ?>[excerpt_words]" value="<?php echo esc_attr( $s['excerpt_words'] ); ?>" class="small-text" /></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Style', 'wm-posts-blocks' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wmpb_accent"><?php esc_html_e( 'Accent colour', 'wm-posts-blocks' ); ?></label></th>
						<td><input type="color" id="wmpb_accent" name="<?php echo esc_attr( self::OPTION ); ?>[accent_color]" value="<?php echo esc_attr( $s['accent_color'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wmpb_card_style"><?php esc_html_e( 'Card style', 'wm-posts-blocks' ); ?></label></th>
						<td>
							<select id="wmpb_card_style" name="<?php echo esc_attr( self::OPTION ); ?>[card_style]">
								<?php
								$styles = array(
									'elevated' => __( 'Elevated (shadow)', 'wm-posts-blocks' ),
									'bordered' => __( 'Bordered', 'wm-posts-blocks' ),
									'minimal'  => __( 'Minimal', 'wm-posts-blocks' ),
								);
								foreach ( $styles as $value => $label ) :
									?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $s['card_style'], $value ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wmpb_ratio"><?php esc_html_e( 'Image aspect ratio', 'wm-posts-blocks' ); ?></label></th>
						<td>
							<select id="wmpb_ratio" name="<?php echo esc_attr( self::OPTION ); ?>[image_ratio]">
								<?php foreach ( array( '3:2', '4:3', '1:1', '16:9' ) as $ratio ) : ?>
									<option value="<?php echo esc_attr( $ratio ); ?>" <?php selected( $s['image_ratio'], $ratio ); ?>><?php echo esc_html( $ratio ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
