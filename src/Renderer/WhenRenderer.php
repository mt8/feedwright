<?php
/**
 * Render `feedwright/when` blocks: emit children only when an expression
 * resolves non-empty (or empty, when `negate` is true).
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Renderer;

use DOMNode;
use Feedwright\Bindings\Resolver;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §12.x. The `feedwright/when` block lets authors gate a group of
 * inner blocks on a binding expression. Common use case: emit
 * `<mdf:deleted/>` only when the post status is `trash`, and the regular
 * `<title>` / `<description>` set only otherwise.
 */
final class WhenRenderer {

	/**
	 * Element renderer used for inner block dispatch.
	 *
	 * @var ElementRenderer
	 */
	private ElementRenderer $element_renderer;

	/**
	 * Binding resolver shared with the renderer facade.
	 *
	 * @var Resolver
	 */
	private Resolver $resolver;

	/**
	 * Wire the when renderer with its dependencies.
	 *
	 * @param ElementRenderer $element_renderer Renderer used for inner blocks.
	 * @param Resolver        $resolver         Binding resolver for the gating expression.
	 */
	public function __construct( ElementRenderer $element_renderer, Resolver $resolver ) {
		$this->element_renderer = $element_renderer;
		$this->resolver         = $resolver;
	}

	/**
	 * Render a `feedwright/when` block: when the gating expression is
	 * non-empty (or empty under `negate`), expand inner blocks and return
	 * every produced DOM node in document order. Otherwise return an empty
	 * array.
	 *
	 * @param array<string,mixed> $block Parsed `feedwright/when` block.
	 * @param Context             $ctx   Render context.
	 * @return array<int,DOMNode>
	 */
	public function render( array $block, Context $ctx ): array {
		$attrs      = (array) ( $block['attrs'] ?? array() );
		$expression = (string) ( $attrs['expression'] ?? '' );
		$negate     = ! empty( $attrs['negate'] );

		$value = '' === $expression ? '' : $this->resolver->resolve( $expression, $ctx );
		// Whitespace-only resolution counts as empty: a stray trailing space
		// in the expression must not flip the gate to always-true.
		$matches   = '' !== trim( $value );
		$gate_open = $negate ? ! $matches : $matches;

		if ( ! $gate_open ) {
			return array();
		}

		$nodes = array();
		foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			foreach ( $this->element_renderer->render_child( $child, $ctx ) as $node ) {
				$nodes[] = $node;
			}
		}
		return $nodes;
	}
}
