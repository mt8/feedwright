<?php
/**
 * Plugin settings registration and admin UI.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright;

defined( 'ABSPATH' ) || exit;

/**
 * Owns registered options and the dedicated settings page.
 */
final class Settings {

	public const OPTION_URL_BASE   = 'feedwright_url_base';
	public const OPTION_CACHE_TTL  = 'feedwright_cache_ttl';
	public const OPTION_DB_VERSION = 'feedwright_db_version';

	public const DEFAULT_URL_BASE  = 'feedwright';
	public const DEFAULT_CACHE_TTL = 300;
	public const MAX_CACHE_TTL     = 86400;

	/**
	 * Slugs that cannot be used as the URL base (rejected on save). Spec §9.2.
	 *
	 * @var array<int,string>
	 */
	private const RESERVED_BASES = array(
		'wp-admin',
		'wp-content',
		'wp-includes',
		'feed',
		'comments',
		'xmlrpc.php',
		'wp-json',
	);

	private const PAGE_SLUG        = 'feedwright-settings';
	private const NONCE_CACHE      = 'feedwright_clear_cache';
	private const NOTICE_TRANSIENT = 'feedwright_settings_notices';

	/**
	 * Hook the settings into WordPress.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'update_option_' . self::OPTION_URL_BASE, array( $this, 'flush_rewrites_on_base_change' ), 10, 2 );
		add_action( 'admin_post_feedwright_clear_cache', array( $this, 'handle_clear_cache' ) );
	}

	/**
	 * Register options with the Settings API.
	 */
	public function register_settings(): void {
		register_setting(
			'feedwright',
			self::OPTION_URL_BASE,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_url_base' ),
				'show_in_rest'      => true,
				'default'           => self::DEFAULT_URL_BASE,
			)
		);
		register_setting(
			'feedwright',
			self::OPTION_CACHE_TTL,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_cache_ttl' ),
				'show_in_rest'      => true,
				'default'           => self::DEFAULT_CACHE_TTL,
			)
		);
		register_setting(
			'feedwright',
			self::OPTION_DB_VERSION,
			array(
				'type'         => 'string',
				'show_in_rest' => false,
				'default'      => FEEDWRIGHT_VERSION,
			)
		);
	}

	/**
	 * Register the settings submenu page under the Feedwright menu.
	 */
	public function register_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . PostType::SLUG,
			__( 'Feedwright Settings', 'feedwright' ),
			__( 'Settings', 'feedwright' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Render the settings page HTML.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$url_base   = (string) get_option( self::OPTION_URL_BASE, self::DEFAULT_URL_BASE );
		$cache_ttl  = (int) get_option( self::OPTION_CACHE_TTL, self::DEFAULT_CACHE_TTL );
		$plain_perm = '' === (string) get_option( 'permalink_structure', '' );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Feedwright Settings', 'feedwright' ); ?></h1>

			<?php if ( $plain_perm ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'Permalinks are set to "Plain". Pretty rewrite rules will not work; please change your permalink structure for Feedwright to serve feeds.', 'feedwright' ); ?></p>
				</div>
			<?php endif; ?>

			<?php settings_errors( 'feedwright' ); ?>

			<form action="options.php" method="post">
				<?php settings_fields( 'feedwright' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="feedwright_url_base"><?php esc_html_e( 'URL Base', 'feedwright' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="feedwright_url_base"
								name="<?php echo esc_attr( self::OPTION_URL_BASE ); ?>"
								value="<?php echo esc_attr( $url_base ); ?>"
								class="regular-text"
							/>
							<p class="description">
								<?php
								printf(
									/* translators: %s: example URL path */
									esc_html__( 'The path prefix for feed URLs. Example: %s', 'feedwright' ),
									'<code>' . esc_html( home_url( '/' . $url_base . '/{slug}/' ) ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="feedwright_cache_ttl"><?php esc_html_e( 'Cache TTL (seconds)', 'feedwright' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="feedwright_cache_ttl"
								name="<?php echo esc_attr( self::OPTION_CACHE_TTL ); ?>"
								value="<?php echo esc_attr( (string) $cache_ttl ); ?>"
								min="0"
								max="<?php echo esc_attr( (string) self::MAX_CACHE_TTL ); ?>"
								class="small-text"
							/>
							<p class="description">
								<?php esc_html_e( 'Render cache lifetime. 0 disables the cache.', 'feedwright' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Maintenance', 'feedwright' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="feedwright_clear_cache" />
				<?php wp_nonce_field( self::NONCE_CACHE ); ?>
				<?php submit_button( __( 'Clear render cache', 'feedwright' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize/validate the url base setting.
	 *
	 * @param mixed $value Submitted value.
	 * @return string Normalized URL base; the previous value on validation error.
	 */
	public function sanitize_url_base( $value ): string {
		$current = (string) get_option( self::OPTION_URL_BASE, self::DEFAULT_URL_BASE );
		$value   = is_string( $value ) ? trim( $value, " \t\n\r\0\x0B/" ) : '';

		$result = self::validate_url_base( $value );
		if ( null !== $result['error'] ) {
			add_settings_error( 'feedwright', 'feedwright_url_base', self::translate_url_base_error( $result['error'], $result['detail'] ), 'error' );
			return $current;
		}

		$collision = self::detect_slug_collision( $value );
		if ( null !== $collision ) {
			add_settings_error(
				'feedwright',
				'feedwright_url_base_collision',
				sprintf(
					/* translators: %s: conflicting slug */
					__( 'URL base "%s" matches an existing page/post slug. Feedwright will take precedence, but consider renaming.', 'feedwright' ),
					$collision
				),
				'warning'
			);
		}

		return $value;
	}

	/**
	 * Translate a validation error code into a human-readable message.
	 *
	 * @param string $code   One of the codes returned by validate_url_base().
	 * @param string $detail Extra context (e.g. the offending segment).
	 */
	private static function translate_url_base_error( string $code, string $detail ): string {
		switch ( $code ) {
			case 'empty':
				return __( 'URL base cannot be empty.', 'feedwright' );
			case 'invalid_chars':
				return __( 'URL base may only contain lowercase letters, digits, slashes, underscores, and hyphens, and must start and end with a letter or digit.', 'feedwright' );
			case 'reserved':
				return sprintf(
					/* translators: %s: reserved path */
					__( 'URL base cannot start with the reserved path "%s".', 'feedwright' ),
					$detail
				);
			default:
				return __( 'URL base is invalid.', 'feedwright' );
		}
	}

	/**
	 * Sanitize the cache TTL setting.
	 *
	 * @param mixed $value Submitted value.
	 * @return int Clamped to [0, MAX_CACHE_TTL].
	 */
	public static function sanitize_cache_ttl( $value ): int {
		$ttl = is_numeric( $value ) ? (int) $value : self::DEFAULT_CACHE_TTL;
		if ( $ttl < 0 ) {
			$ttl = 0;
		}
		if ( $ttl > self::MAX_CACHE_TTL ) {
			$ttl = self::MAX_CACHE_TTL;
		}
		return $ttl;
	}

	/**
	 * Pure validation for the URL base. Returns a normalized value and an
	 * error code (no translation); callers handle add_settings_error.
	 *
	 * @param string $value Trimmed candidate URL base.
	 * @return array{value:string,error:?string,detail:string} `error` is null on success,
	 *               otherwise one of `empty`, `invalid_chars`, `reserved`. `detail` carries
	 *               extra context such as the offending path segment.
	 */
	public static function validate_url_base( string $value ): array {
		if ( '' === $value ) {
			return array(
				'value'  => $value,
				'error'  => 'empty',
				'detail' => '',
			);
		}

		if ( ! preg_match( '#^[a-z0-9](?:[a-z0-9/_-]*[a-z0-9])?$#', $value ) ) {
			return array(
				'value'  => $value,
				'error'  => 'invalid_chars',
				'detail' => '',
			);
		}

		$head = explode( '/', $value )[0];
		if ( in_array( $head, self::RESERVED_BASES, true ) ) {
			return array(
				'value'  => $value,
				'error'  => 'reserved',
				'detail' => $head,
			);
		}

		return array(
			'value'  => $value,
			'error'  => null,
			'detail' => '',
		);
	}

	/**
	 * Detect collision with an existing page/post slug.
	 *
	 * @param string $value URL base being validated.
	 * @return string|null The colliding head segment, or null if there is no collision.
	 */
	public static function detect_slug_collision( string $value ): ?string {
		$head = explode( '/', $value )[0];
		$page = get_page_by_path( $head, OBJECT, array( 'page', 'post' ) );
		if ( null === $page ) {
			return null;
		}
		return $head;
	}

	/**
	 * On URL base change, flush rewrite rules so the new prefix is honoured.
	 *
	 * @param string $old_value Previous value.
	 * @param string $value     New value.
	 */
	public function flush_rewrites_on_base_change( $old_value, $value ): void {
		if ( $old_value === $value ) {
			return;
		}
		flush_rewrite_rules( false );
	}

	/**
	 * Handle the manual cache-clear admin-post action.
	 */
	public function handle_clear_cache(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to clear the cache.', 'feedwright' ) );
		}
		check_admin_referer( self::NONCE_CACHE );

		( new \Feedwright\Cache\RenderCache() )->flush_all();

		add_settings_error( 'feedwright', 'feedwright_cache_cleared', __( 'Render cache cleared.', 'feedwright' ), 'updated' );
		set_transient( self::NOTICE_TRANSIENT, get_settings_errors( 'feedwright' ), 30 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => PostType::SLUG,
					'page'      => self::PAGE_SLUG,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
