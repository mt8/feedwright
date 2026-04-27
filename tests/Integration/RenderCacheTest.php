<?php
/**
 * Integration tests for the render-result cache.
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Integration;

use Feedwright\Cache\RenderCache;
use Feedwright\Plugin;
use Feedwright\PostType;
use Feedwright\Renderer\Renderer;
use Feedwright\Settings;
use WP_UnitTestCase;

final class RenderCacheTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( Settings::OPTION_CACHE_TTL, 300 );
		( new RenderCache() )->flush_all();
	}

	private function make_feed_post(): \WP_Post {
		$content = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. '<!-- wp:feedwright/element {"tagName":"title","contentMode":"binding","bindingExpression":"{{option.blogname}}"} /-->'
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$id = self::factory()->post->create(
			array(
				'post_type'    => PostType::SLUG,
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
		return get_post( $id );
	}

	public function test_second_call_returns_same_object_via_cache(): void {
		$post     = $this->make_feed_post();
		$resolver = Plugin::build_resolver();
		$renderer = new Renderer( $resolver );

		$first  = $renderer->render( $post );
		$cached = ( new RenderCache() )->get( $post );

		$this->assertNotNull( $cached, 'cache hit expected after first render' );
		$this->assertSame( $first['xml'], $cached['xml'] );
	}

	public function test_ttl_zero_disables_cache(): void {
		update_option( Settings::OPTION_CACHE_TTL, 0 );
		$post = $this->make_feed_post();

		$renderer = new Renderer( Plugin::build_resolver() );
		$renderer->render( $post );

		$this->assertNull( ( new RenderCache() )->get( $post ), 'cache must be empty when TTL is 0' );
	}

	public function test_save_post_on_other_post_type_flushes_cache(): void {
		$post     = $this->make_feed_post();
		$cache    = new RenderCache();
		$renderer = new Renderer( Plugin::build_resolver(), $cache );

		$renderer->render( $post );
		$this->assertNotNull( $cache->get( $post ) );

		// Saving a regular post should trigger a conservative full flush.
		self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Side effect',
			)
		);

		$this->assertNull( $cache->get( $post ), 'unrelated post save must invalidate the cache' );
	}

	public function test_deleting_a_post_flushes_cache(): void {
		$post     = $this->make_feed_post();
		$cache    = new RenderCache();
		$renderer = new Renderer( Plugin::build_resolver(), $cache );

		$other_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$renderer->render( $post );
		$this->assertNotNull( $cache->get( $post ) );

		wp_delete_post( $other_id, true );

		$this->assertNull( $cache->get( $post ) );
	}

	public function test_url_base_change_invalidates_cache(): void {
		$post     = $this->make_feed_post();
		$cache    = new RenderCache();
		$renderer = new Renderer( Plugin::build_resolver(), $cache );

		$renderer->render( $post );
		$this->assertNotNull( $cache->get( $post ), 'cache hit expected before url base change' );

		// Same key recipe relies on the url_base hash, so changing the option
		// changes the key. The hooked listener also flush_all()'s for safety.
		update_option( Settings::OPTION_URL_BASE, 'news/feeds' );

		$this->assertNull( $cache->get( $post ), 'cache must be empty under the new url base' );
	}
}
