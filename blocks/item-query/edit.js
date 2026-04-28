import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	SelectControl,
	RadioControl,
	__experimentalNumberControl as NumberControl,
	CheckboxControl,
	Notice,
	FormTokenField,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

const ALLOWED_CHILDREN = [ 'feedwright/item' ];
const TEMPLATE = [ [ 'feedwright/item' ] ];

const ORDER_BY_OPTIONS = [
	{ label: __( 'Publish date', 'feedwright' ), value: 'date' },
	{ label: __( 'Modified date', 'feedwright' ), value: 'modified' },
	{ label: __( 'Title', 'feedwright' ), value: 'title' },
	{ label: __( 'Menu order', 'feedwright' ), value: 'menu_order' },
	{ label: __( 'Random', 'feedwright' ), value: 'rand' },
	{ label: __( 'Comment count', 'feedwright' ), value: 'comment_count' },
	{ label: __( 'Meta value (string)', 'feedwright' ), value: 'meta_value' },
	{ label: __( 'Meta value (numeric)', 'feedwright' ), value: 'meta_value_num' },
	{ label: __( 'None', 'feedwright' ), value: 'none' },
];

const POST_STATUS_OPTIONS = [
	{ label: 'publish', value: 'publish' },
	{ label: 'private', value: 'private' },
	{ label: 'future', value: 'future' },
	{ label: 'trash', value: 'trash' },
];

const XML_NAME_REGEX = /^[A-Za-z_][A-Za-z0-9._-]*(:[A-Za-z_][A-Za-z0-9._-]*)?$/;

