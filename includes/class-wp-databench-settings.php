<?php
/**
 * Settings page — plugin options via the WordPress Settings API.
 *
 * @package WP_DataBench
 */
defined( 'ABSPATH' ) || exit;

/**
 * Manages plugin settings: enabled state, read-only mode, IP allowlist, and write password.
 */
class WP_DataBench_Settings {

	const OPT_ENABLED  = 'wp_databench_enabled';
	const OPT_READONLY = 'wp_databench_read_only';
	const OPT_IP_LIST  = 'wp_databench_ip_allowlist';
	const OPT_PASSWORD = 'wp_databench_unlock_password';

	/**
	 * Registers plugin settings with the WordPress Settings API.
	 */
	public static function register_settings() {
		register_setting( 'wp_databench', self::OPT_ENABLED,  array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( 'wp_databench', self::OPT_READONLY, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ) ) );
		register_setting( 'wp_databench', self::OPT_IP_LIST,  array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'wp_databench', self::OPT_PASSWORD, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_password' ) ) );
	}

	/**
	 * Returns whether the plugin is enabled. Defaults to true.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return get_option( self::OPT_ENABLED, '1' ) !== '0';
	}

	/**
	 * Returns whether the plugin is in read-only mode.
	 *
	 * @return bool
	 */
	public static function is_read_only() {
		return '1' === get_option( self::OPT_READONLY, '0' );
	}

	/**
	 * Returns the parsed IP allowlist as an array of trimmed addresses.
	 * An empty array means all IPs are permitted.
	 *
	 * @return string[]
	 */
	public static function get_ip_list() {
		$raw = trim( (string) get_option( self::OPT_IP_LIST, '' ) );
		if ( '' === $raw ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) );
	}

	/**
	 * Returns whether a write-unlock password is currently configured.
	 *
	 * @return bool
	 */
	public static function has_write_password() {
		return '' !== (string) get_option( self::OPT_PASSWORD, '' );
	}

	/**
	 * Verifies a plain-text password against the stored hash.
	 *
	 * @param string $password Plain-text password to verify.
	 * @return bool
	 */
	public static function verify_password( $password ) {
		$hash = (string) get_option( self::OPT_PASSWORD, '' );
		if ( '' === $hash ) {
			return false;
		}
		return wp_check_password( $password, $hash );
	}

	/**
	 * Sanitize callback — coerces any truthy value to '1', falsy to '0'.
	 *
	 * @param mixed $value
	 * @return string '1' or '0'.
	 */
	public static function sanitize_bool( $value ) {
		return $value ? '1' : '0';
	}

	/**
	 * Sanitize callback for the unlock password field.
	 *
	 * - Empty string  → preserves the existing hash.
	 * - '**clear**'   → removes write-password protection (set by the JS "Remove" checkbox).
	 * - Any other value → hashed and stored.
	 *
	 * @param string $value Raw form input.
	 * @return string Hashed password or empty string.
	 */
	public static function sanitize_password( $value ) {
		$value = (string) $value;
		if ( '**clear**' === $value ) {
			return '';
		}
		if ( '' === $value ) {
			return (string) get_option( self::OPT_PASSWORD, '' );
		}
		return wp_hash_password( $value );
	}

	/**
	 * Renders the plugin settings admin page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'wp-databench' ) );
		}

		$enabled   = self::is_enabled();
		$read_only = self::is_read_only();
		$ip_list   = (string) get_option( self::OPT_IP_LIST, '' );
		$has_pass  = self::has_write_password();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'DataBench — Settings', 'wp-databench' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'wp_databench' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Plugin enabled', 'wp-databench' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( self::OPT_ENABLED ); ?>"
									value="1"
									<?php checked( $enabled ); ?>>
								<?php esc_html_e( 'Enable DataBench', 'wp-databench' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Uncheck to hide the plugin without deactivating it.', 'wp-databench' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Read-only mode', 'wp-databench' ); ?></th>
						<td>
							<label>
								<input type="checkbox"
									name="<?php echo esc_attr( self::OPT_READONLY ); ?>"
									value="1"
									<?php checked( $read_only ); ?>>
								<?php esc_html_e( 'Disable all write operations (insert, update, delete)', 'wp-databench' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'IP allowlist', 'wp-databench' ); ?></th>
						<td>
							<textarea
								name="<?php echo esc_attr( self::OPT_IP_LIST ); ?>"
								rows="5"
								cols="40"
								class="regular-text code"
							><?php echo esc_textarea( $ip_list ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'One IP address per line. Leave blank to allow all IPs. Uses REMOTE_ADDR — may not reflect the real IP behind a reverse proxy.', 'wp-databench' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Write unlock password', 'wp-databench' ); ?></th>
						<td>
							<?php if ( $has_pass ) : ?>
								<p>
									<span class="dashicons dashicons-lock" style="color:#2271b1;vertical-align:middle;margin-right:4px"></span>
									<strong><?php esc_html_e( 'A write password is currently set.', 'wp-databench' ); ?></strong>
								</p>
							<?php endif; ?>
							<input
								type="password"
								id="databench-pw-input"
								name="<?php echo esc_attr( self::OPT_PASSWORD ); ?>"
								value=""
								autocomplete="new-password"
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Requires users to enter this password before insert, update, or delete operations are allowed. Leave blank to keep the existing password.', 'wp-databench' ); ?>
							</p>
							<?php if ( $has_pass ) : ?>
								<p>
									<label>
										<input type="checkbox" id="databench-clear-pw">
										<?php esc_html_e( 'Remove write password', 'wp-databench' ); ?>
									</label>
								</p>
								<script>
								/* Sets a sentinel value so the server-side sanitize callback knows to clear the hash. */
								document.getElementById( 'databench-clear-pw' ).addEventListener( 'change', function () {
									document.getElementById( 'databench-pw-input' ).value = this.checked ? '**clear**' : '';
								} );
								</script>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
