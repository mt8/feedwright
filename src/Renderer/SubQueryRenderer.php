<?php
/**
 * Render `feedwright/sub-query` blocks into related-post DOM nodes.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Renderer;

use DOMNode;
use Feedwright\Query\ArgsBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §13.7. Sub-query lives inside `feedwright/item` and produces N copies
 * of its single `feedwright/sub-item` template — one per related post — each
 * expanded against a context whose current post is the related post.
 */
final class SubQueryRenderer {

	/**
	 * Element renderer used to render the sub-item template's children.
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
	 * Wire the sub-query renderer with its dependencies.
	 *
	 * @param ElementRenderer  $element_renderer Renderer used for inner element blocks.
	 * @param ArgsBuilder|null $args_builder     Optional builder; defaults to a fresh instance.
	 */
	public function __construct( ElementRenderer $element_renderer, ?ArgsBuilder $args_builder = null ) {
		$this->element_renderer = $element_renderer;
		$this->args_builder     = $args_builder ?? new ArgsBuilder();
	}

	/**
	 * Run the sub-query for $block in $ctx and return every node produced by
	 * the sub-item template, in document order.
	 *
	 * @param array<string,mixed> $block Parsed `feedwright/sub-query` block.
	 * @param Context             $ctx   Item-scope render context.
	 * @return array<int,DOMNode>
	 */
	public function render( array $block, Context $ctx ): array {
		$current = $ctx->current_post();
		if ( ! $current instanceof \WP_Post ) {
			\Feedwright\Plugin::log( 'feedwright/sub-query rendered outside item context; skipping.' );
			return array();
		}

		$attrs = (array) ( $block['attrs'] ?? array() );
		$args  = $this->args_builder->build_sub( $attrs, $current );
		if ( null === $args ) {
			return array();
		}

		/**
		 * Filter the WP_Query args generated for this sub-query.
		 *
		 * @param array<string,mixed> $args    Built sub-query args.
		 * @param array<string,mixed> $block   Parsed sub-query block.
		 * @param \WP_Post            $current Current item post.
		 * @param Context             $ctx     Render context.
		 */
		$args = (array) apply_filters( 'feedwright/sub_query_args', $args, $block, $current, $ctx );

		/**
		 * Hard cap on sub-query result count, applied after WP_Query runs.
		 *
		 * Returning <= 0 disables the cap. Use this to enforce spec-mandated
		 * limits (goo `smp:relation` <= 3, mediba `mdf:relatedLink` <= 5, ...).
		 *
		 * @param int                 $hard_max Default 0 (no cap).
		 * @param array<string,mixed> $block    Parsed sub-query block.
		 * @param Context             $ctx      Render context.
		 */
		$hard_max = (int) apply_filters( 'feedwright/sub_query/hard_max', 0, $block, $ctx );

		$template = $this->find_sub_item_template( $block );
		if ( null === $template ) {
			return array();
		}

		$nodes = array();

		// Preserve outer global state so nested loops do not leak.
		global $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$saved_post = $post;

		$query = new \WP_Query( $args );
		$count = 0;
		while ( $query->have_posts() ) {
			$query->the_post();
			$related = get_post();
			if ( ! $related instanceof \WP_Post ) {
				continue;
			}

			$related_ctx = $ctx->with_post( $related );

			foreach ( (array) ( $template['innerBlocks'] ?? array() ) as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}
				foreach ( $this->element_renderer->render_child( $child, $related_ctx ) as $node ) {
					$nodes[] = $node;
				}
			}

			++$count;
			if ( $hard_max > 0 && $count >= $hard_max ) {
				break;
			}
		}
		wp_reset_postdata();
		$post = $saved_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		return $nodes;
	}

	/**
	 * Locate the inner `feedwright/sub-item` template inside a sub-query.
	 *
	 * @param array<string,mixed> $block Parsed sub-query block.
	 * @return array<string,mixed>|null
	 */
	private function find_sub_item_template( array $block ): ?array {
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $inner ) {
			if ( is_array( $inner ) && 'feedwright/sub-item' === ( $inner['blockName'] ?? '' ) ) {
				return $inner;
			}
		}
		return null;
	}
}
