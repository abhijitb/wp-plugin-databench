<?php
defined( 'ABSPATH' ) || exit;

class WP_DataBench_Admin_Page {

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
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wp-databench' ) );
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
				'restUrl' => esc_url_raw( rest_url( 'wp-databench/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'dbName'  => DB_NAME,
			)
		);

		require WP_DATABENCH_DIR . 'templates/admin-page.php';
	}
}
