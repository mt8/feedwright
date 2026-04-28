<?php
/**
 * Plugin bootstrap singleton.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up Feedwright's runtime components.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Retrieve the singleton instance, creating it on first access.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up runtime components.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );

		( new PostType() )->register();
		( new Settings() )->register();
		( new BlockRegistry() )->register();
		( new BlockRestriction() )->register();

		( new Cache\RenderCache() )->register();

		$resolver = self::build_resolver();
		( new Routing\FeedEndpoint( $resolver ) )->register();
		( new REST\PreviewController() )->register();
		( new REST\BindingIntrospectionController() )->register();
	}

	/**
	 * Construct the binding resolver and register the default providers,
	 * exposing them via the `feedwright/binding_providers` filter for extension.
	 */
	public static function build_resolver(): Bindings\Resolver {
		$resolver = new Bindings\Resolver();

		/**
		 * Filter the list of binding providers.
		 *
		 * @param array<int,Bindings\ProviderInterface> $providers Default providers.
		 */
		$providers = (array) apply_filters(
			'feedwright/binding_providers',
			array(
				new Bindings\Providers\OptionProvider(),
				new Bindings\Providers\FeedProvider(),
				new Bindings\Providers\NowProvider(),
				new Bindings\Providers\PostProvider(),
				new Bindings\Providers\PostRawProvider(),
				new Bindings\Providers\PostMetaProvider(),
				new Bindings\Providers\PostTermProvider(),
				new Bindings\Providers\PostTermMetaProvider(),
				new Bindings\Providers\AuthorProvider(),
			)
		);
		foreach ( $providers as $provider ) {
			if ( $provider instanceof Bindings\ProviderInterface ) {
				$resolver->add( $provider );
			}
		}
		return $resolver;
	}

	/**
	 * Load plugin translations from /languages.
	 *
	 * WP 6.7+ auto-loads translations, but we keep this for the 6.5 support
	 * floor. Hooked at init priority 1 so subsequent code can use translated
	 * strings.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'feedwright',
			false,
			dirname( plugin_basename( FEEDWRIGHT_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Plugin activation hook.
	 *
	 * Registers the post type and feed endpoint so WordPress knows about
	 * both before flushing rewrite rules.
	 */
	public static function on_activation(): void {
		( new PostType() )->register();
		( new Routing\FeedEndpoint() )->register();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 */
	public static function on_deactivation(): void {
		flush_rewrite_rules();
	}

	/**
	 * Lightweight debug logger gated by WP_DEBUG_LOG.
	 *
	 * @param string $message Message to log.
	 */
	public static function log( string $message ): void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Feedwright] ' . $message );
		}
	}
}
