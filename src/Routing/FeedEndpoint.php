<?php
/**
 * Public feed URL routing.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright\Routing;

use Feedwright\PostType;
use Feedwright\Renderer\Renderer;
use Feedwright\Settings;
use Feedwright\Bindings\Resolver;

defined( 'ABSPATH' ) || exit;

/**
 * Wires `/{base}/{slug}/` URLs to the renderer.
 */
final class FeedEndpoint {

	public const QUERY_VAR = 'feedwright_feed_slug';

	/**
	 * Binding resolver passed through to the renderer.
	 *
	 * @var Resolver
	 */
	private Resolver $resolver;

	/**
	 * Wire the endpoint with an optional resolver.
	 *
	 * @param Resolver|null $resolver Binding resolver. Tests may pass null.
	 */
	public function __construct( ?Resolver $resolver = null ) {
		$this->resolver = $resolver ?? \Feedwright\Plugin::build_resolver();
	}

	/**
	 * Hook the endpoint into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_rewrites' ), 10 );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_serve_feed' ), 5 );
	}

	/**
	 * Add the rewrite rule and tag for the feed prefix.
	 */
	public function register_rewrites(): void {
		$base = self::current_base();
		if ( '' === $base ) {
			return;
		}

		add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([^&]+)' );
		add_rewrite_rule(
			'^' . preg_quote( $base, '#' ) . '/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Whitelist the public query var.
	 *
	 * @param array<int,string> $vars Existing public query vars.
	 * @return array<int,string>
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Detect feed requests and dispatch to the renderer.
	 */
	public function maybe_serve_feed(): void {
		$slug = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $slug ) {
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) )
			: 'GET';
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			$this->respond_method_not_allowed();
			exit;
		}

		$resolved = get_page_by_path( $slug, OBJECT, PostType::SLUG );
		$post     = $resolved instanceof \WP_Post ? $resolved : null;

		if ( $this->is_preview_request() ) {
			$this->serve_preview( $post );
			exit;
		}

		if ( null === $post || 'publish' !== $post->post_status ) {
			$this->respond_not_found();
			exit;
		}

		( new Renderer( $this->resolver ) )->render_to_output( $post, $this->is_pretty_request() );
		exit;
	}

	/**
	 * Whether the caller asked for a human-readable (formatted) feed.
	 *
	 * Gated to logged-in admins (or `WP_DEBUG` builds) to avoid leaking the
	 * pretty variant to scrapers — production output stays minified.
	 */
	private function is_pretty_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only debug flag.
		if ( ! isset( $_GET['pretty'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '1' !== sanitize_text_field( wp_unslash( (string) $_GET['pretty'] ) ) ) {
			return false;
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}
		return is_user_logged_in() && current_user_can( 'manage_options' );
	}

	/**
	 * Compute the URL base used for rewrite rules. Empty string disables routing.
	 */
	public static function current_base(): string {
		$base = (string) get_option( Settings::OPTION_URL_BASE, Settings::DEFAULT_URL_BASE );
		return trim( $base, '/' );
	}

	/**
	 * Build the public URL for a feed slug.
	 *
	 * @param string $slug Feed post_name.
	 */
	public static function feed_url( string $slug ): string {
		$base = self::current_base();
		if ( '' === $base ) {
			return '';
		}
		return home_url( '/' . $base . '/' . ltrim( $slug, '/' ) . '/' );
	}

	/**
	 * Whether the current request is asking for a preview.
	 */
	private function is_preview_request(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified in serve_preview().
		if ( ! isset( $_GET['feedwright_preview'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$flag = sanitize_text_field( wp_unslash( (string) $_GET['feedwright_preview'] ) );
		return '1' === $flag;
	}

	/**
	 * Serve a preview to logged-in admins regardless of post status.
	 *
	 * @param \WP_Post|null $post Resolved post or null.
	 */
	private function serve_preview( ?\WP_Post $post ): void {
		$nonce = isset( $_GET['_wpnonce'] )
			? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) )
			: '';

		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			$this->respond_forbidden();
			return;
		}
		if ( null === $post ) {
			$this->respond_not_found();
			return;
		}
		if ( ! wp_verify_nonce( $nonce, 'feedwright_preview_' . $post->ID ) ) {
			$this->respond_forbidden();
			return;
		}

		nocache_headers();
		// Preview is for human inspection: always pretty-print.
		( new Renderer( $this->resolver ) )->render_to_output( $post, true );
	}

	/**
	 * Emit a 404 with a tiny XML error body.
	 */
	private function respond_not_found(): void {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<error>Feed not found</error>';
	}

	/**
	 * Emit a 403 with a tiny XML error body.
	 */
	private function respond_forbidden(): void {
		status_header( 403 );
		nocache_headers();
		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<error>Forbidden</error>';
	}

	/**
	 * Emit a 405 with the appropriate Allow header.
	 */
	private function respond_method_not_allowed(): void {
		status_header( 405 );
		header( 'Allow: GET, HEAD' );
		nocache_headers();
	}
}
