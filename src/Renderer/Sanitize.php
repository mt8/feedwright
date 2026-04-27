<?php
/**
 * Sanitization helpers for XML output.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §13.7.
 */
final class Sanitize {

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
}
