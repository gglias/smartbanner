<?php
/**
 * Plugin Name:       SmartBanner
 * Description:       Floating banner in the bottom corner that promotes a blog post you choose (or custom content). Visitors can close it and it stays closed. WPML ready.
 * Version:           1.3.7
 * Author:            Smartup
 * Author URI:        https://smartupweb.com
 * Plugin URI:        https://github.com/gglias/smartbanner
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smartbanner
 * Domain Path:       /languages
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Update URI:        https://smartupweb.com/plugins/smartbanner
 */

defined( 'ABSPATH' ) || exit;

define( 'SMB_VERSION', '1.3.7' );
define( 'SMB_FILE', __FILE__ );
define( 'SMB_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMB_URL', plugin_dir_url( __FILE__ ) );
define( 'SMB_OPTION', 'smartbanner_settings' );
define( 'SMB_RESET_OPTION', 'smartbanner_reset' ); // time of the last "Show it to everyone again"

add_action( 'init', function () {
	load_plugin_textdomain( 'smartbanner', false, dirname( plugin_basename( SMB_FILE ) ) . '/languages' );
} );

/*
 * SmartBanner is updated only by uploading a new zip. The "Update URI" header already tells wordpress.org not to
 * offer updates for it; this makes sure that, even so, no update offered from anywhere (e.g. an unrelated plugin
 * that happens to use the same "smartbanner" slug) can ever replace this code.
 */
add_filter( 'site_transient_update_plugins', function ( $updates ) {
	$file = plugin_basename( SMB_FILE );
	if ( is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ) {
		unset( $updates->response[ $file ] );
	}
	return $updates;
} );
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	return ( is_object( $item ) && isset( $item->plugin ) && plugin_basename( SMB_FILE ) === $item->plugin ) ? false : $update;
}, 10, 2 );

/* ---------- Languages (WPML) ---------- */

/**
 * Active WPML languages, default first: code => [code, name, locale, flag, default].
 * Empty array when the site is not multilingual.
 */
function smb_languages() {
	$wpml = apply_filters( 'wpml_active_languages', null, 'skip_missing=0' );
	if ( ! is_array( $wpml ) || ! $wpml ) {
		return array();
	}
	$default = apply_filters( 'wpml_default_language', null );
	$langs   = array();
	foreach ( $wpml as $code => $l ) {
		$code = sanitize_key( (string) $code );
		if ( '' === $code || 'all' === $code ) {
			continue;
		}
		// Strings only, whatever another plugin returns.
		$name           = smb_str( $l, 'native_name' );
		$langs[ $code ] = array(
			'code'    => $code,
			'name'    => '' !== $name ? $name : $code,
			'locale'  => smb_str( $l, 'default_locale' ),
			'flag'    => smb_str( $l, 'country_flag_url' ),
			'default' => $code === $default,
		);
	}
	uasort( $langs, function ( $a, $b ) {
		return (int) $b['default'] - (int) $a['default'];
	} );
	return $langs;
}

/** Keys the per-language content is stored under. "all" = site without WPML. */
function smb_lang_keys() {
	$langs = smb_languages();
	return $langs ? array_keys( $langs ) : array( 'all' );
}

function smb_current_lang() {
	$langs = smb_languages();
	if ( ! $langs ) {
		return 'all';
	}
	$cur = apply_filters( 'wpml_current_language', null );
	return isset( $langs[ $cur ] ) ? $cur : key( $langs );
}

function smb_lang_locale( $lang ) {
	$langs = smb_languages();
	return isset( $langs[ $lang ] ) ? $langs[ $lang ]['locale'] : get_locale();
}

/* ---------- Settings ---------- */

/** Shared by every language. */
function smb_defaults() {
	return array(
		'enabled'           => 0,
		'delay'             => 3,
		'cookie_hours'      => 720, // 0 = until the browser closes
		'position'          => 'right', // right | left
		'offset_x'          => 24,
		'offset_y'          => 24,
		'show_mobile'       => 1,
		'hide_image_mobile' => 0,
		'button_bg'         => '#141413',
		'button_color'      => '#ffffff',
		'z_index'           => 99, // above page content, below theme menus and popups
		'content'           => array(),
	);
}

/** Per language. The default texts follow the language of the content, not of the admin. */
function smb_content_defaults( $locale = '' ) {
	$el = 0 === strpos( (string) $locale, 'el' );
	return array(
		'active'      => 1,
		'source'      => 'post', // post | custom
		'post_id'     => 0,
		'label'       => $el ? 'Νέο στο blog' : 'New on the blog',
		'title'       => '',
		'text'        => '',
		'image_id'    => 0,
		'button_text' => $el ? 'Διαβάστε περισσότερα' : 'Read more',
		'button_url'  => '',
		'new_tab'     => 0,
	);
}

