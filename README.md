<p align="center">
  <img src="assets/logo.svg" width="96" height="96" alt="Feedwright">
</p>

<h1 align="center">Feedwright</h1>

<p align="center">
  <strong>Edit custom RSS / Atom / XML feeds visually in the WordPress block editor.</strong>
</p>

<p align="center">
  <a href="https://playground.wordpress.net/?storage=none&blueprint-url=https://raw.githubusercontent.com/mt8/feedwright/main/playground/blueprint.json" target="_blank" rel="noopener">
    <img src="https://img.shields.io/badge/▶%20Try%20it%20live-WordPress%20Playground-21759b?style=for-the-badge&logo=wordpress&logoColor=white" alt="Try Feedwright in WordPress Playground">
  </a>
</p>

<p align="center">
  <a href="#try-it-live">Try it live</a> •
  <a href="#features">Features</a> •
  <a href="#how-it-works">How It Works</a> •
  <a href="#bindings">Bindings</a> •
  <a href="#installation">Installation</a> •
  <a href="#development">Development</a> •
  <a href="#faq">FAQ</a> •
  <a href="#contributing">Contributing</a> •
  <a href="#license">License</a>
</p>

---

## Try it live

Click **▶ Try it live** above (or <a href="https://playground.wordpress.net/?storage=none&blueprint-url=https://raw.githubusercontent.com/mt8/feedwright/main/playground/blueprint.json" target="_blank" rel="noopener">open the demo</a>). WordPress Playground boots a full WordPress entirely in your browser with Feedwright pre-installed and a SmartNews-shaped sample feed already seeded — four sample posts with featured images, a `<channel>` carrying `snf:logo`, and item-level `media:thumbnail` / `media:content` / `dc:creator` / `category` / `media:status` (mapped from post status). It lands you straight in the block editor for that feed; visit `/feedwright/smartnews/` to see the rendered XML.

## The Problem

WordPress ships exactly one RSS feed shape. Anything else — Atom 1.0, Media RSS, namespaced extensions, conditional elements, alternate query orderings — means writing PHP templates and shipping a bespoke endpoint per feed.

There's no GUI for "I want this tag here, that namespace declared, this query running, this field bound to that element". Editors who own the feeds can't touch them without engineering involvement.

**Feedwright fixes this.** Feeds are post objects you compose with the block editor: nest XML elements as blocks, declare namespaces on the root, configure a query, bind dynamic values via `{{post.post_title}}` expressions. The plugin renders the resulting tree as XML and serves it at a public URL.

## Features

- **Block-tree editor for XML** — `<rss>` / `<channel>` / `<item>` and arbitrary `element` blocks compose the feed structure visually.
- **Namespaced tags** — declare `xmlns:` prefixes on the root block; use them anywhere as `prefix:tagname` on elements and attributes.
- **Query as a block** — `feedwright/item-query` exposes WP_Query options (post types, ordering, post status, taxonomy filter, sticky toggle) and expands each result inside `<channel>` as an `<item>`.
- **Dynamic bindings** — `{{post.post_title}}`, `{{post.permalink}}`, `{{post.post_date:r}}`, `{{author.display_name}}`, `{{post_meta.x}}`, `{{post_term.category}}`, `{{option.blogname}}` and more, with modifiers and processors (`truncate`, `allow_tags`, `strip_tags`, `map`).
- **Live XML preview** — sidebar panel that renders the current feed by calling a REST endpoint, with auto-update on edit.
- **Object-cache–backed render** — keyed by `post_modified_gmt` for natural invalidation; manual flush in settings.
- **Multisite-aware** — each site stores and serves its own feeds.
- **Custom post type permissions** — feeds are restricted to `manage_options` by default.
- **i18n** — Japanese localization included.

## How It Works

```
                  feedwright_feed (custom post type)
                            │
            block tree:  <rss> → <channel> → element*
                                         └── <item-query> → <item> → element*
                            │
                      parse_blocks
                            │
            ┌───────────────┴───────────────┐
            ▼                               ▼
        Renderer                     BindingResolver
   (DOMDocument)                  ({{ns.path:mod|proc}})
            │                               │
            └─────── XML string  ◀──────────┘
                            │
                  /{base}/{slug}/  ──▶  client
                            │
                      Render cache
                  (object cache, TTL)
```

1. A `feedwright_feed` post stores the block tree.
2. A custom rewrite rule maps `/{base}/{slug}/` to that post.
3. The renderer walks the block tree, resolves bindings against post / option / now / feed contexts, and builds a `DOMDocument`.
4. Result is cached by post id + `post_modified_gmt` and served as `application/xml`.

## Bindings

Bindings are inline expressions that resolve at render time. Inside an element block (or any `static text`-mode field), type `{{` to insert one — the editor offers contextual suggestions for available providers, modifiers (date format, image size, term separator), and post-resolution processors.

```
{{namespace.path[:modifier][|processor[:arg]] [...]}}
```

Examples:

