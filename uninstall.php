<?php
/**
 * Uninstall cleanup for REST Email API Mailer.
 *
 * Runs when the user explicitly deletes the plugin from the Plugins screen.
 * Removes every persisted option, clears cron events and deletes the protected
 * log directory created under uploads.
 *
 * Legacy option names and log directory paths from previous identities of this
 * plugin (`cyberpanel_email_*` in v2.0.0 – v2.1.0 and `cyberpersons_*` prior to
 * that) are also removed as a safety net for installs that uninstalled
 * immediately after upgrading, before the one-time migration had a chance to
 * run.
 *
 * @package Restemap_Plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Uninstall routine wrapped in a function so variables stay in local scope
 * (WordPress coding standards require plugin-scope variables in top-level
 * files to be prefixed; wrapping avoids the prefix noise here).
 */
function restemap_run_uninstall() {
	$options_to_delete = array(
		// Current option keys (v2.2.0+).
		'restemap_api_key',
		'restemap_from_email',
		'restemap_from_name',
		'restemap_enabled',
		'restemap_pending_messages',
		'restemap_account_stats',
		'restemap_migrated_from_legacy',
		// Previous identity (v2.0.0 – v2.1.0).
		'cyberpanel_email_api_key',
		'cyberpanel_email_from_email',
		'cyberpanel_email_from_name',
		'cyberpanel_email_enabled',
		'cyberpanel_email_pending_messages',
		'cyberpanel_email_account_stats',
		'cyberpanel_email_migrated_from_legacy',
		// Original internal release (pre-2.0.0).
		'cyberpersons_api_key',
		'cyberpersons_from_email',
		'cyberpersons_from_name',
		'cyberpersons_enabled',
		'cyberpersons_pending_messages',
		'cyberpersons_account_stats',
	);

	foreach ( $options_to_delete as $option_name ) {
		delete_option( $option_name );
		delete_site_option( $option_name );
	}

	$cron_hooks = array(
		'restemap_check_delivery',
		'cyberpanel_email_check_delivery',
		'cyberpersons_check_delivery',
	);
	foreach ( $cron_hooks as $hook_name ) {
		$next_event = wp_next_scheduled( $hook_name );
		if ( $next_event ) {
			wp_unschedule_event( $next_event, $hook_name );
		}
		wp_clear_scheduled_hook( $hook_name );
	}

	// Clear per-user admin notice transients (current + legacy key).
	$users = get_users( array( 'fields' => 'ID' ) );
	foreach ( $users as $user_id ) {
		delete_transient( 'restemap_notice_' . (int) $user_id );
		delete_transient( 'cp_email_notice_' . (int) $user_id );
	}

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	$uploads = wp_upload_dir( null, false );
	if ( ! empty( $uploads['basedir'] ) && WP_Filesystem() ) {
		global $wp_filesystem;
		$log_dirs = array(
			trailingslashit( $uploads['basedir'] ) . 'restemap',
			trailingslashit( $uploads['basedir'] ) . 'cyberpanel-email',
		);
		foreach ( $log_dirs as $log_dir ) {
			if ( is_dir( $log_dir ) ) {
				$wp_filesystem->delete( $log_dir, true, 'd' );
			}
		}
	}
}

restemap_run_uninstall();
