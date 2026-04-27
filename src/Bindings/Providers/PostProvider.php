<?php
/**
 * Binding provider for `post.*` — filter-applied post fields and computed values.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings\Providers;

use Feedwright\Renderer\Context;
use Feedwright\Bindings\DateFormatter;
use Feedwright\Bindings\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §14.4 `post.*`. Always returns empty (null) outside item context.
 */
final class PostProvider implements ProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function namespace_name(): string {
		return 'post';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $path     Binding path.
	 * @param string  $modifier Optional modifier (date format / image size).
	 * @param Context $ctx      Render context.
	 */
	public function resolve( string $path, string $modifier, Context $ctx ): ?string {
		$post = $ctx->current_post();
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		switch ( $path ) {
			case 'ID':
				return (string) $post->ID;
			case 'post_title':
				return (string) get_the_title( $post );
			case 'post_content':
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter intentionally applied.
				return (string) apply_filters( 'the_content', $post->post_content );
			case 'post_excerpt':
				return (string) get_the_excerpt( $post );
			case 'post_date':
				return DateFormatter::format(
					(int) get_post_time( 'U', true, $post ),
					'' === $modifier ? 'c' : $modifier
				);
			case 'post_modified':
				return DateFormatter::format(
					(int) get_post_modified_time( 'U', true, $post ),
					'' === $modifier ? 'c' : $modifier
				);
			case 'post_status':
				return (string) get_post_status( $post );
			case 'post_name':
				return (string) $post->post_name;
			case 'permalink':
				return (string) get_permalink( $post );
			case 'content_plaintext':
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter intentionally applied.
				return (string) wp_strip_all_tags( (string) apply_filters( 'the_content', $post->post_content ) );
			case 'comments_count':
				return (string) get_comments_number( $post );
		}

		if ( str_starts_with( $path, 'thumbnail' ) ) {
			return $this->thumbnail_value( $path, $modifier, $post );
		}

		return null;
	}

	/**
	 * Resolve any of the `thumbnail_*` paths.
	 *
	 * @param string   $path     Path beginning with `thumbnail`.
	 * @param string   $modifier Image size (default `full`).
	 * @param \WP_Post $post     Current item post.
	 */
	private function thumbnail_value( string $path, string $modifier, \WP_Post $post ): ?string {
		$thumb_id = (int) get_post_thumbnail_id( $post );
		if ( 0 === $thumb_id ) {
			return '';
		}
		$size = '' === $modifier ? 'full' : $modifier;

		switch ( $path ) {
			case 'thumbnail_id':
				return (string) $thumb_id;
			case 'thumbnail_url':
				$url = wp_get_attachment_image_url( $thumb_id, $size );
				if ( false === $url && 'full' !== $size ) {
					$url = wp_get_attachment_image_url( $thumb_id, 'full' );
				}
				return false === $url ? '' : (string) $url;
			case 'thumbnail_width':
			case 'thumbnail_height':
				$src = wp_get_attachment_image_src( $thumb_id, $size );
				if ( false === $src && 'full' !== $size ) {
					$src = wp_get_attachment_image_src( $thumb_id, 'full' );
				}
				if ( ! is_array( $src ) ) {
					return '';
				}
				return 'thumbnail_width' === $path ? (string) ( $src[1] ?? '' ) : (string) ( $src[2] ?? '' );
			case 'thumbnail_alt':
				return (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
			case 'thumbnail_mime':
				return (string) get_post_mime_type( $thumb_id );
		}
		return null;
	}

	/**
	 * Binding catalogue for this provider.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function describe(): array {
		return array(
			array(
				'expression' => 'post.ID',
				'label'      => 'Post ID',
				'context'    => 'item',
				'namespace'  => 'post',
			),
			array(
				'expression' => 'post.post_title',
				'label'      => 'Post title (filtered)',
				'context'    => 'item',
				'namespace'  => 'post',
			),
			array(
				'expression' => 'post.post_content',
				'label'      => 'Post content (the_content filtered)',
				'context'    => 'item',
				'namespace'  => 'post',
			),
			array(
				'expression' => 'post.post_excerpt',
				'label'      => 'Post excerpt',
				'context'    => 'item',
				'namespace'  => 'post',
			),
			array(
				'expression' => 'post.post_date:r',
				'label'      => 'Publish date (RFC 2822)',
				'context'    => 'item',
				'namespace'  => 'post',
			),
			array(
				'expression' => 'post.post_modified:r',
				'label'      => 'Modified date (RFC 2822)',
				'context'    => 'item',
				'namespace'  => 'post',
			),
			array(
				'expression' => 'post.permalink',
				'label'      => 'Permalink URL',
				'context'    => 'item',
				'namespace'  => 'post',
			),
			array(
				'expression' => 'post.thumbnail_url:large',
				'label'      => 'Featured image URL (large)',
				'context'    => 'item',
				'namespace'  => 'post',
			),
			array(
				'expression' => 'post.thumbnail_url:full',
				'label'      => 'Featured image URL (full)',
				'context'    => 'item',
				'namespace'  => 'post',
			),
			array(
				'expression' => 'post.comments_count',
				'label'      => 'Comment count',
				'context'    => 'item',
				'namespace'  => 'post',
			),
		);
	}
}
