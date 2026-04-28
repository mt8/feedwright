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

	/**
	 * Compose a feed whose item template embeds a sub-query whose sub-item
	 * template emits one element bound to a related post field.
	 */
	private function build_sub_query_feed(
		array $sub_query_attrs,
		string $sub_item_inner = '<!-- wp:feedwright/element {"tagName":"related","contentMode":"binding","bindingExpression":"{{post.post_title}}"} /-->'
	): string {
		$sub_query_json = wp_json_encode( $sub_query_attrs );

		$item_inner = '<!-- wp:feedwright/element {"tagName":"title","contentMode":"binding","bindingExpression":"{{post.post_title}}"} /-->'
			. "<!-- wp:feedwright/sub-query {$sub_query_json} -->"
			. '<!-- wp:feedwright/sub-item -->'
			. $sub_item_inner
			. '<!-- /wp:feedwright/sub-item -->'
			. '<!-- /wp:feedwright/sub-query -->';

		$item_query_attrs = wp_json_encode( array( 'postsPerPage' => 10, 'orderBy' => 'title', 'order' => 'ASC' ) );

		return '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. "<!-- wp:feedwright/item-query {$item_query_attrs} --><!-- wp:feedwright/item -->"
			. $item_inner
			. '<!-- /wp:feedwright/item --><!-- /wp:feedwright/item-query -->'
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';
	}

	public function test_sub_query_taxonomy_emits_related_titles_and_excludes_self(): void {
		$term = self::factory()->category->create( array( 'name' => 'Tech', 'slug' => 'tech' ) );
		$one  = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Tech One' ) );
		$two  = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Tech Two' ) );
		$lone = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Lonely' ) );

		wp_set_post_terms( $one, array( $term ), 'category', false );
		wp_set_post_terms( $two, array( $term ), 'category', false );

		$feed_post = $this->make_feed_post(
			$this->build_sub_query_feed(
				array(
					'relationMode'  => 'taxonomy',
					'taxonomy'      => 'category',
					'postsPerPage'  => 5,
					'excludeCurrent' => true,
				)
			)
		);

		$xml = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		// Outer item titles for all three.
		$this->assertStringContainsString( '<title>Tech One</title>', $xml );
		$this->assertStringContainsString( '<title>Tech Two</title>', $xml );
		$this->assertStringContainsString( '<title>Lonely</title>', $xml );

		// Each Tech item should reference the *other* Tech post via <related>.
		$this->assertStringContainsString( '<related>Tech Two</related>', $xml );
		$this->assertStringContainsString( '<related>Tech One</related>', $xml );

		// Lonely has no shared term -> no <related> output for it.
		// Sub-query for Lonely should produce zero nodes (the only <related>
		// occurrences come from the two Tech items referencing each other).
		$this->assertSame( 2, substr_count( $xml, '<related>' ), 'Lonely item should emit no related nodes' );

		// Self-reference must never appear.
		$this->assertStringNotContainsString( '<related>Tech One</related><related>Tech One</related>', $xml );
		// Save outer post IDs for downstream debugging if this fails.
		unset( $one, $two, $lone );
	}

	public function test_sub_query_manual_mode_orders_by_post_in(): void {
		$first  = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'First Pick' ) );
		$second = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Second Pick' ) );
		self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Outer' ) );

		$feed_post = $this->make_feed_post(
			$this->build_sub_query_feed(
				array(
					'relationMode' => 'manual',
					'manualIds'    => array( $second, $first ),
					'postsPerPage' => 5,
				)
			)
		);

		$xml = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		// Manual mode preserves the order specified in manualIds.
		$pos_second = strpos( $xml, '<related>Second Pick</related>' );
		$pos_first  = strpos( $xml, '<related>First Pick</related>' );
		$this->assertNotFalse( $pos_second );
		$this->assertNotFalse( $pos_first );
		$this->assertLessThan( $pos_first, $pos_second, 'Manual order (second, first) must be preserved.' );
	}

	public function test_sub_query_taxonomy_mode_ignores_flat_taxonomies(): void {
		// post_tag is non-hierarchical; sharing the same tag must NOT produce
		// related nodes — flat taxonomies are user-typed free input.
		$one = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Tagged One' ) );
		$two = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Tagged Two' ) );
		wp_set_post_terms( $one, array( 'shared' ), 'post_tag', false );
		wp_set_post_terms( $two, array( 'shared' ), 'post_tag', false );

		$feed_post = $this->make_feed_post(
			$this->build_sub_query_feed(
				array(
					'relationMode'  => 'taxonomy',
					'taxonomy'      => 'post_tag',
					'postsPerPage'  => 5,
					'excludeCurrent' => true,
				)
			)
		);

		$xml = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		$this->assertStringContainsString( '<title>Tagged One</title>', $xml );
		$this->assertStringContainsString( '<title>Tagged Two</title>', $xml );
		// Sub-query is skipped wholesale → no <related> nodes anywhere.
		$this->assertSame( 0, substr_count( $xml, '<related>' ) );
	}

	public function test_sub_query_hard_max_filter_caps_results(): void {
		$term  = self::factory()->category->create( array( 'name' => 'Many', 'slug' => 'many' ) );
		$ids   = array();
		$ids[] = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'M1' ) );
		$ids[] = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'M2' ) );
		$ids[] = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'M3' ) );
		$ids[] = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'M4' ) );
		foreach ( $ids as $id ) {
			wp_set_post_terms( $id, array( $term ), 'category', false );
		}

		$cap = static fn ( int $max ): int => 1;
		add_filter( 'feedwright/sub_query/hard_max', $cap );

		try {
			$feed_post = $this->make_feed_post(
				$this->build_sub_query_feed(
					array(
						'relationMode'  => 'taxonomy',
						'taxonomy'      => 'category',
						'postsPerPage'  => 50,
						'excludeCurrent' => true,
					)
				)
			);

			$xml = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

			// Each of the 4 outer items should emit at most 1 <related> node.
			$this->assertSame( 4, substr_count( $xml, '<related>' ), 'hard_max=1 must cap each sub-query at one node' );
		} finally {
			remove_filter( 'feedwright/sub_query/hard_max', $cap );
		}
	}

	public function test_sub_query_outside_item_context_emits_no_nodes(): void {
		// Place sub-query directly in <channel> (no item ancestor) and verify it
		// degrades to zero nodes without crashing the renderer.
		$sub_query_attrs = wp_json_encode( array( 'relationMode' => 'manual', 'manualIds' => array( 1 ) ) );
		$content         = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. "<!-- wp:feedwright/sub-query {$sub_query_attrs} --><!-- wp:feedwright/sub-item -->"
			. '<!-- wp:feedwright/element {"tagName":"x","contentMode":"binding","bindingExpression":"{{post.post_title}}"} /-->'
			. '<!-- /wp:feedwright/sub-item --><!-- /wp:feedwright/sub-query -->'
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$result    = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post );

		// Renderer treats sub-query at channel level as an unsupported block, so
		// channel ends up empty and a warning is recorded.
		$this->assertStringContainsString( '<channel/>', $result['xml'] );
	}

	public function test_post_term_meta_binding_resolves_first_term_meta(): void {
		// Aggregator category-mapping pattern: assign a CP-side numeric ID to
		// each WP category once via term meta, then bind to it from the feed.
		$cat = self::factory()->category->create( array( 'name' => 'Helpful', 'slug' => 'helpful' ) );
		add_term_meta( $cat, '_mediba_category_id', '91', true );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Tip Article' ) );
		wp_set_post_terms( $post_id, array( $cat ), 'category', false );

		$el      = '<!-- wp:feedwright/element {"tagName":"category","contentMode":"binding","bindingExpression":"{{post_term_meta.category._mediba_category_id|default:99}}"} /-->';
		$content = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. '<!-- wp:feedwright/item-query {"postsPerPage":5} --><!-- wp:feedwright/item -->'
			. $el
			. '<!-- /wp:feedwright/item --><!-- /wp:feedwright/item-query -->'
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$xml       = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		$this->assertStringContainsString( '<category>91</category>', $xml );
	}

	public function test_post_term_meta_falls_back_via_default_processor_when_meta_unset(): void {
		// Term exists but no meta value -> default processor supplies fallback.
		$cat = self::factory()->category->create( array( 'name' => 'Plain', 'slug' => 'plain' ) );
		// No add_term_meta() call here.

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Plain Article' ) );
		wp_set_post_terms( $post_id, array( $cat ), 'category', false );

		$el      = '<!-- wp:feedwright/element {"tagName":"category","contentMode":"binding","bindingExpression":"{{post_term_meta.category._mediba_category_id|default:99}}"} /-->';
		$content = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. '<!-- wp:feedwright/item-query {"postsPerPage":5} --><!-- wp:feedwright/item -->'
			. $el
			. '<!-- /wp:feedwright/item --><!-- /wp:feedwright/item-query -->'
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$xml       = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		$this->assertStringContainsString( '<category>99</category>', $xml );
	}

	public function test_post_term_meta_uses_first_term_when_multiple_assigned(): void {
		// Two categories on the same post; the provider returns the first
		// term's meta. wp_set_post_terms ordering / get_the_terms ordering are
		// stable for a given config, so we just verify whichever wins maps to
		// its own term meta value (not the other one's).
		$cat_a = self::factory()->category->create( array( 'name' => 'A', 'slug' => 'a' ) );
		$cat_b = self::factory()->category->create( array( 'name' => 'B', 'slug' => 'b' ) );
		add_term_meta( $cat_a, '_aggregator_id', 'A_ID', true );
		add_term_meta( $cat_b, '_aggregator_id', 'B_ID', true );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Multi Cat' ) );
		wp_set_post_terms( $post_id, array( $cat_a, $cat_b ), 'category', false );

		$el      = '<!-- wp:feedwright/element {"tagName":"category","contentMode":"binding","bindingExpression":"{{post_term_meta.category._aggregator_id}}"} /-->';
		$content = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. '<!-- wp:feedwright/item-query {"postsPerPage":5} --><!-- wp:feedwright/item -->'
			. $el
			. '<!-- /wp:feedwright/item --><!-- /wp:feedwright/item-query -->'
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$xml       = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		// Output is exactly one of the two term meta values (whichever
		// get_the_terms() returns first), not a join of both.
		$this->assertMatchesRegularExpression( '#<category>(A_ID|B_ID)</category>#', $xml );
		$this->assertStringNotContainsString( 'A_IDB_ID', $xml );
		$this->assertStringNotContainsString( 'A_ID, B_ID', $xml );
	}

	public function test_post_term_meta_returns_empty_when_post_has_no_terms(): void {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'No Cat' ) );
		wp_set_post_terms( $post_id, array(), 'category', false );

		$el      = '<!-- wp:feedwright/element {"tagName":"category","contentMode":"binding","bindingExpression":"{{post_term_meta.category._mediba_category_id|default:99}}"} /-->';
		$content = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. '<!-- wp:feedwright/item-query {"postsPerPage":5} --><!-- wp:feedwright/item -->'
			. $el
			. '<!-- /wp:feedwright/item --><!-- /wp:feedwright/item-query -->'
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$xml       = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		$this->assertStringContainsString( '<category>99</category>', $xml );
	}

	public function test_first_then_map_inline_pattern_for_category_id(): void {
		// Inline alternative: WP term name -> aggregator ID via |first|map.
		// Used when there are too few categories to bother with term meta.
		$tech = self::factory()->category->create( array( 'name' => 'Tech', 'slug' => 'tech' ) );
		$news = self::factory()->category->create( array( 'name' => 'News', 'slug' => 'news' ) );

		$post_id = self::factory()->post->create( array( 'post_status' => 'publish', 'post_title' => 'Inline Map' ) );
		wp_set_post_terms( $post_id, array( $tech, $news ), 'category', false );

		$el      = '<!-- wp:feedwright/element {"tagName":"category","contentMode":"binding","bindingExpression":"{{post_term.category|first|map:Tech=10,News=20|default:99}}"} /-->';
		$content = '<!-- wp:feedwright/rss --><!-- wp:feedwright/channel -->'
			. '<!-- wp:feedwright/item-query {"postsPerPage":5} --><!-- wp:feedwright/item -->'
			. $el
			. '<!-- /wp:feedwright/item --><!-- /wp:feedwright/item-query -->'
			. '<!-- /wp:feedwright/channel --><!-- /wp:feedwright/rss -->';

		$feed_post = $this->make_feed_post( $content );
		$xml       = ( new Renderer( Plugin::build_resolver() ) )->render( $feed_post )['xml'];

		// Whichever term comes first must map to its own ID; never both joined.
		$this->assertMatchesRegularExpression( '#<category>(10|20)</category>#', $xml );
		$this->assertStringNotContainsString( '<category>99</category>', $xml );
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
