<?php
/**
 * Render-result cache built on the WordPress object cache.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Cache;

use Feedwright\PostType;
use Feedwright\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §15.
 *
 * Key: `feedwright:render:{blog_id}:{post_id}:{post_modified_gmt_unix}:{url_base_hash}`
 *
 * - post_modified_gmt advances on each post update, invalidating naturally.
 *   (Cases where another post's update changes the feed are flushed explicitly via events.)
 * - Changing the URL base shifts url_base_hash, so the key flips immediately
 *   (old keyed values expire via TTL).
 */
final class RenderCache {

	public const GROUP = 'feedwright';

	/**
	 * Compute the cache key for $post under the current url base.
	 *
	 * @param WP_Post $post Feed post.
	 */
	public static function key_for( WP_Post $post ): string {
		$url_base = (string) get_option( Settings::OPTION_URL_BASE, Settings::DEFAULT_URL_BASE );
		$url_hash = substr( md5( $url_base ), 0, 8 );
		$blog_id  = (int) get_current_blog_id();
		$modified = strtotime( (string) $post->post_modified_gmt . ' UTC' );
		$modified = false === $modified ? 0 : (int) $modified;
		return sprintf( 'feedwright:render:%d:%d:%d:%s', $blog_id, (int) $post->ID, $modified, $url_hash );
	}

	/**
	 * Look up the cached render result for $post.
	 *
	 * @param WP_Post $post Feed post.
	 * @return array{xml:string,warnings:array<int,string>}|null
	 */
	public function get( WP_Post $post ): ?array {
		if ( $this->ttl() <= 0 ) {
			return null;
		}
		$value = wp_cache_get( self::key_for( $post ), self::GROUP );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * Persist the render result under the configured TTL.
	 *
	 * @param WP_Post                                      $post   Feed post.
	 * @param array{xml:string,warnings:array<int,string>} $result Renderer output.
	 */
	public function set( WP_Post $post, array $result ): void {
		$ttl = $this->ttl();
		if ( $ttl <= 0 ) {
			return;
		}
		wp_cache_set( self::key_for( $post ), $result, self::GROUP, $ttl );
	}

	/**
	 * Drop all entries in the feedwright group.
	 *
	 * Falls back to a global flush when the active object cache does not
	 * implement group flushing (rare on hosts without persistent caching).
	 */
	public function flush_all(): void {
		if ( function_exists( 'wp_cache_flush_group' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( self::GROUP );
			return;
		}
		// Last-resort: nuke everything. Persistent cache backends almost
		// always provide flush_group, so this only fires on the in-memory
		// fallback where wp_cache_flush itself is cheap.
		wp_cache_flush();
	}

	/**
	 * Forget cached output for one feed post.
	 *
	 * Since the cache key contains $post->post_modified_gmt, which advances
	 * after wp_update_post, the previous key is naturally orphaned and will
	 * expire by TTL. We still attempt a delete for the current key for
	 * completeness.
	 *
	 * @param WP_Post $post Feed post.
	 */
	public function flush_for( WP_Post $post ): void {
		wp_cache_delete( self::key_for( $post ), self::GROUP );
	}

	/**
	 * Wire global invalidation hooks. Call once at plugin bootstrap.
	 */
	public function register(): void {
		add_action( 'save_post', array( $this, 'on_save_post' ), 10, 2 );
		add_action( 'deleted_post', array( $this, 'on_deleted_post' ) );
		add_action( 'update_option_' . Settings::OPTION_URL_BASE, array( $this, 'flush_all' ) );
	}

	/**
	 * Save handler: a feed post update purges only its own entry; any other
	 * post type triggers a conservative full flush since item-queries can
	 * pull arbitrary post types.
	 *
	 * @param int     $post_id Saved post id.
	 * @param WP_Post $post    Saved post object.
	 */
	public function on_save_post( int $post_id, WP_Post $post ): void {
		unset( $post_id );
		if ( PostType::SLUG === $post->post_type ) {
			$this->flush_for( $post );
			return;
		}
		// Skip auto-draft / revision noise.
		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		$this->flush_all();
	}

	/**
	 * Conservative flush whenever any post is deleted.
	 *
	 * @param int $post_id Deleted post id.
	 */
	public function on_deleted_post( int $post_id ): void {
		unset( $post_id );
		$this->flush_all();
	}

	/**
	 * Read the configured TTL, clamped to 0 minimum.
	 */
	private function ttl(): int {
		$ttl = (int) get_option( Settings::OPTION_CACHE_TTL, Settings::DEFAULT_CACHE_TTL );
		if ( $ttl < 0 ) {
			$ttl = 0;
		}
		return $ttl;
	}
}
