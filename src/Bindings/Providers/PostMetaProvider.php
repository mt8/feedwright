<?php
/**
 * Binding provider for `post_meta.{key}` — single-value post meta.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings\Providers;

use Feedwright\Renderer\Context;
use Feedwright\Bindings\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §14.4 `post_meta.*`. Returns scalar values only; arrays/objects yield empty strings.
 */
final class PostMetaProvider implements ProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function namespace_name(): string {
		return 'post_meta';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $path     Custom field key.
	 * @param string  $modifier Unused.
	 * @param Context $ctx      Render context.
	 */
	public function resolve( string $path, string $modifier, Context $ctx ): ?string {
		unset( $modifier );
		$post = $ctx->current_post();
		if ( ! $post instanceof \WP_Post || '' === $path ) {
			return null;
		}

		$value = get_post_meta( $post->ID, $path, true );
		if ( '' === $value ) {
			return '';
		}
		if ( ! is_scalar( $value ) ) {
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
				'expression' => 'post_meta.{key}',
				'label'      => 'Custom field value (single, scalar)',
				'context'    => 'item',
				'namespace'  => 'post_meta',
				'dynamic'    => true,
			),
		);
	}
}
