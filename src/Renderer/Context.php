<?php
/**
 * Render-time context shared between the renderer and binding providers.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Renderer;

use DOMDocument;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Carries the feed post, optional current item post, DOM document, and
 * namespace map (prefix => uri) through a render pass. Instances are
 * effectively immutable; `with_post` returns a copy with a new current post.
 */
final class Context {

	/**
	 * Feed post being rendered.
	 *
	 * @var WP_Post
	 */
	private WP_Post $feed_post;

	/**
	 * Current item post when iterating an item-query, null at channel scope.
	 *
	 * @var WP_Post|null
	 */
	private ?WP_Post $current_post = null;

	/**
	 * Shared DOMDocument used for the entire render pass.
	 *
	 * @var DOMDocument
	 */
	private DOMDocument $dom;

	/**
	 * Namespace map.
	 *
	 * @var array<string,string> prefix => uri
	 */
	private array $namespaces;

	/**
	 * Whether the current branch is inside an item template.
	 *
	 * @var bool
	 */
	private bool $in_item_context;

	/**
	 * Output mode (`strict` / `compat`). Carried via context so renderers and
	 * sanitizers can pick the right encoding strategy without re-parsing the
	 * rss block.
	 *
	 * @var string
	 */
	private string $output_mode;

	/**
	 * Build a render-time context.
	 *
	 * @param WP_Post              $feed_post   The feedwright_feed post being rendered.
	 * @param DOMDocument          $dom         Shared DOMDocument.
	 * @param array<string,string> $namespaces  Map of prefix => uri.
	 * @param string               $output_mode `strict` (default) or `compat`.
	 */
	public function __construct(
		WP_Post $feed_post,
		DOMDocument $dom,
		array $namespaces = array(),
		string $output_mode = Sanitize::MODE_STRICT
	) {
		$this->feed_post       = $feed_post;
		$this->dom             = $dom;
		$this->namespaces      = $namespaces;
		$this->in_item_context = false;
		$this->output_mode     = Sanitize::normalize_mode( $output_mode );
	}

	/**
	 * Derive a child context with a current post bound (entering item scope).
	 *
	 * @param WP_Post $post Current item post.
	 */
	public function with_post( WP_Post $post ): self {
		$child                  = clone $this;
		$child->current_post    = $post;
		$child->in_item_context = true;
		return $child;
	}

	/**
	 * Feed post being rendered.
	 */
	public function feed_post(): WP_Post {
		return $this->feed_post;
	}

	/**
	 * Current item post (null outside item-query context).
	 */
	public function current_post(): ?WP_Post {
		return $this->current_post;
	}

	/**
	 * Shared DOMDocument.
	 */
	public function dom(): DOMDocument {
		return $this->dom;
	}

	/**
	 * Whether we are inside an item template branch.
	 */
	public function in_item_context(): bool {
		return $this->in_item_context;
	}

	/**
	 * Output encoding mode for this render pass.
	 */
	public function output_mode(): string {
		return $this->output_mode;
	}

	/**
	 * Namespace map declared on the rss element.
	 *
	 * @return array<string,string>
	 */
	public function namespaces(): array {
		return $this->namespaces;
	}

	/**
	 * Resolve a namespace prefix to its URI.
	 *
	 * @param string $prefix Namespace prefix (without colon).
	 */
	public function namespace_for( string $prefix ): ?string {
		return $this->namespaces[ $prefix ] ?? null;
	}

	/**
	 * Create a DOMElement, honouring namespace prefixes declared on the root.
	 *
	 * Returns null when $tag is not a valid XML Name. Unknown prefixes fall
	 * back to a plain (non-namespaced) element with a warning log.
	 *
	 * @param string $tag XML element tag name (with optional `prefix:` part).
	 */
	public function create_element( string $tag ): ?\DOMElement {
		if ( ! Sanitize::is_valid_xml_name( $tag ) ) {
			return null;
		}
		if ( false !== strpos( $tag, ':' ) ) {
			[ $prefix ] = explode( ':', $tag, 2 );
			$uri        = $this->namespaces[ $prefix ] ?? null;
			if ( null !== $uri ) {
				return $this->dom->createElementNS( $uri, $tag );
			}
			\Feedwright\Plugin::log( "Unknown namespace prefix in element name: {$tag}" );
		}
		return $this->dom->createElement( $tag );
	}

	/**
	 * Set an attribute on $element, honouring namespace prefixes.
	 *
	 * Invalid names are silently ignored to keep rendering best-effort.
	 *
	 * @param \DOMElement $element Target element.
	 * @param string      $name    Attribute name (with optional `prefix:` part).
	 * @param string      $value   Attribute value (will be sanitized).
	 */
	public function set_attribute( \DOMElement $element, string $name, string $value ): void {
		if ( ! Sanitize::is_valid_xml_name( $name ) ) {
			return;
		}
		$value = Sanitize::xml_chars( $value );
		if ( false !== strpos( $name, ':' ) ) {
			[ $prefix ] = explode( ':', $name, 2 );
			$uri        = $this->namespaces[ $prefix ] ?? null;
			if ( null !== $uri ) {
				$element->setAttributeNS( $uri, $name, $value );
				return;
			}
		}
		$element->setAttribute( $name, $value );
	}
}
