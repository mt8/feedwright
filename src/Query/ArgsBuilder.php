<?php
/**
 * Translate `feedwright/item-query` attributes into WP_Query arguments.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Query;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §12.5.
 */
final class ArgsBuilder {

	private const ALLOWED_ORDER_BY = array(
		'date',
		'modified',
		'title',
		'menu_order',
		'rand',
		'comment_count',
		'meta_value',
		'meta_value_num',
		'none',
	);

	private const ALLOWED_POST_STATUS = array( 'publish', 'private', 'future' );

	public const MAX_POSTS_PER_PAGE = 500;

	/**
	 * Build WP_Query args from raw block attributes.
	 *
	 * @param array<string,mixed> $attrs Block attributes.
	 * @return array<string,mixed>
	 */
	public function build( array $attrs ): array {
		$post_types = $this->normalize_post_types( $attrs['postType'] ?? array( 'post' ) );

		$per_page = $this->clamp_per_page( $attrs['postsPerPage'] ?? 20 );

		$order_by = strtolower( (string) ( $attrs['orderBy'] ?? 'date' ) );
		if ( ! in_array( $order_by, self::ALLOWED_ORDER_BY, true ) ) {
			$order_by = 'date';
		}

		$order = strtoupper( (string) ( $attrs['order'] ?? 'DESC' ) );
		if ( 'ASC' !== $order && 'DESC' !== $order ) {
			$order = 'DESC';
		}

		$post_status = $this->normalize_post_status( $attrs['postStatus'] ?? array( 'publish' ) );

		$args = array(
			'post_type'           => $post_types,
			'post_status'         => $post_status,
			'posts_per_page'      => $per_page,
			'orderby'             => $order_by,
			'order'               => $order,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => empty( $attrs['includeStickyPosts'] ),
		);

		if ( ! empty( $attrs['excludeIds'] ) && is_array( $attrs['excludeIds'] ) ) {
			$args['post__not_in'] = array_values(
				array_filter(
					array_map( 'intval', $attrs['excludeIds'] ),
					static fn ( int $id ): bool => $id > 0
				)
			);
		}

		if ( ! empty( $attrs['taxQuery'] ) && is_array( $attrs['taxQuery'] ) ) {
			$filtered = array();
			foreach ( $attrs['taxQuery'] as $entry ) {
				if ( ! is_array( $entry ) ) {
					continue;
				}
				if ( empty( $entry['taxonomy'] ) || empty( $entry['terms'] ) ) {
					continue;
				}
				$filtered[] = $entry;
			}
			if ( ! empty( $filtered ) ) {
				$args['tax_query'] = $filtered; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			}
		}
		if ( ! empty( $attrs['metaQuery'] ) && is_array( $attrs['metaQuery'] ) ) {
			$args['meta_query'] = $attrs['metaQuery']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		if ( ! empty( $attrs['dateQuery'] ) && is_array( $attrs['dateQuery'] ) ) {
			$args['date_query'] = $attrs['dateQuery'];
		}

		return $args;
	}

	/**
	 * Coerce the postType attribute into a non-empty array of strings.
	 *
	 * @param mixed $raw Possibly an array of post type slugs.
	 * @return array<int,string>
	 */
	private function normalize_post_types( $raw ): array {
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return array( 'post' );
		}
		$out = array();
		foreach ( $raw as $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$out[] = $value;
			}
		}
		return empty( $out ) ? array( 'post' ) : $out;
	}

	/**
	 * Coerce the postStatus attribute, dropping disallowed statuses.
	 *
	 * @param mixed $raw Possibly an array of post statuses.
	 * @return array<int,string>
	 */
	private function normalize_post_status( $raw ): array {
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return array( 'publish' );
		}
		$out = array();
		foreach ( $raw as $value ) {
			if ( in_array( $value, self::ALLOWED_POST_STATUS, true ) ) {
				$out[] = $value;
			}
		}
		return empty( $out ) ? array( 'publish' ) : $out;
	}

	/**
	 * Clamp postsPerPage to [1, MAX_POSTS_PER_PAGE].
	 *
	 * @param mixed $raw Possibly numeric.
	 */
	private function clamp_per_page( $raw ): int {
		$value = is_numeric( $raw ) ? (int) $raw : 20;
		if ( $value < 1 ) {
			$value = 1;
		}
		if ( $value > self::MAX_POSTS_PER_PAGE ) {
			$value = self::MAX_POSTS_PER_PAGE;
		}
		return $value;
	}
}
