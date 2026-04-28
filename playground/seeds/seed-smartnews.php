<?php
// phpcs:ignoreFile -- demo bootstrap, not part of the plugin runtime.
/**
 * Playground seed: SmartNews SmartFormat (RSS 2.0 準拠) フィード。
 *
 * Spec: https://publishers.smartnews.com/hc/ja/articles/360010977813
 *  - snf / media / dc / content namespace
 *  - content:encoded に記事本文の全文を CDATA で
 *  - media:thumbnail は 200×200 以上必須、L<=3.3S
 *  - media:status=deleted で記事削除（active で公開）
 *  - snf:logo + snf:darkModeLogo
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

// Use the Feedwright logo PNGs that ship under playground/assets/.
// The horizontal wordmark fits SmartNews' "≤700 × ~100 px" recommendation.
// Dark mode: the same logo works since the source SVG already has a dark bg
// with an orange accent (designed to look good on dark surfaces).
$logo_url      = 'https://raw.githubusercontent.com/mt8/feedwright/main/playground/assets/logo-horizontal.png';
$logo_dark_url = 'https://raw.githubusercontent.com/mt8/feedwright/main/playground/assets/logo-horizontal.png';

$channel_logo = feedwright_pg_el(
	array( 'tagName' => 'snf:logo', 'contentMode' => 'children' ),
	array(
		feedwright_pg_static( 'url', $logo_url ),
	)
);
$channel_dark_logo = feedwright_pg_el(
	array( 'tagName' => 'snf:darkModeLogo', 'contentMode' => 'children' ),
	array(
		feedwright_pg_static( 'url', $logo_dark_url ),
	)
);

$channel_elements = array(
	feedwright_pg_bind( 'title', 'option.blogname' ),
	feedwright_pg_bind( 'link', 'option.home_url' ),
	feedwright_pg_bind( 'description', 'option.blogdescription' ),
	feedwright_pg_bind( 'pubDate', 'feed.last_build_date:r' ),
	feedwright_pg_static( 'language', 'ja' ),
	feedwright_pg_bind_inline( 'copyright', '© {{now:Y}} {{option.blogname}}' ),
	feedwright_pg_static( 'ttl', '5' ),
	$channel_logo,
	$channel_dark_logo,
);

$item_elements = array(
	feedwright_pg_bind( 'title', 'post.post_title' ),
	feedwright_pg_bind( 'link', 'post.permalink' ),
	feedwright_pg_bind( 'guid', 'post.permalink', array(
		array( 'name' => 'isPermaLink', 'valueMode' => 'static', 'value' => 'true' ),
	) ),
	feedwright_pg_bind( 'pubDate', 'post.post_date:r' ),
	feedwright_pg_bind( 'description', 'post.post_excerpt' ),
	feedwright_pg_cdata( 'content:encoded', 'post.post_content' ),
	feedwright_pg_bind( 'category', 'post_term.category' ),
	feedwright_pg_bind( 'dc:creator', 'author.display_name' ),
	feedwright_pg_empty(
		'media:thumbnail',
		array(
			array( 'name' => 'url',    'valueMode' => 'binding', 'value' => '{{post.thumbnail_url:large}}' ),
			array( 'name' => 'width',  'valueMode' => 'binding', 'value' => '{{post.thumbnail_width:large}}' ),
			array( 'name' => 'height', 'valueMode' => 'binding', 'value' => '{{post.thumbnail_height:large}}' ),
		)
	),
	feedwright_pg_bind_inline( 'media:status', '{{post_raw.post_status|map:publish=active,trash=deleted,*=active}}' ),
);

$item_query = feedwright_pg_item_query(
	array(
		'label'        => 'SmartFormat feed',
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
		array( 'prefix' => 'media',   'uri' => 'http://search.yahoo.com/mrss/' ),
		array( 'prefix' => 'snf',     'uri' => 'http://www.smartnews.be/snf' ),
	),
	$channel_block
);

$post_id = feedwright_pg_insert_feed( 'smartnews', 'SmartNews SmartFormat', $rss_block );
WP_CLI::success( "Created smartnews feed (post ID {$post_id}) at /feedwright/smartnews/" );
