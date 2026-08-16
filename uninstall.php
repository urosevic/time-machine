<?php
/*
 * Uninstall procedure for Time Machine plugin for WordPress
 * Purpose of this procedure is to delete plugin settings and defined widgets from database
 *
 */

// security checks
defined( 'ABSPATH' ) OR exit;

if ( ! current_user_can( 'activate_plugins' ) )
	return;

check_admin_referer( 'bulk-plugins' );

if ( 'time-machine/time-machine.php' !== WP_UNINSTALL_PLUGIN )
	return;

// set option names
$settings_option_name = 'time_machine';
$widget_option_name   = 'widget_time-machine';

// delete plugin settings option
delete_option( $settings_option_name );
delete_option( $widget_option_name );

// For site options in multisite
delete_site_option( $settings_option_name );
delete_site_option( $widget_option_name );
