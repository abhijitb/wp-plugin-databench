<?php
/**
 * Access guard — REST permission callbacks and write-unlock endpoint.
 *
 * @package WP_DataBench
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides permission callbacks for all REST routes and handles the unlock flow.
 */
class WP_DataBench_Access_Guard {

	/**
	 * Transient key prefix used to store per-user write tokens (suffixed with user ID).
	 */
	const UNLOCK_TRANSIENT = 'wp_databench_unlock_';

	/**
	 * Permission callback for all read routes.
	 *
	 * Checks: plugin enabled → IP allowlist → manage_options capability.
	 *
	 * @return true|WP_Error
	 */
	public static function permission_callback() {
		if ( ! WP_DataBench_Settings::is_enabled() ) {
			return new WP_Error( 'plugin_disabled', 'DataBench is disabled.', array( 'status' => 503 ) );
		}

		$ip_list = WP_DataBench_Settings::get_ip_list();
		if ( ! empty( $ip_list ) ) {
			$remote_ip = isset( $_SERVER['REMOTE_ADDR'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
				: '';
			if ( ! in_array( $remote_ip, $ip_list, true ) ) {
				return new WP_Error( 'ip_not_allowed', 'Your IP address is not permitted.', array( 'status' => 403 ) );
			}
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Permission callback for write routes (insert, update, delete).
	 *
	 * Extends permission_callback with read-only mode and write-lock token checks.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error
	 */
	public static function write_permission_callback( WP_REST_Request $request ) {
		$auth = self::permission_callback();
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		if ( WP_DataBench_Settings::is_read_only() ) {
			return new WP_Error( 'read_only', 'DataBench is in read-only mode.', array( 'status' => 403 ) );
		}

		if ( WP_DataBench_Settings::has_write_password() ) {
			$token   = (string) $request->get_header( 'x_databench_write_token' );
			$user_id = get_current_user_id();
			$stored  = get_transient( self::UNLOCK_TRANSIENT . $user_id );
			if ( ! $stored || ! hash_equals( $stored, $token ) ) {
				return new WP_Error( 'write_locked', 'Write operations are locked. Unlock DataBench first.', array( 'status' => 403 ) );
			}
		}

		return true;
	}

	/**
	 * POST /unlock — verifies the write password and issues a short-lived write token.
	 *
	 * The token is stored as a transient scoped to the current user and expires in one hour.
	 *
	 * @param WP_REST_Request $request JSON body: { password: string }.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function unlock( WP_REST_Request $request ) {
		if ( ! WP_DataBench_Settings::has_write_password() ) {
			return new WP_Error( 'not_locked', 'No write password is configured.', array( 'status' => 400 ) );
		}

		$password = (string) ( $request->get_param( 'password' ) ?? '' );

		if ( ! WP_DataBench_Settings::verify_password( $password ) ) {
			return new WP_Error( 'wrong_password', 'Incorrect password.', array( 'status' => 403 ) );
		}

		$token   = wp_generate_password( 32, false );
		$user_id = get_current_user_id();
		set_transient( self::UNLOCK_TRANSIENT . $user_id, $token, HOUR_IN_SECONDS );

		return rest_ensure_response( array( 'token' => $token ) );
	}
}
