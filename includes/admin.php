<?php
defined( 'ABSPATH' ) || exit;

const SMB_PAGE_HOOK = 'toplevel_page_smartbanner';

/* ---------- UI language (per user, independent of the WordPress language) ---------- */

function smb_ui_lang_pref() {
	$p = get_user_meta( get_current_user_id(), 'smb_ui_lang', true );
	return in_array( $p, array( 'en', 'el' ), true ) ? $p : '';
}

function smb_ui_lang() {
	$p = smb_ui_lang_pref();
	return $p ? $p : ( 0 === strpos( get_user_locale(), 'el' ) ? 'el' : 'en' );
}

// Runs after load_plugin_textdomain (priority 10).
add_action( 'init', function () {
	$pref = smb_ui_lang_pref();
	if ( ! $pref ) {
		return;
	}
	unload_textdomain( 'smartbanner' ); // not reloadable, so English really stays English
	if ( 'el' === $pref ) {
		load_textdomain( 'smartbanner', SMB_DIR . 'languages/smartbanner-el.mo' );
	}
}, 20 );

/* ---------- Settings ---------- */

add_action( 'admin_init', function () {
	register_setting( 'smartbanner_group', SMB_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'smb_sanitize_settings',
		'default'           => smb_defaults(),
	) );
} );

function smb_sanitize_settings( $in ) {
	$in = is_array( $in ) ? $in : array(); // Callers pass it already unslashed.
	$d  = smb_defaults();

	$hex = function ( $key ) use ( $in, $d ) {
		$c = sanitize_hex_color( trim( smb_str( $in, $key ) ) );
		return $c ? $c : $d[ $key ];
	};
	$int = function ( $key, $min, $max ) use ( $in, $d ) {
		return is_numeric( smb_str( $in, $key ) ) ? max( $min, min( $max, (int) $in[ $key ] ) ) : $d[ $key ];
	};
	$delay = smb_str( $in, 'delay' );

	// Languages not in this submission (e.g. switched off in WPML for now) keep what they had.
	$content = smb_get_settings()['content'];
	if ( isset( $in['content'] ) && is_array( $in['content'] ) ) {
		$allowed = smb_lang_keys();
		foreach ( $in['content'] as $lang => $c ) {
			if ( in_array( (string) $lang, $allowed, true ) && is_array( $c ) ) {
				$content[ $lang ] = smb_sanitize_content( $c, $lang );
			}
		}
	}

	return array(
		'enabled'           => empty( $in['enabled'] ) ? 0 : 1,
		'delay'             => is_numeric( $delay ) ? max( 0, min( 120, round( (float) $delay, 1 ) ) ) : $d['delay'],
		'cookie_hours'      => $int( 'cookie_hours', 0, 8760 ),
		'position'          => 'left' === smb_str( $in, 'position' ) ? 'left' : 'right',
		'offset_x'          => $int( 'offset_x', 0, 300 ),
		'offset_y'          => $int( 'offset_y', 0, 300 ),
		'show_mobile'       => empty( $in['show_mobile'] ) ? 0 : 1,
		'hide_image_mobile' => empty( $in['hide_image_mobile'] ) ? 0 : 1,
		'button_bg'         => $hex( 'button_bg' ),
		'button_color'      => $hex( 'button_color' ),
		'z_index'           => $int( 'z_index', 1, 999999 ),
		'content'           => $content,
	);
}

function smb_sanitize_content( $c, $lang ) {
	$text = function ( $key ) use ( $c ) {
		return sanitize_text_field( smb_str( $c, $key ) );
	};
	$post = smb_promotable_post( (int) smb_str( $c, 'post_id' ) );
	return array(
		'active'      => ( 'all' === $lang || ! empty( $c['active'] ) ) ? 1 : 0,
		'source'      => 'custom' === smb_str( $c, 'source' ) ? 'custom' : 'post',
		'post_id'     => $post ? $post->ID : 0,
		'label'       => $text( 'label' ),
		'title'       => $text( 'title' ),
		'text'        => sanitize_textarea_field( smb_str( $c, 'text' ) ),
		'image_id'    => smb_valid_image( smb_str( $c, 'image_id' ) ),
		'button_text' => $text( 'button_text' ),
		'button_url'  => esc_url_raw( trim( smb_str( $c, 'button_url' ) ), array( 'http', 'https', 'mailto', 'tel' ) ),
		'new_tab'     => empty( $c['new_tab'] ) ? 0 : 1,
	);
}

/* ---------- Menu & assets ---------- */

/** Fill-based so WordPress can recolour it like the other menu icons. */
function smb_menu_icon() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M4 3h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H4a3 3 0 0 1-3-3V6a3 3 0 0 1 3-3zm0 1.7A1.3 1.3 0 0 0 2.7 6v8A1.3 1.3 0 0 0 4 15.3h12a1.3 1.3 0 0 0 1.3-1.3V6A1.3 1.3 0 0 0 16 4.7z"/><rect fill="black" x="9.2" y="8.8" width="6.3" height="4.7" rx="1.1"/></svg>';
	return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore
}