function toggleInArray( arr, value ) {
	const set = new Set( Array.isArray( arr ) ? arr : [] );
	if ( set.has( value ) ) {
		set.delete( value );
	} else {
		set.add( value );
	}
	return Array.from( set );
}

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'feedwright-block feedwright-block-item-query',
	} );

	const {
		label,
		postType = [ 'post' ],
		postsPerPage = 20,
		orderBy = 'date',
		order = 'DESC',
		postStatus = [ 'publish' ],
		trashWithinDays = 0,
		itemTagName = 'item',
		includeStickyPosts = false,
		taxQuery = [],
	} = attributes;

	const includesTrash = Array.isArray( postStatus ) && postStatus.includes( 'trash' );

	const taxonomies = useSelect(
		( select ) => {
			const all = select( coreStore ).getTaxonomies( { per_page: -1 } );
			if ( ! Array.isArray( all ) ) {
				return [];
			}
			const targetTypes = Array.isArray( postType ) && postType.length > 0 ? postType : [ 'post' ];
			return all.filter(
				( t ) =>
					Array.isArray( t.types ) &&
					t.types.some( ( type ) => targetTypes.includes( type ) )
			);
		},
		[ postType ]
	);

	// Single-condition: surface only the first taxQuery entry.
	const currentTaxEntry = Array.isArray( taxQuery ) && taxQuery.length > 0 ? taxQuery[ 0 ] : null;
	const currentTaxonomy = currentTaxEntry?.taxonomy || '';
	const currentTermSlugs = Array.isArray( currentTaxEntry?.terms ) ? currentTaxEntry.terms : [];

	// Existing terms in the chosen taxonomy that have at least one assigned post.
	const termObjects = useSelect(
		( select ) => {
			if ( ! currentTaxonomy ) {
				return [];
			}
			const recs = select( coreStore ).getEntityRecords( 'taxonomy', currentTaxonomy, {
				per_page: -1,
				hide_empty: true,
			} );
			return Array.isArray( recs ) ? recs : [];
		},
		[ currentTaxonomy ]
	);

	const slugByName = {};
	const nameBySlug = {};
	termObjects.forEach( ( t ) => {
		slugByName[ t.name ] = t.slug;
		nameBySlug[ t.slug ] = t.name;
	} );

	const setTaxonomyChoice = ( nextTaxonomy ) => {
		if ( ! nextTaxonomy ) {
			setAttributes( { taxQuery: [] } );
			return;
		}
		// Store taxonomy with empty terms; the filter only activates once
		// terms are picked (ArgsBuilder skips empty-terms entries).
		setAttributes( {
			taxQuery: [
				{
					taxonomy: nextTaxonomy,
					field: 'slug',
					terms: [],
				},
			],
		} );
	};

	const setTermSlugs = ( slugs ) => {
		if ( ! currentTaxonomy ) {
			setAttributes( { taxQuery: [] } );
			return;
		}
		setAttributes( {
			taxQuery: [
				{
					taxonomy: currentTaxonomy,
					field: 'slug',
					terms: slugs,
				},
			],
		} );
	};

	const types = Array.isArray( postType ) ? postType.join( ', ' ) : 'post';
	const summary = sprintf(
		/* translators: 1: post type list, 2: count, 3: orderBy field, 4: ASC/DESC */
		__( 'Latest %2$d %1$s (%3$s %4$s)', 'feedwright' ),
		types,
		postsPerPage,
		orderBy,
		order
	);

	const itemTagValid = '' === itemTagName || XML_NAME_REGEX.test( itemTagName );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Basics', 'feedwright' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Editor label', 'feedwright' ) }
						value={ label || '' }
						onChange={ ( next ) => setAttributes( { label: next } ) }
						help={ __( 'Used in the editor only; not emitted in XML.', 'feedwright' ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<TextControl
						label={ __( 'Post types (comma-separated)', 'feedwright' ) }
						value={ ( postType || [] ).join( ',' ) }
						onChange={ ( next ) =>
							setAttributes( {
								postType: next
									.split( ',' )
									.map( ( s ) => s.trim() )
									.filter( Boolean ),
							} )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<NumberControl
						label={ __( 'Posts per page', 'feedwright' ) }
						min={ 1 }
						max={ 500 }
						value={ postsPerPage }
						onChange={ ( next ) => setAttributes( { postsPerPage: parseInt( next, 10 ) || 20 } ) }
					/>
				</PanelBody>

				<PanelBody title={ __( 'Item element name', 'feedwright' ) } initialOpen={ false }>
					<TextControl
						label={ __( 'Tag name', 'feedwright' ) }
						value={ itemTagName }
						onChange={ ( next ) => setAttributes( { itemTagName: next } ) }
						help={ __( 'Default "item" (RSS 2.0). Use "entry" for Atom, or any prefix:local declared on the rss block.', 'feedwright' ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					{ ! itemTagValid && (
						<Notice status="error" isDismissible={ false }>
							{ __( 'Item tag name is not a valid XML Name.', 'feedwright' ) }
						</Notice>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Sorting', 'feedwright' ) } initialOpen={ false }>
					<SelectControl
						label={ __( 'Order by', 'feedwright' ) }
						value={ orderBy }
						options={ ORDER_BY_OPTIONS }
						onChange={ ( next ) => setAttributes( { orderBy: next } ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<RadioControl
						label={ __( 'Order', 'feedwright' ) }
						selected={ order }
						options={ [
							{ label: __( 'Descending (newest first)', 'feedwright' ), value: 'DESC' },
							{ label: __( 'Ascending (oldest first)', 'feedwright' ), value: 'ASC' },
						] }
						onChange={ ( next ) => setAttributes( { order: next } ) }
					/>
					{ 'rand' === orderBy && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'Random ordering is cached together with the rendered XML. Set Cache TTL to 0 in Feedwright settings if you need fresh randomization.', 'feedwright' ) }
						</Notice>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Filtering', 'feedwright' ) } initialOpen={ false }>
					<p style={ { margin: 0, fontWeight: 'bold' } }>
						{ __( 'Post status', 'feedwright' ) }
					</p>
					{ POST_STATUS_OPTIONS.map( ( option ) => (
						<CheckboxControl
							key={ option.value }
							label={ option.label }
							checked={ ( postStatus || [] ).includes( option.value ) }
							onChange={ () =>
								setAttributes( {
									postStatus: toggleInArray( postStatus, option.value ),
								} )
							}
						/>
					) ) }
					{ includesTrash && (
						<NumberControl
							label={ __( 'Include trashed posts modified within N days', 'feedwright' ) }
							help={ __( '0 = no time limit. Caps trash entries to recently-deleted ones; published posts are unaffected.', 'feedwright' ) }
							min={ 0 }
							max={ 365 }
							value={ trashWithinDays }
							onChange={ ( next ) => {
								const parsed = parseInt( next, 10 );
								setAttributes( { trashWithinDays: Number.isFinite( parsed ) && parsed > 0 ? parsed : 0 } );
							} }
						/>
					) }
					<CheckboxControl
						label={ __( 'Include sticky posts', 'feedwright' ) }
						checked={ !! includeStickyPosts }
						onChange={ ( next ) => setAttributes( { includeStickyPosts: next } ) }
					/>

					<hr />
					<p style={ { margin: 0, fontWeight: 'bold' } }>
						{ __( 'Taxonomy filter', 'feedwright' ) }
					</p>
					<SelectControl
						label={ __( 'Taxonomy', 'feedwright' ) }
						value={ currentTaxonomy }
						options={ [
							{ label: __( '— None —', 'feedwright' ), value: '' },
							...taxonomies.map( ( t ) => ( {
								label: t.name + ' (' + t.slug + ')',
								value: t.slug,
							} ) ),
						] }
						onChange={ setTaxonomyChoice }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					{ currentTaxonomy && (
						<FormTokenField
							label={ __( 'Terms', 'feedwright' ) }
							value={ currentTermSlugs.map( ( slug ) => nameBySlug[ slug ] || slug ) }
							suggestions={ termObjects.map( ( t ) => t.name ) }
							onChange={ ( names ) => {
								const slugs = names
									.map( ( n ) => slugByName[ n ] || n )
									.filter( Boolean );
								setTermSlugs( slugs );
							} }
							__experimentalExpandOnFocus={ true }
							__experimentalShowHowTo={ false }
							placeholder={
								termObjects.length === 0
									? __( 'No terms with posts yet.', 'feedwright' )
									: __( 'Select terms…', 'feedwright' )
							}
						/>
					) }
					{ currentTaxonomy && (
						<p style={ { margin: '4px 0 0 0', opacity: 0.7, fontSize: 11 } }>
							{ __( 'Only terms with at least one post are listed.', 'feedwright' ) }
						</p>
					) }
				</PanelBody>
			</InspectorControls>

			<header className="feedwright-block__header">
				<strong>{ label || __( 'Item Query', 'feedwright' ) }</strong>
				<small>{ summary } · &lt;{ itemTagName || 'item' }&gt;</small>
			</header>
			<InnerBlocks
				allowedBlocks={ ALLOWED_CHILDREN }
				template={ TEMPLATE }
				templateLock="all"
			/>
		</div>
	);
}