function smb_get_settings() {
	$saved = get_option( SMB_OPTION, array() );
	$saved = is_array( $saved ) ? $saved : array();

	// 1.1 stored one set of content at the top level.
	if ( ! isset( $saved['content'] ) && isset( $saved['source'] ) ) {
		$saved['content'] = array( 'all' => array_intersect_key( $saved, smb_content_defaults() ) );
	}
	// Up to 1.2 the "keep hidden" time was stored in days.
	if ( ! isset( $saved['cookie_hours'] ) && isset( $saved['cookie_days'] ) ) {
		$saved['cookie_hours'] = 24 * (int) $saved['cookie_days'];
	}

	return smb_normalize_settings( wp_parse_args( array_intersect_key( $saved, smb_defaults() ), smb_defaults() ) );
}

/** String value of a key, or '' when missing or not a scalar (arrays must never reach string functions). */
function smb_str( $arr, $key ) {
	return ( is_array( $arr ) && isset( $arr[ $key ] ) && is_scalar( $arr[ $key ] ) ) ? (string) $arr[ $key ] : '';
}

/**
 * Forces every shared setting into its allowed type and range. Runs on every read, so values that reached the
 * database some other way (WP-CLI, another plugin, an import) can't end up in the page as-is.
 */
function smb_normalize_settings( $s ) {
	$d   = smb_defaults();
	$int = function ( $key, $min, $max ) use ( $s, $d ) {
		return is_numeric( smb_str( $s, $key ) ) ? max( $min, min( $max, (int) $s[ $key ] ) ) : $d[ $key ];
	};
	$hex = function ( $key ) use ( $s, $d ) {
		$c = sanitize_hex_color( trim( smb_str( $s, $key ) ) );
		return $c ? $c : $d[ $key ];
	};
	$delay = smb_str( $s, 'delay' );

	return array(
		'enabled'           => empty( $s['enabled'] ) ? 0 : 1,
		'delay'             => is_numeric( $delay ) ? max( 0, min( 120, round( (float) $delay, 1 ) ) ) : $d['delay'],
		'cookie_hours'      => $int( 'cookie_hours', 0, 8760 ),
		'position'          => 'left' === smb_str( $s, 'position' ) ? 'left' : 'right',
		'offset_x'          => $int( 'offset_x', 0, 300 ),
		'offset_y'          => $int( 'offset_y', 0, 300 ),
		'show_mobile'       => empty( $s['show_mobile'] ) ? 0 : 1,
		'hide_image_mobile' => empty( $s['hide_image_mobile'] ) ? 0 : 1,
		'button_bg'         => $hex( 'button_bg' ),
		'button_color'      => $hex( 'button_color' ),
		'z_index'           => $int( 'z_index', 1, 999999 ),
		'content'           => ( isset( $s['content'] ) && is_array( $s['content'] ) ) ? $s['content'] : array(),
	);
}

/** Same idea for one language's content. Texts are escaped again on output; this keeps the types right. */
function smb_normalize_content( $c, $defaults ) {
	$c   = is_array( $c ) ? $c : array();
	$out = $defaults;
	foreach ( array( 'label', 'title', 'text', 'button_text', 'button_url' ) as $key ) {
		if ( array_key_exists( $key, $c ) ) {
			$out[ $key ] = smb_str( $c, $key );
		}
	}
	foreach ( array( 'post_id', 'image_id' ) as $key ) {
		$out[ $key ] = max( 0, (int) smb_str( $c, $key ) );
	}
	foreach ( array( 'active', 'new_tab' ) as $key ) {
		if ( array_key_exists( $key, $c ) ) {
			$out[ $key ] = empty( $c[ $key ] ) ? 0 : 1;
		}
	}
	$out['source'] = 'custom' === smb_str( $c, 'source' ) ? 'custom' : 'post';
	return $out;
}

/** Content for one language, with defaults filled in. */
function smb_get_content( $lang, $s = null ) {
	$s = $s ? $s : smb_get_settings();
	$c = isset( $s['content'][ $lang ] ) ? $s['content'][ $lang ] : null;

	// Content saved before WPML was switched on belongs to the default language.
	if ( ! $c && 'all' !== $lang && isset( $s['content']['all'] ) ) {
		$langs = smb_languages();
		if ( ! empty( $langs[ $lang ]['default'] ) ) {
			$c = $s['content']['all'];
		}
	}
	return smb_normalize_content( $c, smb_content_defaults( smb_lang_locale( $lang ) ) );
}

