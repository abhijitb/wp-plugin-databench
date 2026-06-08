<?php
/**
 * REST API route registration under the wp-databench/v1 namespace.
 *
 * @package WP_DataBench
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers REST API routes for WP DataBench.
 */
class WP_DataBench_REST_API {

	const NS = 'wp-databench/v1';

	/**
	 * Registers all wp-databench/v1 REST routes on the rest_api_init hook.
	 */
	public static function register_routes() {
		$perm = array( 'WP_DataBench_Access_Guard', 'permission_callback' );

		register_rest_route(
			self::NS,
			'/tables',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'WP_DataBench_DB_Explorer', 'get_tables' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NS,
			'/tables/(?P<table>[a-zA-Z0-9_]+)/structure',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( 'WP_DataBench_DB_Explorer', 'get_structure' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NS,
			'/tables/(?P<table>[a-zA-Z0-9_]+)/rows',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'WP_DataBench_DB_Explorer', 'get_rows' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( 'WP_DataBench_DB_Explorer', 'insert_row' ),
					'permission_callback' => $perm,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/query',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'WP_DataBench_DB_Explorer', 'execute_query' ),
				'permission_callback' => $perm,
			)
		);

		register_rest_route(
			self::NS,
			'/tables/(?P<table>[a-zA-Z0-9_]+)/rows/(?P<pk>[^/]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'WP_DataBench_DB_Explorer', 'get_row' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( 'WP_DataBench_DB_Explorer', 'update_row' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( 'WP_DataBench_DB_Explorer', 'delete_row' ),
					'permission_callback' => $perm,
				),
			)
		);
	}
}
