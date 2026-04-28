=== Feedwright ===
Contributors: mt8
Tags: rss, feed, atom, mrss, xml
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.3
Stable tag: 0.2.1
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

= 0.2.1 =
* Improvement: the editor's List View now labels each `feedwright/element` row with its configured `tagName` (e.g. `title`, `media:thumbnail`) instead of a uniform "Element", making it easy to navigate large feeds at a glance. Empty tag names still fall back to the default block title.

= 0.2.0 =
* Playground demo: per-aggregator seed scripts (`seed-goonews.php`, `seed-mediba.php`, `seed-smartnews.php`) plus a 30-post fixture from `posts.xml` and sideloaded Unsplash featured images. Seeds also publish Feedwright wordmark logos for the SmartNews `snf:logo` / `snf:darkModeLogo` channel elements.
* Playground blueprint: flatten seed paths to `/wordpress/` root so `writeFile` no longer fails on a missing parent directory in the virtual filesystem.

= 0.1.4 =
* Feature: new `feedwright/when` block wraps inner blocks with a binding-driven gate. Compose with the `eq` / `in` / `map` / `default` / `first` processors to render elements only under specific conditions (e.g. emit `<mdf:deleted/>` only when the post is in the trash).
* Feature: `item-query` now accepts the `trash` post status, plus a `trashWithinDays` cap that keeps trashed posts in the feed only for a configurable window. Live (non-trashed) posts are unaffected.
* Feature: new `eq` / `in` binding processors for direct equality and list-membership checks (`{{post_raw.post_status|eq:trash}}`, `{{post_raw.post_status|in:trash,draft}}`). Both compose naturally with `feedwright/when`.
* Feature: `first` and `default` processors are now surfaced in the editor's binding autocomplete.
* Feature: in admin contexts (editor, post list, admin bar) the `feedwright_feed` permalink auto-appends `?pretty=1` so the "View" link opens formatted XML by default. Front-end and REST contexts keep the canonical clean URL.
* Feature: the element block's editor preview now wraps `cdata-binding` expressions with `<![CDATA[ ... ]]>` markers so the CDATA intent is visible at a glance.
* Improvement: `feedwright/when` truthiness check is whitespace-tolerant — a stray trailing space in the expression no longer flips the gate to always-true.
* Cleanup: removed the dedicated "Feedwright XML Preview" sidebar panel and its REST endpoint; the standard Gutenberg preview / publish flow handles XML preview natively.

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