/**
 * Only real, published, public blog posts can be promoted. Password-protected posts are refused too,
 * so nothing behind a password can reach the banner (the picker doesn't offer them either).
 */
function smb_promotable_post( $id ) {
	$post = $id ? get_post( $id ) : null;
	return ( $post && 'post' === $post->post_type && 'publish' === $post->post_status && '' === (string) $post->post_password ) ? $post : null;
}

function smb_valid_image( $id ) {
	$id = (int) $id;
	return ( $id > 0 && wp_attachment_is_image( $id ) ) ? $id : 0;
}

/**
 * A short plain-text excerpt. Without a hand-written excerpt, only the blocks WordPress itself allows in
 * excerpts are used (no Custom HTML, hidden or embedded blocks). Membership/paywall plugins can hook
 * `smartbanner_excerpt` to hide gated text, or the admin can simply type the banner text.
 */
function smb_post_excerpt( $post ) {
	$raw  = has_excerpt( $post ) ? $post->post_excerpt : excerpt_remove_blocks( strip_shortcodes( $post->post_content ) );
	$text = wp_trim_words( wp_strip_all_tags( $raw ), 20, '…' );
	return (string) apply_filters( 'smartbanner_excerpt', $text, $post );
}

/**
 * Resolves the banner for a language: explicit fields win, otherwise they fall back to the chosen post.
 * Returns null when there is nothing valid to show.
 */
function smb_get_banner( $lang = null ) {
	$lang = $lang ? $lang : smb_current_lang();
	$c    = smb_get_content( $lang );
	if ( ! $c['active'] ) {
		return null;
	}

	$post = null;
	if ( 'post' === $c['source'] ) {
		$post = smb_promotable_post( $c['post_id'] );
		if ( ! $post ) {
			return null;
		}
	}

	// Plain text only, whatever is stored: the banner never outputs markup from its texts.
	$title = trim( wp_strip_all_tags( '' !== $c['title'] ? $c['title'] : ( $post ? get_the_title( $post ) : '' ) ) );
	if ( '' === $title ) {
		return null;
	}

	$excerpt = $post ? smb_post_excerpt( $post ) : '';
	$image   = smb_valid_image( $c['image_id'] );
	if ( ! $image && $post ) {
		$image = smb_valid_image( get_post_thumbnail_id( $post ) );
	}

	$data = array(
		'lang'        => $lang,
		'post_id'     => $post ? $post->ID : 0,
		'label'       => trim( wp_strip_all_tags( $c['label'] ) ),
		'title'       => $title,
		'text'        => trim( wp_strip_all_tags( '' !== $c['text'] ? $c['text'] : $excerpt ) ),
		'image_id'    => $image,
		'button_text' => trim( wp_strip_all_tags( $c['button_text'] ) ),
		'url'         => '' !== $c['button_url'] ? $c['button_url'] : ( $post ? get_permalink( $post ) : '' ),
		'new_tab'     => (int) $c['new_tab'],
	);

	// Changing any of the content gives the banner a new key, so visitors who closed the old one see the new one.
	// "Show it to everyone again" in the admin does the same without touching the content.
	$reset       = (int) get_option( SMB_RESET_OPTION, 0 );
	$data['key'] = substr( md5( wp_json_encode( $data ) . ( $reset ? '|' . $reset : '' ) ), 0, 12 );

	return $data;
}

/* ---------- Front end ---------- */

