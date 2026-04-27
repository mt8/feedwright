<?php
// phpcs:ignoreFile -- demo bootstrap script, not part of the plugin runtime.
/**
 * WordPress Playground seed for the Feedwright demo.
 *
 * Boots the site with:
 *  - pretty permalinks enabled
 *  - 4 sample posts (with placeholder featured images, categories, tags)
 *  - one Feedwright feed shaped like a SmartNews-compatible SmartFormat feed
 *
 * Loaded by blueprint.json via:
 *   wp eval-file wp-content/plugins/feedwright/playground/seed.php
 *
 * Designed for the WP Playground demo only — not used in production.
 *
 * @package Feedwright
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Generate a 1200x630 PNG placeholder named after the post and register it as
 * an attachment, so featured images do not require an outbound HTTP fetch.
 *
 * @param int    $post_id Parent post ID.
 * @param string $title   Title to draw on the image.
 * @return int|null Attachment ID, or null if GD is unavailable.
 */
function feedwright_playground_placeholder( int $post_id, string $title ): ?int {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return null;
	}

	$width  = 1200;
	$height = 630;
	$image  = imagecreatetruecolor( $width, $height );

	$seed     = abs( crc32( $title ) );
	$bg_color = imagecolorallocate( $image, ( $seed * 13 ) % 200 + 30, ( $seed * 17 ) % 200 + 30, ( $seed * 19 ) % 200 + 30 );
	imagefilledrectangle( $image, 0, 0, $width, $height, $bg_color );

	$accent = imagecolorallocate( $image, 255, 255, 255 );
	imagefilledrectangle( $image, 60, $height - 90, $width - 60, $height - 70, $accent );

	$text_color = imagecolorallocate( $image, 255, 255, 255 );
	$lines      = str_split( $title, 40 );
	$y          = 80;
	foreach ( $lines as $line ) {
		imagestring( $image, 5, 60, $y, $line, $text_color );
		$y += 30;
	}
	imagestring( $image, 3, 60, $height - 60, 'Feedwright sample', $text_color );

	$uploads = wp_get_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		imagedestroy( $image );
		return null;
	}
	$slug     = sanitize_title( $title );
	$basename = $slug . '-' . substr( md5( $title ), 0, 6 ) . '.png';
	$dest     = trailingslashit( $uploads['path'] ) . $basename;
	$ok       = imagepng( $image, $dest );
	imagedestroy( $image );
	if ( ! $ok ) {
		return null;
	}

	$attach_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$dest,
		$post_id
	);
	if ( ! $attach_id || is_wp_error( $attach_id ) ) {
		return null;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $dest ) );
	update_post_meta( $attach_id, '_wp_attachment_image_alt', $title );

	return (int) $attach_id;
}

WP_CLI::log( '== Site options ==' );
update_option( 'blogname', 'Feedwright Demo' );
update_option( 'blogdescription', 'A WordPress site with a Feedwright-built SmartNews-shaped feed.' );
// Pretty permalinks so /feedwright/{slug}/ works without query strings.
update_option( 'permalink_structure', '/%postname%/' );

WP_CLI::log( '== Taxonomy terms ==' );
$cat_ids = array();
foreach ( array( 'News', 'Tech', 'Lifestyle' ) as $cat ) {
	$existing = get_term_by( 'name', $cat, 'category' );
	if ( $existing ) {
		$cat_ids[ $cat ] = (int) $existing->term_id;
		continue;
	}
	$created = wp_insert_term( $cat, 'category' );
	if ( ! is_wp_error( $created ) ) {
		$cat_ids[ $cat ] = (int) $created['term_id'];
	}
}

$tag_names = array( 'wordpress', 'rss', 'smartnews', 'feed', 'editor' );
foreach ( $tag_names as $tag ) {
	if ( ! get_term_by( 'name', $tag, 'post_tag' ) ) {
		wp_insert_term( $tag, 'post_tag' );
	}
}

