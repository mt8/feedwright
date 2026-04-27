/**
 * Read the binding catalogue injected by PHP (BlockRegistry) and filter it by
 * editor context (item / channel). The catalogue rows look like:
 *   { expression, label, context, namespace, dynamic? }
 */

import { useMemo } from '@wordpress/element';

/**
 * @returns {Array<{expression: string, label: string, context: string, namespace: string, dynamic?: boolean}>}
 */
function getCatalogue() {
	if ( typeof window === 'undefined' ) {
		return [];
	}
	const cat = window.feedwrightBindings;
	return Array.isArray( cat ) ? cat : [];
}

/**
 * Suggest bindings for the given context.
 *
 * @param {Object} options
 * @param {boolean} options.inItemContext True inside `feedwright/item` subtree.
 * @param {string}  [options.query]       Optional substring filter (without `{{`).
 */
export default function useBindingSuggestions( { inItemContext, query = '' } ) {
	return useMemo( () => {
		const cat = getCatalogue();
		const want = inItemContext ? [ 'item', 'any' ] : [ 'channel', 'any' ];
		const q = query.trim().toLowerCase();
		return cat
			.filter( ( row ) => want.includes( row.context ) )
			.filter( ( row ) => ! q || row.expression.toLowerCase().includes( q ) || row.label.toLowerCase().includes( q ) )
			.slice( 0, 20 );
	}, [ inItemContext, query ] );
}
