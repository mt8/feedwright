<?php
/**
 * Block registration for Feedwright editor blocks.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Feedwright's seven editor blocks from the build output.
 */
final class BlockRegistry {

	public const CATEGORY = 'feedwright';

	/**
	 * Block names as registered (also the directory under build/).
	 *
	 * @var array<int,string>
	 */
	public const BLOCK_DIRS = array(
		'rss',
		'channel',
		'element',
		'item-query',
		'item',
		'sub-query',
		'sub-item',
		'when',
		'raw',
		'comment',
	);

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_blocks' ) );
		// Enqueuing on `enqueue_block_assets` injects into Gutenberg's iframe;
		// `is_admin()` excludes front-end calls so this only runs in the editor.
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_styles' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'inject_binding_catalogue' ) );
		add_filter( 'block_categories_all', array( $this, 'register_category' ), 10, 2 );
		add_filter( 'pre_load_script_translations', array( $this, 'serve_jed_for_editor_scripts' ), 10, 4 );
	}

	/**
	 * Load the shared editor stylesheet inside the block editor (including the
	 * iframe-based canvas) for `feedwright_feed` only.
	 */
	public function enqueue_editor_styles(): void {
		if ( ! is_admin() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || PostType::SLUG !== ( $screen->post_type ?? '' ) ) {
			return;
		}

		$rel  = 'blocks/_shared/editor.css';
		$path = FEEDWRIGHT_PLUGIN_DIR . $rel;
		if ( ! file_exists( $path ) ) {
			return;
		}
		wp_enqueue_style(
			'feedwright-editor',
			FEEDWRIGHT_PLUGIN_URL . $rel,
			array(),
			(string) filemtime( $path )
		);
	}

	/**
	 * Register blocks from their built directories, falling back to the source
	 * `blocks/` directory when the build output is missing (CI / fresh checkout
	 * without `npm run build`). The fallback is sufficient for backend
	 * registration; the editor still requires build output for JS to load.
	 */
	public function register_blocks(): void {
		foreach ( self::BLOCK_DIRS as $dir ) {
			$path = $this->resolve_block_path( $dir );
			if ( null !== $path ) {
				register_block_type( $path );
				// register_block_type's internal wp_set_script_translations defaults
				// to wp-content/languages; explicitly register the bundled languages/.
				$handle = generate_block_asset_handle( 'feedwright/' . $dir, 'editorScript' );
				wp_set_script_translations(
					$handle,
					'feedwright',
					FEEDWRIGHT_PLUGIN_DIR . 'languages'
				);
			}
		}
	}

	/**
	 * Serve our shipped JED translations regardless of the install folder casing.
	 *
	 * `wp i18n make-json` emits per-source JEDs hashed by source path. WP looks
	 * them up by hash of the registered script's URL path. Per-source paths
	 * (e.g. `blocks/element/edit.js`) never match the build-script path
	 * (`build/element/index.js`) used at runtime, and the install folder casing
	 * can vary between environments too. We bypass the hash lookup entirely by
	 * merging all per-source JEDs in `languages/` and returning the combined
	 * content for any editor handle.
	 *
	 * @param string|false|null $translations Existing JED content.
	 * @param string|false      $file         Resolved JED path (may not exist).
	 * @param string            $handle       Script handle.
	 * @param string            $domain       Text domain.
	 *
	 * @return string|false|null
	 */
	public function serve_jed_for_editor_scripts( $translations, $file, $handle, $domain ) {
		if ( 'feedwright' !== $domain ) {
			return $translations;
		}
		if ( ! preg_match( '/^feedwright-[\w-]+-editor-script$/', $handle ) ) {
			return $translations;
		}

		static $cache = array();
		$locale       = determine_locale();
		if ( ! isset( $cache[ $locale ] ) ) {
			$cache[ $locale ] = $this->build_merged_jed( $locale );
		}
		return $cache[ $locale ] ?? $translations;
	}

	/**
	 * Combine every `feedwright-{locale}-*.json` JED in `languages/` into one
	 * JED payload suitable for `wp.i18n.setLocaleData()`.
	 *
	 * @param string $locale Locale slug.
	 *
	 * @return string|null JSON string, or null when no JED is shipped for the locale.
	 */
	private function build_merged_jed( string $locale ): ?string {
		$pattern = FEEDWRIGHT_PLUGIN_DIR . 'languages/feedwright-' . $locale . '-*.json';
		$files   = glob( $pattern );
		if ( empty( $files ) ) {
			return null;
		}
		$messages = array(
			'' => array(
				'domain'       => 'messages',
				'lang'         => $locale,
				'plural-forms' => 'nplurals=2; plural=(n != 1);',
			),
		);
		foreach ( $files as $jed_path ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data = json_decode( (string) file_get_contents( $jed_path ), true );
			$msgs = $data['locale_data']['messages'] ?? array();
			foreach ( $msgs as $key => $value ) {
				if ( '' === $key ) {
					continue;
				}
				$messages[ $key ] = $value;
			}
		}
		return (string) wp_json_encode(
			array(
				'domain'      => 'messages',
				'locale_data' => array( 'messages' => $messages ),
			)
		);
	}

	/**
	 * Make the binding catalogue and runtime settings available to the editor:
	 *  - `window.feedwrightBindings`   — full describe() output (offline cache).
	 *  - `window.feedwrightSettings`   — { urlBase, homeUrl } for the preview panel.
	 *
	 * Triggered only on the block editor screen for `feedwright_feed`.
	 */
	public function inject_binding_catalogue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || PostType::SLUG !== ( $screen->post_type ?? '' ) ) {
			return;
		}

		$resolver  = Plugin::build_resolver();
		$catalogue = $resolver->describe_all();

		$settings = array(
			'urlBase' => trim( (string) get_option( Settings::OPTION_URL_BASE, Settings::DEFAULT_URL_BASE ), '/' ),
			'homeUrl' => home_url( '/' ),
		);

		// Each block.json registers its handle as `<namespace>-<name>-editor-script`
		// (e.g. feedwright-element-editor-script).
		$handle = generate_block_asset_handle( 'feedwright/element', 'editorScript' );
		wp_add_inline_script(
			$handle,
			'window.feedwrightBindings = ' . wp_json_encode( $catalogue ) . ';' .
			'window.feedwrightSettings = ' . wp_json_encode( $settings ) . ';',
			'before'
		);
	}

	/**
	 * Locate the directory containing a block's `block.json`.
	 *
	 * @param string $dir Block subdirectory name.
	 */
	private function resolve_block_path( string $dir ): ?string {
		$build = FEEDWRIGHT_PLUGIN_DIR . 'build/' . $dir;
		if ( file_exists( $build . '/block.json' ) ) {
			return $build;
		}
		$src = FEEDWRIGHT_PLUGIN_DIR . 'blocks/' . $dir;
		if ( file_exists( $src . '/block.json' ) ) {
			return $src;
		}
		return null;
	}

	/**
	 * Add the Feedwright category to the editor's block category list.
	 *
	 * Restricted to the `feedwright_feed` post type to avoid leaking the
	 * category into post / page editing screens.
	 *
	 * @param array<int,array<string,mixed>>    $categories Existing categories.
	 * @param \WP_Block_Editor_Context|\WP_Post $context    Editor context.
	 * @return array<int,array<string,mixed>>
	 */
	public function register_category( array $categories, $context ): array {
		$post = null;
		if ( $context instanceof \WP_Block_Editor_Context ) {
			$post = $context->post ?? null;
		} elseif ( $context instanceof \WP_Post ) {
			$post = $context;
		}

		if ( ! $post || PostType::SLUG !== $post->post_type ) {
			return $categories;
		}

		array_unshift(
			$categories,
			array(
				'slug'  => self::CATEGORY,
				'title' => __( 'Feedwright', 'feedwright' ),
				'icon'  => 'rss',
			)
		);
		return $categories;
	}

	/**
	 * Fully-qualified block names (`feedwright/{dir}`).
	 *
	 * @return array<int,string>
	 */
	public static function block_names(): array {
		return array_map(
			static fn ( string $dir ): string => 'feedwright/' . $dir,
			self::BLOCK_DIRS
		);
	}
}
