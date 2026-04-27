<?php
/**
 * Render `feedwright/element` (and friends) into DOMElement instances.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Renderer;

use DOMElement;
use Feedwright\Bindings\Resolver;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §13.4.
 */
final class ElementRenderer {

	/**
	 * Binding resolver shared with the renderer facade.
	 *
	 * @var Resolver
	 */
	private Resolver $resolver;

	/**
	 * Wire the element renderer with the configured binding resolver.
	 *
	 * @param Resolver $resolver Binding resolver to use for `{{...}}` substitution.
	 */
	public function __construct( Resolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Render a parsed `feedwright/element` block.
	 *
	 * @param array<string,mixed> $block Parsed block.
	 * @param Context             $ctx   Render context.
	 */
	public function render( array $block, Context $ctx ): ?DOMElement {
		$attrs    = $block['attrs'] ?? array();
		$tag_name = (string) ( $attrs['tagName'] ?? '' );

		$element = $ctx->create_element( $tag_name );
		if ( null === $element ) {
			\Feedwright\Plugin::log( "Skipping element with invalid tagName: '{$tag_name}'" );
			return null;
		}

		$this->apply_attributes( $element, (array) ( $attrs['attributes'] ?? array() ), $ctx );
		$this->apply_content( $element, $block, $attrs, $ctx );

		/**
		 * Filter the generated DOMElement just before insertion.
		 *
		 * @param DOMElement          $element Generated element.
		 * @param array<string,mixed> $block   Parsed block.
		 * @param Context             $ctx     Render context.
		 */
		$filtered = apply_filters( 'feedwright/element_node', $element, $block, $ctx );
		return $filtered instanceof DOMElement ? $filtered : $element;
	}

	/**
	 * Set the configured attributes on $element.
	 *
	 * @param DOMElement                     $element     Element being built.
	 * @param array<int,array<string,mixed>> $attribute_specs Block `attributes` array.
	 * @param Context                        $ctx         Render context.
	 */
	private function apply_attributes( DOMElement $element, array $attribute_specs, Context $ctx ): void {
		foreach ( $attribute_specs as $spec ) {
			if ( ! is_array( $spec ) ) {
				continue;
			}
			$name = (string) ( $spec['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			$mode  = (string) ( $spec['valueMode'] ?? 'static' );
			$value = (string) ( $spec['value'] ?? '' );
			if ( 'binding' === $mode ) {
				$value = $this->resolver->resolve( $value, $ctx );
			}
			$ctx->set_attribute( $element, $name, $value );
		}
	}

	/**
	 * Materialize the element content according to contentMode.
	 *
	 * @param DOMElement          $element Target element.
	 * @param array<string,mixed> $block   Full parsed block (needed for innerBlocks).
	 * @param array<string,mixed> $attrs   Block attributes.
	 * @param Context             $ctx     Render context.
	 */
	private function apply_content( DOMElement $element, array $block, array $attrs, Context $ctx ): void {
		$mode = (string) ( $attrs['contentMode'] ?? 'static' );

		switch ( $mode ) {
			case 'children':
				foreach ( (array) ( $block['innerBlocks'] ?? array() ) as $child ) {
					if ( ! is_array( $child ) ) {
						continue;
					}
					$node = $this->render_child( $child, $ctx );
					if ( null !== $node ) {
						$element->appendChild( $node );
					}
				}
				break;

			case 'static':
				$value = (string) ( $attrs['staticValue'] ?? '' );
				if ( '' !== $value ) {
					$element->appendChild( $ctx->dom()->createTextNode( Sanitize::xml_chars( $value ) ) );
				}
				break;

			case 'binding':
				$value = $this->resolver->resolve( (string) ( $attrs['bindingExpression'] ?? '' ), $ctx );
				if ( '' !== $value ) {
					$element->appendChild( $ctx->dom()->createTextNode( Sanitize::xml_chars( $value ) ) );
				}
				break;

			case 'cdata-binding':
				$value = $this->resolver->resolve( (string) ( $attrs['bindingExpression'] ?? '' ), $ctx );
				$element->appendChild( $ctx->dom()->createCDATASection( Sanitize::xml_chars( $value ) ) );
				break;

			case 'empty':
			default:
				break;
		}
	}

	/**
	 * Dispatch a child block (element / raw / comment) inside `children` mode.
	 *
	 * @param array<string,mixed> $block Parsed child block.
	 * @param Context             $ctx   Render context.
	 */
	public function render_child( array $block, Context $ctx ): ?\DOMNode {
		$name = (string) ( $block['blockName'] ?? '' );
		switch ( $name ) {
			case 'feedwright/element':
				return $this->render( $block, $ctx );
			case 'feedwright/raw':
				return $this->render_raw( $block, $ctx );
			case 'feedwright/comment':
				return $this->render_comment( $block, $ctx );
		}
		return null;
	}

	/**
	 * Render a `feedwright/raw` block as a text/CDATA node.
	 *
	 * @param array<string,mixed> $block Parsed raw block.
	 * @param Context             $ctx   Render context.
	 */
	private function render_raw( array $block, Context $ctx ): ?\DOMNode {
		$attrs       = $block['attrs'] ?? array();
		$value       = (string) ( $attrs['value'] ?? '' );
		$as_cdata    = ! empty( $attrs['asCdata'] );
		$interpolate = ! isset( $attrs['interpolate'] ) || ! empty( $attrs['interpolate'] );

		if ( $interpolate ) {
			$value = $this->resolver->resolve( $value, $ctx );
		}
		$value = Sanitize::xml_chars( $value );

		return $as_cdata
			? $ctx->dom()->createCDATASection( $value )
			: $ctx->dom()->createTextNode( $value );
	}

	/**
	 * Render a `feedwright/comment` block as a DOMComment.
	 *
	 * @param array<string,mixed> $block Parsed comment block.
	 * @param Context             $ctx   Render context.
	 */
	public function render_comment( array $block, Context $ctx ): \DOMComment {
		$text = (string) ( $block['attrs']['text'] ?? '' );
		$text = Sanitize::xml_chars( $text );
		// XML 1.0: comments cannot contain "--".
		$text = str_replace( '--', '- -', $text );
		return $ctx->dom()->createComment( $text );
	}
}
