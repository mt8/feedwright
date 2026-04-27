<?php
/**
 * Feed renderer entrypoint.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Renderer;

use DOMDocument;
use Feedwright\Cache\RenderCache;
use Feedwright\Bindings\Resolver;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Parse a feedwright_feed post's blocks and emit the corresponding XML.
 *
 * Result of `render()`: array{xml:string,warnings:array<int,string>}.
 */
final class Renderer {

	private const XMLNS_URI = 'http://www.w3.org/2000/xmlns/';

	/**
	 * Binding resolver shared with element / item-query renderers.
	 *
	 * @var Resolver
	 */
	private Resolver $resolver;

	/**
	 * Render-result cache.
	 *
	 * @var RenderCache
	 */
	private RenderCache $cache;

	/**
	 * Wire the renderer with the configured binding resolver and cache.
	 *
	 * @param Resolver         $resolver Binding resolver to use for `{{...}}` expansion.
	 * @param RenderCache|null $cache    Optional cache; defaults to a fresh instance.
	 */
	public function __construct( Resolver $resolver, ?RenderCache $cache = null ) {
		$this->resolver = $resolver;
		$this->cache    = $cache ?? new RenderCache();
	}

	/**
	 * Render a feed post to XML, returning warnings collected along the way.
	 *
	 * @param WP_Post $post Feed post (post type `feedwright_feed`).
	 * @return array{xml:string,warnings:array<int,string>}
	 */
	public function render( WP_Post $post ): array {
		$cached = $this->cache->get( $post );
		if ( null !== $cached ) {
			return $cached;
		}

		$result = $this->render_uncached( $post );
		// Only persist successful renders (no rss/no channel errors must not
		// poison the cache, but they do still emit an XML error body so OK
		// to cache for the configured TTL).
		$this->cache->set( $post, $result );
		return $result;
	}

	/**
	 * The actual render path, without cache lookup.
	 *
	 * @param WP_Post $post Feed post.
	 * @return array{xml:string,warnings:array<int,string>}
	 */
	private function render_uncached( WP_Post $post ): array {
		$warnings = array();

		$blocks    = parse_blocks( (string) $post->post_content );
		$rss_block = $this->find_root_rss( $blocks, $warnings );

		$dom               = new DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		if ( null === $rss_block ) {
			$warnings[] = 'No <rss> block found in feed post.';
			return array(
				'xml'      => $this->error_document( $dom, 'No rss block in feed post' ),
				'warnings' => $warnings,
			);
		}

		$rss_attrs = (array) ( $rss_block['attrs'] ?? array() );
		$ns_pairs  = $this->namespace_map( $rss_attrs );
		$ctx       = new Context( $post, $dom, $ns_pairs );

		$rss_el = $dom->createElement( 'rss' );
		$rss_el->setAttribute( 'version', (string) ( $rss_attrs['version'] ?? '2.0' ) );
		foreach ( $ns_pairs as $prefix => $uri ) {
			$rss_el->setAttributeNS( self::XMLNS_URI, 'xmlns:' . $prefix, $uri );
		}
		$dom->appendChild( $rss_el );

		$channel_block = $this->find_channel( $rss_block );
		if ( null === $channel_block ) {
			$warnings[] = 'No <channel> block inside rss.';
			$xml        = $dom->saveXML();
			return array(
				'xml'      => false === $xml ? '' : (string) $xml,
				'warnings' => $warnings,
			);
		}

		$channel_el = $dom->createElement( 'channel' );
		$rss_el->appendChild( $channel_el );

		$element_renderer = new ElementRenderer( $this->resolver );
		$item_renderer    = new ItemQueryRenderer( $element_renderer );

		foreach ( (array) ( $channel_block['innerBlocks'] ?? array() ) as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$name = (string) ( $child['blockName'] ?? '' );
			switch ( $name ) {
				case 'feedwright/element':
					$node = $element_renderer->render( $child, $ctx );
					if ( null !== $node ) {
						$channel_el->appendChild( $node );
					}
					break;
				case 'feedwright/item-query':
					foreach ( $item_renderer->render( $child, $ctx ) as $item_node ) {
						$channel_el->appendChild( $item_node );
					}
					break;
				case 'feedwright/comment':
					$channel_el->appendChild( $element_renderer->render_comment( $child, $ctx ) );
					break;
				case '':
					break;
				default:
					$warnings[] = "Unsupported block in channel: {$name}";
					break;
			}
		}

		$xml = $dom->saveXML();
		return array(
			'xml'      => false === $xml ? '' : (string) $xml,
			'warnings' => $warnings,
		);
	}

