# Feedwright Implementation Specification

> Feedwright — RSS Feed Editor for WordPress

Implementation spec for the WordPress plugin **Feedwright** (`feed` + `-wright`: the same craftsman suffix used in playwright / shipwright). This document is written assuming Claude Code will be doing the coding, and exhaustively defines implementation units, data structures, and acceptance criteria.

---

## 0. How to use this spec

- Each section is split into a granularity that can be implemented independently. When working through it with Claude Code, follow the Phase order in §22 "Implementation Phases" and refer to the corresponding sections.
- "**MUST**" is required, "**SHOULD**" is recommended, "**MAY**" is optional.
- Code samples are minimum skeletons. Real implementations must include error handling, type annotations, and i18n.
- Block JS uses Block API v3 (WordPress 6.5+). Use `block.json`-driven auto-registration via the equivalent of `register_block_type_from_metadata`.
- If anything is unclear, append it to §24 "Open Questions" rather than deciding on your own.

---

## 1. Project overview

A plugin that repurposes the block editor as an XML element tree editor, allowing users to edit and serve custom-format RSS feeds (such as those required by various aggregators and namespaced XML formats) through a GUI.

Without writing PHP templates, users compose blocks to generate any RSS 2.0-compatible feed.

---

## 2. Goals / Non-goals

### Goals

- Manage multiple feed definitions via the `feedwright_feed` custom post type
- Visually compose feed XML structure in the block editor
- Define arbitrary namespaced tags and attributes from blocks
- Configure WP_Query conditions through a GUI and expand the results into `<item>` elements
- Inject post fields, custom fields, and terms dynamically via bindings
- Serve public RSS XML at `/{base}/{slug}/`
- Operate independently per site under multisite

### Non-goals (not implemented in MVP)

- JSON export / import of feed definitions
- Format preset libraries bundled in the core plugin
- Custom block pattern management UI
- Network admin features
- Fine-grained permission delegation (admin-only editing)
- Overriding the standard WordPress `/feed/`
- Revision diff UI for feed definitions (only the standard revision feature)

---

## 3. Glossary

| Term | Meaning |
|---|---|
| **Feed post** | An individual post of the `feedwright_feed` post type. One post = one feed definition |
| **Feed slug** | The `post_name` of the feed post. The trailing URL segment |
| **URL base** | Fixed prefix of the feed URL. Default `feedwright` |
| **Element block** | `feedwright/element`. A general-purpose block representing any XML element |
| **Binding** | A dynamic value placeholder like `{{post.post_title}}` |
| **Context** | The `{ post, feed, site, ... }` dictionary referenced when resolving bindings |
| **Item template** | The contents of the `feedwright/item` block. Expanded for each query result |

---

## 4. Tech stack

