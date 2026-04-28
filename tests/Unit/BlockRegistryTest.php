<?php
/**
 * Unit tests for BlockRegistry static helpers.
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Unit;

use Feedwright\BlockRegistry;
use PHPUnit\Framework\TestCase;

final class BlockRegistryTest extends TestCase {

	public function test_block_dirs_contains_nine_entries(): void {
		$this->assertCount( 9, BlockRegistry::BLOCK_DIRS );
	}

	public function test_block_names_are_namespaced(): void {
		$names = BlockRegistry::block_names();
		$this->assertCount( 9, $names );
		foreach ( $names as $name ) {
			$this->assertStringStartsWith( 'feedwright/', $name );
		}
	}

	public function test_includes_expected_blocks(): void {
		$names = BlockRegistry::block_names();
		foreach ( array(
			'feedwright/rss',
			'feedwright/channel',
			'feedwright/element',
			'feedwright/item-query',
			'feedwright/item',
			'feedwright/sub-query',
			'feedwright/sub-item',
			'feedwright/raw',
			'feedwright/comment',
		) as $expected ) {
			$this->assertContains( $expected, $names, "{$expected} should be registered" );
		}
	}
}
