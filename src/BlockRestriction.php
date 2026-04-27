<?php
/**
 * Restrict the block editor inserter on Feedwright feed posts.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright;

defined( 'ABSPATH' ) || exit;

/**
 * Limits the editor inserter to Feedwright blocks for `feedwright_feed`
 * post types and surfaces an admin notice for stray blocks at save time.
 */
final class BlockRestriction {

	private const NOTICE_META = '_feedwright_unsupported_blocks';

	/**
	 * Hook into WordPress.
	 */
	public function register(): void {
		add_filter( 'allowed_block_types_all', array( $this, 'filter_allowed_blocks' ), 10, 2 );
		add_action( 'save_post_' . PostType::SLUG, array( $this, 'detect_unsupported_blocks' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'render_unsupported_blocks_notice' ) );
	}

	/**
	 * Restrict allowed blocks on the Feedwright feed post type.
	 *
	 * @param bool|array<int,string>   $allowed Existing allow-list (bool) or array.
	 * @param \WP_Block_Editor_Context $context Editor context.
	 * @return bool|array<int,string>
	 */
	public function filter_allowed_blocks( $allowed, $context ) {
		if ( ! $context instanceof \WP_Block_Editor_Context ) {
			return $allowed;
		}
		if ( ! $context->post || PostType::SLUG !== $context->post->post_type ) {
			return $allowed;
		}
		return BlockRegistry::block_names();
	}

	/**
	 * On save, scan the post content for blocks not registered by Feedwright
	 * and stash a list on post meta so the next admin screen can warn the user.
	 *
	 * @param int      $post_id Post ID being saved.
	 * @param \WP_Post $post    Post object.
	 */
	public function detect_unsupported_blocks( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( PostType::SLUG !== $post->post_type ) {
			return;
		}

		$content = (string) $post->post_content;
		if ( ! has_blocks( $content ) ) {
			delete_post_meta( $post_id, self::NOTICE_META );
			return;
		}

		$allowed     = BlockRegistry::block_names();
		$unsupported = array();
		foreach ( parse_blocks( $content ) as $block ) {
			$this->collect_unsupported( $block, $allowed, $unsupported );
		}

		if ( empty( $unsupported ) ) {
			delete_post_meta( $post_id, self::NOTICE_META );
			return;
		}

		update_post_meta( $post_id, self::NOTICE_META, array_values( array_unique( $unsupported ) ) );
	}

	/**
	 * Walk a parsed block tree and collect any block names not in $allowed.
	 *
	 * @param array<string,mixed> $block       Parsed block.
	 * @param array<int,string>   $allowed     Allowed block names.
	 * @param array<int,string>   $unsupported Output list, populated by reference.
	 */
	private function collect_unsupported( array $block, array $allowed, array &$unsupported ): void {
		$name = (string) ( $block['blockName'] ?? '' );
		if ( '' !== $name && ! in_array( $name, $allowed, true ) ) {
			$unsupported[] = $name;
		}
		foreach ( $block['innerBlocks'] ?? array() as $inner ) {
			if ( is_array( $inner ) ) {
				$this->collect_unsupported( $inner, $allowed, $unsupported );
			}
		}
	}

	/**
	 * Render a one-shot admin notice when unsupported blocks were detected
	 * during the most recent save of the post being edited.
	 */
	public function render_unsupported_blocks_notice(): void {
		if ( ! is_admin() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || PostType::SLUG !== $screen->post_type ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen detection.
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id ) {
			return;
		}
		$names = get_post_meta( $post_id, self::NOTICE_META, true );
		if ( ! is_array( $names ) || empty( $names ) ) {
			return;
		}

		$listed = array_map( 'esc_html', $names );
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <code>%s</code></p></div>',
			esc_html__( 'Feedwright:', 'feedwright' ),
			esc_html__( 'Unsupported blocks were detected and will be ignored when rendering this feed:', 'feedwright' ),
			implode( '</code>, <code>', $listed ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		);
	}
}
