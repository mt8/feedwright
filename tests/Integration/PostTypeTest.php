<?php
/**
 * `feedwright_feed` カスタム投稿タイプの登録挙動を検証する。
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Integration;

use Feedwright\PostType;
use WP_UnitTestCase;

final class PostTypeTest extends WP_UnitTestCase {

	public function test_post_type_is_registered(): void {
		$this->assertTrue( post_type_exists( PostType::SLUG ) );
	}

	public function test_post_type_visibility(): void {
		$pt = get_post_type_object( PostType::SLUG );
		$this->assertNotNull( $pt );
		$this->assertFalse( $pt->public );
		$this->assertTrue( $pt->show_ui );
		$this->assertTrue( $pt->show_in_rest );
		$this->assertFalse( $pt->has_archive );
		$this->assertTrue( $pt->exclude_from_search );
	}

	public function test_capabilities_map_to_manage_options(): void {
		$pt = get_post_type_object( PostType::SLUG );
		$this->assertNotNull( $pt );
		$caps = (array) $pt->cap;
		foreach ( array( 'edit_posts', 'edit_others_posts', 'publish_posts', 'create_posts', 'delete_posts' ) as $cap_key ) {
			$this->assertSame( 'manage_options', $caps[ $cap_key ] ?? null, "cap '{$cap_key}' should map to manage_options" );
		}
	}

	public function test_editor_role_cannot_manage_feeds(): void {
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->assertFalse( current_user_can( 'manage_options' ) );
	}

	public function test_administrator_can_create_feed_post(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$this->assertTrue( current_user_can( 'manage_options' ) );

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => PostType::SLUG,
				'post_title'  => 'Test feed',
				'post_status' => 'publish',
			)
		);
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
		$this->assertSame( PostType::SLUG, get_post_type( $post_id ) );
	}

	public function test_default_template_has_minimum_skeleton(): void {
		$post_type  = new PostType();
		$template   = $post_type->default_template();
		$this->assertCount( 1, $template, 'root must be a single block' );
		$this->assertSame( 'feedwright/rss', $template[0][0] );
		$channel    = $template[0][2][0];
		$this->assertSame( 'feedwright/channel', $channel[0] );
		$item_query = $channel[2][0];
		$this->assertSame( 'feedwright/item-query', $item_query[0] );
		$item       = $item_query[2][0];
		$this->assertSame( 'feedwright/item', $item[0] );
	}
}
