<?php
/**
 * Binding resolver: substitutes `{{ns.path:mod|processor:arg}}` expressions.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Bindings;

use Feedwright\Renderer\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Spec §14.6.
 */
final class Resolver {

	private const ESCAPE_MARKER = "\x00FEEDWRIGHT_OPEN\x00";

	/**
	 * Provider registry.
	 *
	 * @var array<string,ProviderInterface> ns => provider
	 */
	private array $providers = array();

	/**
	 * Built-in / filtered processors. Key is the processor name, value is a
	 * callable receiving (string $value, string $arg) and returning string.
	 *
	 * @var array<string,callable>
	 */
	private array $processors;

	/**
	 * Initialise built-in processors and let plugins extend the registry.
	 */
	public function __construct() {
		$defaults = array(
			'truncate'   => array( self::class, 'process_truncate' ),
			'allow_tags' => array( self::class, 'process_allow_tags' ),
			'strip_tags' => array( self::class, 'process_strip_tags' ),
			'map'        => array( self::class, 'process_map' ),
		);

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filter the registered binding processors.
			 *
			 * @param array<string,callable> $defaults Default processors.
			 */
			$defaults = (array) apply_filters( 'feedwright/binding_processors', $defaults );
		}

		$this->processors = $defaults;
	}

	/**
	 * Register a provider, replacing any existing one for the same namespace.
	 *
	 * @param ProviderInterface $provider Provider implementation.
	 */
	public function add( ProviderInterface $provider ): void {
		$this->providers[ $provider->namespace_name() ] = $provider;
	}

	/**
	 * Currently registered providers.
	 *
	 * @return array<string,ProviderInterface>
	 */
	public function providers(): array {
		return $this->providers;
	}

	/**
	 * Substitute every `{{...}}` binding in $expression. `\{{` is an escape
	 * sequence that emits a literal `{{`. After the binding is resolved,
	 * processors chained with `|` are applied in order.
	 *
	 * @param string  $expression Template string.
	 * @param Context $ctx        Render context.
	 */
	public function resolve( string $expression, Context $ctx ): string {
		if ( '' === $expression ) {
			return '';
		}

		$expression = str_replace( '\{{', self::ESCAPE_MARKER, $expression );

		$result = preg_replace_callback(
			'/\{\{([a-z_][a-z0-9_]*(?:\.[a-z0-9_]+)*)(?::([^|}]*))?((?:\|[^}]*)*)\}\}/i',
			function ( array $m ) use ( $ctx ): string {
				$full     = $m[1];
				$modifier = $m[2] ?? '';
				$procs    = $m[3] ?? '';

				$dot = strpos( $full, '.' );
				if ( false === $dot ) {
					$ns   = $full;
					$path = '';
				} else {
					$ns   = substr( $full, 0, $dot );
					$path = substr( $full, $dot + 1 );
				}

				$provider = $this->providers[ $ns ] ?? null;
				if ( null === $provider ) {
					\Feedwright\Plugin::log( "Unknown binding provider: {$ns}" );
					return '';
				}

				$value = $provider->resolve( $path, $modifier, $ctx );
				$value = null === $value ? '' : (string) $value;

				if ( '' !== $procs ) {
					$segments = explode( '|', $procs );
					array_shift( $segments ); // discard empty before the first `|`.
					foreach ( $segments as $segment ) {
						$value = $this->apply_processor( trim( $segment ), $value );
					}
				}

				return $value;
			},
			$expression
		);

		if ( null === $result ) {
			$result = $expression;
		}

		return str_replace( self::ESCAPE_MARKER, '{{', $result );
	}

	/**
	 * Apply a single processor segment (`name:arg`) to $value.
	 *
	 * @param string $segment Processor segment without the leading `|`.
	 * @param string $value   Value to transform.
	 */
	private function apply_processor( string $segment, string $value ): string {
		if ( '' === $segment ) {
			return $value;
		}
		$colon = strpos( $segment, ':' );
		$name  = false === $colon ? $segment : substr( $segment, 0, $colon );
		$arg   = false === $colon ? '' : substr( $segment, $colon + 1 );

		$processor = $this->processors[ $name ] ?? null;
		if ( null === $processor || ! is_callable( $processor ) ) {
			\Feedwright\Plugin::log( "Unknown binding processor: {$name}" );
			return $value;
		}

		$next = call_user_func( $processor, $value, $arg );
		return is_string( $next ) ? $next : $value;
	}

	/**
	 * Aggregated `describe()` output for REST autocompletion.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function describe_all(): array {
		$out = array();
		foreach ( $this->providers as $provider ) {
			foreach ( $provider->describe() as $row ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Truncate to N characters (multibyte-aware). Empty/zero arg = no-op.
	 *
	 * @param string $value Input value.
	 * @param string $arg   Character limit as string.
	 */
	public static function process_truncate( string $value, string $arg ): string {
		$limit = (int) $arg;
		if ( $limit <= 0 ) {
			return $value;
		}
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $value ) <= $limit ) {
			return $value;
		}
		return function_exists( 'mb_substr' )
			? (string) mb_substr( $value, 0, $limit )
			: (string) substr( $value, 0, $limit );
	}

	/**
	 * Allow only the comma-separated tag names. Attributes are stripped.
	 * Empty arg = strip everything.
	 *
	 * @param string $value Input value.
	 * @param string $arg   Comma-separated tag names (e.g. "p,a,strong").
	 */
	public static function process_allow_tags( string $value, string $arg ): string {
		$tags = array_filter( array_map( 'trim', explode( ',', $arg ) ) );
		if ( empty( $tags ) ) {
			return wp_strip_all_tags( $value );
		}
		$allowed = array();
		foreach ( $tags as $tag ) {
			$allowed[ $tag ] = array();
		}
		return wp_kses( $value, $allowed );
	}

	/**
	 * Strip every HTML tag.
	 *
	 * @param string $value Input value.
	 * @param string $arg   Unused.
	 */
	public static function process_strip_tags( string $value, string $arg ): string {
		unset( $arg );
		return wp_strip_all_tags( $value );
	}

	/**
	 * Map the input to a value via `key=val,*=fallback` pairs.
	 *
	 * Examples:
	 *   {{post_raw.post_status|map:publish=1,*=0}}
	 *   {{post_meta.priority|map:high=★★★,medium=★★,low=★}}
	 *
	 * - `*` defines the fallback value when no other key matches.
	 * - Keys and values are trimmed; first `=` separates them so values may
	 *   contain `=`.
	 * - When no key matches and no `*` is given, the empty string is returned
	 *   (the goal is a deterministic mapped output, not pass-through).
	 *
	 * @param string $value Input value.
	 * @param string $arg   Mapping spec.
	 */
	public static function process_map( string $value, string $arg ): string {
		if ( '' === $arg ) {
			return $value;
		}

		$fallback = null;
		foreach ( explode( ',', $arg ) as $pair ) {
			$eq = strpos( $pair, '=' );
			if ( false === $eq ) {
				continue;
			}
			$key = trim( substr( $pair, 0, $eq ) );
			$out = trim( substr( $pair, $eq + 1 ) );
			if ( '*' === $key ) {
				$fallback = $out;
				continue;
			}
			if ( $key === $value ) {
				return $out;
			}
		}
		return null === $fallback ? '' : $fallback;
	}
}