| Item | Specification |
|---|---|
| PHP | 8.3 or later |
| WordPress | 6.5 or later |
| Block API | v3 (block.json + apiVersion: 3) |
| Build | `@wordpress/scripts` (standard) |
| Package manager | npm |
| Autoload | Composer + PSR-4 (`Feedwright\` → `src/`) |
| Text domain | `feedwright` |
| License | GPLv2 or later |
| Coding standards | WordPress Coding Standards (PHPCS), WordPress ESLint config |

---

## 5. Directory layout

```
feedwright/
├── feedwright.php                    Plugin header + bootstrap
├── readme.txt                      For WordPress.org
├── README.md                       For developers
├── composer.json
├── package.json
├── webpack.config.js               Inherits @wordpress/scripts defaults
├── phpcs.xml.dist
├── .eslintrc.js
├── src/                            PHP (PSR-4: Feedwright\)
│   ├── Plugin.php
│   ├── PostType.php
│   ├── Settings.php
│   ├── BlockRegistry.php
│   ├── BlockRestriction.php
│   ├── Routing/
│   │   └── FeedEndpoint.php
│   ├── Renderer/
│   │   ├── Renderer.php
│   │   ├── DomBuilder.php
│   │   ├── ElementRenderer.php
│   │   └── ItemQueryRenderer.php
│   ├── Bindings/
│   │   ├── Resolver.php
│   │   ├── ProviderInterface.php
│   │   └── Providers/
│   │       ├── OptionProvider.php
│   │       ├── FeedProvider.php
│   │       ├── NowProvider.php
│   │       ├── PostProvider.php
│   │       ├── PostRawProvider.php
│   │       ├── PostMetaProvider.php
│   │       ├── PostTermProvider.php
│   │       ├── PostTermMetaProvider.php
│   │       └── AuthorProvider.php
│   ├── Query/
│   │   └── ArgsBuilder.php
│   ├── Cache/
│   │   └── RenderCache.php
│   └── REST/
│       └── BindingIntrospectionController.php
├── blocks/                          Block JS / block.json
│   ├── rss/
│   │   ├── block.json
│   │   ├── index.js
│   │   ├── edit.js
│   │   └── style.scss
│   ├── channel/
│   ├── element/
│   ├── item-query/
│   ├── item/
│   ├── raw/
│   └── comment/
├── editor/                          Editor extensions (shared UI)
│   ├── src/
│   │   ├── index.js
│   │   ├── components/
│   │   │   ├── BindingInput.jsx
│   │   │   └── AttributeListEditor.jsx
│   │   ├── hooks/
│   │   │   └── useBindingSuggestions.js
│   │   └── store/
│   │       └── feedwright-bindings.js
│   └── style.scss
├── languages/
│   ├── feedwright.pot
│   ├── feedwright-ja.po
│   ├── feedwright-ja.mo
│   └── feedwright-ja-{md5}.json   per-source JED from wp i18n make-json
└── tests/
    ├── bootstrap.php
    ├── Unit/
    │   ├── BindingResolverTest.php
    │   └── QueryArgsBuilderTest.php
    └── Integration/
        ├── RenderTest.php
        └── FeedEndpointTest.php
```

The build artifact `build/` is in `.gitignore`. The distribution ZIP includes `build/`.

---

## 6. Coding conventions

- PHP: WordPress Coding Standards. Everything lives under the `Feedwright\` namespace. Use type annotations aggressively.
- JS: WordPress ESLint config. React function components + hooks.
- All user-facing strings are made translatable with `__( 'foo', 'feedwright' )`.
- Translation files ship in `languages/`. Generation steps (pure wp-cli):
  - `wp i18n make-pot . languages/feedwright.pot --domain=feedwright --exclude=vendor,node_modules,build,tests,tmp`
  - After translating `.po`: `wp i18n make-mo languages/`
  - `wp i18n make-json languages/ --no-purge` to generate per-source JED files
  - Hash mismatches between per-source JED files and build script handles are absorbed by the `BlockRegistry::serve_jed_for_editor_scripts` filter (it merges all JED files and serves them via `pre_load_script_translations`).
- All URL inputs and attribute values are escaped on output (`esc_attr` / `esc_url` etc.). XML values are auto-escaped through DOMDocument, but URLs use `esc_url_raw` at the parameter stage.
- Direct access prevention: every PHP file starts with `defined( 'ABSPATH' ) || exit;`
- Hooks follow the naming convention `feedwright/` prefix (slash-separated). Example: `feedwright/binding_providers`
- Logging never calls `error_log` directly; use the debug helper `Feedwright\Plugin::log()` (which only emits when `WP_DEBUG_LOG` is on).

---

## 7. Plugin bootstrap

### 7.1 `feedwright.php`

```php
<?php
/**
 * Plugin Name: Feedwright
 * Description: Build custom RSS / XML feeds (Atom, MRSS, namespaced formats, etc.) with the WordPress block editor.
 * Version: 0.1.0
 * Author: ...
 * License: GPL-2.0-or-later
 * Text Domain: feedwright
 * Requires PHP: 8.3
 * Requires at least: 6.5
 */

defined( 'ABSPATH' ) || exit;

define( 'FEEDWRIGHT_VERSION', '0.1.0' );
define( 'FEEDWRIGHT_PLUGIN_FILE', __FILE__ );
define( 'FEEDWRIGHT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEEDWRIGHT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/vendor/autoload.php';

add_action( 'plugins_loaded', [ \Feedwright\Plugin::class, 'instance' ] );

register_activation_hook( __FILE__, [ \Feedwright\Plugin::class, 'on_activation' ] );
register_deactivation_hook( __FILE__, [ \Feedwright\Plugin::class, 'on_deactivation' ] );
```

### 7.2 `Feedwright\Plugin`

Singleton. Wires up each component.

```php
namespace Feedwright;

final class Plugin {
    private static ?self $instance = null;
    public static function instance(): self { ... }

    private function __construct() {
        ( new PostType() )->register();
        ( new Settings() )->register();
        ( new BlockRegistry() )->register();
        ( new BlockRestriction() )->register();
        ( new Routing\FeedEndpoint() )->register();
        ( new REST\BindingIntrospectionController() )->register();
    }

    public static function on_activation(): void {
        // register CPT → flush rewrite rules
        ( new PostType() )->register();
        ( new Routing\FeedEndpoint() )->register();
        flush_rewrite_rules();
    }

    public static function on_deactivation(): void {
        flush_rewrite_rules();
    }
}
```

---

## 8. Post type

### 8.1 Specification

| Item | Value |
|---|---|
| post type slug | `feedwright_feed` |
| public | `false` |
| show_ui | `true` |
| show_in_rest | `true` (required) |
| show_in_menu | `true` (top-level menu) |
| menu_icon | `dashicons-rss` |
| supports | `[ 'title', 'editor', 'revisions' ]` |
| has_archive | `false` |
| rewrite | `false` (custom routing handles this) |
| exclude_from_search | `true` |
| capability_type | `post` |
| capabilities | All mapped to `manage_options` (§8.2) |
| map_meta_cap | `true` |
| labels | i18n-ready (§8.3) |
| template | Minimum skeleton (§18). Includes only structural blocks, no element blocks |
| template_lock | `false` (controlled per-block via the `lock` attribute) |

### 8.2 capabilities

Map **only the primitive caps** to `manage_options`. Do not include meta caps (`edit_post` / `read_post` / `delete_post`) here. With `map_meta_cap = true`, WordPress automatically resolves them from the primitive caps, so explicit declaration is unnecessary.

> **Note**: If you map `edit_post` / `read_post` / `delete_post` directly, WordPress's internal `_post_type_meta_capabilities()` reverse-registers `'manage_options'` in the global meta-cap map. From that point on, any `current_user_can( 'manage_options' )` call will fire a `_doing_it_wrong` notice saying "you should pass a post ID."

```php
'capabilities' => [
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
],
'map_meta_cap' => true,
```

### 8.3 labels

All translatable. Example translations in the Japanese .po file (for reference):

| key | en | ja |
|---|---|---|
| name | Feeds | フィード |
| singular_name | Feed | フィード |
| menu_name | Feedwright | Feedwright |
| add_new | Add Feed | 新規フィード |
| edit_item | Edit Feed | フィードを編集 |
| view_item | View Feed | フィードを表示（→ 公開 URL を開く） |

The `view_item` link points to `/{base}/{slug}/` (§10).

### 8.4 Acceptance criteria

- [ ] The "Feedwright" menu appears in the admin
- [ ] Non-administrators (editors, authors) do not see the menu
- [ ] "Add Feed" opens the block editor
- [ ] The "View" link points to the correct feed URL
- [ ] On the front end, both the archive and individual pages of this post type return 404

---

## 9. Settings / options

### 9.1 Option keys

| key | type | default | description |
|---|---|---|---|
| `feedwright_url_base` | string | `feedwright` | Prefix for feed URLs |
| `feedwright_cache_ttl` | int | `300` | Render result cache duration in seconds |
| `feedwright_db_version` | string | `0.1.0` | Schema version |

All exposed via REST through `register_setting` (admin only).

### 9.2 Settings screen

A "Settings" submenu under the `Feedwright` menu.

#### URL base field

- Input: text field
- Validation: `^[a-z0-9][a-z0-9/_-]*[a-z0-9]$` or single character `^[a-z0-9]$`
- Reserved-word block (error on save): `wp-admin`, `wp-content`, `wp-includes`, `feed`, `comments`, `xmlrpc.php`, `wp-json`
- Collision with existing slugs: check pages/posts via `get_page_by_path`; if a name collides, **show a warning** (does not block saving)
- Warn if the permalink setting is "Plain" (because pretty rewrites won't work)
- Run `flush_rewrite_rules()` whenever the value changes

#### Cache TTL field

- Integer. 0 = cache disabled. Maximum 86400 (1 day)

#### Manual cache clear button

- Purges the cache for all `feedwright_feed` posts

### 9.3 Acceptance criteria

- [ ] Changing the URL base to `news/feeds` serves feeds at `/news/feeds/{slug}/`
- [ ] Entering a reserved word displays an error message on save
- [ ] A warning appears when permalinks are set to "Plain"
- [ ] Clearing the cache empties the object cache

---

## 10. URL routing

### 10.1 Rewrite rules

```php
add_action( 'init', function () {
    $base = get_option( 'feedwright_url_base', 'feedwright' );
    $base = trim( $base, '/' );
    $base = preg_quote( $base, '#' );

    add_rewrite_rule(
        '^' . $base . '/([^/]+)/?$',
        'index.php?feedwright_feed_slug=$matches[1]',
        'top'
    );
    add_rewrite_tag( '%feedwright_feed_slug%', '([^&]+)' );
}, 10 );
```

### 10.2 Request handling

```php
add_action( 'template_redirect', function () {
    $slug = get_query_var( 'feedwright_feed_slug' );
    if ( ! $slug ) {
        return;
    }

    $post = get_page_by_path( $slug, OBJECT, 'feedwright_feed' );

    if ( ! $post || $post->post_status !== 'publish' ) {
        status_header( 404 );
        nocache_headers();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<error>Feed not found</error>';
        exit;
    }

    \Feedwright\Renderer\Renderer::render_to_output( $post );
    exit;
}, 5 );
```

### 10.3 Response headers

```
HTTP/1.1 200 OK
Content-Type: application/rss+xml; charset=UTF-8
Last-Modified: {post_modified_gmt as RFC1123}
ETag: "{md5(post_id + post_modified_gmt + url_base)}"
Cache-Control: public, max-age={feedwright_cache_ttl}
X-Robots-Tag: noindex
```

Honors `If-Modified-Since` / `If-None-Match` and returns 304 when appropriate.

`X-Robots-Tag: noindex` does not affect feed-consuming aggregators, but prevents Google and others from indexing the feed URL.

### 10.4 Acceptance criteria

- [ ] `/feedwright/{slug}/` returns 200 + RSS XML
- [ ] A nonexistent slug returns 404
- [ ] A draft feed returns 404 from its public URL
- [ ] `If-None-Match` returns 304
- [ ] After changing the URL base, rewrites follow (`flush_rewrite_rules` runs)

---

## 11. Block restrictions

### 11.1 Inserter filter

```php
add_filter( 'allowed_block_types_all', function ( $allowed, $context ) {
    if ( ! $context instanceof \WP_Block_Editor_Context ) {
        return $allowed;
    }
    if ( ! $context->post || $context->post->post_type !== 'feedwright_feed' ) {
        return $allowed;
    }
    return [
        'feedwright/rss',
        'feedwright/channel',
        'feedwright/element',
        'feedwright/item-query',
        'feedwright/item',
        'feedwright/raw',
        'feedwright/comment',
    ];
}, 10, 2 );
```

### 11.2 Save-time validation

In `save_post_feedwright_feed`, run `parse_blocks` and warn if disallowed blocks are present. **Do not auto-remove them** (risk of data loss). Instead, show an admin notice "Unsupported blocks are present" and have the renderer simply ignore unsupported blocks.

### 11.3 Editor UI assistance

- Promote `feedwright/element` to the top of the Inserter block list
- Add a `feedwright` category via the `block_categories_all` filter and put all Feedwright blocks in it

```php
add_filter( 'block_categories_all', function ( $categories, $context ) {
    if ( ! $context->post || $context->post->post_type !== 'feedwright_feed' ) {
        return $categories;
    }
    array_unshift( $categories, [
        'slug'  => 'feedwright',
        'title' => __( 'Feedwright', 'feedwright' ),
        'icon'  => 'rss',
    ] );
    return $categories;
}, 10, 2 );
```

### 11.4 Acceptance criteria

- [ ] When editing `feedwright_feed`, no core blocks appear in the Inserter
- [ ] Editing other post types (post / page) is unaffected
- [ ] Parent/child violations (e.g. channel outside rss) never appear as Inserter candidates

---

## 12. Block specifications

### 12.1 Common

- All use `apiVersion: 3`
- All are **dynamic blocks** (`save: () => null`). Only `attrs` is serialized into the block comment
- `category: "feedwright"`
- `textdomain: "feedwright"`
- Each `block.json` declares at least 3 `keywords`

### 12.2 `feedwright/rss`

#### block.json

```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "feedwright/rss",
  "version": "0.1.0",
  "title": "RSS",
  "category": "feedwright",
  "icon": "rss",
  "description": "RSS feed root element.",
  "keywords": [ "rss", "feed", "root" ],
  "textdomain": "feedwright",
  "attributes": {
    "version": {
      "type": "string",
      "default": "2.0"
    },
    "namespaces": {
      "type": "array",
      "default": [
        { "prefix": "content", "uri": "http://purl.org/rss/1.0/modules/content/" }
      ]
    },
    "outputMode": {
      "type": "string",
      "default": "strict",
      "enum": [ "strict", "compat" ]
    }
  },
  "supports": {
    "html": false,
    "reusable": false,
    "lock": false,
    "multiple": false,
    "inserter": false
  },
  "parent": [],
  "editorScript": "file:./index.js",
  "editorStyle":  "file:./style.css"
}
```

- `multiple: false`: only one per document
- `inserter: false`: cannot be inserted manually (placed automatically by the template)
- The only allowed child is `feedwright/channel`. `allowedBlocks` for InnerBlocks is specified in edit.js

#### Editor UI

- Inspector contains a "Namespaces" list editor (add/remove prefix / uri pairs)
- Version is read-only (room to support RSS 1.0 / Atom in the future)
- "Output mode" radio (default `strict`):
  - **strict** — minified XML; in regular text nodes all five XML entities (`& < > " '`) are encoded. The `cdata-binding` element mode keeps its CDATA wrapper — most aggregator specs explicitly permit CDATA for HTML-bearing body fields (e.g. mediba: "CDATAで囲えばHTMLを使用可能"), and entity-encoding the markup would balloon the payload for no semantic gain.
  - **compat** — original behavior: `formatOutput=true` (pretty-formatted), CDATA preserved, only `& < >` escaped (DOMDocument default; `'` and `"` are passed through literally).

#### Default save lock

When inserted by the template, `lock: { remove: true, move: true }` is applied unconditionally.

### 12.3 `feedwright/channel`

#### block.json

```json
{
  "apiVersion": 3,
  "name": "feedwright/channel",
  "title": "Channel",
  "category": "feedwright",
  "icon": "megaphone",
  "parent": [ "feedwright/rss" ],
  "supports": {
    "html": false,
    "reusable": false,
    "multiple": false,
    "inserter": false
  },
  "attributes": {}
}
```

- Children: `feedwright/element`, `feedwright/item-query`, `feedwright/comment` (specified via `allowedBlocks` in edit.js)
- `multiple: false`: one per rss

### 12.4 `feedwright/element`

**The heart of the plugin.**

#### attributes

```json
{
  "tagName":     { "type": "string", "default": "" },
  "namespace":   { "type": "string", "default": "" },
  "attributes":  {
    "type": "array",
    "default": []
  },
  "contentMode": {
    "type": "string",
    "enum": [ "children", "static", "binding", "cdata-binding", "empty" ],
    "default": "static"
  },
  "staticValue":     { "type": "string", "default": "" },
  "bindingExpression": { "type": "string", "default": "" }
}
```

Each entry in the `attributes` array has the shape:

```ts
{
  name: string;            // Required. XML attribute name (may include prefix: "ext:type")
  valueMode: 'static' | 'binding';
  value: string;
}
```

#### block.json

```json
{
  "apiVersion": 3,
  "name": "feedwright/element",
  "title": "Element",
  "category": "feedwright",
  "icon": "editor-code",
  "ancestor": [ "feedwright/rss" ],
  "supports": {
    "html": false,
    "reusable": false
  },
  "attributes": { /* see above */ },
  "providesContext": {
    "feedwright/inItemContext": "feedwright/inItemContext"
  },
  "usesContext": [ "feedwright/inItemContext" ]
}
```

#### Block Variations

Common tags are registered as variations (implementation lives in editor/src/variations/):

- `title`, `link`, `description`, `language`, `pubDate`, `lastBuildDate`, `guid`,
  `category`, `author`, `copyright`, `ttl`, `image`, `enclosure`,
  `content:encoded`, `dc:creator`,
  `media:thumbnail`, `media:content`,
  `ext:logo`, `ext:analytics`

Each variation has default values (preset tagName, contentMode, bindingExpression). Example:

```js
{
  name: 'feedwright-title',
  title: 'Title',
  description: '<title> element',
  icon: 'editor-textcolor',
  attributes: {
    tagName: 'title',
    contentMode: 'binding',
    bindingExpression: 'site.title'  // suggests post.title in item context
  },
  scope: [ 'inserter', 'transform' ]
}
```

The parent provides `feedwright/inItemContext` as block context (`true` under `feedwright/item`), and variations use it to switch their default binding between `option.*` and `post.*`.

#### Editor UI

- Block body: tag name (inline-editable) and a pseudo-rendering of the current value. Example:
  ```
  <title>{{post.post_title}}</title>
  ```
  Bindings are color-coded.
- Inspector panels:
  - **Element** section: tag name input, namespace selection (suggestions sourced from the rss block definition)
  - **Content** section: contentMode radio + an input matching the chosen mode
    - `children`: shows "Composed from child blocks"
    - `static`: textarea
    - `binding`: BindingInput component (with autocomplete)
    - `cdata-binding`: BindingInput (multiple bindings allowed, output as CDATA)
    - `empty`: no input
  - **Attributes** section: AttributeListEditor (rows of name / valueMode / value)

### 12.5 `feedwright/item-query`

**Multiple instances can be placed directly under channel.** Each block runs its own independent `WP_Query` and emits the results as `<item>` elements in block order.

Use cases:
- "Latest news 10" + "Editors' picks 5" + "Popular articles (by comment count) 3"
- "Latest 20 from category A" + "Latest 20 from category B" laid out in sequence
- "Updated in the last 24 hours" + "Added in the past week"

#### attributes

```json
{
  "label":        { "type": "string", "default": "" },
  "itemTagName":  { "type": "string", "default": "item" },
  "postType":     { "type": "array",  "default": [ "post" ] },
  "postsPerPage": { "type": "number", "default": 20 },
  "orderBy":      { "type": "string", "default": "date" },
  "order":        { "type": "string", "default": "DESC" },
  "postStatus":   { "type": "array",  "default": [ "publish" ] },
  "taxQuery":     { "type": "array",  "default": [] },
  "metaQuery":    { "type": "array",  "default": [] },
  "dateQuery":    { "type": "object", "default": {} },
  "excludeIds":   { "type": "array",  "default": [] },
  "includeStickyPosts": { "type": "boolean", "default": false }
}
```

`label`: identifying label shown in the editor (e.g. "Latest news", "Editors' picks"). Has no effect on XML output. Used to distinguish multiple instances in the UI.

`itemTagName`: tag name wrapping each query result. Default `item` (RSS 2.0). For Atom feeds use `entry`; for custom formats any valid XML Name is accepted. **Namespaced prefixes are also allowed** (e.g. `atom:entry`). Prefixes must already be declared on the rss block.

#### Sorting (orderBy / order)

`orderBy` supports the following values:

| Value | Meaning | Behavior in WP_Query |
|---|---|---|
| `date` | **Publish date** (`post_date`) | Standard. Default for fresh-content feeds |
| `modified` | **Modified date** (`post_modified`) | Order by edit recency. Useful when re-distributing edits |
| `title` | Title | Alphabetical / Japanese kana order |
| `menu_order` | Menu order | For static pages or manual sorting |
| `rand` | Random | Plays badly with caching. Use carefully |
| `comment_count` | Comment count | "Popular articles" use case |
| `meta_value` | Custom field value (string) | Combine with `metaQuery` |
| `meta_value_num` | Custom field value (numeric) | Same |
| `none` | No sorting | DB insertion order |

`order` is `ASC` or `DESC`. Default `DESC`.

The "publish-date / modified-date ascending or descending" requirement maps to:

| Use case | orderBy | order |
|---|---|---|
| Newest by publish date (standard feed) | `date` | `DESC` |
| Oldest by publish date | `date` | `ASC` |
| Newest by modified date | `modified` | `DESC` |
| Oldest by modified date | `modified` | `ASC` |

#### Validation

- `postsPerPage`: 1–500 (clamped to 500 on save when exceeded)
- `orderBy`: must be one of the values in the table above
- `order`: `ASC` / `DESC` (case-insensitive, normalized internally)
- `postStatus`: only `publish` / `private` / `future` accepted (`draft` / `trash` rejected)
- `label`: at most 100 characters; XML control characters stripped
- `itemTagName`: must satisfy XML Name rules (`is_valid_xml_name`). Empty or invalid values fall back to default `item` with a warning

#### block.json

```json
{
  "apiVersion": 3,
  "name": "feedwright/item-query",
  "title": "Item Query",
  "category": "feedwright",
  "icon": "search",
  "parent": [ "feedwright/channel" ],
  "supports": {
    "html": false,
    "reusable": false,
    "multiple": true
  },
  "providesContext": {
    "feedwright/inItemContext": "feedwright/inItemContext"
  }
}
```

`multiple: true` is set explicitly (it is the default, but we declare it for clarity).

`providesContext` propagates the "item context" to descendant elements. The value is the constant `true` (it can also be modeled as `inItemContext: { default: true }` in attributes).

Children: only one `feedwright/item`.

#### Editor UI

- **Block body (preview)**:
  - If `label` is set, display it prominently
  - Natural-language summary: "Latest **`posts`**, 20 items (publish date, newest first) → expanded into each item below"
  - Approximate match count fetched via REST and shown alongside (e.g. `→ Posts matching current conditions: 142`)
- **Inspector**:
  - **Basic**: label, post type (multi-select), count
  - **Item element name**: `itemTagName` input field. Default `item`. Help text: "RSS 2.0 uses `item`, Atom uses `entry`. Custom namespaced formats may keep `item` or use a vendor-prefixed name." Namespaced prefixes (`atom:entry` etc.) are picked from a dropdown of namespaces already declared on the rss block
  - **Sorting**: orderBy select (labeled options from the table above), order radio (ASC/DESC)
  - **Filtering**: post status, taxonomy conditions (taxonomy selector → term picker), meta conditions (key / value / compare), date range (after / before), excluded post IDs, include sticky posts

#### Behavior with multiple instances

- Multiple item-query blocks under channel run independent queries
- The order of `<item>` elements in the output XML follows **block order** (results from earlier queries appear first)
- **No deduplication is performed**: if multiple queries match the same post, that post is emitted as multiple `<item>` elements. To avoid this, the user passes `excludeIds` to subsequent queries
- `postsPerPage` is independent per query. Total count = sum of per-query counts
- Render order is deterministic (same input → same output; queries are not parallelized)

### 12.6 `feedwright/item`

#### block.json

```json
{
  "apiVersion": 3,
  "name": "feedwright/item",
  "title": "Item Template",
  "category": "feedwright",
  "icon": "media-document",
  "parent": [ "feedwright/item-query" ],
  "supports": {
    "html": false,
    "reusable": false,
    "multiple": false,
    "inserter": false
  },
  "providesContext": {
    "feedwright/inItemContext": "feedwright/inItemContext"
  },
  "attributes": {
    "inItemContext": { "type": "boolean", "default": true }
  }
}
```

No attributes (acts as a template). Children: `feedwright/element`, `feedwright/sub-query`, `feedwright/comment`.

### 12.6.1 `feedwright/sub-query`

Inside an `feedwright/item` template, expand related posts into N sibling DOM nodes per outer item. Resolves the "related links" pattern that several aggregator specs require — goo `smp:relation` (max 3), mediba `mdf:relatedLink` (max 5), Google Merchant `g:additional_image_link` (max 10), etc.

#### attributes

```json
{
  "label":          { "type": "string",  "default": "" },
  "relationMode":   { "type": "string",  "default": "taxonomy",
                      "enum": [ "taxonomy", "manual" ] },
  "taxonomy":       { "type": "string",  "default": "" },
  "manualIds":      { "type": "array",   "default": [] },
  "postType":       { "type": "array",   "default": [ "post" ] },
  "postStatus":     { "type": "array",   "default": [ "publish" ] },
  "postsPerPage":   { "type": "number",  "default": 3 },
  "orderBy":        { "type": "string",  "default": "date" },
  "order":          { "type": "string",  "default": "DESC" },
  "excludeCurrent": { "type": "boolean", "default": true }
}
```

#### block.json

```json
{
  "apiVersion": 3,
  "name": "feedwright/sub-query",
  "title": "Sub Query",
  "category": "feedwright",
  "icon": "networking",
  "ancestor": [ "feedwright/item" ],
  "providesContext": {
    "feedwright/inItemContext": "feedwright/inItemContext"
  },
  "supports": { "html": false, "reusable": false, "multiple": true }
}
```

#### Relation modes

| Mode | Behavior |
|---|---|
| `taxonomy` | Fetch posts that share at least one term with the current item in `taxonomy`. **Hierarchical taxonomies only** (`category`-like). Flat taxonomies (`post_tag` etc.) are user-typed free input where exact-term matches are noise; non-hierarchical selections are skipped at render time and emit no nodes. Also falls through when the current item has no terms in that taxonomy. |
| `manual` | `post__in` against `manualIds`. Order is preserved (`orderby = post__in`); `order` is ignored. `excludeCurrent` filters the ID list directly. |

The current item is excluded by default (`excludeCurrent = true`). `postsPerPage` is clamped to `[1, ArgsBuilder::MAX_POSTS_PER_PAGE]` like the top-level query.

#### Hard-cap filter

```php
add_filter( 'feedwright/sub_query/hard_max', function ( int $max, array $block, $ctx ): int {
    // goo: smp:relation 最大 3
    return 3;
}, 10, 3 );
```

Use this filter to enforce spec-mandated caps. A return value `<= 0` disables the cap (default).

### 12.6.2 `feedwright/sub-item`

Template applied to each related post returned by `feedwright/sub-query`. Children are rendered with `Context::current_post()` switched to the related post for the duration of the iteration. Allowed children: `feedwright/element`, `feedwright/raw`, `feedwright/comment`.

```json
{
  "apiVersion": 3,
  "name": "feedwright/sub-item",
  "title": "Sub Item Template",
  "category": "feedwright",
  "icon": "media-document",
  "parent": [ "feedwright/sub-query" ],
  "supports": { "html": false, "reusable": false, "inserter": false }
}
```

### 12.7 `feedwright/raw`

Escape hatch. In principle the element block's cdata-binding mode is enough, but this exists for extreme cases.

#### attributes

```json
{
  "value":    { "type": "string",  "default": "" },
  "asCdata":  { "type": "boolean", "default": false },
  "interpolate": { "type": "boolean", "default": true }
}
```

#### block.json

```json
{
  "apiVersion": 3,
  "name": "feedwright/raw",
  "title": "Raw",
  "category": "feedwright",
  "icon": "editor-code",
  "parent": [ "feedwright/element" ],
  "supports": { "html": false, "reusable": false }
}
```

### 12.8 `feedwright/comment`

Emits an XML comment `<!-- ... -->`.

#### attributes

```json
{ "text": { "type": "string", "default": "" } }
```

#### block.json

```json
{
  "apiVersion": 3,
  "name": "feedwright/comment",
  "title": "XML Comment",
  "category": "feedwright",
  "icon": "format-status",
  "ancestor": [ "feedwright/rss" ],
  "supports": { "html": false, "reusable": false }
}
```

### 12.9 Acceptance criteria (all blocks)

- [ ] Each block is registered individually
- [ ] Insertions that violate parent/child rules don't appear in the Inserter
- [ ] rss / channel / item have `multiple: false` and cannot be inserted twice
- [ ] As dynamic blocks, save output is empty
- [ ] Attributes are fully restored after reloading the editor

---

## 13. Renderer

### 13.1 Input / output

- Input: `WP_Post` (a `feedwright_feed` post)
- Output: a string (XML). Also provides `render_to_output($post): void` for direct HTTP output.

### 13.2 Class layout

```
Feedwright\Renderer\Renderer            Facade
Feedwright\Renderer\DomBuilder          Wrapper for DOMDocument operations
Feedwright\Renderer\ElementRenderer     Handles feedwright/element
Feedwright\Renderer\ItemQueryRenderer   Handles feedwright/item-query
Feedwright\Renderer\Context             Context (post, feed, site, namespaces, dom)
```

### 13.3 Processing flow

```
1. parse_blocks( $post->post_content ) → array
2. Find the top-level feedwright/rss. If not found, raise an empty feed error
3. Initialize DomBuilder (create DOMDocument, formatOutput=true)
4. Create <rss> element
   - version attribute
   - setAttribute each namespace with xmlns:prefix="uri"
5. Extract feedwright/channel from innerBlocks and process recursively
6. Loop through channel contents:
   - feedwright/element        → ElementRenderer
   - feedwright/item-query     → ItemQueryRenderer (returns multiple <item> elements)
   - feedwright/comment        → DOMComment
   - Unsupported blocks        → skip (warning log)
7. saveXML() → string
8. Save to cache (optional)
```

### 13.4 ElementRenderer

```php
public function render(
    array $block,
    DOMDocument $dom,
    Context $ctx
): ?DOMElement {
    $tag = $block['attrs']['tagName'] ?? '';
    if ( ! self::is_valid_xml_name( $tag ) ) {
        return null;  // ignore invalid tag names
    }

    $el = $this->create_element( $dom, $tag, $ctx );

    foreach ( $block['attrs']['attributes'] ?? [] as $attr ) {
        if ( ! self::is_valid_xml_name( $attr['name'] ) ) continue;
        $value = $this->resolve_value( $attr, $ctx );
        $el->setAttribute( $attr['name'], $value );
    }

    switch ( $block['attrs']['contentMode'] ?? 'static' ) {
        case 'children':
            foreach ( $block['innerBlocks'] as $child ) {
                $node = $this->render_child( $child, $dom, $ctx );
                if ( $node ) $el->appendChild( $node );
            }
            break;
        case 'static':
            $value = $block['attrs']['staticValue'] ?? '';
            $el->appendChild( $dom->createTextNode( $this->sanitize_text( $value ) ) );
            break;
        case 'binding':
            $value = $this->resolver->resolve( $block['attrs']['bindingExpression'] ?? '', $ctx );
            $el->appendChild( $dom->createTextNode( $this->sanitize_text( $value ) ) );
            break;
        case 'cdata-binding':
            $value = $this->resolver->resolve( $block['attrs']['bindingExpression'] ?? '', $ctx );
            $el->appendChild( $dom->createCDATASection( $this->sanitize_xml_chars( $value ) ) );
            break;
        case 'empty':
            break;
    }

    return apply_filters( 'feedwright/element_node', $el, $block, $ctx );
}
```

### 13.5 Namespace resolution

The Context holds a `prefix → uri` map built from the `<rss>` block's `namespaces` attribute. When a tag name or attribute name contains `:`:

```php
private function create_element( DOMDocument $dom, string $tag, Context $ctx ): DOMElement {
    if ( strpos( $tag, ':' ) !== false ) {
        [ $prefix, $local ] = explode( ':', $tag, 2 );
        $uri = $ctx->namespace_for( $prefix );
        if ( $uri ) {
            return $dom->createElementNS( $uri, $tag );
        }
    }
    return $dom->createElement( $tag );
}
```

Tags using an undeclared prefix log a warning and the element is created **without a namespace** (technically invalid output, but more user-friendly than failing). Implementation choice: under `WP_DEBUG`, raise an error; in production, just log.

### 13.6 ItemQueryRenderer

```php
public function render(
    array $block,
    DOMDocument $dom,
    Context $ctx
): array {
    $args = ( new ArgsBuilder() )->build( $block['attrs'] );
    $args = apply_filters( 'feedwright/query_args', $args, $block, $ctx );

    $query = new \WP_Query( $args );
    $nodes = [];

    $item_template = $this->find_item_template( $block );
    if ( ! $item_template ) {
        wp_reset_postdata();
        return [];
    }

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_ctx = $ctx->with_post( get_post() );

        $item_tag = $block['attrs']['itemTagName'] ?? 'item';
        if ( ! self::is_valid_xml_name( $item_tag ) ) {
            $this->log_warning( "Invalid itemTagName: {$item_tag}, falling back to 'item'" );
            $item_tag = 'item';
        }

        $item_el = $this->create_element( $dom, $item_tag, $ctx );  // also resolves namespace
        foreach ( $item_template['innerBlocks'] as $child ) {
            $node = $this->render_child( $child, $dom, $post_ctx );
            if ( $node ) $item_el->appendChild( $node );
        }
        $nodes[] = $item_el;
    }
    wp_reset_postdata();
    return $nodes;
}
```

> **Customizing the `<item>` tag name**: Default is `item` (RSS 2.0). Set `itemTagName` to `entry` for Atom compatibility, or to a namespaced element like `my:thing` for custom formats. Tag names go through `create_element()` at render time, so namespace prefixes are resolved automatically.

#### Handling multiple item-query instances

When channel contains multiple `feedwright/item-query` blocks, the renderer runs each query **in InnerBlocks order** and appends the resulting `<item>` nodes to the channel element in sequence.

```php
// pseudocode for the channel rendering side
foreach ( $channel_block['innerBlocks'] as $child ) {
    if ( $child['blockName'] === 'feedwright/item-query' ) {
        $items = ( new ItemQueryRenderer() )->render( $child, $dom, $ctx );
        foreach ( $items as $item_el ) {
            $channel_el->appendChild( $item_el );  // ← preserve order
        }
    } elseif ( $child['blockName'] === 'feedwright/element' ) {
        // ... regular element processing
    }
}
```

Each `WP_Query` runs independently, with `wp_reset_postdata()` called at the end of each. To avoid polluting `global $post`, save it before the loop with `$original = $post;` and restore it afterwards.

**No deduplication is performed.** If the same post matches multiple queries, it appears as multiple `<item>` elements (resulting in multiple elements with the same `<guid>`). This is by design; the user controls it by setting `excludeIds` on subsequent queries.

### 13.6.1 SubQueryRenderer

Runs once per outer item, producing the inner DOM nodes for a `feedwright/sub-query`.

```php
public function render( array $block, Context $ctx ): array {
    $current = $ctx->current_post();
    if ( ! $current instanceof \WP_Post ) {
        return [];   // sub-query outside item scope -> no nodes
    }

    $args = ( new ArgsBuilder() )->build_sub( $block['attrs'], $current );
    if ( null === $args ) {
        return [];   // no terms / missing meta key / empty manualIds
    }

    $args     = apply_filters( 'feedwright/sub_query_args', $args, $block, $current, $ctx );
    $hard_max = (int) apply_filters( 'feedwright/sub_query/hard_max', 0, $block, $ctx );

    $template = $this->find_sub_item_template( $block );
    if ( null === $template ) return [];

    $nodes = [];
    $count = 0;
    $query = new \WP_Query( $args );
    while ( $query->have_posts() ) {
        $query->the_post();
        $related     = get_post();
        $related_ctx = $ctx->with_post( $related );

        foreach ( $template['innerBlocks'] as $child ) {
            foreach ( $this->element_renderer->render_child( $child, $related_ctx ) as $node ) {
                $nodes[] = $node;
            }
        }

        if ( $hard_max > 0 && ++$count >= $hard_max ) break;
    }
    wp_reset_postdata();
    return $nodes;
}
```

#### Context isolation

`Context` is immutable. `with_post()` returns a clone with the related post bound, so siblings of the `feedwright/sub-query` block (other elements of the same outer item) continue to see the original `current_post`. No explicit stack is required — the call chain *is* the stack.

#### `render_child` contract

To allow a single child block to expand to multiple nodes (sub-query), `ElementRenderer::render_child()` returns `array<\DOMNode>` rather than a nullable single node. Single-node block kinds (`element` / `raw` / `comment`) return at most one entry; sub-query may return many. Callers always iterate.

#### Performance

Each outer item triggers one extra `WP_Query` (N+1). Defaults set `update_post_meta_cache = false` to keep the cost bounded; the rendered XML is cached at the feed level by `RenderCache`. Spec-imposed caps (3 / 5 / 10) are applied **after** the query via `feedwright/sub_query/hard_max`, since most aggregator specs cap related links per item rather than per feed.

### 13.7 Sanitization

- `is_valid_xml_name`: `XML 1.0` Name production. `/^[A-Za-z_][A-Za-z0-9._-]*(:[A-Za-z_][A-Za-z0-9._-]*)?$/`
- `xml_chars`: strips control characters via `preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s )`
- `normalize_mode( string $mode )`: coerces an arbitrary string to `strict` (default) or `compat`
- `build_text_nodes( DOMDocument $dom, string $value, string $mode ): array<DOMNode>`:
  - **compat**: returns a single `DOMText` (DOMDocument auto-escapes only `& < >`)
  - **strict**: splits on `'` and `"` and returns alternating `DOMText` / `DOMEntityReference` nodes (`apos` / `quot`). The 5 predefined XML entities are always available without a DTD; libxml emits them verbatim through `saveXML()`. `& < >` continue to be auto-escaped.
- `append_text_node( DOMElement $element, string $value, string $mode )`: convenience wrapper that calls `build_text_nodes` and appends each result.
- `append_cdata( DOMElement $element, string $value )`: appends a CDATA section regardless of mode. The `cdata-binding` element mode is an explicit author choice and most aggregator specs allow CDATA for HTML body fields, so we honor it in both strict and compat.

#### Output mode resolution

`Renderer::render_uncached()` reads `outputMode` from the rss block's attributes (default `strict`), passes it through `Sanitize::normalize_mode()`, and stores it on `Context`. `ElementRenderer` and `render_raw()` consult `$ctx->output_mode()` to pick the right sanitizer call. `DOMDocument::formatOutput` is also tied to the mode: `compat || pretty` enables formatting; otherwise it stays off (minified production output).

#### Pretty override

The `?pretty=1` query parameter on the public feed URL toggles `formatOutput=true` regardless of the feed's `outputMode`. It is gated to logged-in admins (`current_user_can('manage_options')`) or builds with `WP_DEBUG=true`, to avoid leaking the formatted variant to scrapers. Pretty responses bypass the render cache and emit `Cache-Control: no-store`. The REST preview endpoint and the in-editor "View Feed" preview also force pretty mode internally.

### 13.8 Acceptance criteria

- [ ] A minimal feed with only a static `<title>` renders successfully
- [ ] When item-query yields 5 posts, 5 `<item>` elements are emitted
- [ ] **Multiple item-query**: with two item-queries under channel matching 3 and 2 posts respectively, 5 `<item>` elements are emitted in block order
- [ ] **Multiple item-query order**: all results from the first item-query come before results from the second
- [ ] Publish-date ASC vs DESC reverses ordering
- [ ] Modified-date DESC orders by `post_modified` descending
- [ ] **Setting `itemTagName` to `entry` outputs `<entry>...</entry>`**
- [ ] **Setting `itemTagName` to `atom:entry` resolves correctly when the `atom` namespace is declared on the rss block**
- [ ] **Invalid `itemTagName` (empty / XML Name violation) falls back to `item` and logs a warning**
- [ ] `content:encoded` is wrapped in CDATA
- [ ] Namespaced tags like `ext:logo` are emitted with the correct `xmlns:ext` declaration
- [ ] Self-closing tags like `<media:thumbnail url="..." />` are emitted (`contentMode: empty`)
- [ ] Invalid tag/attribute names are ignored (no crash)

---

## 14. Bindings

### 14.1 Syntax

```
{{<provider>.<path>[:<modifier>]}}
```

- `provider`: namespace (`option` / `feed` / `now` / `post` / `post_raw` / `post_meta` / `post_term` / `author` etc.)
- `path`: dot-separated. Arbitrary depth
- `modifier`: colon-separated. May contain multiple colons (e.g. `post_term::, ` described later)

Examples:
```
{{option.home_url}}
{{post.post_title}}
{{post.post_date:r}}
{{post_meta.feed_category}}
{{post_term.category::, }}
{{post.thumbnail_url:large}}
```

Multiple bindings can be embedded inside a single string. Example: `{{option.home_url}}feed/{{feed.slug}}/`

To emit a literal `{{`, escape with `\{{`.

### 14.2 Design principle

**Filtered vs raw, two-layer model** is made explicit by splitting `post.*` and `post_raw.*`:

| Layer | Role | Examples |
|---|---|---|
| `post.*` | Values passed through WordPress template tags / filters (recommended for external distribution) | `get_the_title()`, `apply_filters('the_content', ...)` |
| `post_raw.*` | Raw `WP_Post` field values (for custom needs) | `$post->post_title`, `$post->post_content` |

Keys inside the `post.*` namespace **match `WP_Post` field names** (e.g. `post.post_title`, `post.post_date`). This keeps the same key on both sides — users only need to think about "should filters apply or not?"

Computed values that have no `WP_Post` counterpart (permalink, thumbnail_url, etc.) are placed directly under `post.*` without trying to match a field name.

### 14.3 ProviderInterface

```php
namespace Feedwright\Bindings;

interface ProviderInterface {
    /** Provider namespace (e.g. "post") */
    public function namespace_name(): string;

    /**
     * @param string $path     e.g. "post_title" / "thumbnail_url"
     * @param string $modifier String after ":" (empty string if absent)
     * @param Context $ctx
     * @return string|null     null if unresolvable (→ converted to empty string)
     */
    public function resolve( string $path, string $modifier, Context $ctx ): ?string;

    /** For REST completion: list of binding candidates this provider offers */
    public function describe(): array;
}
```

### 14.4 Standard providers

#### `option.*` — site-wide settings

Supports both shortcuts (aliases) sourced from `get_bloginfo()` and direct `get_option()` access. Aliases follow a small predefined map; anything else falls back to `get_option($key)`.

| Binding | Value | Kind |
|---|---|---|
| `option.home_url` | `home_url('/')` | Alias |
| `option.site_url` | `site_url('/')` | Alias |
| `option.blogname` | `get_option('blogname')` | get_option |
| `option.blogdescription` | `get_option('blogdescription')` | get_option |
| `option.language` | `get_bloginfo('language')` (BCP 47) | Alias |
| `option.charset` | `get_bloginfo('charset')` (effectively UTF-8) | Alias |
| `option.timezone_string` | `get_option('timezone_string')` | get_option |
| `option.{any_key}` | `get_option($key)` | Fallback |

Serialized values (arrays / objects) return an empty string, since non-scalar values can't form a meaningful string.

#### `feed.*` — feed post metadata

| Binding | Value |
|---|---|
| `feed.title` | The feed post's `post_title` |
| `feed.slug` | `post_name` |
| `feed.url` | `home_url('/{base}/{slug}/')` |
| `feed.last_build_date[:fmt]` | The latest `post_modified_gmt` from the query results, or now if absent. Default format is `r` |

#### `now` — current time

| Binding | Value |
|---|---|
| `now` | Default format `c` (ISO 8601) |
| `now:r` | RFC 2822 (e.g. `Mon, 27 Apr 2026 10:00:00 +0900`, the format most RSS aggregators expect) |
| `now:c` | ISO 8601 |
| `now:U` | Unix timestamp |
| `now:{php_date_format}` | Any PHP date format via `date_i18n()` |

#### `post.*` — filtered post values (only inside item context)

For keys that map to `WP_Post` fields, returns the value passed through the corresponding template tag / filter.

| Binding | Value |
|---|---|
| `post.ID` | `get_the_ID()` |
| `post.post_title` | `get_the_title()` |
| `post.post_content` | `apply_filters('the_content', $post->post_content)` |
| `post.post_excerpt` | `get_the_excerpt()` (auto-generated if no manual excerpt) |
| `post.post_date[:fmt]` | `get_the_date($fmt)`, default `c` |
| `post.post_modified[:fmt]` | `get_the_modified_date($fmt)`, default `c` |
| `post.post_status` | `get_post_status()` |
| `post.post_name` | `$post->post_name` (same as raw; included in `post.*` for semantic consistency) |

Computed values without a `WP_Post` counterpart:

| Binding | Value |
|---|---|
| `post.permalink` | `get_permalink()` |
| `post.content_plaintext` | `wp_strip_all_tags( apply_filters('the_content', ...) )` |
| `post.thumbnail_id` | `get_post_thumbnail_id()` |
| `post.thumbnail_url[:size]` | `wp_get_attachment_image_url(id, $size)`, default `full` |
| `post.thumbnail_width[:size]` | width of the size |
| `post.thumbnail_height[:size]` | height of the size |
| `post.thumbnail_alt` | meta `_wp_attachment_image_alt` |
| `post.thumbnail_mime` | `get_post_mime_type( $thumbnail_id )` |
| `post.comments_count` | `get_comments_number()` |

Calling `post.*` outside an item context (e.g. directly under channel) returns an empty string.

#### `post_raw.*` — raw `WP_Post` fields (only inside item context)

Returns each `WP_Post` property as-is, **without filters**.

| Binding | Value |
|---|---|
| `post_raw.ID` | `$post->ID` |
| `post_raw.post_title` | `$post->post_title` |
| `post_raw.post_content` | `$post->post_content` (shortcodes unresolved) |
| `post_raw.post_excerpt` | `$post->post_excerpt` (manual only; empty if blank) |
| `post_raw.post_date[:fmt]` | `$post->post_date` formatted via `date($fmt, ...)` if a modifier is given. Without modifier, the raw string `"2026-04-27 10:00:00"` |
| `post_raw.post_date_gmt[:fmt]` | Same, GMT |
| `post_raw.post_modified[:fmt]` | Same |
| `post_raw.post_modified_gmt[:fmt]` | Same, GMT |
| `post_raw.post_status` | `$post->post_status` |
| `post_raw.post_name` | `$post->post_name` |
| `post_raw.post_author` | `$post->post_author` (numeric ID) |
| `post_raw.post_parent` | `$post->post_parent` |
| `post_raw.post_type` | `$post->post_type` |
| `post_raw.menu_order` | `$post->menu_order` |
| `post_raw.guid` | `$post->guid` |

**When to use which**:
- For external feeds, `<title>`, `<description>`, `<content:encoded>` should **use `post.*`** (so shortcodes resolve and theme customizations apply)
- For internal processing, debugging, or to avoid filter side effects, use `post_raw.*`
- When performance is critical and you don't want `the_content` filters to run, also use `post_raw.post_content`

#### `post_meta.*` — custom fields (only inside item context)

| Binding | Value |
|---|---|
| `post_meta.{key}` | `get_post_meta($id, $key, true)` |

Serialized values (arrays / objects) return an empty string. Scalars only.

#### `post_term.*` — taxonomies (only inside item context)

| Binding | Value |
|---|---|
| `post_term.{taxonomy}` | term `name`s joined with `, ` |
| `post_term.{taxonomy}::{sep}` | `name`s joined with `{sep}` (modifier acts as separator) |
| `post_term.{taxonomy}:slug` | `slug`s joined with `, ` |
| `post_term.{taxonomy}:slug::{sep}` | `slug`s joined with `{sep}` |

Examples:
- `{{post_term.category}}` → `"News, Tech"`
- `{{post_term.category::|}}` → `"News|Tech"`
- `{{post_term.post_tag:slug::, }}` → `"japan, ai"`

#### `post_term_meta.*` — term meta of the first matching term (only inside item context)

Path format: `{taxonomy}.{meta_key}`. Returns `get_term_meta()` (single value) of the first term `get_the_terms()` returns for the post in `{taxonomy}`. Powers the aggregator category-mapping pattern: each WP term carries an aggregator-side ID (e.g. mediba category ID, SmartNews channel ID) as term meta, and the binding surfaces it without per-feed configuration.

| Binding | Value |
|---|---|
| `post_term_meta.{taxonomy}.{meta_key}` | first term's `get_term_meta(... , true)` value |

Examples:
- `{{post_term_meta.category._mediba_category_id}}` → `"91"` if the first category has that meta set, else `""`
- `{{post_term_meta.category._mediba_category_id|default:99}}` → `"99"` when meta is unset
- `{{post_term.category|first|map:お役立ち=91,芸能=92|default:99}}` — alternative inline pattern when the mapping is small enough to hand-author

Returns empty string when:
- the post has no terms in the taxonomy
- the meta key is unset on the first term
- the meta value is an array (only scalar meta is supported)

#### `author.*` — post author (only inside item context)

Resolves through `get_the_author_meta()` using `$post->post_author` as the ID.

| Binding | Value |
|---|---|
| `author.ID` | `$post->post_author` |
| `author.display_name` | `get_the_author_meta('display_name', $author_id)` |
| `author.user_login` | same `user_login` |
| `author.user_email` | same `user_email` |
| `author.user_url` | same `user_url` |
| `author.user_nicename` | same `user_nicename` |
| `author.first_name` | same |
| `author.last_name` | same |
| `author.archive_url` | `get_author_posts_url( $author_id )` |

### 14.5 Date formats

`post.post_date`, `post_raw.post_date`, `post_raw.post_date_gmt`, `post.post_modified`, `post_raw.post_modified`, `post_raw.post_modified_gmt`, `feed.last_build_date`, and `now` accept a PHP [`date()` format](https://www.php.net/manual/en/datetime.format.php) as the modifier.

Common shortcuts:

| modifier | example output | use case |
|---|---|---|
| `r` | `Mon, 27 Apr 2026 10:00:00 +0900` | RFC 2822 (**commonly required by RSS aggregators for `<pubDate>`**) |
| `c` | `2026-04-27T10:00:00+09:00` | ISO 8601 / RFC 3339 |
| `U` | `1761548400` | Unix timestamp |
| `Y-m-d` | `2026-04-27` | Date only |
| `Y-m-d\TH:i:sP` | `2026-04-27T10:00:00+09:00` | Hand-written ISO 8601 |

`post.*` and `post_raw.*` (non-`_gmt`) follow the site timezone setting. `*_gmt` variants are UTC.

### 14.6 Resolver

```php
namespace Feedwright\Bindings;

class Resolver {
    /** @var ProviderInterface[] */
    private array $providers = [];

    public function add( ProviderInterface $p ): void {
        $this->providers[ $p->namespace_name() ] = $p;
    }

    public function resolve( string $expression, Context $ctx ): string {
        // 1. \{{ → temporary marker (escape for literal {{ )
        $expression = str_replace( '\{{', "\x00OPEN\x00", $expression );

        // 2. Replace {{provider.path:mod}} in order
        $result = preg_replace_callback(
            '/\{\{([a-z_][a-z0-9_]*(?:\.[a-z0-9_]+)*)(?::([^}]*))?\}\}/i',
            function ( $m ) use ( $ctx ) {
                $full = $m[1];                     // "post.thumbnail_url"
                $mod  = $m[2] ?? '';
                [ $ns, $path ] = explode( '.', $full, 2 ) + [ 1 => '' ];
                $provider = $this->providers[ $ns ] ?? null;
                if ( ! $provider ) return '';
                $value = $provider->resolve( $path, $mod, $ctx );
                return $value ?? '';
            },
            $expression
        );

        // 3. Restore markers to {{
        return str_replace( "\x00OPEN\x00", '{{', $result );
    }
}
```

### 14.6.1 Post-processing pipeline

You can append `|name:arg` to the end of a binding to apply transforms to the resolved value. Multiple `|` separators chain transforms in order.

```
{{post.post_title|truncate:80}}
{{post.post_content|allow_tags:p,a,strong,em|truncate:500}}
{{post.post_date:r|truncate:25}}
```

#### Built-in processors

| Name | Argument | Behavior |
|---|---|---|
| `truncate` | character count | First N characters via `mb_substr` (no-op for negative or zero) |
| `allow_tags` | comma-separated tag names | Allow only listed tags via `wp_kses`, no attributes. Empty argument strips all tags |
| `strip_tags` | (ignored) | Strip all tags via `wp_strip_all_tags` |
| `map` | `key=val,*=fallback` | If the input matches `key`, return that line's `val`. `*` is the fallback if no key matches. If neither matches nor `*` exists, returns empty string. The first `=` separates key and val, so `=` may appear in val. Useful for conditionals such as mapping `post_status` to a numeric `<media:status>` flag (publish=1 / removed=0): `{{post_raw.post_status\|map:publish=1,*=0}}` |
| `first` | separator (default `, `) | Returns the first segment of a separator-joined string. Pairs naturally with `post_term.{taxonomy}` (which joins multi-term posts with `", "`) before piping into `map`. Example: `{{post_term.category\|first\|map:Tech=10,News=20}}`. The separator argument cannot contain `\|` (pipe-syntax conflict) or `}`; to split on `\|`, change the upstream binding to emit a different separator via its modifier first |
| `default` | replacement value | Returns the literal argument when the input is the empty string; otherwise passes the input through unchanged. Differs from `map`'s `*` in that it triggers only on empty input — `"0"` and other falsy-looking strings pass through. Idiomatic with `post_term_meta`: `{{post_term_meta.category._mediba_category_id\|default:99}}` |

Unknown processor names log a warning and pass the input through unchanged.

#### Extension

Add `name => callable` entries via the `feedwright/binding_processors` filter:

```php
add_filter( 'feedwright/binding_processors', function ( $procs ) {
    $procs['my_uppercase'] = static function ( string $value, string $arg ): string {
        return strtoupper( $value );
    };
    return $procs;
} );
```

### 14.7 Extension hooks

```php
$providers = apply_filters( 'feedwright/binding_providers', [
    new Providers\OptionProvider(),
    new Providers\FeedProvider(),
    new Providers\NowProvider(),
    new Providers\PostProvider(),
    new Providers\PostRawProvider(),
    new Providers\PostMetaProvider(),
    new Providers\PostTermProvider(),
    new Providers\PostTermMetaProvider(),
    new Providers\AuthorProvider(),
] );
```

Third parties add their own providers via this filter:

```php
add_filter( 'feedwright/binding_providers', function ( $providers ) {
    $providers[] = new My_Acf_Provider();   // adds {{acf.field_name}}
    return $providers;
} );
```

### 14.8 Acceptance criteria

- [ ] `{{option.home_url}}` resolves to the site URL
- [ ] `{{option.blogname}}` resolves to the blog name
- [ ] `{{post.post_title}}` resolves to the filtered title (`get_the_title()`)
- [ ] `{{post_raw.post_title}}` resolves to the raw `$post->post_title`
- [ ] `{{post.post_date:r}}` returns RFC 2822 format
- [ ] `{{post.post_date:Y-m-d}}` returns `2026-04-27` format
- [ ] `{{post_meta.foo}}` returns the value of `get_post_meta($id, 'foo', true)`
- [ ] `{{post_term.category::|}}` joins terms as `News|Tech` with `|`
- [ ] `{{author.display_name}}` returns the author's display name
- [ ] `\{{post.post_title\}}` is emitted as the literal `{{post.post_title}}`
- [ ] `{{post.post_title}}` outside item context returns an empty string
- [ ] An unknown provider `{{unknown.foo}}` returns empty + logs a warning

---

## 15. Cache

### 15.1 Key strategy

```
feedwright:render:{blog_id}:{post_id}:{post_modified_gmt_unix}:{url_base_hash}
```

`url_base_hash = substr( md5( get_option('feedwright_url_base') ), 0, 8 )`

### 15.2 Store

`wp_cache_get` / `wp_cache_set` (group `feedwright`). Without a persistent object cache, the TTL is effectively short (it works as `wp_cache_set`'s in-process fallback).

### 15.3 Invalidation

| Event | Action |
|---|---|
| `save_post_feedwright_feed` | Invalidate cache for the saved feed |
| `save_post` (other post types) | Invalidate cache for all `feedwright_feed` posts (conservative) |
| `deleted_post` | Same |
| `update_option_feedwright_url_base` | Invalidate all caches + flush_rewrite_rules |
| Settings page "Clear cache" button | Invalidate all caches |

### 15.4 Acceptance criteria

- [ ] A second request to the same slug hits the cache (when object cache is enabled)
- [ ] Updating a feed post invalidates its cache
- [ ] Updating a normal post invalidates the cache
- [ ] TTL=0 fully disables caching

---

## 16. REST API

### 16.1 Binding autocomplete endpoint

```
GET /wp-json/feedwright/v1/bindings?context=item|channel
Permission: edit_posts → manage_options
Response:
  [
    { "expression": "post.post_title",          "label": "Post title (filtered)",      "context": "item",    "namespace": "post" },
    { "expression": "post.permalink",           "label": "Post permalink",             "context": "item",    "namespace": "post" },
    { "expression": "post.post_date:r",         "label": "Post date RFC2822",          "context": "item",    "namespace": "post" },
    { "expression": "post.thumbnail_url:large", "label": "Featured image URL (large)", "context": "item",    "namespace": "post" },
    { "expression": "post_raw.post_title",      "label": "Post title (raw)",           "context": "item",    "namespace": "post_raw" },
    { "expression": "post_meta.{key}",          "label": "Custom field",               "context": "item",    "namespace": "post_meta", "dynamic": true },
    { "expression": "post_term.{taxonomy}",     "label": "Term concatenation",         "context": "item",    "namespace": "post_term", "dynamic": true },
    { "expression": "author.display_name",      "label": "Author name",                "context": "item",    "namespace": "author" },
    { "expression": "option.home_url",          "label": "Site URL",                   "context": "channel", "namespace": "option" },
    { "expression": "option.blogname",          "label": "Site name",                  "context": "channel", "namespace": "option" },
    { "expression": "feed.last_build_date:r",   "label": "Feed last build RFC2822",    "context": "channel", "namespace": "feed" },
    { "expression": "now:r",                    "label": "Current time RFC2822",       "context": "any",     "namespace": "now" }
  ]
```

When `context=item`, the response includes `post.*` / `post_raw.*` / `post_meta.*` / `post_term.*` / `author.*`; when `context=channel` it excludes them. (`option.*` / `feed.*` / `now` are always included because they work in either context.) The filtering happens server-side.

`dynamic: true` indicates that the key is dynamic (driven by the post's actual custom field names or taxonomy names). Clients see this flag and fetch the real list of meta keys / taxonomies separately for the autocomplete UI.

### 16.2 Acceptance criteria

- [ ] Unauthenticated users get 401 / 403
- [ ] Editor role gets 403 (admins only)
- [ ] The binding list is correctly filtered by context

---

## 17. Editor UI

### 17.1 BindingInput component

Extends `<TextControl>`.

- Typing `{{` opens an autocomplete popup
- Candidates are fetched from `feedwright/v1/bindings` (the context is determined from the parent block's `feedwright/inItemContext`)
- Arrow keys to navigate, Enter to confirm
- On confirm, inserts something like `{{post.post_title}}`; the binding is rendered with color highlighting
- When the cursor is inside a binding, an "Edit this binding" button opens a modifier UI (PHP date format string, `post_term` separator, `thumbnail_url` size, etc.)
- Validation: highlights unbalanced `}}` or unknown providers in red

### 17.2 AttributeListEditor component

Edits the `attributes` array on the `element` block.

- Each row has "name", "value mode (radio: static/binding)", and "value"
- Drag-and-drop reordering
- "+ Add" button
- Validates name against XML Name rules → red border when invalid
- When value mode is binding, value is edited via BindingInput

### 17.3 Preview / publish flow

`feedwright_feed` registers with `public=true` / `publicly_queryable=true`, so the standard Gutenberg preview / publish flow handles XML previewing without a custom sidebar:

- The editor's "View" / "Preview" buttons resolve via `PostType::filter_permalink`, which redirects to `/{base}/{slug}/`. In admin contexts (`is_admin()` true) for users that can `manage_options`, the filter additionally appends `?pretty=1` so the formatted variant opens by default — front-end and REST contexts get the canonical clean URL.
- Published posts are served by `Routing\FeedEndpoint::maybe_serve_feed` as production XML (with `?pretty=1` available to admins / `WP_DEBUG` for human inspection).
- Drafts / non-published posts return 404 from the public URL — preview UX for unpublished feeds is intentionally left to the standard WordPress preview path that consumers can opt into via filters.

### 17.4 Acceptance criteria

- [ ] BindingInput's `{{` autocomplete works
- [ ] Inside item context, `post.*`, `post_raw.*`, `post_meta.*`, `post_term.*`, `author.*` appear as candidates
- [ ] Outside item context, those candidates are excluded
- [ ] XML Name violations in the attribute list display an instant warning
- [ ] The "View" link in the editor opens `/{base}/{slug}/` for published feeds

---

## 18. Initial template (minimum skeleton)

The block structure loaded when a new `feedwright_feed` post is created. Because blocks like `feedwright/rss` have `inserter: false` and cannot be inserted manually, **only structural blocks** are provided as the minimum skeleton.

Element blocks for `<title>`, `<link>`, `<description>`, etc. are **not included**. Format-specific element collections (Google News / MRSS / Apple News / other namespaced XML formats) are intended to be inserted from separately distributed block patterns.

### 18.1 Skeleton definition

`Feedwright\PostType::default_template()`:

```php
public function default_template(): array {
    return [
        [ 'feedwright/rss', [
            'namespaces' => [],   // Users add as needed (via the rss block's Inspector)
        ], [
            [ 'feedwright/channel', [], [
                // Directly under channel: no elements. Users insert a pattern or add manually
                [ 'feedwright/item-query', [
                    'postType'     => [ 'post' ],
                    'postsPerPage' => 20,
                    'orderBy'      => 'date',
                    'order'        => 'DESC',
                ], [
                    [ 'feedwright/item', [], [
                        // Inside item: no elements. Same as above
                    ] ],
                ] ],
            ] ],
        ] ],
    ];
}
```

The initial editor view looks like:

```
[RSS]                              ← cannot delete/move
└── [Channel]                       ← cannot delete/move
    └── [Item Query: 20 posts]     ← settings configurable
        └── [Item]                  ← cannot delete/move
```

Users can:
1. Add the namespaces they need via the rss block's Inspector (content, media, custom vendor namespaces, etc.)
2. Insert element blocks directly into channel (title, link, description, etc.)
3. Insert element blocks directly into item (title, link, pubDate, etc.)
4. Or insert a complete set from a block pattern

### 18.2 First-run guide banner

Because the skeleton alone leaves users wondering "what should I add?", a dismissable guide banner is shown on first load (using `@wordpress/notices` or a custom Notice component):

> **Get started with Feedwright**: We've laid out the channel and item scaffolding. Add element blocks (title / link / description, etc.) inside `channel` and `item`. Pre-built templates for Google News, MRSS and other formats can be inserted from block patterns.

A "Browse patterns" button shifts focus to the patterns tab in the inserter.

### 18.3 Distributed patterns (reference)

Out of scope for implementation. The following patterns are planned to be provided as block patterns in the future:

- **Google News feed**: Compatible with Google News Sitemap RSS
- **MRSS (Media RSS)**: Video / image distribution leveraging the media:* namespace
- **Apple News Format (lite)**: Lightweight Apple News-compatible RSS
- **Plain RSS 2.0**: A typical blog RSS output (for cases where a normal RSS 2.0 feed is needed)

These will not ship inside Feedwright itself; the architecture supports registering them externally via `register_block_pattern`.

---

## 19. Sample output XML (reference)

Since the skeleton in §18 contains no elements, the output starts out essentially empty. The expected output below shows a typical custom-namespaced feed once **the user has added elements via patterns or by hand**, and serves as a target for verifying the implementation:

Block layout (illustration):
- rss (namespaces: content, media, ext)
  - channel
    - element: title (`{{option.blogname}}`)
    - element: link (`{{option.home_url}}`)
    - element: description (`{{option.blogdescription}}`)
    - element: language (`{{option.language}}`)
    - element: lastBuildDate (`{{feed.last_build_date:r}}`)
    - item-query (postType=post, 20 items, date DESC)
      - item
        - element: title (`{{post.post_title}}`)
        - element: link (`{{post.permalink}}`)
        - element: guid (`{{post.permalink}}`, attr `isPermaLink=true`)
        - element: pubDate (`{{post.post_date:r}}`)
        - element: description (`{{post.post_excerpt}}`)
        - element: content:encoded (cdata-binding, `{{post.post_content}}`)

Expected output (on a site with 2 `post`s):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <title>Site Title</title>
    <link>https://example.com/</link>
    <description>Site Description</description>
    <language>ja</language>
    <lastBuildDate>Mon, 27 Apr 2026 10:00:00 +0900</lastBuildDate>
    <item>
      <title>Sample Article A</title>
      <link>https://example.com/2026/04/27/article-a/</link>
      <guid isPermaLink="true">https://example.com/2026/04/27/article-a/</guid>
      <pubDate>Mon, 27 Apr 2026 09:00:00 +0900</pubDate>
      <description>Excerpt of article A</description>
      <content:encoded><![CDATA[<p>Body of article A.</p>]]></content:encoded>
    </item>
    <item>
      <title>Sample Article B</title>
      <link>https://example.com/2026/04/26/article-b/</link>
      <guid isPermaLink="true">https://example.com/2026/04/26/article-b/</guid>
      <pubDate>Sun, 26 Apr 2026 14:30:00 +0900</pubDate>
      <description>Excerpt of article B</description>
      <content:encoded><![CDATA[<p>Body of article B.</p>]]></content:encoded>
    </item>
  </channel>
</rss>
```

If you change `itemTagName` to `entry`, the `<item>...</item>` blocks above become `<entry>...</entry>` (Atom-compatible use case).

---

## 20. Edge cases

| Case | Expected behavior |
|---|---|
| Post has no `feedwright/rss` block | 500 + log error (or empty 200; chosen: **500 Internal Server Error**) |
| Multiple rss blocks in the same post | Use only the first one, log a warning |
| `feedwright/channel` missing inside rss | 500 + log error |
| Zero `feedwright/item-query` blocks | Return an empty channel feed with no items (200) |
| **Multiple `feedwright/item-query` blocks** | **Run each query independently and emit `<item>`s in block order (no deduplication)** |
| **Same post matches multiple item-query** | **The post is emitted as multiple `<item>` elements. Use `excludeIds` on subsequent queries to avoid duplicates** |
| **All item-query blocks match zero posts** | Empty channel feed with no items (200) |
| Single item-query matches zero posts | 200 with no items |
| `orderBy` is `rand` | A cache hit does **not** re-randomize each request (the cached result comes back). If genuine randomness is needed, set TTL to 0 — call this out on the settings screen |
| `orderBy` is `meta_value` / `meta_value_num` but `metaQuery` is unset | Falls back to WP_Query's default behavior (order undefined). Show a warning on save |
| **Empty `itemTagName`** | Fall back to `item`, log a warning |
| **`itemTagName` violates XML Name rules** | Fall back to `item`, log a warning |
| **`itemTagName` uses an undeclared prefix** | Create the element without a namespace, log a warning (follows the general tag resolution rule) |
| Empty `tagName` | Skip the element (warning) |
| `tagName` violates XML Name rules (e.g. `123foo`, contains whitespace) | Skip (warning) |
| Attribute name violates XML Name rules | Skip just that attribute |
| Undefined namespace prefix (e.g. `unknown:tag`) | Create the element without a namespace, log a warning |
| Empty `bindingExpression` | Emit empty string |
| Binding has no matching provider | Emit empty string, log a warning |
| `post_meta.foo` resolves to an array / object | Empty string |
| Title etc. contains control characters | Strip the control characters and output |
| Permalinks are set to "Plain" | Warn on the settings screen. Rewrite still registers but won't work |
| URL base collides with an existing page slug | Warn on save (but save is allowed). Feedwright wins because it registers with `top` priority |
| POST to the public URL | 405 Method Not Allowed |

---

## 21. Acceptance criteria (entire plugin)

The following must work end to end:

1. Activate plugin → "Feedwright" menu appears in admin
2. "Add Feed" → editor opens with the rss / channel / item-query / item minimum skeleton (no element blocks)
3. Title "My Demo Feed", slug "demo", publish
4. Open `/feedwright/demo/` in the browser → RSS XML is served
5. Create one post → it appears in the feed
6. Change URL base to `news` in settings → feed is served at `/news/demo/`
7. Log in as another administrator → can edit the same feed
8. Log in as editor role → menu is hidden
9. On multisite, switch to a subsite → can create independent feeds
10. The output (using only supported elements) passes a generic RSS 2.0 validator

---

## 22. Implementation phases

Recommended implementation order. Each phase is independently demoable.

### Phase 0: Scaffold

- [ ] composer.json / package.json / @wordpress/scripts setup
- [ ] `Feedwright\Plugin` singleton
- [ ] `Feedwright\PostType` registers `feedwright_feed`
- [ ] Activation / deactivation hooks
- [ ] **DoD**: `feedwright_feed` posts can be created and saved in the admin

### Phase 1: Routing + static renderer

- [ ] `Settings` implementation (URL base, TTL, cache clear)
- [ ] Rewrite rule registration + `template_redirect` post lookup
- [ ] DOMDocument-based minimum renderer. Returns hardcoded `<rss><channel><title>{{option.blogname}}</title></channel></rss>`
- [ ] **DoD**: `/feedwright/{slug}/` returns a fixed-text XML

### Phase 2: Block registration (no editor)

- [ ] `block.json` and minimum edit.js for all 7 blocks
- [ ] Inserter restriction via `BlockRestriction`
- [ ] Pass minimum skeleton to `register_post_type`'s `template`
- [ ] Parent/ancestor constraints in block.json
- [ ] **DoD**: A new post expands the skeleton (rss → channel → item-query → item, no elements) and the block tree is visible (editing capability is still minimal)

### Phase 3: Renderer complete

- [ ] Implement `Renderer\Renderer`
- [ ] Implement `Renderer\ElementRenderer`
- [ ] Implement `Bindings\Resolver` + standard providers
- [ ] Implement `Renderer\ItemQueryRenderer`
- [ ] Sanitization and namespace handling
- [ ] **DoD**: The sample XML in §19 is actually generated

### Phase 4: Editor UI complete

- [ ] `BindingInput` component
- [ ] `AttributeListEditor` component
- [ ] Inspector UI for `feedwright/element`
- [ ] Inspector UI for `feedwright/item-query`
- [ ] Namespace editor UI for `feedwright/rss`
- [ ] Register block variations (common tags)
- [ ] **DoD**: A custom namespaced feed (RSS 2.0 + media:* + a vendor namespace) can be built entirely from the GUI

### Phase 5: REST

- [ ] `REST\BindingIntrospectionController`
- [ ] **DoD**: Binding autocomplete REST returns the catalogue filtered by context. Preview / publish UX is delegated to standard Gutenberg flow (no custom sidebar)

### Phase 6: Caching, optimization, polish

- [ ] `Cache\RenderCache`
- [ ] Invalidation hooks
- [ ] `Last-Modified` / `ETag` / 304 support
- [ ] Error logging cleanup
- [ ] **DoD**: All E2E acceptance criteria in §21 pass

### Phase 7: Tests

- [ ] PHPUnit (`tests/Unit/`, `tests/Integration/`)
- [ ] ESLint / PHPCS pass
- [ ] **DoD**: CI is green

---

## 24. Open questions

Items that may need a decision during implementation. Claude Code should not decide on its own — append questions here.

### Resolved

1. ~~Is a fixed `<item>` tag name acceptable?~~ → **It is configurable via `feedwright/item-query`'s `itemTagName` attribute (§12.5 / §13.6). Default is `item`; use `entry` for Atom; use any XML Name for custom formats. Namespaced prefixes such as `atom:entry` are supported.**
2. **Can multiple `feedwright/item-query` blocks be placed?** → **YES. Multiple instances are allowed under channel; `<item>` elements are emitted in block order (§12.5 / §13.6 / §20). Deduplication is the user's responsibility.**
3. **Which sort options are supported?** → **Publish date / modified date / title / menu order / random / comment count / meta value (string and numeric), all in ASC/DESC. See §12.5.**
4. Running `the_content` filters on `post.post_content` triggers shortcode processing for things like Jetpack. Do we need a mode that bypasses them? → **Already covered by `post_raw.post_content` (§14.4)**
5. With modifier-based bindings like `post.thumbnail_url:large`, what's the fallback when the size doesn't exist? → **Return `full`**
6. ~~Should the default template ship with format-specific elements?~~ → **It does not ship them (§18). Only the minimum skeleton is passed via `template`; no element blocks are included. Complete templates for Google News, MRSS, etc. can be registered externally as block patterns.**
7. What happens if a single post has multiple feeds with the same slug? → **Since WordPress's post_name is unique within a post type, there's no collision inside `feedwright_feed` (WP automatically appends `-2`)**

### Pending (to be decided during implementation)

8. If `ext:logo` is used while its namespace prefix is undefined on the rss block, should the editor warn? → **Add a warning UI in Phase 4**
9. Should channel get a "dedupe" option that automatically merges duplicate posts across multiple item-query blocks? → **Not in MVP. Re-evaluate based on feedback**
10. UI warning for the cache-vs-rand interaction → **The settings screen will note: "If you choose random ordering, set TTL to 0 — otherwise every request returns the same shuffled order"**
11. Should item-query blocks have a separate REST endpoint for "preview (how many posts does this query match right now)"? → **Recommend adding `/wp-json/feedwright/v1/query-preview` in Phase 5**
12. Wording and timing of the first-run banner (§18.2) → **Implemented in Phase 4. Dismiss state is stored in `wp_user_meta` so users who close it never see it again**
13. Distribution channel for block patterns: register on the WordPress.org Pattern Directory or host on the mt8 site? → **Consider both. WordPress.org has a review process but better discoverability; mt8 site offers more flexibility**

---

## Appendix A: Custom XML format example

A reference table showing how a typical custom-namespaced RSS feed (RSS 2.0 + `media:*` + a generic vendor namespace `ext`) maps to Feedwright blocks (for verifying the implementation). `ext` here stands in for any vendor-defined namespace such as `xmlns:ext="https://example.com/ext"`:

| Target XML element | Block layout |
|---|---|
| `<channel><title>` | `element(tagName=title, binding={{option.blogname}})` |
| `<channel><link>` | `element(tagName=link, binding={{option.home_url}})` |
| `<channel><description>` | `element(tagName=description, binding={{option.blogdescription}})` |
| `<channel><language>` | `element(tagName=language, binding={{option.language}})` |
| `<channel><ext:logo><url>` | `element(tagName=ext:logo, mode=children)` containing `element(tagName=url, static=https://...)` |
| `<item><title>` | inside item: `element(tagName=title, binding={{post.post_title}})` |
| `<item><link>` | inside item: `element(tagName=link, binding={{post.permalink}})` |
| `<item><guid isPermaLink="true">` | inside item: `element(tagName=guid, binding={{post.permalink}}, attrs=[{name:isPermaLink, mode:static, value:true}])` |
| `<item><pubDate>` | inside item: `element(tagName=pubDate, binding={{post.post_date:r}})` |
| `<item><description>` | inside item: `element(tagName=description, binding={{post.post_excerpt}})` |
| `<item><content:encoded>` CDATA | inside item: `element(tagName=content:encoded, mode=cdata-binding, binding={{post.post_content}})` |
| `<item><author>` | inside item: `element(tagName=author, binding={{author.user_email}} ({{author.display_name}}))` |
| `<item><category>` | inside item: `element(tagName=category, binding={{post_term.category}})` |
| `<item><ext:analytics>` (multiple) | inside item: multiple `element(tagName=ext:analytics, mode=children)` |
| `<item><media:thumbnail url="...">` | inside item: `element(tagName=media:thumbnail, mode=empty, attrs=[{name:url, mode:binding, value:{{post.thumbnail_url:large}}}])` |
| `<item><ext:logo>` from a custom field | inside item: `element(tagName=ext:logo, binding={{post_meta.feed_logo}})` |

If you can assemble all of the above, the result is a fully composed custom-namespaced RSS feed ready for downstream consumers.

---

## Appendix B: Reference resources

- WordPress Block API: https://developer.wordpress.org/block-editor/reference-guides/block-api/
- block.json schema: https://schemas.wp.org/trunk/block.json
- WP_Query parameters: https://developer.wordpress.org/reference/classes/wp_query/
- DOMDocument: https://www.php.net/manual/en/class.domdocument.php
- RSS 2.0 specification: https://www.rssboard.org/rss-specification

---

**Last updated**: 2026-04-27  
**Version**: 0.1.0 (initial spec)  
**Naming history**: Originally designed as "Feeditor" → renamed to "Feedwright" to avoid a clash with Adcore Inc.'s SaaS of the same name (2026-04-27).
