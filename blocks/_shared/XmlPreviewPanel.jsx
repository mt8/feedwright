/**
 * Sidebar plugin that fetches /wp-json/feedwright/v1/preview/{id} and shows
 * the rendered XML with warnings.
 */

import { useState, useEffect, useRef, useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { PluginSidebar } from '@wordpress/editor';
import {
	Button,
	ToggleControl,
	Notice,
	Spinner,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const FEED_POST_TYPE = 'feedwright_feed';
const PUBLIC_BASE = ( window?.feedwrightSettings?.urlBase || 'feedwright' ).replace( /^\/+|\/+$/g, '' );

/**
 * Very small XML pretty-printing escape — input is already pretty-formatted by
 * DOMDocument, we just escape angle brackets for safe innerHTML and add a tiny
 * bit of color via inline spans.
 *
 * @param {string} xml
 */
function highlight( xml ) {
	if ( typeof xml !== 'string' || '' === xml ) {
		return '';
	}
	const escape = ( s ) =>
		s
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );

	// Tags / attributes / values via a binding-friendly regex.
	return escape( xml ).replace(
		/(&lt;\/?)([a-zA-Z][a-zA-Z0-9:_-]*)([^&]*?)(\/?&gt;)/g,
		( _, open, name, attrs, close ) => {
			const attrColored = attrs.replace(
				/([a-zA-Z][a-zA-Z0-9:_-]*)=("[^"]*")/g,
				'<span style="color:#7a3e3e">$1</span>=<span style="color:#0a6e0a">$2</span>'
			);
			return `${ open }<span style="color:#1d4ed8">${ name }</span>${ attrColored }${ close }`;
		}
	);
}

function PreviewBody( { postId, postType } ) {
	const [ state, setState ] = useState( {
		loading: false,
		xml: '',
		warnings: [],
		error: null,
		fetchedAt: null,
	} );
	const [ autoUpdate, setAutoUpdate ] = useState( true );
	const debounceRef = useRef( null );

	const isFeed = FEED_POST_TYPE === postType;
	const slug = useSelect( ( select ) => select( editorStore ).getEditedPostAttribute( 'slug' ), [] );
	const editedContent = useSelect(
		( select ) => select( editorStore ).getEditedPostContent(),
		[]
	);

	const publicUrl = useMemo( () => {
		if ( ! slug ) {
			return null;
		}
		return ( window.location.origin + '/' + PUBLIC_BASE + '/' + slug + '/' ).replace( /\/+/g, '/' ).replace( ':/', '://' );
	}, [ slug ] );

	const fetchPreview = async () => {
		if ( ! postId ) {
			return;
		}
		setState( ( prev ) => ( { ...prev, loading: true, error: null } ) );
		try {
			const data = await apiFetch( {
				path: `/feedwright/v1/preview/${ postId }`,
			} );
			setState( {
				loading: false,
				xml: data?.xml || '',
				warnings: Array.isArray( data?.warnings ) ? data.warnings : [],
				error: null,
				fetchedAt: new Date(),
			} );
		} catch ( e ) {
			setState( ( prev ) => ( {
				...prev,
				loading: false,
				error: e?.message || String( e ),
			} ) );
		}
	};

	// Auto-update on edited content changes (debounced 2s) when enabled.
	useEffect( () => {
		if ( ! isFeed || ! autoUpdate ) {
			return;
		}
		if ( debounceRef.current ) {
			clearTimeout( debounceRef.current );
		}
		debounceRef.current = setTimeout( fetchPreview, 2000 );
		return () => {
			if ( debounceRef.current ) {
				clearTimeout( debounceRef.current );
			}
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ editedContent, autoUpdate, postId ] );

	if ( ! isFeed ) {
		return (
			<p style={ { padding: 12 } }>
				{ __( 'XML preview is only available for Feedwright feed posts.', 'feedwright' ) }
			</p>
		);
	}

	return (
		<VStack spacing={ 4 } style={ { padding: 12 } }>
			<ToggleControl
				label={ __( 'Auto-update preview after editing', 'feedwright' ) }
				checked={ autoUpdate }
				onChange={ setAutoUpdate }
				__nextHasNoMarginBottom
			/>

			<Button variant="secondary" onClick={ fetchPreview } disabled={ state.loading }>
				{ state.loading ? (
					<>
						<Spinner /> { __( 'Fetching…', 'feedwright' ) }
					</>
				) : (
					__( 'Refresh preview', 'feedwright' )
				) }
			</Button>

			{ state.error && (
				<Notice status="error" isDismissible={ false }>
					{ state.error }
				</Notice>
			) }

			{ state.warnings.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					<strong>{ __( 'Renderer warnings', 'feedwright' ) }</strong>
					<ul style={ { margin: '4px 0 0 16px' } }>
						{ state.warnings.map( ( w, i ) => (
							<li key={ i }>{ w }</li>
						) ) }
					</ul>
				</Notice>
			) }

			{ state.xml && (
				<pre
					className="feedwright-xml-preview"
					style={ {
						maxHeight: 480,
						overflow: 'auto',
						padding: 8,
						fontSize: 12,
						background: '#f6f7f7',
						border: '1px solid #ddd',
						borderRadius: 4,
						whiteSpace: 'pre-wrap',
						wordBreak: 'break-word',
					} }
					// eslint-disable-next-line react/no-danger
					dangerouslySetInnerHTML={ { __html: highlight( state.xml ) } }
				/>
			) }

			{ publicUrl && (
				<p style={ { margin: 0 } }>
					<a href={ publicUrl } target="_blank" rel="noreferrer">
						{ __( 'Open public URL', 'feedwright' ) }
					</a>
				</p>
			) }
		</VStack>
	);
}

export default function XmlPreviewPanel() {
	const { postId, postType } = useSelect( ( select ) => {
		const editor = select( editorStore );
		return {
			postId: editor.getCurrentPostId(),
			postType: editor.getCurrentPostType(),
		};
	}, [] );

	return (
		<PluginSidebar
			name="feedwright-xml-preview"
			title={ __( 'Feedwright XML Preview', 'feedwright' ) }
			icon="rss"
		>
			<PreviewBody postId={ postId } postType={ postType } />
		</PluginSidebar>
	);
}