```
{{post.post_title}}                            → post title (filtered)
{{post.post_date:r}}                           → publish date in RFC 2822
{{post.thumbnail_url:large}}                   → featured image URL at the "large" size
{{post.post_excerpt|truncate:120}}             → excerpt, max 120 chars
{{post.post_content|allow_tags:p,br,strong}}   → content stripped to a few HTML tags
{{post_raw.post_status|map:publish=1,*=0}}     → 1 if published, 0 otherwise
{{post_term.category}}                         → joined term names
{{option.blogname}} / {{option.home_url}}
{{author.display_name}} / {{author.user_email}}
{{feed.last_build_date:r}} / {{now:r}}
```

Available namespaces: `option`, `feed`, `now`, `post`, `post_raw`, `post_meta`, `post_term`, `author`. Built-in processors: `truncate`, `allow_tags`, `strip_tags`, `map`. Both providers and processors are extensible from third-party code via `feedwright/binding_providers` and `feedwright/binding_processors` filters.

## Installation

1. Upload the plugin folder to `wp-content/plugins/feedwright/`, or install via the Plugins screen.
2. Activate the plugin.
3. Go to **Feedwright → Add Feed** and start composing in the block editor.
4. Optionally adjust the URL prefix at **Feedwright → Settings**.

## Development

Requires Docker Desktop, Node 20+, PHP 8.3+, and Composer.

```bash
git clone https://github.com/mt8/feedwright.git
cd feedwright
composer install
npm install

# Boot wp-env (dev: 9888, tests: 9889)
npx wp-env start

# Build editor assets
npm run build           # one-shot
npm run start           # watch mode
```

### Tests

```bash
# Static analysis (WordPress Coding Standards)
composer run phpcs
composer run phpcbf      # auto-fix

# PHP unit (no WP, fast)
composer run test:unit

# PHP integration (inside wp-env tests-cli)
npx wp-env run tests-cli --env-cwd=wp-content/plugins/feedwright \
    vendor/bin/phpunit --testsuite integration --bootstrap=tests/bootstrap.php

# E2E (Playwright)
npx playwright install chromium
npm run test:e2e
```

`husky` + `lint-staged` runs `phpcs` against staged PHP files before each commit (enabled after `npm install`).

### Translations

```bash
# Refresh the .pot template
npx wp-env run cli wp i18n make-pot wp-content/plugins/feedwright \
    wp-content/plugins/feedwright/languages/feedwright.pot \
    --domain=feedwright --slug=feedwright \
    --exclude=vendor,node_modules,build,tests,tmp

# After editing a .po (e.g. feedwright-ja.po), regenerate .mo + JSON
npx wp-env run cli wp i18n make-mo  wp-content/plugins/feedwright/languages
npx wp-env run cli wp i18n make-json wp-content/plugins/feedwright/languages \
    --no-purge --extensions=jsx
```

### Directory layout

```
feedwright/
├── feedwright.php       Plugin main entry (bootstrap)
├── src/                 PHP (PSR-4: Feedwright\)
├── blocks/              Block source (block.json + edit / index / variations)
├── languages/           .pot / .po / .mo / per-script JED
├── assets/              Logos and icons
└── tests/
    ├── bootstrap.php    Integration bootstrap
    ├── Unit/            Unit tests
    └── Integration/     Integration tests
```

Full implementation spec: [`docs/requirements.md`](docs/requirements.md).

## FAQ

**How is this different from a custom feed template in a theme or plugin?**

A theme template ties the feed shape to PHP and to the people who can deploy code. Feedwright stores feeds as posts, so editors with appropriate permissions can change the structure, add elements, or rewire bindings without touching files or shipping a release.

**Where are feeds served?**

At `/{base}/{slug}/` where `base` is configurable (default `feedwright`) and `slug` comes from each `feedwright_feed` post's `post_name`. Default: `/feedwright/{slug}/`.

**Does it cache?**

Yes — the render is cached in the WordPress object cache, keyed by `(blog_id, post_id, post_modified_gmt, url_base_hash)`. Updating the feed post invalidates naturally; settings provides a manual flush button. TTL defaults to one hour and is configurable.

**Can I declare arbitrary XML namespaces?**

Yes. Add `prefix` / `uri` pairs on the `<rss>` block's Inspector. Any element or attribute can then use `prefix:localname`.

**Does it support Atom?**

Yes — set the item element name on `feedwright/item-query` (`item` for RSS 2.0, `entry` for Atom, or any declared `prefix:local`), and choose tag names for the channel-level elements accordingly.

**Multisite?**

Yes — each site has independent feeds, settings, and cache.

## Contributing

Issues and PRs are welcome. Please open an issue first to discuss non-trivial changes.

The project follows an `issue/{ISSUE_NUMBER}` branching convention. CI runs `phpcs`, PHPUnit (Unit + Integration on a matrix of WordPress 6.5 / latest × PHP 8.3 / 8.4), Plugin Check, and Playwright E2E.

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.
