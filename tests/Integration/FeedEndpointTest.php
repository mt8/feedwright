<?php
/**
 * Feed routing と最小レンダラを検証する Integration テスト。
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Integration;

use Feedwright\PostType;
use Feedwright\Routing\FeedEndpoint;
use Feedwright\Settings;
use WP_UnitTestCase;

final class FeedEndpointTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		// Pretty permalinks are required for our rewrite rule to be registered.
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( Settings::OPTION_URL_BASE, Settings::DEFAULT_URL_BASE );

		global $wp_rewrite;
		$wp_rewrite->init();

		// Settings / FeedEndpoint are wired in Plugin::__construct, but the test
		// runs without re-bootstrapping; explicitly register so rewrites pick up
		// the current url base.
		( new FeedEndpoint() )->register_rewrites();
	}

	public function test_rewrite_rule_is_registered(): void {
		global $wp_rewrite;
		$rules = $wp_rewrite->wp_rewrite_rules();
		$this->assertIsArray( $rules );

		$expected_key   = '^' . Settings::DEFAULT_URL_BASE . '/([^/]+)/?$';
		$expected_value = 'index.php?' . FeedEndpoint::QUERY_VAR . '=$matches[1]';

		$this->assertArrayHasKey( $expected_key, $rules );
		$this->assertSame( $expected_value, $rules[ $expected_key ] );
	}

	public function test_feed_url_helper_uses_configured_base(): void {
		update_option( Settings::OPTION_URL_BASE, 'news/feeds' );
		$url = FeedEndpoint::feed_url( 'demo' );
		$this->assertStringEndsWith( '/news/feeds/demo/', $url );
	}

}
