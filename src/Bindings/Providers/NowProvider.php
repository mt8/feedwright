<?php
/**
 * Binding provider for `now` — current time.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings\Providers;

use Feedwright\Renderer\Context;
use Feedwright\Bindings\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §14.4`now`.
 */
final class NowProvider implements ProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function namespace_name(): string {
		return 'now';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $path     Binding path (must be empty for `now`).
	 * @param string  $modifier Date format (default `c`).
	 * @param Context $ctx      Render context (unused).
	 */
	public function resolve( string $path, string $modifier, Context $ctx ): ?string {
		unset( $ctx );

		// `now` always has an empty path; misuse like `{{now.foo}}` returns null.
		if ( '' !== $path ) {
			return null;
		}
		$format = '' === $modifier ? 'c' : $modifier;
		return (string) wp_date( $format );
	}

	/**
	 * Binding catalogue for this provider.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function describe(): array {
		return array(
			array(
				'expression' => 'now',
				'label'      => 'Current time (ISO 8601)',
				'context'    => 'any',
				'namespace'  => 'now',
			),
			array(
				'expression' => 'now:r',
				'label'      => 'Current time (RFC 2822)',
				'context'    => 'any',
				'namespace'  => 'now',
			),
			array(
				'expression' => 'now:U',
				'label'      => 'Current time (Unix timestamp)',
				'context'    => 'any',
				'namespace'  => 'now',
			),
		);
	}
}
