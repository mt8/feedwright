/**
 * Built-in binding processors offered by the editor. Mirrors what
 * Feedwright\Bindings\Resolver registers server-side. Any custom processors
 * registered via the `feedwright/binding_processors` filter remain usable in
 * the rendered output but are not surfaced here unless distributed alongside
 * a corresponding editor extension.
 */

import { __ } from '@wordpress/i18n';

const PROCESSORS = [
	{
		name: 'truncate',
		label: __( 'truncate — first N characters', 'feedwright' ),
		takesArg: true,
		argHint: '80',
	},
	{
		name: 'allow_tags',
		label: __( 'allow_tags — keep only listed HTML tags', 'feedwright' ),
		takesArg: true,
		argHint: 'p,a,strong',
	},
	{
		name: 'strip_tags',
		label: __( 'strip_tags — remove all HTML', 'feedwright' ),
		takesArg: false,
	},
	{
		name: 'map',
		label: __( 'map — translate value via key=val pairs (* for fallback)', 'feedwright' ),
		takesArg: true,
		argHint: 'publish=1,*=0',
	},
];

/**
 * @param {string} [query] Partial processor name typed by the user.
 */
export default function processorSuggestions( query = '' ) {
	const q = query.trim().toLowerCase();
	if ( ! q ) {
		return PROCESSORS;
	}
	return PROCESSORS.filter(
		( p ) => p.name.toLowerCase().includes( q ) || p.label.toLowerCase().includes( q )
	);
}