function smb_is_preview() {
	return isset( $_GET['smartbanner_preview'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
}

// A preview page shows an unsaved/disabled banner to the admin only; caches must never keep it.
add_action( 'template_redirect', function () {
	if ( smb_is_preview() ) {
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}
} );

function smb_is_builder_screen() {
	// Elementor / WPBakery editor iframes and the Customizer.
	return isset( $_GET['elementor-preview'] ) || isset( $_GET['vc_editable'] ) || is_customize_preview(); // phpcs:ignore WordPress.Security.NonceVerification
}

/** Decided once per request; both the enqueue and the footer render use it. */
function smb_current_banner() {
	static $resolved = false, $data = null;
	if ( $resolved ) {
		return $data;
	}
	$resolved = true;

	if ( is_admin() || is_feed() || is_embed() || smb_is_builder_screen() ) {
		return null;
	}
	$s = smb_get_settings();
	if ( ! $s['enabled'] && ! smb_is_preview() ) {
		return null;
	}

	$data = smb_get_banner();

	// No point promoting a post to someone who is already reading it.
	if ( $data && $data['post_id'] && is_single( $data['post_id'] ) && ! smb_is_preview() ) {
		$data = null;
	}

	return $data;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! smb_current_banner() ) {
		return;
	}
	wp_enqueue_style( 'smartbanner', SMB_URL . 'assets/smartbanner.css', array(), SMB_VERSION );
	wp_enqueue_script( 'smartbanner', SMB_URL . 'assets/smartbanner.js', array(), SMB_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
} );

add_action( 'wp_footer', function () {
	$b = smb_current_banner();
	if ( ! $b ) {
		return;
	}
	$s       = smb_get_settings();
	$preview = smb_is_preview();

	$classes = array( 'smb', 'smb--' . $s['position'] );
	if ( ! $s['show_mobile'] ) {
		$classes[] = 'smb--hide-mobile';
	}
	if ( $s['hide_image_mobile'] ) {
		$classes[] = 'smb--no-img-mobile';
	}
	if ( ! $b['image_id'] ) {
		$classes[] = 'smb--no-img';
	}

	$style = sprintf(
		'--smb-x:%dpx;--smb-y:%dpx;--smb-btn-bg:%s;--smb-btn-fg:%s;--smb-z:%d',
		$s['offset_x'],
		$s['offset_y'],
		$s['button_bg'],
		$s['button_color'],
		$s['z_index']
	);

	$target = $b['new_tab'] ? ' target="_blank" rel="noopener"' : '';
	$el     = 0 === strpos( smb_lang_locale( $b['lang'] ), 'el' );
	$label  = '' !== $b['label'] ? $b['label'] : ( $el ? 'Ανακοίνωση' : 'Announcement' );
	?>
	<aside id="smartbanner" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $style ); ?>"
		aria-label="<?php echo esc_attr( $label ); ?>"
		data-key="<?php echo esc_attr( $b['key'] ); ?>"
		data-cookie="<?php echo esc_attr( 'smartbanner_closed_' . sanitize_key( $b['lang'] ) ); ?>"
		data-hours="<?php echo esc_attr( $s['cookie_hours'] ); ?>"
		data-delay="<?php echo esc_attr( $preview ? 0 : $s['delay'] ); ?>"
		<?php echo $preview ? 'data-preview' : ''; ?> hidden>
		<div class="smb__body">
			<?php if ( '' !== $b['label'] ) : ?>
				<p class="smb__label">
					<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" stroke-width="1.2"/><path d="M8 7.2v3.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="5.1" r=".8" fill="currentColor"/></svg>
					<span><?php echo esc_html( $b['label'] ); ?></span>
				</p>
			<?php endif; ?>
			<p class="smb__title"><?php echo esc_html( $b['title'] ); ?></p>
			<?php if ( '' !== $b['text'] ) : ?>
				<p class="smb__text"><?php echo esc_html( $b['text'] ); ?></p>
			<?php endif; ?>
			<?php if ( $b['url'] && '' !== $b['button_text'] ) : ?>
				<a class="smb__btn" href="<?php echo esc_url( $b['url'] ); ?>"<?php echo $target; // phpcs:ignore ?>><?php echo esc_html( $b['button_text'] ); ?></a>
			<?php endif; ?>
		</div>
		<?php if ( $b['image_id'] ) : ?>
			<?php
			$img = wp_get_attachment_image( $b['image_id'], 'medium', false, array(
				'class'   => 'smb__img',
				'alt'     => '',
				'sizes'   => '128px',
				'loading' => 'lazy',
			) );
			?>
			<?php if ( $b['url'] ) : ?>
				<a class="smb__media" href="<?php echo esc_url( $b['url'] ); ?>" tabindex="-1" aria-hidden="true"<?php echo $target; // phpcs:ignore ?>><?php echo $img; // phpcs:ignore ?></a>
			<?php else : ?>
				<div class="smb__media" aria-hidden="true"><?php echo $img; // phpcs:ignore ?></div>
			<?php endif; ?>
		<?php endif; ?>
		<button type="button" class="smb__close" aria-label="<?php echo esc_attr( $el ? 'Κλείσιμο' : 'Close' ); ?>">
			<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
		</button>
	</aside>
	<?php
} );

if ( is_admin() ) {
	require_once SMB_DIR . 'includes/admin.php';
}