WP_CLI::log( '== Author profile ==' );
$admin = get_user_by( 'login', 'admin' );
if ( $admin ) {
	wp_update_user(
		array(
			'ID'           => $admin->ID,
			'display_name' => 'Feedwright Editorial',
			'first_name'   => 'Feedwright',
			'last_name'    => 'Editorial',
		)
	);
	wp_set_current_user( $admin->ID );
	// Admins on single-site have unfiltered_html; disable KSES to keep
	// block-comment delimiter JSON intact through wp_insert_post.
	kses_remove_filters();
}

WP_CLI::log( '== Sample posts ==' );
$old_post_ids = get_posts(
	array(
		'post_type'   => 'post',
		'numberposts' => -1,
		'post_status' => 'any',
		'fields'      => 'ids',
	)
);
foreach ( $old_post_ids as $old_id ) {
	wp_delete_post( (int) $old_id, true );
}

$sample_posts = array(
	array(
		'title'    => 'WordPress 6.9 highlights for editors',
		'excerpt'  => 'A quick tour of what WordPress 6.9 brings to people who live in the block editor.',
		'content'  => "<p>WordPress 6.9 doubles down on the Block Bindings API, making dynamic data inserts feel native to the editor.</p>\n<p>The biggest change is improved <strong>Synced Patterns</strong>, which makes shipping the same template to multiple sites genuinely practical.</p>",
		'date'     => '2026-04-26 09:00:00',
		'category' => 'Tech',
		'tags'     => array( 'wordpress', 'editor' ),
	),
	array(
		'title'    => 'Five things to get right when shaping a SmartNews feed',
		'excerpt'  => 'The pitfalls when serving a SmartFormat-compliant feed from WordPress.',
		'content'  => "<p>SmartNews delivery is more than RSS 2.0. Things like <code>snf:logo</code>, <code>snf:analytics</code>, and well-formed <code>media:thumbnail</code> entries are what separate \"works\" from \"reliable\".</p>\n<p>This piece walks through five mistakes editors run into when assembling a SmartFormat feed with Feedwright.</p>",
		'date'     => '2026-04-25 12:30:00',
		'category' => 'News',
		'tags'     => array( 'smartnews', 'rss', 'feed' ),
	),
	array(
		'title'    => 'Editing RSS in the block editor: why bother?',
		'excerpt'  => 'Stop hand-writing XML — start composing it.',
		'content'  => "<p>For years, custom RSS / Atom feeds have meant a theme template or an MU plugin. Feedwright treats the feed itself as a block tree.</p>\n<p>Here is the design rationale and the operational wins it produces.</p>",
		'date'     => '2026-04-24 16:00:00',
		'category' => 'Tech',
		'tags'     => array( 'wordpress', 'editor', 'feed' ),
	),
	array(
		'title'    => 'Bindings make WordPress data fluent for syndication',
		'excerpt'  => 'No SSG, no headless setup — just expression syntax against post data.',
		'content'  => "<p>With <code>{{post.post_title}}</code>-style expressions in place, you can pipe CMS data straight into XML output. That is a chunk of the SSG playbook brought back to WordPress.</p>",
		'date'     => '2026-04-23 18:45:00',
		'category' => 'Lifestyle',
		'tags'     => array( 'wordpress', 'rss' ),
	),
);

foreach ( $sample_posts as $sample ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_title'   => $sample['title'],
			'post_excerpt' => $sample['excerpt'],
			'post_content' => $sample['content'],
			'post_status'  => 'publish',
			'post_date'    => $sample['date'],
			'post_author'  => $admin ? $admin->ID : 1,
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		continue;
	}
	if ( isset( $cat_ids[ $sample['category'] ] ) ) {
		wp_set_post_terms( $post_id, array( $cat_ids[ $sample['category'] ] ), 'category', false );
	}
	if ( ! empty( $sample['tags'] ) ) {
		wp_set_post_terms( $post_id, $sample['tags'], 'post_tag', false );
	}
	$attach_id = feedwright_playground_placeholder( $post_id, $sample['title'] );
	if ( $attach_id ) {
		set_post_thumbnail( $post_id, $attach_id );
	}
}

WP_CLI::log( '== Feedwright feed ==' );

