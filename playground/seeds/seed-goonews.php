<?php
// phpcs:ignoreFile -- demo bootstrap, not part of the plugin runtime.
/**
 * Playground seed: goo ニュース / dmenu ニュース 共通仕様の RSS 2.0 フィード。
 *
 * Spec: https://news.goo.ne.jp/sp/specification/rss2.0/
 *
 * @package Feedwright
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

require_once __DIR__ . '/seed-helpers.php';

$item_elements = array(
	feedwright_pg_bind( 'guid', 'post.permalink', array(
		array( 'name' => 'isPermaLink', 'valueMode' => 'static', 'value' => 'true' ),
	) ),
	feedwright_pg_bind( 'title', 'post.post_title' ),
	feedwright_pg_bind( 'link', 'post.permalink' ),
	feedwright_pg_bind( 'category', 'post_term.category|first' ),
	feedwright_pg_bind( 'pubDate', 'post.post_date:r' ),
	feedwright_pg_bind( 'goonews:modified', 'post.post_modified:r' ),
	feedwright_pg_cdata_inline( 'description', '{{post.post_content|strip_tags|truncate:30000}}' ),
	feedwright_pg_bind( 'dc:creator', 'author.display_name' ),
	// 関連記事（最大 3）— 同じカテゴリの他記事を smp:relation で展開。
	feedwright_pg_sub_query(
		array(
			'relationMode'   => 'taxonomy',
			'taxonomy'       => 'category',
			'postsPerPage'   => 3,
			'orderBy'        => 'date',
			'order'          => 'DESC',
			'excludeCurrent' => true,
		),
		array(
			feedwright_pg_el(
				array( 'tagName' => 'smp:relation', 'contentMode' => 'children' ),
				array(
					feedwright_pg_bind( 'smp:url', 'post.permalink' ),
					feedwright_pg_bind( 'smp:caption', 'post.post_title' ),
				)
			),
		)
	),
);

$channel_elements = array(
	feedwright_pg_bind( 'title', 'option.blogname' ),
	feedwright_pg_bind( 'link', 'option.home_url' ),
	feedwright_pg_bind( 'description', 'option.blogdescription' ),
	feedwright_pg_static( 'language', 'ja' ),
	feedwright_pg_bind( 'lastBuildDate', 'feed.last_build_date:r' ),
);

$item_query = feedwright_pg_item_query(
	array(
		'label'        => 'Latest articles',
		'itemTagName'  => 'item',
		'postType'     => array( 'post' ),
		'postsPerPage' => 20,
		'orderBy'      => 'date',
		'order'        => 'DESC',
		'postStatus'   => array( 'publish' ),
	),
	$item_elements
);

$channel_block = feedwright_pg_channel( implode( "\n", $channel_elements ) . "\n" . $item_query );

$rss_block = feedwright_pg_rss(
	array(
		array( 'prefix' => 'content', 'uri' => 'http://purl.org/rss/1.0/modules/content/' ),
		array( 'prefix' => 'dc',      'uri' => 'http://purl.org/dc/elements/1.1/' ),
		array( 'prefix' => 'goonews', 'uri' => 'http://news.goo.ne.jp/rss/2.0/news/goonews/' ),
		array( 'prefix' => 'smp',     'uri' => 'http://news.goo.ne.jp/rss/2.0/news/smp/' ),
	),
	$channel_block
);

$post_id = feedwright_pg_insert_feed( 'goonews', 'goo ニュース / dmenu ニュース', $rss_block );
WP_CLI::success( "Created goonews feed (post ID {$post_id}) at /feedwright/goonews/" );
