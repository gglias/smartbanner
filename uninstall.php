<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Leave nothing behind: the settings (on every site of a multisite network) and every user's EN/ΕΛ choice.
if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'smartbanner_settings' );
		delete_option( 'smartbanner_reset' );
		restore_current_blog();
	}
} else {
	delete_option( 'smartbanner_settings' );
	delete_option( 'smartbanner_reset' );
}
delete_metadata( 'user', 0, 'smb_ui_lang', '', true );
