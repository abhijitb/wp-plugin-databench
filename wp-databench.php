<?php
/**
 * Plugin Name: WP DataBench
 * Description: Browse and edit your WordPress database from the admin area.
 * Version:     0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL-2.0+
 * Text Domain: wp-databench
 *
 * @package WP_DataBench
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_DATABENCH_VERSION', '0.1.0' );
define( 'WP_DATABENCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_DATABENCH_URL', plugin_dir_url( __FILE__ ) );

require_once WP_DATABENCH_DIR . 'includes/class-wp-databench-access-guard.php';
require_once WP_DATABENCH_DIR . 'includes/class-wp-databench-db-explorer.php';
require_once WP_DATABENCH_DIR . 'includes/class-wp-databench-rest-api.php';
require_once WP_DATABENCH_DIR . 'includes/class-wp-databench-admin-page.php';

add_action( 'rest_api_init', array( 'WP_DataBench_REST_API', 'register_routes' ) );
add_action( 'admin_menu', array( 'WP_DataBench_Admin_Page', 'register' ) );
