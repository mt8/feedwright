<?php
/**
 * Binding provider for `post_term.{taxonomy}` — taxonomy terms attached to the
 * current post.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings\Providers;

use Feedwright\Renderer\Context;
use Feedwright\Bindings\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §14.4 `post_term.*`. Supported modifiers: `slug` / `::sep` / `slug::sep`.
 */
final class PostTermProvider implements ProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function namespace_name(): string {
		return 'post_term';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $path     Taxonomy slug.
	 * @param string  $modifier `slug` / `::sep` / `slug::sep`.
	 * @param Context $ctx      Render context.
	 */
	public function resolve( string $path, string $modifier, Context $ctx ): ?string {
		$post = $ctx->current_post();
		if ( ! $post instanceof \WP_Post || '' === $path ) {
			return null;
		}

		[ $field, $separator ] = $this->parse_modifier( $modifier );

		$terms = get_the_terms( $post, $path );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}

		$values = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$values[] = (string) ( 'slug' === $field ? $term->slug : $term->name );
			}
		}
		return implode( $separator, $values );
	}

	/**
	 * Parse one of: '', 'slug', '::sep', 'slug::sep'.
	 *
	 * @param string $modifier Modifier portion of the binding.
	 * @return array{0:string,1:string} [field, separator]
	 */
	private function parse_modifier( string $modifier ): array {
		if ( '' === $modifier ) {
			return array( 'name', ', ' );
		}
		$parts = explode( '::', $modifier, 2 );
		$field = ( 'slug' === $parts[0] ) ? 'slug' : 'name';
		$sep   = $parts[1] ?? ', ';
		return array( $field, $sep );
	}

	/**
	 * Binding catalogue for this provider.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function describe(): array {
		return array(
			array(
				'expression' => 'post_term.{taxonomy}',
				'label'      => 'Term names joined by ", "',
				'context'    => 'item',
				'namespace'  => 'post_term',
				'dynamic'    => true,
			),
			array(
				'expression' => 'post_term.{taxonomy}:slug',
				'label'      => 'Term slugs joined by ", "',
				'context'    => 'item',
				'namespace'  => 'post_term',
				'dynamic'    => true,
			),
			array(
				'expression' => 'post_term.{taxonomy}::{sep}',
				'label'      => 'Term names joined by custom separator',
				'context'    => 'item',
				'namespace'  => 'post_term',
				'dynamic'    => true,
			),
		);
	}
}
