<?php
/**
 * REST endpoint that returns the rendered feed XML for the editor preview.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\REST;

use Feedwright\Plugin;
use Feedwright\PostType;
use Feedwright\Renderer\Renderer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §16.1.
 */
final class PreviewController {

	private const NAMESPACE = 'feedwright/v1';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the preview route.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/preview/(?P<post_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'post_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Permission check: only admins (manage_options) may preview.
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
	 * Render the feed and return JSON { xml, warnings }.
	 *
	 * @param WP_REST_Request $request The REST request.
	 */
	public function handle_request( WP_REST_Request $request ) {
		$post_id = (int) $request->get_param( 'post_id' );
		$post    = get_post( $post_id );
		if ( ! $post || PostType::SLUG !== $post->post_type ) {
			return new WP_Error( 'rest_not_found', __( 'Feed post not found.', 'feedwright' ), array( 'status' => 404 ) );
		}

		$resolver = Plugin::build_resolver();
		$result   = ( new Renderer( $resolver ) )->render( $post );

		return new WP_REST_Response(
			array(
				'xml'      => $result['xml'],
				'warnings' => $result['warnings'],
			),
			200
		);
	}
}
