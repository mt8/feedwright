<?php
/**
 * Binding provider for `author.*` — current post's author.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings\Providers;

use Feedwright\Renderer\Context;
use Feedwright\Bindings\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §14.4 `author.*`. Returns null outside item context.
 */
final class AuthorProvider implements ProviderInterface {

	private const META_FIELDS = array(
		'display_name',
		'user_login',
		'user_email',
		'user_url',
		'user_nicename',
		'first_name',
		'last_name',
	);

	/**
	 * {@inheritDoc}
	 */
	public function namespace_name(): string {
		return 'author';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $path     Binding path.
	 * @param string  $modifier Unused.
	 * @param Context $ctx      Render context.
	 */
	public function resolve( string $path, string $modifier, Context $ctx ): ?string {
		unset( $modifier );

		$post = $ctx->current_post();
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		$author_id = (int) $post->post_author;
		if ( $author_id <= 0 ) {
			return '';
		}

		if ( 'ID' === $path ) {
			return (string) $author_id;
		}
		if ( 'archive_url' === $path ) {
			return (string) get_author_posts_url( $author_id );
		}
		if ( in_array( $path, self::META_FIELDS, true ) ) {
			return (string) get_the_author_meta( $path, $author_id );
		}

		return null;
	}

	/**
	 * Binding catalogue for this provider.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function describe(): array {
		$rows = array(
			array(
				'expression' => 'author.ID',
				'label'      => 'Author ID',
				'context'    => 'item',
				'namespace'  => 'author',
			),
			array(
				'expression' => 'author.archive_url',
				'label'      => 'Author archive URL',
				'context'    => 'item',
				'namespace'  => 'author',
			),
		);
		foreach ( self::META_FIELDS as $field ) {
			$rows[] = array(
				'expression' => "author.{$field}",
				'label'      => "Author {$field}",
				'context'    => 'item',
				'namespace'  => 'author',
			);
		}
		return $rows;
	}
}
