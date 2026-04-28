<?php
/**
 * Sanitization helpers for XML output.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Renderer;

use DOMElement;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §13.7.
 */
final class Sanitize {

	public const MODE_STRICT = 'strict';
	public const MODE_COMPAT = 'compat';

	/**
	 * Validate that $name is a syntactically valid XML 1.0 Name (with optional
	 * single namespace prefix).
	 *
	 * @param string $name Candidate XML Name.
	 */
	public static function is_valid_xml_name( string $name ): bool {
		if ( '' === $name ) {
			return false;
		}
		return (bool) preg_match( '/^[A-Za-z_][A-Za-z0-9._-]*(?::[A-Za-z_][A-Za-z0-9._-]*)?$/', $name );
	}

	/**
	 * Strip XML 1.0 illegal control characters (keeps \t \n \r).
	 *
	 * @param string $value String to clean.
	 */
	public static function xml_chars( string $value ): string {
		$out = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value );
		return null === $out ? $value : $out;
	}

	/**
	 * Coerce an arbitrary string to a known output mode token, defaulting to
	 * strict.
	 *
	 * @param string $mode Candidate mode.
	 */
	public static function normalize_mode( string $mode ): string {
		return self::MODE_COMPAT === $mode ? self::MODE_COMPAT : self::MODE_STRICT;
	}

	/**
	 * Build the DOM nodes that represent $value as text content in the given
	 * output mode. Returns an array because strict mode may produce multiple
	 * nodes (text + entity references) for a single value.
	 *
	 * - Strict: `'` / `"` become predefined XML entity references
	 *   (`&apos;` / `&quot;`) — DOMDocument does not auto-escape these in
	 *   text nodes, so we insert them as DOMEntityReference children that
	 *   survive `saveXML()` intact. `&`, `<`, `>` continue to be auto-escaped.
	 * - Compat: original behavior — a single text node with only `&`, `<`, `>`
	 *   escaped by DOMDocument.
	 *
	 * @param \DOMDocument $dom   Owner document for the produced nodes.
	 * @param string       $value Already-resolved value.
	 * @param string       $mode  Output mode (`strict` / `compat`).
	 * @return array<int,\DOMNode>
	 */
	public static function build_text_nodes( \DOMDocument $dom, string $value, string $mode ): array {
		$clean = self::xml_chars( $value );
		if ( '' === $clean ) {
			return array();
		}
		if ( self::MODE_STRICT !== $mode ) {
			return array( $dom->createTextNode( $clean ) );
		}
		$parts = preg_split( "/(['\"])/", $clean, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return array( $dom->createTextNode( $clean ) );
		}
		$nodes = array();
		foreach ( $parts as $part ) {
			if ( "'" === $part ) {
				$nodes[] = $dom->createEntityReference( 'apos' );
			} elseif ( '"' === $part ) {
				$nodes[] = $dom->createEntityReference( 'quot' );
			} elseif ( '' !== $part ) {
				$nodes[] = $dom->createTextNode( $part );
			}
		}
		return $nodes;
	}

	/**
	 * Append a string as text content of $element, encoded according to mode.
	 *
	 * @param DOMElement $element Element to append to.
	 * @param string     $value   Already-resolved value.
	 * @param string     $mode    Output mode.
	 */
	public static function append_text_node( DOMElement $element, string $value, string $mode ): void {
		$dom = $element->ownerDocument; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMElement core property.
		if ( null === $dom ) {
			return;
		}
		foreach ( self::build_text_nodes( $dom, $value, $mode ) as $node ) {
			$element->appendChild( $node );
		}
	}

	/**
	 * Append text content that would normally be wrapped in CDATA. Strict mode
	 * downgrades to entity-encoded text (semantically equivalent, but matches
	 * specs that forbid CDATA); compat mode emits the CDATA section as before.
	 *
	 * @param DOMElement $element Element to append to.
	 * @param string     $value   Already-resolved value.
	 * @param string     $mode    Output mode.
	 */
	public static function append_cdata_or_text( DOMElement $element, string $value, string $mode ): void {
		$dom = $element->ownerDocument; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMElement core property.
		if ( null === $dom ) {
			return;
		}
		if ( self::MODE_STRICT === $mode ) {
			self::append_text_node( $element, $value, $mode );
			return;
		}
		$element->appendChild( $dom->createCDATASection( self::xml_chars( $value ) ) );
	}
}
