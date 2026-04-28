=== Feedwright ===
Contributors: mt8
Tags: rss, feed, atom, mrss, xml
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 0.1.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Edit custom RSS / Atom / XML feeds visually in the WordPress block editor.

== Description ==

Feedwright lets you compose custom feeds visually inside the WordPress block editor. Define namespaces, elements, and attributes via blocks, query posts with the familiar WP_Query options, and bind values to dynamic expressions such as `{{post.post_title}}` or `{{post.post_date:r}}`.

Each feed lives as a `feedwright_feed` post and is served at a configurable URL such as `/feedwright/{slug}/`.

Feedwright targets feed formats that go beyond the default WordPress RSS — Atom, Media RSS, and arbitrary namespaced XML.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/feedwright` directory, or install through the WordPress plugin screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to Feedwright > Add Feed to compose your first feed using the block editor.

== Frequently Asked Questions ==

= Where is the feed URL? =

The default URL pattern is `/feedwright/{slug}/`. You can change the prefix in Feedwright > Settings.

= Can I add namespaced tags like `media:thumbnail`? =

Yes. Declare the namespace prefix and URI on the `<rss>` block, then use prefixed tag names (e.g. `media:thumbnail`) on element blocks and attributes.

== Changelog ==

= 0.1.3 =
* Feature: new `feedwright/sub-query` and `feedwright/sub-item` blocks expand related posts inside each item template (taxonomy term match or manual ID list). Useful for `<smp:relation>`, `<mdf:relatedLink>`, `<g:additional_image_link>`-style aggregator requirements.
* Feature: new `post_term_meta.{taxonomy}.{meta_key}` binding plus `first` / `default` processors for editorial-managed category-ID mapping (e.g. mediba `<category>` numeric IDs).
* Feature: new `outputMode` attribute on the `<rss>` block (default `strict`). Strict produces minified XML and entity-encodes `'` / `"` in regular text nodes; CDATA-binding elements keep their CDATA wrapper. The new `?pretty=1` query parameter forces formatted XML for admins / `WP_DEBUG` builds.

= 0.1.2 =
* Fix: item-template can now be placed inside each item-query individually instead of being a global singleton. Multiple item-query blocks each get their own item-template.

= 0.1.1 =
* Fix: date bindings (`{{now:r}}`, `{{feed.last_build_date:r}}`, etc.) now always emit RFC 2822 / RFC 3339 with English day and month names regardless of site locale.
* Fix: `feedwright_feed` permalinks now resolve correctly under all permalink structures (including `/%post_id%`); the Gutenberg URL / slug panel is now visible.

= 0.1.0 =
* Initial development release.