$existing = get_page_by_path( 'smartnews', OBJECT, 'feedwright_feed' );
if ( $existing instanceof WP_Post ) {
	wp_delete_post( $existing->ID, true );
}

$el = function ( array $attrs, array $inner_blocks = array() ): string {
	$json = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( empty( $inner_blocks ) ) {
		return "<!-- wp:feedwright/element {$json} /-->";
	}
	return "<!-- wp:feedwright/element {$json} -->\n" . implode( "\n", $inner_blocks ) . "\n<!-- /wp:feedwright/element -->";
};
$bind          = function ( string $tag, string $expr, array $attrs = array() ) use ( $el ): string {
	return $el(
		array(
			'tagName'           => $tag,
			'contentMode'       => 'binding',
			'bindingExpression' => '{{' . $expr . '}}',
			'attributes'        => $attrs,
		)
	);
};
$bind_inline   = function ( string $tag, string $expression, array $attrs = array() ) use ( $el ): string {
	return $el(
		array(
			'tagName'           => $tag,
			'contentMode'       => 'binding',
			'bindingExpression' => $expression,
			'attributes'        => $attrs,
		)
	);
};
$cdata         = function ( string $tag, string $expr ) use ( $el ): string {
	return $el(
		array(
			'tagName'           => $tag,
			'contentMode'       => 'cdata-binding',
			'bindingExpression' => '{{' . $expr . '}}',
		)
	);
};
$cdata_inline  = function ( string $tag, string $expression ) use ( $el ): string {
	return $el(
		array(
			'tagName'           => $tag,
			'contentMode'       => 'cdata-binding',
			'bindingExpression' => $expression,
		)
	);
};
$empty_el      = function ( string $tag, array $attrs ) use ( $el ): string {
	return $el(
		array(
			'tagName'     => $tag,
			'contentMode' => 'empty',
			'attributes'  => $attrs,
		)
	);
};
$static_el     = function ( string $tag, string $value ) use ( $el ): string {
	return $el(
		array(
			'tagName'     => $tag,
			'contentMode' => 'static',
			'staticValue' => $value,
		)
	);
};

$channel_logo = $el(
	array(
		'tagName'     => 'snf:logo',
		'contentMode' => 'children',
	),
	array(
		$static_el( 'url', 'https://example.com/feedwright-logo.png' ),
	)
);

$channel_elements = array(
	$bind( 'title', 'option.blogname' ),
	$bind( 'link', 'option.home_url' ),
	$bind( 'description', 'option.blogdescription' ),
	$bind( 'language', 'option.language' ),
	$bind_inline( 'copyright', '© {{now:Y}} {{option.blogname}}. All rights reserved.' ),
	$bind( 'lastBuildDate', 'feed.last_build_date:r' ),
	$channel_logo,
);

$item_elements = array(
	$bind( 'title', 'post.post_title' ),
	$bind( 'link', 'post.permalink' ),
	$bind(
		'guid',
		'post.permalink',
		array(
			array(
				'name'      => 'isPermaLink',
				'valueMode' => 'static',
				'value'     => 'true',
			),
		)
	),
	$bind( 'pubDate', 'post.post_date:r' ),
	$bind( 'description', 'post.post_excerpt' ),
	$cdata( 'content:encoded', 'post.post_content' ),
	$bind( 'dc:creator', 'author.display_name' ),
	$bind_inline( 'author', '{{author.user_email}} ({{author.display_name}})' ),
	$bind( 'category', 'post_term.category' ),
	$empty_el(
		'media:thumbnail',
		array(
			array(
				'name'      => 'url',
				'valueMode' => 'binding',
				'value'     => '{{post.thumbnail_url:medium}}',
			),
			array(
				'name'      => 'width',
				'valueMode' => 'binding',
				'value'     => '{{post.thumbnail_width:medium}}',
			),
			array(
				'name'      => 'height',
				'valueMode' => 'binding',
				'value'     => '{{post.thumbnail_height:medium}}',
			),
		)
	),
	$empty_el(
		'media:content',
		array(
			array(
				'name'      => 'url',
				'valueMode' => 'binding',
				'value'     => '{{post.thumbnail_url:large}}',
			),
			array(
				'name'      => 'medium',
				'valueMode' => 'static',
				'value'     => 'image',
			),
			array(
				'name'      => 'type',
				'valueMode' => 'binding',
				'value'     => '{{post.thumbnail_mime}}',
			),
		)
	),
	$bind_inline( 'media:status', '{{post_raw.post_status|map:publish=1,*=0}}' ),
);

