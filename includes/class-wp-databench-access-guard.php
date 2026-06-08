<?php
/**
 * Access guard — shared REST permission callback.
 *
 * @package WP_DataBench
 */

defined( 'ABSPATH' ) || exit;

/**
 * Access guard.
 */

class WP_DataBench_Access_Guard {

	/**
	 * REST permission callback — requires manage_options.
	 *
	 * @return true|WP_Error
	 */
	public static function permission_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', 'Insufficient permissions.', array( 'status' => 403 ) );
		}
		return true;
	}
}