function smb_icon( $name ) {
	$paths = array(
		'logo'    => '<rect x="3" y="4" width="18" height="16" rx="3.5"/><rect x="11" y="12" width="7" height="5" rx="1.5" fill="currentColor" stroke="none"/>',
		'post'    => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
		'pen'     => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
		'eye'     => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'image'   => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="M21 15l-5-5L5 21"/>',
		'power'   => '<path d="M12 3v8"/><path d="M6.3 7.2a8 8 0 1 0 11.4 0"/>',
		'chevron' => '<path d="M6 9l6 6 6-6"/>',
		'globe'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'refresh' => '<path d="M20 11a8 8 0 0 0-14.3-4.9L4 8"/><path d="M4 3.5V8h4.5"/><path d="M4 13a8 8 0 0 0 14.3 4.9L20 16"/><path d="M20 20.5V16h-4.5"/>',
	);
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

add_action( 'admin_menu', function () {
	add_menu_page(
		__( 'SmartBanner', 'smartbanner' ),
		__( 'SmartBanner', 'smartbanner' ),
		'manage_options',
		'smartbanner',
		'smb_render_admin_page',
		smb_menu_icon(),
		58
	);
} );

add_filter( 'plugin_action_links_' . plugin_basename( SMB_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=smartbanner' ) ) . '">' . esc_html__( 'Settings', 'smartbanner' ) . '</a>' );
	return $links;
} );

add_filter( 'admin_body_class', function ( $classes ) {
	$screen = get_current_screen();
	return ( $screen && SMB_PAGE_HOOK === $screen->id ) ? $classes . ' smb-page' : $classes;
} );

/** The shape the post picker and the live preview work with. */
function smb_post_card( $p, $other_langs = array() ) {
	$card = array(
		'id'      => $p->ID,
		'title'   => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
		'date'    => get_the_date( '', $p ),
		'month'   => get_the_date( 'F Y', $p ),
		'excerpt' => html_entity_decode( smb_post_excerpt( $p ), ENT_QUOTES, 'UTF-8' ),
		'thumb'   => (string) get_the_post_thumbnail_url( $p, 'medium' ),
		'link'    => get_permalink( $p ),
		'tr'      => new stdClass(), // lang => id of the WPML translation
	);
	foreach ( $other_langs as $l ) {
		$tid = apply_filters( 'wpml_object_id', $p->ID, 'post', false, $l );
		if ( $tid && (int) $tid !== $p->ID ) {
			$card['tr']->$l = (int) $tid;
		}
	}
	return $card;
}

/** Published posts of a language, newest first (the latest 500 is plenty for a picker and keeps big sites fast). */
function smb_posts_for_lang( $lang ) {
	$wpml = 'all' !== $lang;
	if ( $wpml ) {
		$orig = apply_filters( 'wpml_current_language', null );
		do_action( 'wpml_switch_language', $lang );
	}
	$posts = get_posts( array(
		'post_type'        => 'post',
		'post_status'            => 'publish',
		'has_password'           => false, // their text must not end up in a public banner
		'numberposts'            => 500,
		'orderby'                => 'date',
		'order'                  => 'DESC',
		'update_post_term_cache' => false,
		'suppress_filters'       => false, // let WPML limit the query to $lang
	) );
	$thumbs = array_filter( array_map( 'get_post_thumbnail_id', $posts ) );
	if ( $thumbs ) {
		_prime_post_caches( $thumbs, false, true );
	}
	$others = $wpml ? array_diff( smb_lang_keys(), array( $lang ) ) : array();
	$cards  = array();
	foreach ( $posts as $p ) {
		$cards[] = smb_post_card( $p, $others );
	}
	if ( $wpml ) {
		do_action( 'wpml_switch_language', $orig );
	}
	return $cards;
}

function smb_preview_url( $lang ) {
	$home = home_url( '/' );
	if ( 'all' !== $lang ) {
		$home = apply_filters( 'wpml_permalink', $home, $lang );
	}
	return esc_url_raw( add_query_arg( 'smartbanner_preview', '1', $home ), array( 'http', 'https' ) );
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( SMB_PAGE_HOOK !== $hook ) {
		return;
	}
	$s     = smb_get_settings();
	$langs = smb_languages();
	$keys  = smb_lang_keys();
	$host  = wp_parse_url( home_url(), PHP_URL_HOST );

	$languages = array();
	$posts     = array();
	$images    = array();
	foreach ( $keys as $key ) {
		$info        = isset( $langs[ $key ] ) ? $langs[ $key ] : array( 'code' => 'all', 'name' => '', 'flag' => '', 'default' => true );
		$c           = smb_get_content( $key, $s );
		$languages[] = array(
			'code'       => $key,
			'name'       => $info['name'],
			'default'    => (bool) $info['default'],
			'address'    => $host . ( $info['default'] ? '/' : '/' . $key . '/' ),
			'previewUrl' => smb_preview_url( $key ),
		);
		$posts[ $key ]  = smb_posts_for_lang( $key );
		$images[ $key ] = $c['image_id'] ? (string) wp_get_attachment_image_url( $c['image_id'], 'medium' ) : '';
	}

	wp_enqueue_media();
	wp_enqueue_style( 'smartbanner', SMB_URL . 'assets/smartbanner.css', array(), SMB_VERSION ); // real banner styles for the preview
	wp_enqueue_style( 'smartbanner-admin', SMB_URL . 'assets/admin.css', array( 'smartbanner' ), SMB_VERSION );
	wp_enqueue_script( 'smartbanner-admin', SMB_URL . 'assets/admin.js', array(), SMB_VERSION, true );

	$boot = array(
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'nonce'        => wp_create_nonce( 'smb_admin' ),
		'multilingual' => (bool) $langs,
		'languages'    => $languages,
		'posts'        => $posts,
		'images'       => $images,
		'icons'        => array( 'image' => smb_icon( 'image' ) ),
		'i18n'         => array(
			'pill_live'          => __( 'Live', 'smartbanner' ),
			'pill_off'           => __( 'Off', 'smartbanner' ),
			'pill_warn'          => __( 'Needs content', 'smartbanner' ),
			'status_live'        => __( 'Visitors can see the banner right now.', 'smartbanner' ),
			'status_off'         => __( 'The banner is hidden. Switch it on when you are ready.', 'smartbanner' ),
			'status_warn'        => __( 'It is switched on, but there is nothing to show yet. Pick a post in step 1.', 'smartbanner' ),
			/* translators: %s: comma-separated language names */
			'status_live_langs'  => __( 'Visitors can see the banner right now in: %s.', 'smartbanner' ),
			'turned_on'          => __( 'The banner is on.', 'smartbanner' ),
			'turned_off'         => __( 'The banner is off.', 'smartbanner' ),
			'save_saved'         => __( 'All changes saved', 'smartbanner' ),
			'save_dirty'         => __( 'Unsaved changes', 'smartbanner' ),
			'save_saving'        => __( 'Saving…', 'smartbanner' ),
			'saved'              => __( 'Saved. Your banner is up to date.', 'smartbanner' ),
			'save_error'         => __( 'Could not save. Please try again.', 'smartbanner' ),
			'no_posts'           => __( 'No published posts in this language yet.', 'smartbanner' ),
			'pick_post'          => __( 'Choose a post', 'smartbanner' ),
			/* translators: %d: number of posts */
			'posts_count'        => __( '%d posts, newest first', 'smartbanner' ),
			/* translators: %s: language name */
			'auto_tr'            => __( 'We also picked its translation for %s.', 'smartbanner' ),
			'auto_tr_note'       => __( 'Picked automatically: the translation of the post you chose in the other language.', 'smartbanner' ),
			'ph_title_custom'    => __( 'e.g. Our new service is live', 'smartbanner' ),
			'ph_text_custom'     => __( 'One or two short sentences.', 'smartbanner' ),
			'content_sub_post'   => __( 'Leave a field empty to use what the post already has (shown in grey).', 'smartbanner' ),
			'content_sub_custom' => __( 'Write the message and choose where the button goes.', 'smartbanner' ),
			'img_custom'         => __( 'Custom image', 'smartbanner' ),
			'img_custom_sub'     => __( 'Shown instead of the post image.', 'smartbanner' ),
			'img_post'           => __( 'Post featured image', 'smartbanner' ),
			'img_post_sub'       => __( 'Taken from the post automatically.', 'smartbanner' ),
			'img_none'           => __( 'No image', 'smartbanner' ),
			'img_none_sub'       => __( 'The banner looks fine without one too.', 'smartbanner' ),
			'img_choose'         => __( 'Choose image', 'smartbanner' ),
			'img_change'         => __( 'Change image', 'smartbanner' ),
			'img_use'            => __( 'Use this image', 'smartbanner' ),
			'img_remove'         => __( 'Remove', 'smartbanner' ),
			'img_use_post'       => __( 'Use the post image', 'smartbanner' ),
			'lang_off_preview'   => __( 'The banner is not shown in this language.', 'smartbanner' ),
			'preview_empty'      => __( 'Pick a post or write a title to see your banner here.', 'smartbanner' ),
			'hidden_mobile'      => __( 'Hidden on mobile', 'smartbanner' ),
			'shown_again'        => __( 'Done. Open your site and the banner will be there again.', 'smartbanner' ),
			'reset_confirm'      => __( 'Show the banner again to all visitors, including those who closed it, and clear the site cache?', 'smartbanner' ),
			/* translators: %s: comma-separated names of caching plugins */
			'reset_done_caches'  => __( 'Done. Everyone will see the banner again. Cache cleared: %s.', 'smartbanner' ),
			'reset_done_nocache' => __( 'Done. Everyone will see the banner again. No caching plugin was found; if your host or Cloudflare caches pages, clear it there too.', 'smartbanner' ),
			'reset_working'      => __( 'Working…', 'smartbanner' ),
		),
	);
	// Printed ourselves with < > & ' " hex-encoded, so post titles written by authors can never close the
	// script tag, whatever the WordPress version does in wp_localize_script().
	wp_add_inline_script(
		'smartbanner-admin',
		'var smbAdmin = ' . wp_json_encode( $boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ) . ';',
		'before'
	);
} );

