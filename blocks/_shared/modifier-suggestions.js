/**
 * Suggest modifier values for a given binding stem.
 *
 * Examples:
 *   stem = "post.post_date"      → date format candidates (r / c / Y-m-d ...)
 *   stem = "post.thumbnail_url"  → image size candidates (thumbnail / medium / ...)
 *   stem = "post_term.category"  → term-field / separator forms (slug / ::, ...)
 *
 * Returns an array of { value, label } rows, or [] when the stem has no
 * structured modifier menu.
 */

import { __ } from '@wordpress/i18n';

const DATE_STEM_REGEX =
	/^(now|post\.(post_date|post_modified)|post_raw\.(post_date|post_date_gmt|post_modified|post_modified_gmt)|feed\.last_build_date)$/;

const IMAGE_STEM_REGEX = /^post\.thumbnail_(url|width|height)$/;

const TERM_STEM_REGEX = /^post_term\..+/;

function dateFormatRows() {
	return [
		{ value: 'r', label: __( 'RFC 2822 (Mon, 27 Apr 2026 10:00:00 +0900)', 'feedwright' ) },
		{ value: 'c', label: __( 'ISO 8601 (2026-04-27T10:00:00+09:00)', 'feedwright' ) },
		{ value: 'U', label: __( 'Unix timestamp', 'feedwright' ) },
		{ value: 'Y-m-d', label: __( 'Date (2026-04-27)', 'feedwright' ) },
		{ value: 'Y-m-d H:i:s', label: __( 'Date + time (2026-04-27 10:00:00)', 'feedwright' ) },
		{ value: 'Y/m/d', label: __( 'Slash date (2026/04/27)', 'feedwright' ) },
	];
}

function imageSizeRows() {
	return [
		{ value: 'thumbnail', label: __( 'thumbnail (default 150×150)', 'feedwright' ) },
		{ value: 'medium', label: __( 'medium (default 300px wide)', 'feedwright' ) },
		{ value: 'medium_large', label: __( 'medium_large (default 768px wide)', 'feedwright' ) },
		{ value: 'large', label: __( 'large (default 1024px wide)', 'feedwright' ) },
		{ value: 'full', label: __( 'full (original size)', 'feedwright' ) },
	];
}

function termModifierRows() {
	return [
		{ value: 'slug', label: __( 'slug (slugs joined by ", ")', 'feedwright' ) },
		{ value: '::|', label: __( 'separator: | (names joined by |)', 'feedwright' ) },
		{ value: 'slug::|', label: __( 'slug + separator |', 'feedwright' ) },
		{ value: '::, ', label: __( 'separator: ", " (default)', 'feedwright' ) },
	];
}

/**
 * @param {string} stem Binding path before the colon (e.g., "post.post_date").
 * @param {string} [query] Optional partial modifier already typed; used for filtering.
 * @returns {Array<{value: string, label: string}>}
 */
export default function modifierSuggestions( stem, query = '' ) {
	let rows = [];
	if ( DATE_STEM_REGEX.test( stem ) ) {
		rows = dateFormatRows();
	} else if ( IMAGE_STEM_REGEX.test( stem ) ) {
		rows = imageSizeRows();
	} else if ( TERM_STEM_REGEX.test( stem ) ) {
		rows = termModifierRows();
	} else {
		return [];
	}

	const q = query.trim().toLowerCase();
	if ( ! q ) {
		return rows;
	}
	return rows.filter(
		( row ) =>
			row.value.toLowerCase().includes( q ) ||
			row.label.toLowerCase().includes( q )
	);
}
