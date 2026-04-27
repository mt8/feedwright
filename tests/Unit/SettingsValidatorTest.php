<?php
/**
 * Pure-PHP unit tests for the URL base validator and cache TTL sanitizer.
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Unit;

use Feedwright\Settings;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

final class SettingsValidatorTest extends TestCase {

	public function test_empty_string_is_rejected(): void {
		$result = Settings::validate_url_base( '' );
		$this->assertNotNull( $result['error'] );
	}

	public function test_simple_lowercase_word_passes(): void {
		$result = Settings::validate_url_base( 'feedwright' );
		$this->assertNull( $result['error'], 'Simple lowercase word must validate' );
	}

	public function test_single_character_passes(): void {
		$result = Settings::validate_url_base( 'f' );
		$this->assertNull( $result['error'] );
	}

	public function test_nested_path_passes(): void {
		$result = Settings::validate_url_base( 'news/feeds' );
		$this->assertNull( $result['error'] );
	}

	public function test_uppercase_is_rejected(): void {
		$result = Settings::validate_url_base( 'Feedwright' );
		$this->assertNotNull( $result['error'] );
	}

	public function test_trailing_slash_is_rejected(): void {
		$result = Settings::validate_url_base( 'news/' );
		$this->assertNotNull( $result['error'] );
	}

	public function test_reserved_path_is_rejected(): void {
		foreach ( array( 'wp-admin', 'wp-content', 'wp-includes', 'feed', 'comments', 'wp-json' ) as $reserved ) {
			$result = Settings::validate_url_base( $reserved );
			$this->assertNotNull( $result['error'], "Reserved path '{$reserved}' must be rejected" );
		}
	}

	public function test_reserved_path_only_blocks_at_head(): void {
		// 仕様 §9.2: 「ベースが予約語で始まる」場合のみブロック。中段に含まれるのは OK。
		$result = Settings::validate_url_base( 'public/feed' );
		$this->assertNull( $result['error'] );
	}

	public function test_cache_ttl_clamps_to_zero(): void {
		$this->assertSame( 0, Settings::sanitize_cache_ttl( -1 ) );
		$this->assertSame( 0, Settings::sanitize_cache_ttl( 0 ) );
	}

	public function test_cache_ttl_clamps_to_max(): void {
		$this->assertSame( Settings::MAX_CACHE_TTL, Settings::sanitize_cache_ttl( 100000 ) );
	}

	public function test_cache_ttl_passes_through_valid_values(): void {
		$this->assertSame( 300, Settings::sanitize_cache_ttl( 300 ) );
		$this->assertSame( 60, Settings::sanitize_cache_ttl( '60' ) );
	}

	public function test_cache_ttl_falls_back_on_garbage(): void {
		$this->assertSame( Settings::DEFAULT_CACHE_TTL, Settings::sanitize_cache_ttl( 'abc' ) );
	}
}