/* ---------- AJAX ---------- */

function smb_ajax_guard() {
	check_ajax_referer( 'smb_admin', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'smartbanner' ) ), 403 );
	}
}

add_action( 'wp_ajax_smb_save', function () {
	smb_ajax_guard();
	$raw = isset( $_POST[ SMB_OPTION ] ) ? wp_unslash( $_POST[ SMB_OPTION ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized on the next line.
	// Sanitized here explicitly, not only through the registered setting, so it can't depend on hook order.
	update_option( SMB_OPTION, smb_sanitize_settings( is_array( $raw ) ? $raw : array() ) );
	wp_send_json_success();
} );

/**
 * Empties the page cache of the common caching plugins and hosts, so visitors get pages with the new banner key
 * instead of a stored copy with the old one. Returns the names of the caches that were cleared.
 */
function smb_purge_page_caches() {
	$purgers = array(
		'WP Rocket'           => function () {
			if ( ! function_exists( 'rocket_clean_domain' ) ) {
				return false;
			}
			rocket_clean_domain();
			return true;
		},
		'LiteSpeed Cache'     => function () {
			if ( ! defined( 'LSCWP_V' ) ) {
				return false;
			}
			do_action( 'litespeed_purge_all' );
			return true;
		},
		'W3 Total Cache'      => function () {
			if ( ! function_exists( 'w3tc_flush_all' ) ) {
				return false;
			}
			w3tc_flush_all();
			return true;
		},
		'WP Super Cache'      => function () {
			if ( ! function_exists( 'wp_cache_clear_cache' ) ) {
				return false;
			}
			wp_cache_clear_cache();
			return true;
		},
		'WP Fastest Cache'    => function () {
			if ( ! isset( $GLOBALS['wp_fastest_cache'] ) || ! method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
				return false;
			}
			$GLOBALS['wp_fastest_cache']->deleteCache( true );
			return true;
		},
		'WP-Optimize'         => function () {
			if ( ! function_exists( 'wpo_cache_flush' ) ) {
				return false;
			}
			wpo_cache_flush();
			return true;
		},
		'SiteGround Optimizer' => function () {
			if ( ! function_exists( 'sg_cachepress_purge_cache' ) ) {
				return false;
			}
			sg_cachepress_purge_cache();
			return true;
		},
		'Breeze'              => function () {
			if ( ! class_exists( 'Breeze_PurgeCache' ) ) {
				return false;
			}
			do_action( 'breeze_clear_all_cache' );
			return true;
		},
		'Hummingbird'         => function () {
			if ( ! class_exists( 'Hummingbird\\WP_Hummingbird' ) && ! class_exists( 'WP_Hummingbird' ) ) {
				return false;
			}
			do_action( 'wphb_clear_page_cache' );
			return true;
		},
		'Cache Enabler'       => function () {
			if ( ! class_exists( 'Cache_Enabler' ) ) {
				return false;
			}
			do_action( 'cache_enabler_clear_complete_cache' );
			return true;
		},
		'Comet Cache'         => function () {
			if ( ! class_exists( 'comet_cache' ) || ! method_exists( 'comet_cache', 'clear' ) ) {
				return false;
			}
			comet_cache::clear();
			return true;
		},
		'Swift Performance'   => function () {
			if ( ! class_exists( 'Swift_Performance_Cache' ) || ! method_exists( 'Swift_Performance_Cache', 'clear_all_cache' ) ) {
				return false;
			}
			Swift_Performance_Cache::clear_all_cache();
			return true;
		},
		'Autoptimize'         => function () {
			if ( ! class_exists( 'autoptimizeCache' ) || ! method_exists( 'autoptimizeCache', 'clearall' ) ) {
				return false;
			}
			autoptimizeCache::clearall();
			return true;
		},
		'Nginx Helper'        => function () {
			if ( ! defined( 'NGINX_HELPER_BASEPATH' ) ) {
				return false;
			}
			do_action( 'rt_nginx_helper_purge_all' );
			return true;
		},
		'WP Engine'           => function () {
			if ( ! class_exists( 'WpeCommon' ) ) {
				return false;
			}
			if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) {
				WpeCommon::purge_memcached();
			}
			if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
				WpeCommon::purge_varnish_cache();
			}
			return true;
		},
	);

	$done = array();
	foreach ( $purgers as $name => $purge ) {
		try {
			if ( $purge() ) {
				$done[] = $name;
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			// Another plugin failing must not stop the reset; that cache is simply not listed.
		}
	}
	return $done;
}

add_action( 'wp_ajax_smb_reset_all', function () {
	smb_ajax_guard();
	update_option( SMB_RESET_OPTION, time(), true ); // autoloaded: read on every page with the banner
	wp_send_json_success( array( 'caches' => smb_purge_page_caches() ) );
} );

add_action( 'wp_ajax_smb_ui_lang', function () {
	smb_ajax_guard();
	$lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : '';
	if ( ! in_array( $lang, array( 'en', 'el' ), true ) ) {
		wp_send_json_error( null, 400 );
	}
	update_user_meta( get_current_user_id(), 'smb_ui_lang', $lang );
	wp_send_json_success();
} );

/* ---------- Page ---------- */

function smb_render_lang_panel( $lang, $info, $c, $multilingual ) {
	$field = function ( $key ) use ( $lang ) {
		return esc_attr( SMB_OPTION . '[content][' . $lang . '][' . $key . ']' );
	};
	$uid = 'smb-' . sanitize_key( $lang );
	?>
	<div class="smb-langpanel" data-lang="<?php echo esc_attr( $lang ); ?>">
		<section class="smb-card">
			<header class="smb-card__head">
				<span class="smb-step">1</span>
				<div>
					<h2><?php esc_html_e( 'What do you want to promote?', 'smartbanner' ); ?></h2>
					<p><?php esc_html_e( 'Pick one of your blog posts, or write your own message.', 'smartbanner' ); ?></p>
				</div>
			</header>

			<?php if ( $multilingual ) : ?>
				<label class="smb-toggle smb-toggle--lang">
					<span class="smb-toggle__text">
						<?php /* translators: %s: language name */ ?>
						<strong><?php echo esc_html( sprintf( __( 'Show the banner in %s', 'smartbanner' ), $info['name'] ) ); ?></strong>
						<small><?php esc_html_e( 'Switch off to show nothing on the pages of this language.', 'smartbanner' ); ?></small>
					</span>
					<span class="smb-switch"><input type="checkbox" name="<?php echo $field( 'active' ); // phpcs:ignore ?>" value="1" <?php checked( $c['active'] ); ?>><span class="smb-switch__track"></span></span>
				</label>
			<?php endif; ?>

			<div class="smb-lang-body">
				<div class="smb-seg" role="radiogroup" aria-label="<?php esc_attr_e( 'Source', 'smartbanner' ); ?>">
					<label><input type="radio" name="<?php echo $field( 'source' ); // phpcs:ignore ?>" value="post" <?php checked( $c['source'], 'post' ); ?>><span><?php echo smb_icon( 'post' ); // phpcs:ignore ?><?php esc_html_e( 'A blog post', 'smartbanner' ); ?></span></label>
					<label><input type="radio" name="<?php echo $field( 'source' ); // phpcs:ignore ?>" value="custom" <?php checked( $c['source'], 'custom' ); ?>><span><?php echo smb_icon( 'pen' ); // phpcs:ignore ?><?php esc_html_e( 'My own message', 'smartbanner' ); ?></span></label>
				</div>

				<div class="smb-picker" data-show-when="post">
					<span class="smb-field__label" id="<?php echo esc_attr( $uid ); ?>-dd-label"><?php esc_html_e( 'Post', 'smartbanner' ); ?></span>
					<div class="smb-dd">
						<button type="button" class="smb-dd__trigger" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="<?php echo esc_attr( $uid ); ?>-dd-label <?php echo esc_attr( $uid ); ?>-dd-value">
							<span class="smb-dd__thumb"></span>
							<span class="smb-dd__text" id="<?php echo esc_attr( $uid ); ?>-dd-value"><span class="smb-dd__title"></span><span class="smb-dd__meta"></span></span>
							<span class="smb-dd__chev"><?php echo smb_icon( 'chevron' ); // phpcs:ignore ?></span>
						</button>
						<div class="smb-dd__panel" role="listbox" tabindex="-1" aria-labelledby="<?php echo esc_attr( $uid ); ?>-dd-label" hidden></div>
					</div>
					<p class="smb-field__hint smb-autotr" hidden></p>
					<input type="hidden" name="<?php echo $field( 'post_id' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $c['post_id'] ); ?>">
				</div>
			</div>
			<p class="smb-lang-off" hidden><?php esc_html_e( 'Nothing is shown in this language. Switch it on above when you want a banner here too.', 'smartbanner' ); ?></p>
		</section>

		<section class="smb-card smb-lang-body">
			<header class="smb-card__head">
				<span class="smb-step">2</span>
				<div>
					<h2><?php esc_html_e( 'Text, image and button', 'smartbanner' ); ?></h2>
					<p class="smb-content-sub"></p>
				</div>
			</header>

			<div class="smb-fields">
				<div class="smb-field">
					<label class="smb-field__label" for="<?php echo esc_attr( $uid ); ?>-label"><?php esc_html_e( 'Small label', 'smartbanner' ); ?> <small><?php esc_html_e( '(above the title)', 'smartbanner' ); ?></small></label>
					<input class="smb-input" type="text" id="<?php echo esc_attr( $uid ); ?>-label" name="<?php echo $field( 'label' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $c['label'] ); ?>" placeholder="<?php esc_attr_e( 'Leave empty to hide it', 'smartbanner' ); ?>">
				</div>
				<div class="smb-field">
					<label class="smb-field__label" for="<?php echo esc_attr( $uid ); ?>-title"><?php esc_html_e( 'Title', 'smartbanner' ); ?></label>
					<input class="smb-input" type="text" id="<?php echo esc_attr( $uid ); ?>-title" name="<?php echo $field( 'title' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $c['title'] ); ?>">
				</div>
				<div class="smb-field">
					<label class="smb-field__label" for="<?php echo esc_attr( $uid ); ?>-text"><?php esc_html_e( 'Short text', 'smartbanner' ); ?></label>
					<textarea class="smb-input" id="<?php echo esc_attr( $uid ); ?>-text" rows="3" name="<?php echo $field( 'text' ); // phpcs:ignore ?>"><?php echo esc_textarea( $c['text'] ); ?></textarea>
					<p class="smb-field__hint"><span><?php esc_html_e( 'About three lines fit in the banner.', 'smartbanner' ); ?></span><span class="smb-counter"></span></p>
				</div>
				<div class="smb-field">
					<span class="smb-field__label"><?php esc_html_e( 'Image', 'smartbanner' ); ?></span>
					<div class="smb-image">
						<span class="smb-image__thumb"><?php echo smb_icon( 'image' ); // phpcs:ignore ?></span>
						<div class="smb-image__body">
							<strong class="smb-image__title"></strong>
							<span class="smb-image__sub"></span>
							<div class="smb-image__actions">
								<button type="button" class="smb-btn smb-btn--soft smb-image-choose"></button>
								<button type="button" class="smb-link smb-image-remove"></button>
							</div>
						</div>
						<input type="hidden" name="<?php echo $field( 'image_id' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $c['image_id'] ); ?>">
					</div>
				</div>
				<div class="smb-row">
					<div class="smb-field">
						<label class="smb-field__label" for="<?php echo esc_attr( $uid ); ?>-btn-text"><?php esc_html_e( 'Button text', 'smartbanner' ); ?></label>
						<input class="smb-input" type="text" id="<?php echo esc_attr( $uid ); ?>-btn-text" name="<?php echo $field( 'button_text' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $c['button_text'] ); ?>" placeholder="<?php esc_attr_e( 'Leave empty to hide the button', 'smartbanner' ); ?>">
					</div>
					<div class="smb-field">
						<label class="smb-field__label" for="<?php echo esc_attr( $uid ); ?>-btn-url"><?php esc_html_e( 'Button link', 'smartbanner' ); ?></label>
						<input class="smb-input" type="url" id="<?php echo esc_attr( $uid ); ?>-btn-url" name="<?php echo $field( 'button_url' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $c['button_url'] ); ?>">
					</div>
				</div>
				<label class="smb-toggle">
					<span class="smb-toggle__text">
						<strong><?php esc_html_e( 'Open the link in a new tab', 'smartbanner' ); ?></strong>
						<small><?php esc_html_e( 'Useful when the link goes to another website.', 'smartbanner' ); ?></small>
					</span>
					<span class="smb-switch"><input type="checkbox" name="<?php echo $field( 'new_tab' ); // phpcs:ignore ?>" value="1" <?php checked( $c['new_tab'] ); ?>><span class="smb-switch__track"></span></span>
				</label>
			</div>
		</section>
	</div>
	<?php
}

function smb_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s            = smb_get_settings();
	$langs        = smb_languages();
	$multilingual = (bool) $langs;
	$field        = function ( $key ) {
		return esc_attr( SMB_OPTION . '[' . $key . ']' );
	};

	// Keyed by hours.
	$cookie_presets = array(
		0    => __( 'Until the browser closes', 'smartbanner' ),
		1    => __( '1 hour', 'smartbanner' ),
		6    => __( '6 hours', 'smartbanner' ),
		24   => __( '1 day', 'smartbanner' ),
		168  => __( '7 days', 'smartbanner' ),
		720  => __( '30 days', 'smartbanner' ),
		2160 => __( '90 days', 'smartbanner' ),
		8760 => __( '1 year', 'smartbanner' ),
	);
	$h = (int) $s['cookie_hours'];
	if ( ! isset( $cookie_presets[ $h ] ) ) {
		$cookie_presets[ $h ] = 0 === $h % 24
			/* translators: %d: number of days */
			? sprintf( _n( '%d day', '%d days', $h / 24, 'smartbanner' ), $h / 24 )
			/* translators: %d: number of hours */
			: sprintf( _n( '%d hour', '%d hours', $h, 'smartbanner' ), $h );
		ksort( $cookie_presets );
	}

	$bg_presets = array( '#141413', '#1f6f3a', '#2563eb', '#7c3aed', '#d97757', '#e11d48' );
	$fg_presets = array( '#ffffff', '#141413' );
	$ui_lang    = smb_ui_lang();
	?>
	<div class="wrap smb-admin">
		<form id="smb-form" class="<?php echo $s['enabled'] ? '' : 'is-off'; ?>" method="post" action="options.php" novalidate>
			<?php settings_fields( 'smartbanner_group' ); ?>

			<header class="smb-top">
				<div class="smb-brand">
					<span class="smb-brand__logo"><?php echo smb_icon( 'logo' ); // phpcs:ignore ?></span>
					<div>
						<h1><?php esc_html_e( 'SmartBanner', 'smartbanner' ); ?> <span class="smb-pill" id="smb-pill"></span></h1>
						<p><?php esc_html_e( 'Promote a blog post in the corner of every page.', 'smartbanner' ); ?></p>
					</div>
				</div>
				<div class="smb-top__actions">
					<span class="smb-savestate" id="smb-savestate" aria-live="polite"></span>
					<div class="smb-seg smb-seg--sm smb-uilang" role="group" aria-label="<?php esc_attr_e( 'Language of this screen', 'smartbanner' ); ?>" title="<?php esc_attr_e( 'Language of this screen', 'smartbanner' ); ?>">
						<button type="button" data-ui-lang="en" aria-pressed="<?php echo 'en' === $ui_lang ? 'true' : 'false'; ?>" lang="en">EN</button>
						<button type="button" data-ui-lang="el" aria-pressed="<?php echo 'el' === $ui_lang ? 'true' : 'false'; ?>" lang="el">ΕΛ</button>
					</div>
					<a class="smb-btn smb-btn--ghost smb-hide-off" id="smb-preview-link" href="<?php echo esc_url( smb_preview_url( smb_lang_keys()[0] ) ); ?>" target="_blank" rel="noopener">
						<?php echo smb_icon( 'eye' ); // phpcs:ignore ?><span><?php esc_html_e( 'Preview on site', 'smartbanner' ); ?></span>
					</a>
					<button type="submit" class="smb-btn smb-btn--primary smb-hide-off" id="smb-save"><?php esc_html_e( 'Save changes', 'smartbanner' ); ?></button>
				</div>
			</header>
			<hr class="wp-header-end">

			<div class="smb-grid">
				<div class="smb-main">

					<section class="smb-card smb-status" id="smb-status">
						<span class="smb-status__icon"><?php echo smb_icon( 'power' ); // phpcs:ignore ?></span>
						<div class="smb-status__body">
							<h2><?php esc_html_e( 'Show the banner on my site', 'smartbanner' ); ?></h2>
							<p class="smb-status__text" id="smb-status-text"></p>
						</div>
						<label class="smb-switch smb-switch--lg">
							<input type="checkbox" name="<?php echo $field( 'enabled' ); // phpcs:ignore ?>" value="1" <?php checked( $s['enabled'] ); ?>>
							<span class="smb-switch__track"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Show the banner on my site', 'smartbanner' ); ?></span>
						</label>
					</section>

					<?php if ( $multilingual ) : ?>
						<section class="smb-card smb-langbar smb-hide-off">
							<div class="smb-langbar__head">
								<span class="smb-langbar__icon"><?php echo smb_icon( 'globe' ); // phpcs:ignore ?></span>
								<div>
									<h2><?php esc_html_e( 'Languages', 'smartbanner' ); ?></h2>
									<p><?php esc_html_e( 'Your site has more than one language. Choose what the banner shows in each one.', 'smartbanner' ); ?></p>
								</div>
							</div>
							<div class="smb-langtabs" role="tablist" aria-label="<?php esc_attr_e( 'Languages', 'smartbanner' ); ?>">
								<?php foreach ( $langs as $code => $info ) : ?>
									<button type="button" class="smb-langtab" role="tab" data-lang-tab="<?php echo esc_attr( $code ); ?>" aria-selected="<?php echo $info['default'] ? 'true' : 'false'; ?>">
										<?php if ( $info['flag'] ) : ?>
											<img src="<?php echo esc_url( $info['flag'] ); ?>" alt="" width="18" height="12">
										<?php endif; ?>
										<span><?php echo esc_html( $info['name'] ); ?></span>
										<i class="smb-langtab__dot" aria-hidden="true"></i>
									</button>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>

					<div class="smb-hide-off">
						<?php
						foreach ( smb_lang_keys() as $key ) {
							$info = isset( $langs[ $key ] ) ? $langs[ $key ] : array( 'name' => '' );
							smb_render_lang_panel( $key, $info, smb_get_content( $key, $s ), $multilingual );
						}
						?>
					</div>

					<section class="smb-card smb-hide-off">
						<header class="smb-card__head">
							<span class="smb-step">3</span>
							<div>
								<h2><?php esc_html_e( 'When does it appear?', 'smartbanner' ); ?></h2>
								<p><?php esc_html_e( 'A short delay feels less intrusive than showing it instantly.', 'smartbanner' ); ?></p>
							</div>
						</header>
						<div class="smb-fields">
							<div class="smb-field">
								<label class="smb-field__label" for="smb-delay"><?php esc_html_e( 'Show it after', 'smartbanner' ); ?></label>
								<div class="smb-stepper">
									<button type="button" data-step="-1" aria-label="<?php esc_attr_e( 'Less', 'smartbanner' ); ?>">−</button>
									<input type="number" id="smb-delay" min="0" max="120" step="0.5" name="<?php echo $field( 'delay' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $s['delay'] ); ?>">
									<span><?php esc_html_e( 'seconds', 'smartbanner' ); ?></span>
									<button type="button" data-step="1" aria-label="<?php esc_attr_e( 'More', 'smartbanner' ); ?>">+</button>
								</div>
							</div>
							<div class="smb-field">
								<span class="smb-field__label" id="smb-cookie-label"><?php esc_html_e( 'When a visitor closes it, keep it hidden for', 'smartbanner' ); ?></span>
								<div class="smb-chips" role="radiogroup" aria-labelledby="smb-cookie-label">
									<?php foreach ( $cookie_presets as $hours => $label ) : ?>
										<label class="smb-chip"><input type="radio" name="<?php echo $field( 'cookie_hours' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $hours ); ?>" <?php checked( $h, $hours ); ?>><span><?php echo esc_html( $label ); ?></span></label>
									<?php endforeach; ?>
								</div>
								<p class="smb-field__hint"><span><?php esc_html_e( 'When you promote a different post, everyone sees the banner again, even if they closed the previous one.', 'smartbanner' ); ?></span></p>
							</div>
							<div class="smb-reset">
								<span class="smb-toggle__text">
									<strong><?php esc_html_e( 'Closed it and want to see it again?', 'smartbanner' ); ?></strong>
									<small><?php esc_html_e( 'Brings the banner back in this browser straight away. Your visitors are not affected.', 'smartbanner' ); ?></small>
								</span>
								<button type="button" class="smb-btn smb-btn--ghost smb-btn--sm" id="smb-show-again"><?php echo smb_icon( 'eye' ); // phpcs:ignore ?><span><?php esc_html_e( 'Show it to me again', 'smartbanner' ); ?></span></button>
							</div>
							<div class="smb-reset">
								<span class="smb-toggle__text">
									<strong><?php esc_html_e( 'Want everyone to see it again?', 'smartbanner' ); ?></strong>
									<small><?php esc_html_e( 'Brings the banner back for all visitors, even those who closed it, and clears the site cache.', 'smartbanner' ); ?></small>
								</span>
								<button type="button" class="smb-btn smb-btn--ghost smb-btn--sm" id="smb-reset-all"><?php echo smb_icon( 'refresh' ); // phpcs:ignore ?><span><?php esc_html_e( 'Show it to everyone again', 'smartbanner' ); ?></span></button>
							</div>
						</div>
					</section>

					<section class="smb-card smb-hide-off">
						<header class="smb-card__head">
							<span class="smb-step">4</span>
							<div>
								<h2><?php esc_html_e( 'Look and position', 'smartbanner' ); ?></h2>
								<p><?php esc_html_e( 'The banner uses your theme fonts. Choose where it sits and the button colours.', 'smartbanner' ); ?></p>
							</div>
						</header>
						<div class="smb-fields">
							<div class="smb-field">
								<span class="smb-field__label"><?php esc_html_e( 'Position', 'smartbanner' ); ?></span>
								<div class="smb-positions" role="radiogroup" aria-label="<?php esc_attr_e( 'Position', 'smartbanner' ); ?>">
									<?php
									foreach ( array(
										'left'  => __( 'Bottom left', 'smartbanner' ),
										'right' => __( 'Bottom right', 'smartbanner' ),
									) as $pos => $label ) :
										?>
										<label class="smb-pos smb-pos--<?php echo esc_attr( $pos ); ?>">
											<input type="radio" name="<?php echo $field( 'position' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $pos ); ?>" <?php checked( $s['position'], $pos ); ?>>
											<span class="smb-pos__screen"><i class="smb-pos__card"></i></span>
											<span class="smb-pos__label"><?php echo esc_html( $label ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="smb-row">
								<div class="smb-field">
									<label class="smb-field__label" for="smb-offset-x"><?php esc_html_e( 'Distance from the side', 'smartbanner' ); ?></label>
									<span class="smb-unit" data-unit="px"><input class="smb-input" type="number" id="smb-offset-x" min="0" max="300" name="<?php echo $field( 'offset_x' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $s['offset_x'] ); ?>"></span>
								</div>
								<div class="smb-field">
									<label class="smb-field__label" for="smb-offset-y"><?php esc_html_e( 'Distance from the bottom', 'smartbanner' ); ?></label>
									<span class="smb-unit" data-unit="px"><input class="smb-input" type="number" id="smb-offset-y" min="0" max="300" name="<?php echo $field( 'offset_y' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $s['offset_y'] ); ?>"></span>
								</div>
							</div>
							<p class="smb-field__hint smb-field__hint--top"><span><?php esc_html_e( 'Covering the theme\'s "back to top" button? Increase the distance from the bottom.', 'smartbanner' ); ?></span></p>

							<div class="smb-field">
								<label class="smb-field__label" for="smb-z"><?php esc_html_e( 'Layer (z-index)', 'smartbanner' ); ?></label>
								<input class="smb-input smb-input--short" type="number" id="smb-z" min="1" max="999999" step="1" name="<?php echo $field( 'z_index' ); // phpcs:ignore ?>" value="<?php echo esc_attr( $s['z_index'] ); ?>">
								<p class="smb-field__hint"><span><?php esc_html_e( 'Showing on top of your theme\'s mobile menu or a popup? Lower this number. Hidden behind something? Raise it. The default is 99.', 'smartbanner' ); ?></span></p>
							</div>

							<div class="smb-colors">
								<?php
								foreach ( array(
									'button_bg'    => array( __( 'Button colour', 'smartbanner' ), $bg_presets ),
									'button_color' => array( __( 'Button text colour', 'smartbanner' ), $fg_presets ),
								) as $key => $cfg ) :
									?>
									<div class="smb-color">
										<span class="smb-field__label"><?php echo esc_html( $cfg[0] ); ?></span>
										<div class="smb-swatches">
											<?php foreach ( $cfg[1] as $hex ) : ?>
												<button type="button" class="smb-swatch" data-color="<?php echo esc_attr( $hex ); ?>" style="background:<?php echo esc_attr( $hex ); ?>" aria-label="<?php echo esc_attr( $hex ); ?>"></button>
											<?php endforeach; ?>
										</div>
										<span class="smb-colorinput">
											<input type="color" value="<?php echo esc_attr( $s[ $key ] ); ?>" aria-label="<?php echo esc_attr( $cfg[0] ); ?>">
											<input type="text" maxlength="7" spellcheck="false" name="<?php echo $field( $key ); // phpcs:ignore ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" aria-label="<?php echo esc_attr( $cfg[0] . ' (HEX)' ); ?>">
										</span>
									</div>
								<?php endforeach; ?>
							</div>
							<p class="smb-contrast" id="smb-contrast" hidden><?php esc_html_e( 'These two colours are too close. The button text may be hard to read.', 'smartbanner' ); ?></p>

							<div class="smb-toggles">
								<label class="smb-toggle">
									<span class="smb-toggle__text">
										<strong><?php esc_html_e( 'Show on mobile', 'smartbanner' ); ?></strong>
										<small><?php esc_html_e( 'On phones the banner spans the width of the screen.', 'smartbanner' ); ?></small>
									</span>
									<span class="smb-switch"><input type="checkbox" name="<?php echo $field( 'show_mobile' ); // phpcs:ignore ?>" value="1" <?php checked( $s['show_mobile'] ); ?>><span class="smb-switch__track"></span></span>
								</label>
								<label class="smb-toggle">
									<span class="smb-toggle__text">
										<strong><?php esc_html_e( 'Hide the image on mobile', 'smartbanner' ); ?></strong>
										<small><?php esc_html_e( 'Makes the banner smaller on small screens.', 'smartbanner' ); ?></small>
									</span>
									<span class="smb-switch"><input type="checkbox" name="<?php echo $field( 'hide_image_mobile' ); // phpcs:ignore ?>" value="1" <?php checked( $s['hide_image_mobile'] ); ?>><span class="smb-switch__track"></span></span>
								</label>
							</div>
						</div>
					</section>
				</div>

				<aside class="smb-side smb-hide-off">
					<div class="smb-card smb-previewcard">
						<div class="smb-previewcard__head">
							<h2><?php esc_html_e( 'Live preview', 'smartbanner' ); ?> <span class="smb-previewcard__lang" id="smb-preview-lang"></span></h2>
							<div class="smb-seg smb-seg--sm">
								<button type="button" data-device-btn="desktop" aria-pressed="true"><?php esc_html_e( 'Desktop', 'smartbanner' ); ?></button>
								<button type="button" data-device-btn="mobile" aria-pressed="false"><?php esc_html_e( 'Mobile', 'smartbanner' ); ?></button>
							</div>
						</div>
						<div class="smb-stage" id="smb-stage" data-device="desktop">
							<div class="smb-browser">
								<div class="smb-browser__bar"><i></i><i></i><i></i><span id="smb-preview-address"></span></div>
								<div class="smb-browser__page" aria-hidden="true"><div class="smb-viewport" id="smb-viewport">
									<div class="smb-skel"><i class="h"></i><i></i><i style="width:85%"></i><i class="img"></i><i></i><i style="width:70%"></i><i style="width:90%"></i></div>

									<div class="smb" id="smb-preview">
										<div class="smb__body">
											<p class="smb__label"><svg viewBox="0 0 16 16"><circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" stroke-width="1.2"/><path d="M8 7.2v3.6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/><circle cx="8" cy="5.1" r=".8" fill="currentColor"/></svg><span></span></p>
											<p class="smb__title"></p>
											<p class="smb__text"></p>
											<span class="smb__btn"></span>
										</div>
										<span class="smb__media"><img class="smb__img" alt=""></span>
										<span class="smb__close"><svg viewBox="0 0 16 16"><path d="M4 4l8 8M12 4l-8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></span>
									</div>

									<div class="smb-stage__msg" id="smb-preview-msg" hidden></div>
								</div></div>
							</div>
						</div>
						<p class="smb-previewcard__note"><?php esc_html_e( 'Changes appear here as you type. Save to publish them on your site.', 'smartbanner' ); ?></p>
					</div>
				</aside>
			</div>
		</form>
		<div class="smb-toast" id="smb-toast" role="status" aria-live="polite"></div>
	</div>
	<?php
}
