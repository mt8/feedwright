<?php
/**
 * Binding provider for `feed.*` — current feed post metadata.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings\Providers;

use Feedwright\Renderer\Context;
use Feedwright\Routing\FeedEndpoint;
use Feedwright\Bindings\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §14.4`feed.*`.
 */
final class FeedProvider implements ProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function namespace_name(): string {
		return 'feed';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $path     Binding path.
	 * @param string  $modifier Binding modifier.
	 * @param Context $ctx      Render context.
	 */
	public function resolve( string $path, string $modifier, Context $ctx ): ?string {
		$feed_post = $ctx->feed_post();

		switch ( $path ) {
			case 'title':
				return (string) get_the_title( $feed_post );
			case 'slug':
				return (string) $feed_post->post_name;
			case 'url':
				return FeedEndpoint::feed_url( $feed_post->post_name );
			case 'last_build_date':
				return $this->format_date( $this->compute_last_build_date( $feed_post ), $modifier );
		}

		return null;
	}

	/**
	 * Heuristic latest build timestamp: max of latest published post,
	 * feed post modified time, and now.
	 *
	 * @param \WP_Post $feed_post Feed post.
	 */
	private function compute_last_build_date( \WP_Post $feed_post ): int {
		$latest_post = get_posts(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'orderby'       => 'modified',
				'order'         => 'DESC',
				'numberposts'   => 1,
				'no_found_rows' => true,
				'fields'        => 'ids',
			)
		);
		$candidates  = array();
		if ( ! empty( $latest_post ) ) {
			$candidates[] = (int) get_post_modified_time( 'U', true, (int) $latest_post[0] );
		}
		$candidates[] = (int) get_post_modified_time( 'U', true, $feed_post );
		$candidates[] = time();
		return max( $candidates );
	}

	/**
	 * Format a timestamp using the modifier as a date format string.
	 *
	 * @param int    $timestamp Unix timestamp.
	 * @param string $modifier  PHP date format. Empty = `r`.
	 */
	private function format_date( int $timestamp, string $modifier ): string {
		$format = '' === $modifier ? 'r' : $modifier;
		return (string) wp_date( $format, $timestamp );
	}

	/**
	 * Binding catalogue for this provider.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function describe(): array {
		return array(
			array(
				'expression' => 'feed.title',
				'label'      => 'Feed post title',
				'context'    => 'any',
				'namespace'  => 'feed',
			),
			array(
				'expression' => 'feed.slug',
				'label'      => 'Feed post slug',
				'context'    => 'any',
				'namespace'  => 'feed',
			),
			array(
				'expression' => 'feed.url',
				'label'      => 'Public feed URL',
				'context'    => 'any',
				'namespace'  => 'feed',
			),
			array(
				'expression' => 'feed.last_build_date:r',
				'label'      => 'Latest build date (RFC 2822)',
				'context'    => 'any',
				'namespace'  => 'feed',
			),
		);
	}
}
