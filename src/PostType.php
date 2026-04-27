<?php
/**
 * Custom post type registration for feed definitions.
 *
 * @package Feedwright
 */

declare(strict_types=1);

namespace Feedwright;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the `feedwright_feed` custom post type.
 */
final class PostType {

	public const SLUG = 'feedwright_feed';

	/**
	 * Hook into WordPress to register the post type.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the post type. Called on `init`.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::SLUG,
			array(
				'labels'              => $this->labels(),
				'public'              => false,
				'show_ui'             => true,
				'show_in_rest'        => true,
				'show_in_menu'        => true,
				'menu_icon'           => 'dashicons-rss',
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'exclude_from_search' => true,
				'capability_type'     => 'post',
				'capabilities'        => $this->capabilities(),
				'map_meta_cap'        => true,
				'template'            => $this->default_template(),
				'template_lock'       => false,
			)
		);
	}

	/**
	 * Translatable labels for the post type.
	 *
	 * @return array<string,string>
	 */
	private function labels(): array {
		return array(
			'name'                  => _x( 'Feeds', 'post type general name', 'feedwright' ),
			'singular_name'         => _x( 'Feed', 'post type singular name', 'feedwright' ),
			'menu_name'             => _x( 'Feedwright', 'admin menu', 'feedwright' ),
			'name_admin_bar'        => _x( 'Feed', 'add new on admin bar', 'feedwright' ),
			'add_new'               => _x( 'Add Feed', 'feed', 'feedwright' ),
			'add_new_item'          => __( 'Add New Feed', 'feedwright' ),
			'new_item'              => __( 'New Feed', 'feedwright' ),
			'edit_item'             => __( 'Edit Feed', 'feedwright' ),
			'view_item'             => __( 'View Feed', 'feedwright' ),
			'all_items'             => __( 'All Feeds', 'feedwright' ),
			'search_items'          => __( 'Search Feeds', 'feedwright' ),
			'not_found'             => __( 'No feeds found.', 'feedwright' ),
			'not_found_in_trash'    => __( 'No feeds found in Trash.', 'feedwright' ),
			'featured_image'        => __( 'Feed Image', 'feedwright' ),
			'set_featured_image'    => __( 'Set feed image', 'feedwright' ),
			'remove_featured_image' => __( 'Remove feed image', 'feedwright' ),
			'use_featured_image'    => __( 'Use as feed image', 'feedwright' ),
			'archives'              => __( 'Feed archives', 'feedwright' ),
			'insert_into_item'      => __( 'Insert into feed', 'feedwright' ),
			'uploaded_to_this_item' => __( 'Uploaded to this feed', 'feedwright' ),
			'filter_items_list'     => __( 'Filter feeds list', 'feedwright' ),
			'items_list_navigation' => __( 'Feeds list navigation', 'feedwright' ),
			'items_list'            => __( 'Feeds list', 'feedwright' ),
		);
	}

	/**
	 * Capability mapping. All primitive caps map to `manage_options`.
	 *
	 * Meta caps (`edit_post` / `read_post` / `delete_post`) are intentionally
	 * omitted. With `map_meta_cap = true`, WordPress resolves them from primitive
	 * caps automatically. Mapping meta caps explicitly here causes WP to register
	 * a reverse mapping in the global meta-cap map via
	 * `_post_type_meta_capabilities()` (e.g. `'manage_options' => 'edit_post'`),
	 * so subsequent `current_user_can( 'manage_options' )` calls would expect a
	 * post ID and trigger a `_doing_it_wrong` notice.
	 *
	 * @return array<string,string>
	 */
	private function capabilities(): array {
		return array(
			'edit_posts'             => 'manage_options',
			'edit_others_posts'      => 'manage_options',
			'publish_posts'          => 'manage_options',
			'read_private_posts'     => 'manage_options',
			'create_posts'           => 'manage_options',
			'delete_posts'           => 'manage_options',
			'delete_others_posts'    => 'manage_options',
			'delete_published_posts' => 'manage_options',
			'delete_private_posts'   => 'manage_options',
			'edit_published_posts'   => 'manage_options',
			'edit_private_posts'     => 'manage_options',
		);
	}

	/**
	 * Minimal block template loaded into new feed posts (spec §18.1).
	 *
	 * Element blocks are intentionally omitted — they come from block patterns.
	 * Block names referenced here will be registered in Phase 2.
	 *
	 * @return array<int,array<int,mixed>>
	 */
	public function default_template(): array {
		return array(
			array(
				'feedwright/rss',
				array(
					'namespaces' => array(),
				),
				array(
					array(
						'feedwright/channel',
						array(),
						array(
							array(
								'feedwright/item-query',
								array(
									'postType'     => array( 'post' ),
									'postsPerPage' => 20,
									'orderBy'      => 'date',
									'order'        => 'DESC',
								),
								array(
									array( 'feedwright/item', array(), array() ),
								),
							),
						),
					),
				),
			),
		);
	}
}
