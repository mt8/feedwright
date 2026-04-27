<?php
/**
 * Binding provider for `option.*` — site-wide options and bloginfo aliases.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings\Providers;

use Feedwright\Renderer\Context;
use Feedwright\Bindings\ProviderInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §14.4`option.*`.
 */
final class OptionProvider implements ProviderInterface {

	/**
	 * {@inheritDoc}
	 */
	public function namespace_name(): string {
		return 'option';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string  $path     Binding path.
	 * @param string  $modifier Binding modifier (unused).
	 * @param Context $ctx      Render context (unused).
	 */
	public function resolve( string $path, string $modifier, Context $ctx ): ?string {
		unset( $modifier, $ctx );

		switch ( $path ) {
			case 'home_url':
				return (string) home_url( '/' );
			case 'site_url':
				return (string) site_url( '/' );
			case 'language':
				return (string) get_bloginfo( 'language' );
			case 'charset':
				return (string) get_bloginfo( 'charset' );
		}

		if ( '' === $path ) {
			return null;
		}

		$value = get_option( $path, null );
		if ( null === $value ) {
			return null;
		}
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		return (string) $value;
	}

	/**
	 * Binding catalogue for this provider.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function describe(): array {
		$rows = array();
		foreach (
			array(
				'home_url'        => 'Site URL (home)',
				'site_url'        => 'Site URL (admin root)',
				'blogname'        => 'Site name',
				'blogdescription' => 'Site tagline',
				'language'        => 'Site language (BCP 47)',
				'charset'         => 'Site charset',
				'timezone_string' => 'Site timezone',
			) as $key => $label
		) {
			$rows[] = array(
				'expression' => "option.{$key}",
				'label'      => $label,
				'context'    => 'any',
				'namespace'  => 'option',
			);
		}
		$rows[] = array(
			'expression' => 'option.{any_key}',
			'label'      => 'Any get_option() key (scalar values only)',
			'context'    => 'any',
			'namespace'  => 'option',
			'dynamic'    => true,
		);
		return $rows;
	}
}
