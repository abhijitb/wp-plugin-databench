<?php
/**
 * Admin page registration, asset enqueuing, and script localisation.
 *
 * @package WP_DataBench
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the DataBench admin page.
 */
class WP_DataBench_Admin_Page {

	/**
	 * Registers the DataBench top-level menu page and the Settings submenu.
	 */
	public static function register() {
		add_menu_page(
			__( 'DataBench', 'wp-databench' ),
			__( 'DataBench', 'wp-databench' ),
			'manage_options',
			'wp-databench',
			array( __CLASS__, 'render' ),
			'dashicons-database',
			80
		);

		add_submenu_page(
			'wp-databench',
			__( 'DataBench Settings', 'wp-databench' ),
			__( 'Settings', 'wp-databench' ),
			'manage_options',
			'wp-databench-settings',
			array( 'WP_DataBench_Settings', 'render_page' )
		);
	}

	/**
	 * Renders the admin page — enqueues assets, localises the script, and outputs the template.
	 * Shows a disabled notice if the plugin has been turned off in Settings.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wp-databench' ) );
		}

		if ( ! WP_DataBench_Settings::is_enabled() ) {
			$settings_url = admin_url( 'admin.php?page=wp-databench-settings' );
			echo '<div class="wrap"><p>' .
				esc_html__( 'DataBench is currently disabled.', 'wp-databench' ) .
				' <a href="' . esc_url( $settings_url ) . '">' .
				esc_html__( 'Go to Settings', 'wp-databench' ) .
				'</a></p></div>';
			return;
		}

		wp_enqueue_style(
			'wp-databench',
			WP_DATABENCH_URL . 'assets/css/style.css',
			array(),
			WP_DATABENCH_VERSION
		);

		wp_enqueue_script(
			'wp-databench',
			WP_DATABENCH_URL . 'assets/js/app.js',
			array(),
			WP_DATABENCH_VERSION,
			true
		);

		wp_localize_script(
			'wp-databench',
			'wpDataBench',
			array(
				'restUrl'     => esc_url_raw( rest_url( 'wp-databench/v1/' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'dbName'      => DB_NAME,
				'readOnly'    => WP_DataBench_Settings::is_read_only(),
				'writeLocked' => WP_DataBench_Settings::has_write_password(),
			)
		);

		require WP_DATABENCH_DIR . 'templates/admin-page.php';
	}
}
