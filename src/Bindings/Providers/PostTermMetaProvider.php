<?php
/**
 * Binding provider for `post_term_meta.{taxonomy}.{meta_key}` — term meta of
 * the current post's first term in the given taxonomy.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings\Providers;

use Feedwright\Renderer\Context;
use Feedwright\Bindings\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §14.4 `post_term_meta.*`. Powers the aggregator category-mapping
 * pattern (e.g. mediba `<category>` requires a CP-assigned numeric ID).
 *
 * Editorial teams set the ID once per WP term as term meta; this provider
 * surfaces it without further per-feed configuration.
 */
final class PostTermMetaProvider implements ProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function namespace_name(): string {
		return 'post_term_meta';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Path format: `{taxonomy}.{meta_key}`. The meta key may itself contain
	 * dots; only the first segment is treated as the taxonomy.
	 *
	 * @param string  $path     Binding path of the form `taxonomy.meta_key`.
	 * @param string  $modifier Unused.
	 * @param Context $ctx      Render context.
	 */
	public function resolve( string $path, string $modifier, Context $ctx ): ?string {
		unset( $modifier );

		$post = $ctx->current_post();
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$dot = strpos( $path, '.' );
		if ( false === $dot ) {
			return null;
		}
		$taxonomy = substr( $path, 0, $dot );
		$meta_key = substr( $path, $dot + 1 );
		if ( '' === $taxonomy || '' === $meta_key ) {
			return null;
		}

		$terms = get_the_terms( $post, $taxonomy );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}

		// Use the first term as returned by core (taxonomy ordering rules apply).
		$first = null;
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$first = $term;
				break;
			}
		}
		if ( null === $first ) {
			return '';
		}

		$value = get_term_meta( $first->term_id, $meta_key, true );
		if ( is_array( $value ) ) {
			return '';
		}
		return (string) $value;
	}

	/**
	 * Binding catalogue for this provider.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function describe(): array {
		return array(
			array(
				'expression' => 'post_term_meta.{taxonomy}.{meta_key}',
				'label'      => "First term's term meta value (e.g. category ID for an aggregator)",
				'context'    => 'item',
				'namespace'  => 'post_term_meta',
				'dynamic'    => true,
			),
		);
	}
}
