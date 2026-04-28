<?php
// phpcs:ignoreFile -- demo bootstrap, not part of the plugin runtime.
/**
 * Block-markup helper functions shared across the per-format Playground seeds.
 *
 * Loaded via `require` from each seed-{format}.php. Functions are namespaced
 * with `feedwright_pg_` to avoid collisions inside the WP-CLI process.
 *
 * @package Feedwright
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'feedwright_pg_el' ) ) {
	/**
	 * Build a `feedwright/element` block. Inner blocks may be other element
	 * markup strings produced by the helpers below.
	 */
	function feedwright_pg_el( array $attrs, array $inner_blocks = array() ): string {
		$json = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( empty( $inner_blocks ) ) {
			return "<!-- wp:feedwright/element {$json} /-->";
		}
		return "<!-- wp:feedwright/element {$json} -->\n" . implode( "\n", $inner_blocks ) . "\n<!-- /wp:feedwright/element -->";
	}

	/** Element with `contentMode=binding` and a `{{token}}`-style expression. */
	function feedwright_pg_bind( string $tag, string $token, array $attrs = array() ): string {
		return feedwright_pg_el( array(
			'tagName'           => $tag,
			'contentMode'       => 'binding',
			'bindingExpression' => '{{' . $token . '}}',
			'attributes'        => $attrs,
		) );
	}

	/** Element with `contentMode=binding` and an inline expression (mix of literal text + bindings). */
	function feedwright_pg_bind_inline( string $tag, string $expression, array $attrs = array() ): string {
		return feedwright_pg_el( array(
			'tagName'           => $tag,
			'contentMode'       => 'binding',
			'bindingExpression' => $expression,
			'attributes'        => $attrs,
		) );
	}

	/** Element with `contentMode=cdata-binding` (HTML body). */
	function feedwright_pg_cdata( string $tag, string $token ): string {
		return feedwright_pg_el( array(
			'tagName'           => $tag,
			'contentMode'       => 'cdata-binding',
			'bindingExpression' => '{{' . $token . '}}',
		) );
	}

	/** cdata-binding with an inline expression. */
	function feedwright_pg_cdata_inline( string $tag, string $expression ): string {
		return feedwright_pg_el( array(
			'tagName'           => $tag,
			'contentMode'       => 'cdata-binding',
			'bindingExpression' => $expression,
		) );
	}

	/** Self-closing element with attributes only (`<media:thumbnail url=".."/>`). */
	function feedwright_pg_empty( string $tag, array $attrs ): string {
		return feedwright_pg_el( array(
			'tagName'     => $tag,
			'contentMode' => 'empty',
			'attributes'  => $attrs,
		) );
	}

	/** Static text element (`<language>ja</language>`). */
	function feedwright_pg_static( string $tag, string $value ): string {
		return feedwright_pg_el( array(
			'tagName'     => $tag,
			'contentMode' => 'static',
			'staticValue' => $value,
		) );
	}

	/** Wrap inner element blocks in a `feedwright/item-query` block. */
	function feedwright_pg_item_query( array $attrs, array $item_inner_blocks ): string {
		$attrs_json = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$item_block = "<!-- wp:feedwright/item -->\n" . implode( "\n", $item_inner_blocks ) . "\n<!-- /wp:feedwright/item -->";
		return "<!-- wp:feedwright/item-query {$attrs_json} -->\n{$item_block}\n<!-- /wp:feedwright/item-query -->";
	}

	/** Wrap a body in a `feedwright/channel` block. */
	function feedwright_pg_channel( string $body ): string {
		return "<!-- wp:feedwright/channel -->\n{$body}\n<!-- /wp:feedwright/channel -->";
	}

	/** Wrap a channel in a `feedwright/rss` block with given namespaces. */
	function feedwright_pg_rss( array $namespaces, string $channel_block, string $output_mode = 'strict' ): string {
		$attrs = wp_json_encode( array(
			'version'    => '2.0',
			'namespaces' => $namespaces,
			'outputMode' => $output_mode,
		), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return "<!-- wp:feedwright/rss {$attrs} -->\n{$channel_block}\n<!-- /wp:feedwright/rss -->";
	}

	/** Build a `feedwright/when` block wrapping inner element markup. */
	function feedwright_pg_when( string $expression, array $inner_blocks, bool $negate = false ): string {
		$attrs_json = wp_json_encode( array(
			'expression' => $expression,
			'negate'     => $negate,
		), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return "<!-- wp:feedwright/when {$attrs_json} -->\n" . implode( "\n", $inner_blocks ) . "\n<!-- /wp:feedwright/when -->";
	}

	/** Build a `feedwright/sub-query` + `feedwright/sub-item` (for related-post expansion). */
	function feedwright_pg_sub_query( array $attrs, array $sub_item_inner_blocks ): string {
		$attrs_json = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$sub_item   = "<!-- wp:feedwright/sub-item -->\n" . implode( "\n", $sub_item_inner_blocks ) . "\n<!-- /wp:feedwright/sub-item -->";
		return "<!-- wp:feedwright/sub-query {$attrs_json} -->\n{$sub_item}\n<!-- /wp:feedwright/sub-query -->";
	}

	/**
	 * Insert a feedwright_feed post (delete previous if same slug exists).
	 *
	 * @return int New post ID
	 */
	function feedwright_pg_insert_feed( string $slug, string $title, string $rss_block ): int {
		$existing = get_page_by_path( $slug, OBJECT, 'feedwright_feed' );
		if ( $existing instanceof WP_Post ) {
			wp_delete_post( $existing->ID, true );
		}
		$admin   = get_user_by( 'login', 'admin' );
		$post_id = wp_insert_post( array(
			'post_type'    => 'feedwright_feed',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_content' => wp_slash( $rss_block ),
			'post_author'  => $admin ? $admin->ID : 1,
		), true );
		if ( is_wp_error( $post_id ) ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::warning( "[{$slug}] " . $post_id->get_error_message() );
			}
			return 0;
		}
		return (int) $post_id;
	}
}
