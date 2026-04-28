<?php
/**
 * Integration: ブロックが WP_Block_Type_Registry に登録されることを検証。
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Integration;

use Feedwright\BlockRegistry;
use Feedwright\BlockRestriction;
use Feedwright\PostType;
use WP_Block_Editor_Context;
use WP_Block_Type_Registry;
use WP_UnitTestCase;

final class BlockRegistrationTest extends WP_UnitTestCase {

	public function test_all_blocks_are_registered(): void {
		$registry = WP_Block_Type_Registry::get_instance();
		foreach ( BlockRegistry::block_names() as $name ) {
			$this->assertNotFalse(
				$registry->get_registered( $name ),
				"Block '{$name}' must be registered."
			);
		}
	}

	public function test_block_metadata_matches_spec(): void {
		$registry = WP_Block_Type_Registry::get_instance();

		$rss = $registry->get_registered( 'feedwright/rss' );
		$this->assertNotFalse( $rss );
		$this->assertSame( 'feedwright', $rss->category );

		$item_query = $registry->get_registered( 'feedwright/item-query' );
		$this->assertNotFalse( $item_query );
		$this->assertSame( array( 'feedwright/channel' ), $item_query->parent );

		$element = $registry->get_registered( 'feedwright/element' );
		$this->assertNotFalse( $element );
		$this->assertSame( array( 'feedwright/rss' ), $element->ancestor );

		$sub_query = $registry->get_registered( 'feedwright/sub-query' );
		$this->assertNotFalse( $sub_query );
		$this->assertSame( array( 'feedwright/item' ), $sub_query->ancestor );

		$sub_item = $registry->get_registered( 'feedwright/sub-item' );
		$this->assertNotFalse( $sub_item );
		$this->assertSame( array( 'feedwright/sub-query' ), $sub_item->parent );

		$when = $registry->get_registered( 'feedwright/when' );
		$this->assertNotFalse( $when );
		$this->assertSame( array( 'feedwright/rss' ), $when->ancestor );
	}

	public function test_inserter_is_restricted_to_feedwright_blocks_for_feed_post(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => PostType::SLUG,
				'post_status' => 'publish',
			)
		);
		$post = get_post( $post_id );
		$this->assertNotNull( $post );

		$context = new WP_Block_Editor_Context( array( 'post' => $post ) );
		$allowed = ( new BlockRestriction() )->filter_allowed_blocks( true, $context );

		$this->assertIsArray( $allowed );
		$this->assertSame( BlockRegistry::block_names(), $allowed );
	}

	public function test_inserter_is_unaffected_for_other_post_types(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);
		$post = get_post( $post_id );
		$this->assertNotNull( $post );

		$context = new WP_Block_Editor_Context( array( 'post' => $post ) );
		$allowed = ( new BlockRestriction() )->filter_allowed_blocks( true, $context );

		$this->assertTrue( $allowed, 'Default allow-all must pass through for non-Feedwright posts.' );
	}

	public function test_category_added_for_feedwright_feed_only(): void {
		$registry = new BlockRegistry();

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => PostType::SLUG,
				'post_status' => 'publish',
			)
		);
		$post = get_post( $post_id );
		$this->assertNotNull( $post );

		$context = new WP_Block_Editor_Context( array( 'post' => $post ) );
		$updated = $registry->register_category( array( array( 'slug' => 'text', 'title' => 'Text' ) ), $context );
		$this->assertSame( 'feedwright', $updated[0]['slug'] ?? null );
	}

	public function test_category_not_added_for_regular_posts(): void {
		$registry = new BlockRegistry();

		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		$post = get_post( $post_id );
		$this->assertNotNull( $post );

		$context  = new WP_Block_Editor_Context( array( 'post' => $post ) );
		$original = array( array( 'slug' => 'text', 'title' => 'Text' ) );
		$updated  = $registry->register_category( $original, $context );
		$this->assertSame( $original, $updated );
	}
}
