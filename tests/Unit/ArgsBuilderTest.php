<?php
/**
 * Unit tests for ArgsBuilder.
 *
 * @package Feedwright
 */

namespace Feedwright\Tests\Unit;

use Feedwright\Query\ArgsBuilder;
use PHPUnit\Framework\TestCase;

final class ArgsBuilderTest extends TestCase {

	private ArgsBuilder $builder;

	public function setUp(): void {
		parent::setUp();
		$this->builder = new ArgsBuilder();
	}

	public function test_default_values(): void {
		$args = $this->builder->build( array() );
		$this->assertSame( array( 'post' ), $args['post_type'] );
		$this->assertSame( array( 'publish' ), $args['post_status'] );
		$this->assertSame( 20, $args['posts_per_page'] );
		$this->assertSame( 'date', $args['orderby'] );
		$this->assertSame( 'DESC', $args['order'] );
	}

	public function test_per_page_is_clamped_at_500(): void {
		$args = $this->builder->build( array( 'postsPerPage' => 1000 ) );
		$this->assertSame( ArgsBuilder::MAX_POSTS_PER_PAGE, $args['posts_per_page'] );
	}

	public function test_per_page_floors_at_one(): void {
		$args = $this->builder->build( array( 'postsPerPage' => -5 ) );
		$this->assertSame( 1, $args['posts_per_page'] );
	}

	public function test_orderby_falls_back_for_unknown_value(): void {
		$args = $this->builder->build( array( 'orderBy' => 'gibberish' ) );
		$this->assertSame( 'date', $args['orderby'] );
	}

	public function test_orderby_accepts_modified_and_comment_count(): void {
		$this->assertSame( 'modified', $this->builder->build( array( 'orderBy' => 'modified' ) )['orderby'] );
		$this->assertSame( 'comment_count', $this->builder->build( array( 'orderBy' => 'comment_count' ) )['orderby'] );
	}

	public function test_order_normalizes_case_and_falls_back(): void {
		$this->assertSame( 'ASC', $this->builder->build( array( 'order' => 'asc' ) )['order'] );
		$this->assertSame( 'DESC', $this->builder->build( array( 'order' => 'descend' ) )['order'] );
	}

	public function test_post_status_filters_disallowed(): void {
		// 'draft' is not allowed; 'trash' was added as part of the deleted-feed
		// pattern (e.g. mediba <mdf:deleted/>).
		$args = $this->builder->build( array( 'postStatus' => array( 'publish', 'draft', 'trash', 'private' ) ) );
		$this->assertSame( array( 'publish', 'trash', 'private' ), $args['post_status'] );
	}

	public function test_post_status_allows_trash(): void {
		$args = $this->builder->build( array( 'postStatus' => array( 'publish', 'trash' ) ) );
		$this->assertSame( array( 'publish', 'trash' ), $args['post_status'] );
	}

	public function test_post_status_falls_back_to_publish(): void {
		$args = $this->builder->build( array( 'postStatus' => array( 'draft', 'pending' ) ) );
		$this->assertSame( array( 'publish' ), $args['post_status'] );
	}

	public function test_exclude_ids_normalizes_to_int_post__not_in(): void {
		$args = $this->builder->build( array( 'excludeIds' => array( '5', 7, 'abc', 0, -1 ) ) );
		$this->assertSame( array( 5, 7 ), $args['post__not_in'] );
	}

	public function test_include_sticky_posts_default_excludes(): void {
		$args = $this->builder->build( array() );
		$this->assertTrue( $args['ignore_sticky_posts'] );
	}

	public function test_include_sticky_posts_when_enabled(): void {
		$args = $this->builder->build( array( 'includeStickyPosts' => true ) );
		$this->assertFalse( $args['ignore_sticky_posts'] );
	}

	public function test_taxonomy_entry_with_empty_terms_is_dropped(): void {
		$args = $this->builder->build(
			array(
				'taxQuery' => array(
					array( 'taxonomy' => 'category', 'field' => 'slug', 'terms' => array() ),
				),
			)
		);
		$this->assertArrayNotHasKey( 'tax_query', $args, 'taxQuery entry with no terms must not become a tax_query argument' );
	}

	public function test_taxonomy_entry_without_taxonomy_is_dropped(): void {
		$args = $this->builder->build(
			array(
				'taxQuery' => array(
					array( 'field' => 'slug', 'terms' => array( 'foo' ) ),
				),
			)
		);
		$this->assertArrayNotHasKey( 'tax_query', $args );
	}

	public function test_tax_meta_date_query_pass_through_when_provided(): void {
		$tax  = array( array( 'taxonomy' => 'category', 'terms' => array( 1 ) ) );
		$meta = array( array( 'key' => 'foo', 'value' => 'bar' ) );
		$date = array( 'after' => '2026-01-01' );
		$args = $this->builder->build(
			array(
				'taxQuery'  => $tax,
				'metaQuery' => $meta,
				'dateQuery' => $date,
			)
		);
		$this->assertSame( $tax, $args['tax_query'] );
		$this->assertSame( $meta, $args['meta_query'] );
		$this->assertSame( $date, $args['date_query'] );
	}
}
