<?php
// phpcs:ignoreFile -- demo bootstrap, not part of the plugin runtime.
/**
 * Playground seed: mediba (au Web ポータル) フィード。
 *
 * Spec: https://article.auone.jp/specification/index.html
 *  - mdf namespace
 *  - description は最大 65,500 byte（CDATA 可）
 *  - <mdf:deleted/> でゴミ箱投稿の削除通知
 *  - <category> は CP 割り当て ID（term meta `_mediba_category_id` から解決）
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

// 通常 (publish) 投稿の要素。trash 投稿には feedwright/when で <mdf:deleted/>
// だけを出す逆フローも組み込んでいる。
$normal_elements = array(
	feedwright_pg_bind_inline( 'title', '{{post.post_title|truncate:255}}' ),
	feedwright_pg_cdata_inline( 'description', '{{post.post_content|truncate:30000}}' ),
	feedwright_pg_bind( 'guid', 'post.permalink', array(
		array( 'name' => 'isPermaLink', 'valueMode' => 'static', 'value' => 'true' ),
	) ),
	feedwright_pg_bind( 'link', 'post.permalink' ),
	feedwright_pg_bind_inline( 'category', '{{post_term_meta.category._mediba_category_id|default:91}}' ),
	feedwright_pg_bind( 'pubDate', 'post.post_date:r' ),
	feedwright_pg_bind( 'mdf:modified', 'post.post_modified:r' ),
	feedwright_pg_bind( 'mdf:thumbnail', 'post.thumbnail_url:large' ),
);

// 削除フロー: trash 投稿には <mdf:deleted/> のみ
$item_elements = array(
	feedwright_pg_when(
		'{{post_raw.post_status|eq:trash}}',
		array( feedwright_pg_empty( 'mdf:deleted', array() ) )
	),
	feedwright_pg_when(
		'{{post_raw.post_status|eq:trash}}',
		$normal_elements,
		true // negate
	),
);

$channel_elements = array(
	feedwright_pg_bind_inline( 'title', '{{option.blogname|truncate:255}}' ),
	feedwright_pg_bind_inline( 'description', '{{option.blogdescription|truncate:255}}' ),
	feedwright_pg_bind( 'link', 'option.home_url' ),
);

$item_query = feedwright_pg_item_query(
	array(
		'label'           => 'Articles + delete notifications',
		'itemTagName'     => 'item',
		'postType'        => array( 'post' ),
		'postsPerPage'    => 20,
		'orderBy'         => 'date',
		'order'           => 'DESC',
		'postStatus'      => array( 'publish', 'trash' ),
		'trashWithinDays' => 7,
	),
	$item_elements
);

$channel_block = feedwright_pg_channel( implode( "\n", $channel_elements ) . "\n" . $item_query );

$rss_block = feedwright_pg_rss(
	array(
		array( 'prefix' => 'mdf', 'uri' => 'http://www.mediba.jp/mdf' ),
	),
	$channel_block
);

$post_id = feedwright_pg_insert_feed( 'mediba', 'mediba (au Web Portal)', $rss_block );
WP_CLI::success( "Created mediba feed (post ID {$post_id}) at /feedwright/mediba/" );
