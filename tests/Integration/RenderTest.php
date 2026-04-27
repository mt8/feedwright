<?php
/**
 * Integration tests for the parse_blocks-driven renderer.
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Integration;

use Feedwright\Plugin;
use Feedwright\PostType;
use Feedwright\Renderer\Renderer;
use WP_UnitTestCase;

final class RenderTest extends WP_UnitTestCase {

	private function build_demo_content(): string {
		$el_bind = static function ( string $tag, string $expression, array $attrs = array() ): string {
			$json = wp_json_encode(
				array(
					'tagName'         => $tag,
					'contentMode'     => 'binding',
					'bindingExpression' => '{{' . $expression . '}}',
					'attributes'      => $attrs,
				)
			);
			return "<!-- wp:feedwright/element {$json} /-->";
		};
		$el_cdata = static function ( string $tag, string $expression ): string {
			$json = wp_json_encode(
				array(
					'tagName'         => $tag,
					'contentMode'     => 'cdata-binding',
					'bindingExpression' => '{{' . $expression . '}}',
				)
			);
			return "<!-- wp:feedwright/element {$json} /-->";
		};
		$el_empty = static function ( string $tag, array $attrs ): string {
			$json = wp_json_encode(
				array(
					'tagName'     => $tag,
					'contentMode' => 'empty',
					'attributes'  => $attrs,
				)
			);
			return "<!-- wp:feedwright/element {$json} /-->";
		};

		$channel_elements = implode(
			"\n",
			array(
				$el_bind( 'title', 'option.blogname' ),
				$el_bind( 'link', 'option.home_url' ),
				$el_bind( 'description', 'option.blogdescription' ),
			)
		);

		$item_elements = implode(
			"\n",
			array(
				$el_bind( 'title', 'post.post_title' ),
				$el_bind( 'link', 'post.permalink' ),
				$el_bind(
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
				$el_bind( 'pubDate', 'post.post_date:r' ),
				$el_cdata( 'content:encoded', 'post.post_content' ),
				$el_empty(
					'media:thumbnail',
					array(
						array(
							'name'      => 'url',
							'valueMode' => 'binding',
							'value'     => '{{post.thumbnail_url:large}}',
						),
					)
				),
			)
		);

		$item_query_attrs = wp_json_encode(
			array(
				'postType'     => array( 'post' ),
				'postsPerPage' => 5,
				'orderBy'      => 'date',
				'order'        => 'DESC',
			)
		);
		$item_query_block = "<!-- wp:feedwright/item-query {$item_query_attrs} -->\n<!-- wp:feedwright/item -->\n{$item_elements}\n<!-- /wp:feedwright/item -->\n<!-- /wp:feedwright/item-query -->";

		$channel_block = "<!-- wp:feedwright/channel -->\n{$channel_elements}\n{$item_query_block}\n<!-- /wp:feedwright/channel -->";

		$rss_attrs = wp_json_encode(
			array(
				'version'    => '2.0',
				'namespaces' => array(
					array( 'prefix' => 'content', 'uri' => 'http://purl.org/rss/1.0/modules/content/' ),
					array( 'prefix' => 'media', 'uri' => 'http://search.yahoo.com/mrss/' ),
				),
			)
		);
		return "<!-- wp:feedwright/rss {$rss_attrs} -->\n{$channel_block}\n<!-- /wp:feedwright/rss -->";
	}

	private function make_feed_post( string $content ): \WP_Post {
		update_option( 'blogname', 'Example Site' );
		update_option( 'blogdescription', 'A test site' );
		update_option( 'home', 'https://example.com' );
		update_option( 'siteurl', 'https://example.com' );

		$post_id = self::factory()->post->create(
			array(
				'post_type'    => PostType::SLUG,
				'post_title'   => 'Smart',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
		return get_post( $post_id );
	}

	public function test_demo_like_feed_renders(): void {
		// Two source posts that the item-query will pick up.
		self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Article A',
				'post_excerpt' => 'Excerpt A',
				'post_content' => '<p>Body A.</p>',
				'post_status'  => 'publish',
				'post_date'    => '2026-04-26 10:00:00',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_title'   => 'Article B',
				'post_excerpt' => 'Excerpt B',
				'post_content' => '<p>Body B.</p>',
				'post_status'  => 'publish',
				'post_date'    => '2026-04-25 10:00:00',
			)
		);

		$feed_post = $this->make_feed_post( $this->build_demo_content() );

		$renderer = new Renderer( Plugin::build_resolver() );
		$result   = $renderer->render( $feed_post );
		$xml      = $result['xml'];

		$this->assertStringContainsString( '<?xml', $xml );
		$this->assertStringContainsString( '<rss ', $xml );
		$this->assertStringContainsString( 'version="2.0"', $xml );
		$this->assertStringContainsString( 'xmlns:content="http://purl.org/rss/1.0/modules/content/"', $xml );
		$this->assertStringContainsString( 'xmlns:media="http://search.yahoo.com/mrss/"', $xml );
		$this->assertStringContainsString( '<channel>', $xml );
		$this->assertStringContainsString( '<title>Example Site</title>', $xml );
		// WP のテスト環境は WP_HOME 定数で home_url を固定するため、
		// 具体的な URL は環境依存。トークン解決が走ったこと（プレースホルダ
		// 文字列がそのまま出ていないこと）を確認する。
		$this->assertStringNotContainsString( '{{option.home_url}}', $xml );
		$this->assertMatchesRegularExpression( '#<link>https?://[^<]+</link>#', $xml );
		$this->assertStringContainsString( '<description>A test site</description>', $xml );

		// Items render in date DESC order.
		$pos_a = strpos( $xml, 'Article A' );
		$pos_b = strpos( $xml, 'Article B' );
		$this->assertNotFalse( $pos_a );
		$this->assertNotFalse( $pos_b );
		$this->assertLessThan( $pos_b, $pos_a, 'Article A (newer) must come before Article B' );

		$this->assertStringContainsString( '<guid isPermaLink="true">', $xml );
		$this->assertStringContainsString( '<![CDATA[', $xml );
		// DOMDocument may add a redundant xmlns:content on the element itself,
		// so do not require an exact substring without attributes.
		$this->assertMatchesRegularExpression( '#<content:encoded[^>]*>#', $xml );
		$this->assertEmpty( $result['warnings'], 'No warnings for valid feed: ' . implode( ', ', $result['warnings'] ) );
	}

	public function test_invalid_tag_name_is_skipped(): void {
		$bad = wp_json_encode(
			array(
				'tagName'         => '123foo',
				'contentMode'     => 'binding',
				'bindingExpression' => '{{option.blogname}}',
			)
		);
		$content = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. "<!-- wp:feedwright/element {$bad} /-->"
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$result    = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post );

		$this->assertStringNotContainsString( '<123foo', $result['xml'] );
		$this->assertStringContainsString( '<channel/>', $result['xml'], 'channel renders empty when its only child is invalid' );
	}

	public function test_custom_item_tag_name(): void {
		$item_attrs = wp_json_encode( array( 'itemTagName' => 'entry', 'postsPerPage' => 1 ) );
		$el_title   = '<!-- wp:feedwright/element {"tagName":"title","contentMode":"binding","bindingExpression":"{{post.post_title}}"} /-->';
		$content    = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. "<!-- wp:feedwright/item-query {$item_attrs} --><!-- wp:feedwright/item -->{$el_title}<!-- /wp:feedwright/item --><!-- /wp:feedwright/item-query -->"
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Hello' ) );
		$feed_post = $this->make_feed_post( $content );
		$xml       = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		$this->assertStringContainsString( '<entry>', $xml );
		$this->assertStringContainsString( '</entry>', $xml );
		$this->assertStringNotContainsString( '<item>', $xml );
	}

	public function test_multiple_item_queries_preserve_order(): void {
		self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Alpha' ) );
		self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Beta' ) );

		$query_a  = wp_json_encode( array( 'postsPerPage' => 1, 'orderBy' => 'title', 'order' => 'ASC' ) );
		$query_b  = wp_json_encode( array( 'postsPerPage' => 1, 'orderBy' => 'title', 'order' => 'DESC' ) );
		$el_title = '<!-- wp:feedwright/element {"tagName":"title","contentMode":"binding","bindingExpression":"{{post.post_title}}"} /-->';
		$item     = "<!-- wp:feedwright/item -->{$el_title}<!-- /wp:feedwright/item -->";

		$content = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. "<!-- wp:feedwright/item-query {$query_a} -->{$item}<!-- /wp:feedwright/item-query -->"
			. "<!-- wp:feedwright/item-query {$query_b} -->{$item}<!-- /wp:feedwright/item-query -->"
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$xml       = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		$pos_alpha = strpos( $xml, 'Alpha' );
		$pos_beta  = strpos( $xml, 'Beta' );
		$this->assertNotFalse( $pos_alpha );
		$this->assertNotFalse( $pos_beta );
		// First query: ASC by title -> Alpha. Second: DESC -> Beta.
		$this->assertLessThan( $pos_beta, $pos_alpha );
	}

	public function test_taxonomy_filter_limits_items_to_matching_terms(): void {
		// Three posts in three categories.
		$pick_term = self::factory()->category->create( array( 'name' => 'Pick', 'slug' => 'pick' ) );
		$skip_term = self::factory()->category->create( array( 'name' => 'Skip', 'slug' => 'skip' ) );

		$pick_a = self::factory()->post->create(
			array( 'post_status' => 'publish', 'post_title' => 'Pick A' )
		);
		$pick_b = self::factory()->post->create(
			array( 'post_status' => 'publish', 'post_title' => 'Pick B' )
		);
		$skip_a = self::factory()->post->create(
			array( 'post_status' => 'publish', 'post_title' => 'Skip A' )
		);

		wp_set_post_terms( $pick_a, array( $pick_term ), 'category', false );
		wp_set_post_terms( $pick_b, array( $pick_term ), 'category', false );
		wp_set_post_terms( $skip_a, array( $skip_term ), 'category', false );

		$query_attrs = wp_json_encode(
			array(
				'postsPerPage' => 10,
				'taxQuery'     => array(
					array(
						'taxonomy' => 'category',
						'field'    => 'slug',
						'terms'    => array( 'pick' ),
					),
				),
			)
		);
		$el_title = '<!-- wp:feedwright/element {"tagName":"title","contentMode":"binding","bindingExpression":"{{post.post_title}}"} /-->';
		$item     = "<!-- wp:feedwright/item -->{$el_title}<!-- /wp:feedwright/item -->";
		$content  = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. "<!-- wp:feedwright/item-query {$query_attrs} -->{$item}<!-- /wp:feedwright/item-query -->"
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$xml       = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		$this->assertStringContainsString( 'Pick A', $xml );
		$this->assertStringContainsString( 'Pick B', $xml );
		$this->assertStringNotContainsString( 'Skip A', $xml );
	}

	public function test_allow_tags_processor_strips_unlisted_tags(): void {
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Mixed',
				'post_content' => '<p>kept</p><div>SHOULD_GO</div><strong>bold</strong>',
			)
		);
		// Use post_raw.post_content to bypass the_content filter and test the
		// allow_tags processor in isolation.
		$el_content = '<!-- wp:feedwright/element {"tagName":"description","contentMode":"binding","bindingExpression":"{{post_raw.post_content|allow_tags:p,strong}}"} /-->';
		$content    = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. '<!-- wp:feedwright/item-query {"postsPerPage":1} --><!-- wp:feedwright/item -->'
			. $el_content
			. '<!-- /wp:feedwright/item --><!-- /wp:feedwright/item-query -->'
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$xml       = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		$this->assertStringContainsString( '&lt;p&gt;kept&lt;/p&gt;', $xml );
		$this->assertStringContainsString( '&lt;strong&gt;bold&lt;/strong&gt;', $xml );
		// div stripped, but its text content is preserved by wp_kses.
		$this->assertStringContainsString( 'SHOULD_GO', $xml );
		$this->assertStringNotContainsString( '&lt;div&gt;', $xml );
	}

	public function test_strip_tags_processor_removes_all_html(): void {
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_title'   => 'Stripped',
				'post_content' => '<p>plain <em>text</em> only</p>',
			)
		);
		// Raw content so we can compare verbatim against the kses output.
		$el_content = '<!-- wp:feedwright/element {"tagName":"description","contentMode":"binding","bindingExpression":"{{post_raw.post_content|strip_tags}}"} /-->';
		$content    = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. '<!-- wp:feedwright/item-query {"postsPerPage":1} --><!-- wp:feedwright/item -->'
			. $el_content
			. '<!-- /wp:feedwright/item --><!-- /wp:feedwright/item-query -->'
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$xml       = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		$this->assertStringContainsString( 'plain text only', $xml );
		$this->assertStringNotContainsString( '&lt;p&gt;', $xml );
		$this->assertStringNotContainsString( '&lt;em&gt;', $xml );
	}

	public function test_no_rss_block_returns_error_xml(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => PostType::SLUG,
				'post_status'  => 'publish',
				'post_content' => '<p>not a feed</p>',
			)
		);
		$result = ( new Renderer( Plugin::build_resolver() ) )->render( get_post( $post_id ) );

		$this->assertStringContainsString( '<error>', $result['xml'] );
		$this->assertNotEmpty( $result['warnings'] );
	}
}
