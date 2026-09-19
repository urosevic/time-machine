<?php
/**
 * Uninstall procedure for Time Machine plugin for WordPress
 * Purpose of this procedure is to delete plugin settings and defined widgets from database
 *
 * @package TimeMachine
 * @author  Aleksandar Urošević
 * @link    https://urosevic.net
 * @link    https://www.techwebux.com
 * @since   1.0.0
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

if ( ! current_user_can( 'activate_plugins' ) ) {
	return;
}

check_admin_referer( 'bulk-plugins' );

if ( 'time-machine/time-machine.php' !== WP_UNINSTALL_PLUGIN ) {
	return;
}

// Set option names.
$time_machine_settings_option_name   = 'time_machine';
$time_machine_widget_option_name     = 'widget_time-machine';
$time_machine_db_version_option_name = 'time_machine_db_version';

// Delete plugin settings option.
delete_option( $time_machine_settings_option_name );
delete_option( $time_machine_widget_option_name );
delete_option( $time_machine_db_version_option_name );

// Delete plugin settings options in multisite.
delete_site_option( $time_machine_settings_option_name );
delete_site_option( $time_machine_widget_option_name );
delete_site_option( $time_machine_db_version_option_name );