	/**
	 * Render and stream the XML body to the response.
	 *
	 * @param WP_Post $post Feed post.
	 */
	public function render_to_output( WP_Post $post ): void {
		$result = $this->render( $post );
		$body   = $result['xml'];
		$ttl    = (int) get_option( 'feedwright_cache_ttl', 300 );

		$last_modified = mysql2date( 'D, d M Y H:i:s', $post->post_modified_gmt, false ) . ' GMT';
		$etag          = '"' . md5( $post->ID . '|' . $post->post_modified_gmt . '|' . (string) get_option( 'feedwright_url_base', 'feedwright' ) ) . '"';

		if ( $this->client_has_fresh_copy( $etag, $post->post_modified_gmt ) ) {
			status_header( 304 );
			header( 'ETag: ' . $etag );
			header( 'Last-Modified: ' . $last_modified );
			return;
		}

		status_header( 200 );
		header( 'Content-Type: application/rss+xml; charset=UTF-8' );
		header( 'Last-Modified: ' . $last_modified );
		header( 'ETag: ' . $etag );
		header( 'Cache-Control: public, max-age=' . $ttl );
		header( 'X-Robots-Tag: noindex' );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- DOMDocument escapes for us.
	}

	/**
	 * Locate the first `feedwright/rss` block at the top level.
	 *
	 * @param array<int,array<string,mixed>> $blocks   Top-level parsed blocks.
	 * @param array<int,string>              $warnings Output: warnings for repeated rss.
	 */
	private function find_root_rss( array $blocks, array &$warnings ): ?array {
		$found = null;
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) || 'feedwright/rss' !== ( $block['blockName'] ?? '' ) ) {
				continue;
			}
			if ( null === $found ) {
				$found = $block;
			} else {
				$warnings[] = 'Multiple rss blocks found; using the first.';
			}
		}
		return $found;
	}

	/**
	 * Locate the channel block inside the rss block.
	 *
	 * @param array<string,mixed> $rss_block Parsed rss block.
	 */
	private function find_channel( array $rss_block ): ?array {
		foreach ( (array) ( $rss_block['innerBlocks'] ?? array() ) as $inner ) {
			if ( is_array( $inner ) && 'feedwright/channel' === ( $inner['blockName'] ?? '' ) ) {
				return $inner;
			}
		}
		return null;
	}

	/**
	 * Build a prefix=>uri map from the rss block's `namespaces` attribute.
	 *
	 * @param array<string,mixed> $rss_attrs RSS block attributes.
	 * @return array<string,string>
	 */
	private function namespace_map( array $rss_attrs ): array {
		$out = array();
		foreach ( (array) ( $rss_attrs['namespaces'] ?? array() ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$prefix = (string) ( $entry['prefix'] ?? '' );
			$uri    = (string) ( $entry['uri'] ?? '' );
			if ( '' === $prefix || '' === $uri ) {
				continue;
			}
			if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9._-]*$/', $prefix ) ) {
				continue;
			}
			$out[ $prefix ] = $uri;
		}
		return $out;
	}

	/**
	 * Decide whether the client already has the current representation.
	 *
	 * @param string $etag              Current ETag (with quotes).
	 * @param string $post_modified_gmt MySQL-formatted modification timestamp (UTC).
	 */
	private function client_has_fresh_copy( string $etag, string $post_modified_gmt ): bool {
		$inm = isset( $_SERVER['HTTP_IF_NONE_MATCH'] )
			? trim( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_IF_NONE_MATCH'] ) ) )
			: '';
		if ( '' !== $inm ) {
			return $inm === $etag;
		}
		$ims = isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
			: '';
		if ( '' === $ims ) {
			return false;
		}
		$client_ts = strtotime( $ims );
		$server_ts = strtotime( $post_modified_gmt . ' UTC' );
		return false !== $client_ts && false !== $server_ts && $server_ts <= $client_ts;
	}

	/**
	 * Build a tiny error XML body to keep clients from choking on empty 500s.
	 *
	 * @param DOMDocument $dom     DOMDocument to populate.
	 * @param string      $message Error message inserted as the document body.
	 */
	private function error_document( DOMDocument $dom, string $message ): string {
		$err = $dom->createElement( 'error', Sanitize::xml_chars( $message ) );
		$dom->appendChild( $err );
		$xml = $dom->saveXML();
		return false === $xml ? '' : (string) $xml;
	}
}
