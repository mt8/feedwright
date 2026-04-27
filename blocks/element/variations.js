/**
 * Block variations for `feedwright/element` — preset commonly-used tags.
 *
 * Each variation defaults `tagName`, `contentMode`, and a sensible
 * `bindingExpression`. Defaults differ for channel-scope vs item-scope, but
 * for simplicity we ship one default per variation; users can switch the
 * binding via the Inspector.
 *
 * Titles use `<tagname>` formatting and are intentionally not wrapped with
 * `__()` — they are XML element identifiers, not user-facing copy.
 *
 * Spec §12.4Block Variations.
 */

const BIND = ( expr ) => '{{' + expr + '}}';

const channelLevel = ( title, tag, expr, extra = {} ) => ( {
	name: 'feedwright-' + tag.replace( ':', '-' ),
	title,
	scope: [ 'inserter', 'transform' ],
	attributes: {
		tagName: tag,
		contentMode: 'binding',
		bindingExpression: BIND( expr ),
		attributes: [],
		...extra,
	},
} );

const itemLevel = ( title, tag, expr, mode = 'binding', extra = {} ) => ( {
	name: 'feedwright-' + tag.replace( ':', '-' ) + '-item',
	title,
	scope: [ 'inserter', 'transform' ],
	attributes: {
		tagName: tag,
		contentMode: mode,
		bindingExpression: BIND( expr ),
		attributes: [],
		...extra,
	},
} );

const variations = [
	// Channel-scope.
	channelLevel( '<title> (site)', 'title', 'option.blogname' ),
	channelLevel( '<link> (home)', 'link', 'option.home_url' ),
	channelLevel( '<description> (tagline)', 'description', 'option.blogdescription' ),
	channelLevel( '<language>', 'language', 'option.language' ),
	channelLevel( '<lastBuildDate>', 'lastBuildDate', 'feed.last_build_date:r' ),
	channelLevel( '<copyright>', 'copyright', 'option.blogname' ),
	channelLevel( '<ttl>', 'ttl', 'option.feedwright_cache_ttl' ),

	// Item-scope.
	itemLevel( '<title> (post)', 'title', 'post.post_title' ),
	itemLevel( '<link> (permalink)', 'link', 'post.permalink' ),
	{
		name: 'feedwright-guid-item',
		title: '<guid> (permalink)',
		scope: [ 'inserter', 'transform' ],
		attributes: {
			tagName: 'guid',
			contentMode: 'binding',
			bindingExpression: BIND( 'post.permalink' ),
			attributes: [
				{ name: 'isPermaLink', valueMode: 'static', value: 'true' },
			],
		},
	},
	itemLevel( '<pubDate>', 'pubDate', 'post.post_date:r' ),
	itemLevel( '<description> (excerpt)', 'description', 'post.post_excerpt' ),
	itemLevel( '<content:encoded>', 'content:encoded', 'post.post_content', 'cdata-binding' ),
	itemLevel( '<dc:creator>', 'dc:creator', 'author.display_name' ),
	itemLevel( '<category>', 'category', 'post_term.category' ),
	itemLevel( '<author>', 'author', 'author.user_email' ),

	// Empty self-closing variations.
	{
		name: 'feedwright-media-thumbnail',
		title: '<media:thumbnail>',
		scope: [ 'inserter', 'transform' ],
		attributes: {
			tagName: 'media:thumbnail',
			contentMode: 'empty',
			bindingExpression: '',
			attributes: [
				{ name: 'url', valueMode: 'binding', value: BIND( 'post.thumbnail_url:large' ) },
			],
		},
	},
	{
		name: 'feedwright-media-content',
		title: '<media:content>',
		scope: [ 'inserter', 'transform' ],
		attributes: {
			tagName: 'media:content',
			contentMode: 'empty',
			bindingExpression: '',
			attributes: [
				{ name: 'url', valueMode: 'binding', value: BIND( 'post.thumbnail_url:full' ) },
				{ name: 'medium', valueMode: 'static', value: 'image' },
			],
		},
	},
];

export default variations;
