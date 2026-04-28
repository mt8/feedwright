<?php
/**
 * Render `feedwright/item-query` blocks into a list of `<item>` DOMElements.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Renderer;

use DOMElement;
use Feedwright\Query\ArgsBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §13.6.
 */
final class ItemQueryRenderer {

	/**
	 * Element renderer used for inner blocks.
	 *
	 * @var ElementRenderer
	 */
	private ElementRenderer $element_renderer;

	/**
	 * WP_Query args builder.
	 *
	 * @var ArgsBuilder
	 */
	private ArgsBuilder $args_builder;

	/**
	 * Wire the item-query renderer with its dependencies.
	 *
	 * @param ElementRenderer  $element_renderer Renderer for inner element blocks.
	 * @param ArgsBuilder|null $args_builder     Optional builder; defaults to a fresh instance.
	 */
	public function __construct( ElementRenderer $element_renderer, ?ArgsBuilder $args_builder = null ) {
		$this->element_renderer = $element_renderer;
		$this->args_builder     = $args_builder ?? new ArgsBuilder();
	}

	/**
	 * Run the WP_Query for $block and return the generated <item> elements
	 * (or whatever `itemTagName` says) in document order.
	 *
	 * @param array<string,mixed> $block Parsed `feedwright/item-query` block.
	 * @param Context             $ctx   Channel-scope render context.
	 * @return array<int,DOMElement>
	 */
	public function render( array $block, Context $ctx ): array {
		$attrs = $block['attrs'] ?? array();
		$args  = $this->args_builder->build( $attrs );

		/**
		 * Filter the WP_Query args generated for this item-query.
		 *
		 * @param array<string,mixed> $args  Built query args.
		 * @param array<string,mixed> $block Parsed block.
		 * @param Context             $ctx   Render context.
		 */
		$args = (array) apply_filters( 'feedwright/query_args', $args, $block, $ctx );

		$item_template = $this->find_item_template( $block );
		if ( null === $item_template ) {
			return array();
		}

		$item_tag = (string) ( $attrs['itemTagName'] ?? 'item' );
		if ( ! Sanitize::is_valid_xml_name( $item_tag ) ) {
			\Feedwright\Plugin::log( "Invalid itemTagName '{$item_tag}', falling back to 'item'." );
			$item_tag = 'item';
		}

		$nodes = array();

		// Preserve outer global state in case this is run inside a nested loop.
		global $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$saved_post = $post;

		// Trash window: when the user includes `trash` in post_status and sets
		// trashWithinDays > 0, only trashed posts modified within the window
		// stay in the result. Implemented via a one-shot posts_where filter
		// because WP_Query date_query is unconditional and would also exclude
		// old published posts.
		$trash_window_days  = isset( $attrs['trashWithinDays'] ) ? (int) $attrs['trashWithinDays'] : 0;
		$apply_trash_window = $trash_window_days > 0
			&& is_array( $args['post_status'] ?? null )
			&& in_array( 'trash', $args['post_status'], true );
		$where_filter       = null;
		if ( $apply_trash_window ) {
			$cutoff_gmt   = gmdate( 'Y-m-d H:i:s', time() - $trash_window_days * DAY_IN_SECONDS );
			$where_filter = static function ( string $where ) use ( $cutoff_gmt ): string {
				global $wpdb;
				return $where . $wpdb->prepare(
					" AND ( {$wpdb->posts}.post_status <> %s OR {$wpdb->posts}.post_modified_gmt > %s )",
					'trash',
					$cutoff_gmt
				);
			};
			add_filter( 'posts_where', $where_filter );
		}

		$query = new \WP_Query( $args );

		if ( null !== $where_filter ) {
			remove_filter( 'posts_where', $where_filter );
		}
		while ( $query->have_posts() ) {
			$query->the_post();
			$current = get_post();
			if ( ! $current instanceof \WP_Post ) {
				continue;
			}

			$item_ctx = $ctx->with_post( $current );

			$item_el = $item_ctx->create_element( $item_tag );
			if ( null === $item_el ) {
				continue;
			}

			foreach ( (array) ( $item_template['innerBlocks'] ?? array() ) as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				foreach ( $this->element_renderer->render_child( $child, $item_ctx ) as $node ) {
					$item_el->appendChild( $node );
				}
			}

			$nodes[] = $item_el;
		}
		wp_reset_postdata();
		$post = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		return $nodes;
	}

	/**
	 * Locate the inner `feedwright/item` template inside an item-query.
	 *
	 * @param array<string,mixed> $block Parsed item-query block.
	 * @return array<string,mixed>|null
	 */
	private function find_item_template( array $block ): ?array {
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner ) {
			if ( is_array( $inner ) && 'feedwright/item' === ( $inner['blockName'] ?? '' ) ) {
				return $inner;
			}
		}
		return null;
	}
}
