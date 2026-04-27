<?php
/**
 * REST endpoint that exposes the binding catalogue for editor autocompletion.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\REST;

use Feedwright\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §16.2.
 */
final class BindingIntrospectionController {

	private const NAMESPACE = 'feedwright/v1';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the bindings route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/bindings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'context' => array(
						'type'    => 'string',
						'enum'    => array( 'item', 'channel', 'any' ),
						'default' => 'any',
					),
				),
			)
		);
	}

	/**
	 * Permission check: only admins.
	 *
	 * @param WP_REST_Request $request The REST request.
	 */
	public function check_permission( WP_REST_Request $request ) {
		unset( $request );
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'feedwright' ), array( 'status' => 401 ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'Insufficient permissions.', 'feedwright' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Filter the catalogue for the requested context.
	 *
	 * @param WP_REST_Request $request The REST request.
	 */
	public function handle_request( WP_REST_Request $request ) {
		$context  = (string) $request->get_param( 'context' );
		$resolver = Plugin::build_resolver();
		$rows     = $resolver->describe_all();

		$filtered = array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $context ): bool {
					$row_context = (string) ( $row['context'] ?? 'any' );
					if ( 'any' === $context ) {
						return true;
					}
					if ( 'any' === $row_context ) {
						return true;
					}
					return $context === $row_context;
				}
			)
		);

		return new WP_REST_Response( $filtered, 200 );
	}
}
