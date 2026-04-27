<?php
/**
 * Locale-independent date formatter for binding providers.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings;

defined( 'ABSPATH' ) || exit;

/**
 * Format Unix timestamps with the site timezone but English day/month names.
 *
 * `wp_date()` (and helpers like `get_the_date()` that wrap it) translate
 * `D` / `M` / `l` / `F` format characters to the site locale. That breaks
 * format strings such as `r` (RFC 2822) and `D, d M Y H:i:s O`, which the
 * RFC 2822 / RFC 3339 specs require to be in English regardless of locale.
 *
 * This helper uses `DateTimeImmutable::format()` instead, which always
 * produces English names — exactly matching PHP's bare `date()` behavior.
 * Numeric formats (`Y`, `m`, `d`, etc.) are unaffected either way.
 */
final class DateFormatter {

	/**
	 * Format a Unix timestamp as a string using the given PHP date format.
	 *
	 * @param int                       $timestamp Unix timestamp (UTC).
	 * @param string                    $format    PHP date format string.
	 * @param \DateTimeZone|string|null $timezone  Output timezone. Defaults to site timezone.
	 */
	public static function format( int $timestamp, string $format, $timezone = null ): string {
		if ( null === $timezone ) {
			$timezone = wp_timezone();
		} elseif ( is_string( $timezone ) ) {
			$timezone = new \DateTimeZone( $timezone );
		}
		$dt = ( new \DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );
		return $dt->format( $format );
	}
}
