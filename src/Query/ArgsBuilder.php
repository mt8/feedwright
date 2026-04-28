<?php
/**
 * Translate `feedwright/item-query` attributes into WP_Query arguments.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Query;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §12.5 / §12.6 (sub-query).
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

	public const SUB_QUERY_DEFAULT_MAX = 10;

	private const SUB_RELATION_MODES = array( 'taxonomy', 'manual' );

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
	 * Build WP_Query args for a `feedwright/sub-query` block, scoped by the
	 * current item post.
	 *
	 * @param array<string,mixed> $attrs   Block attributes.
	 * @param WP_Post             $current Currently iterating item post.
	 * @return array<string,mixed>|null Args, or null when the sub-query cannot run
	 *                                  (missing taxonomy / meta key / no relation match).
	 */
	public function build_sub( array $attrs, WP_Post $current ): ?array {
		$mode = (string) ( $attrs['relationMode'] ?? 'taxonomy' );
		if ( ! in_array( $mode, self::SUB_RELATION_MODES, true ) ) {
			$mode = 'taxonomy';
		}

		$post_types = $this->normalize_post_types( $attrs['postType'] ?? array( $current->post_type ) );

		$default_per_page = max( 1, min( self::SUB_QUERY_DEFAULT_MAX, (int) ( $attrs['postsPerPage'] ?? 3 ) ) );
		$per_page         = $this->clamp_per_page( $attrs['postsPerPage'] ?? $default_per_page );

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
			'post_type'              => $post_types,
			'post_status'            => $post_status,
			'posts_per_page'         => $per_page,
			'orderby'                => $order_by,
			'order'                  => $order,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
		);

		$exclude_current = ! isset( $attrs['excludeCurrent'] ) || ! empty( $attrs['excludeCurrent'] );
		if ( $exclude_current ) {
			$args['post__not_in'] = array( $current->ID );
		}

		switch ( $mode ) {
			case 'taxonomy':
				$taxonomy = (string) ( $attrs['taxonomy'] ?? '' );
				if ( '' === $taxonomy ) {
					return null;
				}
				// Restrict to hierarchical taxonomies. Flat taxonomies (tags
				// etc.) are user-typed free input, so two posts almost never
				// share an exact term — the relation would be noise.
				if ( ! is_taxonomy_hierarchical( $taxonomy ) ) {
					return null;
				}
				$term_ids = wp_get_object_terms( $current->ID, $taxonomy, array( 'fields' => 'ids' ) );
				if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
					return null;
				}
				$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => array_map( 'intval', (array) $term_ids ),
					),
				);
				break;

			case 'manual':
				$ids = array();
				if ( isset( $attrs['manualIds'] ) && is_array( $attrs['manualIds'] ) ) {
					foreach ( $attrs['manualIds'] as $raw ) {
						$id = (int) $raw;
						if ( $id > 0 && ( ! $exclude_current || $id !== $current->ID ) ) {
							$ids[] = $id;
						}
					}
				}
				if ( empty( $ids ) ) {
					return null;
				}
				$args['post__in'] = $ids;
				$args['orderby']  = 'post__in';
				unset( $args['order'] );
				if ( $exclude_current ) {
					unset( $args['post__not_in'] );
				}
				break;
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
