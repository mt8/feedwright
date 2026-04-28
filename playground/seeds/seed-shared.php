<?php
// phpcs:ignoreFile -- demo bootstrap, not part of the plugin runtime.
/**
 * Shared bootstrap for the Feedwright Playground demo.
 *
 *  - blog options
 *  - taxonomy terms (categories with mediba category-ID term meta) loaded
 *    from posts.xml's <terms> block
 *  - admin profile
 *  - sample regular posts loaded from posts.xml's <posts> block (15 posts
 *    across 5 categories; 2 marked status="trash" to demo the
 *    feedwright/when + <mdf:deleted/> + trashWithinDays flow)
 *
 * The feedwright_feed posts themselves are created by the per-format
 * seed-{format}.php files; this script only sets up the surrounding
 * content that those feeds query.
 *
 * @package Feedwright
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

WP_CLI::log( '== Site options ==' );
update_option( 'blogname', 'Feedwright Demo' );
update_option( 'blogdescription', 'A WordPress site demonstrating Feedwright with multiple aggregator feed presets.' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'language', 'ja' );

// Locate posts.xml relative to this script.
$xml_path = __DIR__ . '/posts.xml';
if ( ! file_exists( $xml_path ) ) {
	WP_CLI::error( 'posts.xml not found at ' . $xml_path );
}
$xml = simplexml_load_file( $xml_path );
if ( false === $xml ) {
	WP_CLI::error( 'Failed to parse ' . $xml_path );
}

WP_CLI::log( '== Taxonomy terms (from posts.xml) ==' );
$cat_ids = array();
foreach ( $xml->terms->category as $term ) {
	$slug      = (string) $term['slug'];
	$name      = (string) $term['name'];
	$mediba_id = (string) $term['mediba_id'];

	$existing = get_term_by( 'slug', $slug, 'category' );
	if ( $existing instanceof WP_Term ) {
		$cat_ids[ $slug ] = (int) $existing->term_id;
	} else {
		$res = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
		if ( is_wp_error( $res ) ) {
			continue;
		}
		$cat_ids[ $slug ] = (int) $res['term_id'];
	}
	if ( '' !== $mediba_id ) {
		update_term_meta( $cat_ids[ $slug ], '_mediba_category_id', $mediba_id );
	}
}

WP_CLI::log( '== Author profile ==' );
$admin = get_user_by( 'login', 'admin' );
if ( $admin ) {
	wp_update_user( array(
		'ID'           => $admin->ID,
		'display_name' => 'Feedwright Editorial',
		'first_name'   => 'Feedwright',
		'last_name'    => 'Editorial',
	) );
	wp_set_current_user( $admin->ID );
	// Admin has unfiltered_html on single site; disable kses so block-comment
	// JSON delimiters survive wp_insert_post intact.
	kses_remove_filters();
}

WP_CLI::log( '== Sample posts (replaces previous run, imports from posts.xml) ==' );
// WP_Query's "any" excludes trash + auto-draft, so list explicit statuses
// to also clean up trashed leftovers from prior demo runs.
$old_post_ids = get_posts( array(
	'post_type'   => 'post',
	'numberposts' => -1,
	'post_status' => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
	'fields'      => 'ids',
) );
foreach ( $old_post_ids as $old_id ) {
	wp_delete_post( (int) $old_id, true );
}

// Unsplash image sideload requires media-handling helpers, which aren't
// loaded in WP-CLI by default.
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Image downloads can be slow; raise the HTTP timeout.
add_filter( 'http_request_timeout', static fn () => 30 );

/**
 * Sideload an Unsplash image into the WP media library and return its ID.
 * Returns 0 on failure (network / filesystem / etc.).
 */
$sideload = static function ( string $image_id, string $alt, int $post_id ): int {
	if ( '' === $image_id ) {
		return 0;
	}
	$source_url = "https://images.unsplash.com/{$image_id}?w=1200&q=80&fit=crop";
	$tmp        = download_url( $source_url, 30 );
	if ( is_wp_error( $tmp ) ) {
		WP_CLI::warning( "[{$image_id}] download_url failed: " . $tmp->get_error_message() );
		return 0;
	}
	$file_array = array(
		'name'     => $image_id . '.jpg',
		'tmp_name' => $tmp,
	);
	$attachment_id = media_handle_sideload( $file_array, $post_id, $alt );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp );
		WP_CLI::warning( "[{$image_id}] sideload failed: " . $attachment_id->get_error_message() );
		return 0;
	}
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
	return (int) $attachment_id;
};

$to_trash = array();
$index    = 0;
foreach ( $xml->posts->post as $entry ) {
	$slug      = (string) $entry['category'];
	$status    = (string) $entry['status'];
	$date      = (string) $entry['date'];
	$tags      = array_filter( array_map( 'trim', explode( ',', (string) $entry['tags'] ) ) );
	$image_id  = (string) $entry['image_id'];
	$title     = (string) $entry->title;
	$excerpt   = (string) $entry->excerpt;
	$content   = (string) $entry->content;

	$post_id = wp_insert_post( array(
		'post_type'    => 'post',
		'post_title'   => $title,
		'post_excerpt' => $excerpt,
		'post_content' => $content,
		'post_status'  => 'publish',     // insert as publish; trash after
		'post_date'    => $date,
		'post_author'  => $admin ? $admin->ID : 1,
	), true );
	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( 'insert failed: ' . $post_id->get_error_message() );
		continue;
	}
	if ( isset( $cat_ids[ $slug ] ) ) {
		wp_set_post_terms( $post_id, array( $cat_ids[ $slug ] ), 'category', false );
	}
	if ( ! empty( $tags ) ) {
		wp_set_post_terms( $post_id, $tags, 'post_tag', false );
	}

	// Sideload Unsplash image, set as featured, and embed at top of body.
	$attachment_id = $sideload( $image_id, $title, $post_id );
	if ( $attachment_id > 0 ) {
		set_post_thumbnail( $post_id, $attachment_id );

		$image_url = wp_get_attachment_image_url( $attachment_id, 'large' );
		if ( $image_url ) {
			$alt    = esc_attr( $title );
			$figure = '<figure class="wp-block-image size-large"><img src="' . esc_url( $image_url ) . '" alt="' . $alt . '"/></figure>' . "\n";
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => $figure . $content,
			) );
		}
	}
	WP_CLI::log( sprintf( '  [%2d] %s%s', ++$index, $title, $attachment_id ? ' (image ✓)' : ' (image ✗)' ) );

	if ( 'trash' === $status ) {
		// Trash AFTER attaching terms + image so taxonomy / featured stay
		// intact, which keeps mediba's category-ID resolution working on the
		// deleted item.
		$to_trash[] = $post_id;
	}
}
foreach ( $to_trash as $pid ) {
	wp_trash_post( $pid );
}

// Old single-feed demo wrote a redirector mu-plugin; remove it if present.
$mu = WP_CONTENT_DIR . '/mu-plugins/feedwright-demo-redirect.php';
if ( file_exists( $mu ) ) {
	unlink( $mu );
}

flush_rewrite_rules();
if ( class_exists( '\Feedwright\Cache\RenderCache' ) ) {
	( new \Feedwright\Cache\RenderCache() )->flush_all();
}

WP_CLI::success( sprintf( 'Shared bootstrap complete (%d posts from posts.xml, %d trashed).', $index, count( $to_trash ) ) );
