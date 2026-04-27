<?php
/**
 * PSR-4 オートロード経由でコアクラスを解決できることを確認する。
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AutoloadTest extends TestCase {

	public function test_plugin_class_is_autoloadable(): void {
		$this->assertTrue( class_exists( \Feedwright\Plugin::class ) );
	}

	public function test_post_type_class_is_autoloadable(): void {
		$this->assertTrue( class_exists( \Feedwright\PostType::class ) );
	}

	public function test_post_type_slug_constant(): void {
		$this->assertSame( 'feedwright_feed', \Feedwright\PostType::SLUG );
	}
}