$item_block       = "<!-- wp:feedwright/item -->\n" . implode( "\n", $item_elements ) . "\n<!-- /wp:feedwright/item -->";
$item_query_attrs = wp_json_encode(
	array(
		'label'        => 'Latest articles',
		'itemTagName'  => 'item',
		'postType'     => array( 'post' ),
		'postsPerPage' => 20,
		'orderBy'      => 'date',
		'order'        => 'DESC',
		'postStatus'   => array( 'publish' ),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
$item_query_block = "<!-- wp:feedwright/item-query {$item_query_attrs} -->\n{$item_block}\n<!-- /wp:feedwright/item-query -->";
$channel_block    = "<!-- wp:feedwright/channel -->\n" . implode( "\n", $channel_elements ) . "\n{$item_query_block}\n<!-- /wp:feedwright/channel -->";
$rss_attrs        = wp_json_encode(
	array(
		'version'    => '2.0',
		'namespaces' => array(
			array(
				'prefix' => 'content',
				'uri'    => 'http://purl.org/rss/1.0/modules/content/',
			),
			array(
				'prefix' => 'media',
				'uri'    => 'http://search.yahoo.com/mrss/',
			),
			array(
				'prefix' => 'snf',
				'uri'    => 'http://www.smartnews.be/snf',
			),
			array(
				'prefix' => 'dc',
				'uri'    => 'http://purl.org/dc/elements/1.1/',
			),
		),
	),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
$rss_block        = "<!-- wp:feedwright/rss {$rss_attrs} -->\n{$channel_block}\n<!-- /wp:feedwright/rss -->";

// wp_insert_post calls wp_unslash internally, which strips backslashes used to
// escape JSON quotes in the block delimiters. wp_slash before insert.
$post_id = wp_insert_post(
	array(
		'post_type'    => 'feedwright_feed',
		'post_title'   => 'My SmartNews',
		'post_name'    => 'smartnews',
		'post_status'  => 'publish',
		'post_content' => wp_slash( $rss_block ),
		'post_author'  => $admin ? $admin->ID : 1,
	),
	true
);
if ( is_wp_error( $post_id ) ) {
	WP_CLI::error( $post_id->get_error_message() );
}
WP_CLI::success( "Created feed post ID {$post_id} at /feedwright/smartnews/" );

// Drop a tiny mu-plugin that opens the seeded feed in the block editor when
// the Playground landing page hits it. Written here (not committed to the
// plugin) so the demo helper does not ship with the plugin.
$mu_dir = WP_CONTENT_DIR . '/mu-plugins';
if ( ! is_dir( $mu_dir ) ) {
	wp_mkdir_p( $mu_dir );
}
$mu_code = <<<PHP
<?php
/**
 * Feedwright Playground demo: redirect to the seeded feed editor.
 * Auto-generated by playground/seed.php — not part of the plugin.
 */
add_action( 'init', function () {
    if ( empty( \$_GET['fw_demo_open_feed_editor'] ) ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        return;
    }
    wp_safe_redirect( admin_url( 'post.php?post={$post_id}&action=edit' ) );
    exit;
}, 1 );
PHP;
file_put_contents( $mu_dir . '/feedwright-demo-redirect.php', $mu_code );
WP_CLI::log( 'Wrote demo landing redirector to mu-plugins/' );

// Flush rewrite rules so the /feedwright/{slug}/ endpoint resolves immediately.
flush_rewrite_rules();

if ( class_exists( '\Feedwright\Cache\RenderCache' ) ) {
	( new \Feedwright\Cache\RenderCache() )->flush_all();
}
