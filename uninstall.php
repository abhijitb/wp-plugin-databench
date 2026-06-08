<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * @package WP_DataBench
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wp_databench_enabled' );
delete_option( 'wp_databench_read_only' );
delete_option( 'wp_databench_ip_allowlist' );
delete_option( 'wp_databench_unlock_password' );
