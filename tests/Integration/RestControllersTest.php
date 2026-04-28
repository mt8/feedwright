<?php
/**
 * Integration tests for the REST endpoints.
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

final class RestControllersTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// REST routes are registered on rest_api_init.
		do_action( 'rest_api_init' );
	}

	private function as_admin(): int {
		$id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $id );
		return $id;
	}

	public function test_preview_endpoint_no_longer_registered(): void {
		// XmlPreviewPanel was removed; the standard editor preview / publish
		// flow replaces it. Confirm /feedwright/v1/preview/{id} 404s.
		$this->as_admin();
		$response = rest_do_request( new WP_REST_Request( 'GET', '/feedwright/v1/preview/1' ) );
		$this->assertSame( 404, $response->get_status() );
	}

	private function bindings_request( string $context ): \WP_REST_Response {
		$request = new WP_REST_Request( 'GET', '/feedwright/v1/bindings' );
		$request->set_query_params( array( 'context' => $context ) );
		return rest_do_request( $request );
	}

	public function test_bindings_returns_full_catalogue_with_any_context(): void {
		$this->as_admin();
		$response = $this->bindings_request( 'any' );
		$this->assertSame( 200, $response->get_status() );

		$rows        = $response->get_data();
		$expressions = array_column( $rows, 'expression' );
		$this->assertContains( 'option.home_url', $expressions );
		$this->assertContains( 'post.post_title', $expressions );
		$this->assertContains( 'feed.last_build_date:r', $expressions );
	}

	public function test_bindings_filters_to_item_context(): void {
		$this->as_admin();
		$response = $this->bindings_request( 'item' );
		$this->assertSame( 200, $response->get_status() );
		$rows = $response->get_data();
		foreach ( $rows as $row ) {
			$this->assertContains(
				$row['context'],
				array( 'item', 'any' ),
				"Row '{$row['expression']}' has context '{$row['context']}' but should be item or any"
			);
		}
		$expressions = array_column( $rows, 'expression' );
		$this->assertContains( 'post.post_title', $expressions );
	}

	public function test_bindings_filters_to_channel_context(): void {
		$this->as_admin();
		$response = $this->bindings_request( 'channel' );
		$this->assertSame( 200, $response->get_status() );
		$rows = $response->get_data();
		foreach ( $rows as $row ) {
			$this->assertContains(
				$row['context'],
				array( 'channel', 'any' ),
				"Row '{$row['expression']}' has context '{$row['context']}' but should be channel or any"
			);
		}
		$expressions = array_column( $rows, 'expression' );
		$this->assertNotContains( 'post.post_title', $expressions, 'post.* should be filtered out for channel context' );
		$this->assertContains( 'option.home_url', $expressions, 'option.* (any) should still be present' );
	}

	public function test_bindings_rejects_anonymous(): void {
		wp_set_current_user( 0 );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/feedwright/v1/bindings' ) );
		$this->assertSame( 401, $response->get_status() );
	}
}
