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
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

const ALLOWED_CHILDREN = [ 'feedwright/sub-item' ];
const TEMPLATE = [ [ 'feedwright/sub-item' ] ];

const RELATION_MODES = [
	{ label: __( 'Same taxonomy term (hierarchical only)', 'feedwright' ), value: 'taxonomy' },
	{ label: __( 'Manual ID list', 'feedwright' ), value: 'manual' },
];

const ORDER_BY_OPTIONS = [
	{ label: __( 'Publish date', 'feedwright' ), value: 'date' },
	{ label: __( 'Modified date', 'feedwright' ), value: 'modified' },
	{ label: __( 'Title', 'feedwright' ), value: 'title' },
	{ label: __( 'Menu order', 'feedwright' ), value: 'menu_order' },
	{ label: __( 'Random', 'feedwright' ), value: 'rand' },
	{ label: __( 'Comment count', 'feedwright' ), value: 'comment_count' },
];

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'feedwright-block feedwright-block-sub-query',
	} );

	const {
		label,
		relationMode = 'taxonomy',
		taxonomy = '',
		manualIds = [],
		postType = [ 'post' ],
		postsPerPage = 3,
		orderBy = 'date',
		order = 'DESC',
		excludeCurrent = true,
	} = attributes;

	const taxonomies = useSelect(
		( select ) => {
			const all = select( coreStore ).getTaxonomies( { per_page: -1 } );
			if ( ! Array.isArray( all ) ) {
				return [];
			}
			const targetTypes =
				Array.isArray( postType ) && postType.length > 0 ? postType : [ 'post' ];
			// Hierarchical only: flat taxonomies (e.g. tags) are user-typed
			// free input so two posts almost never share an exact term.
			return all.filter(
				( t ) =>
					t.hierarchical &&
					Array.isArray( t.types ) &&
					t.types.some( ( type ) => targetTypes.includes( type ) )
			);
		},
		[ postType ]
	);

	const summary = sprintf(
		/* translators: 1: relation mode, 2: max count */
		__( '%1$s · up to %2$d related', 'feedwright' ),
		relationMode,
		postsPerPage
	);

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Relation', 'feedwright' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Editor label', 'feedwright' ) }
						value={ label || '' }
						onChange={ ( next ) => setAttributes( { label: next } ) }
						help={ __( 'Used in the editor only; not emitted in XML.', 'feedwright' ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<RadioControl
						label={ __( 'Match by', 'feedwright' ) }
						selected={ relationMode }
						options={ RELATION_MODES }
						onChange={ ( next ) => setAttributes( { relationMode: next } ) }
					/>
					{ 'taxonomy' === relationMode && (
						<>
							<SelectControl
								label={ __( 'Taxonomy', 'feedwright' ) }
								value={ taxonomy }
								options={ [
									{ label: __( '— Select —', 'feedwright' ), value: '' },
									...taxonomies.map( ( t ) => ( {
										label: t.name + ' (' + t.slug + ')',
										value: t.slug,
									} ) ),
								] }
								onChange={ ( next ) => setAttributes( { taxonomy: next } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							{ taxonomies.length === 0 && (
								<Notice status="info" isDismissible={ false }>
									{ __( 'No hierarchical taxonomy is registered for the chosen post type.', 'feedwright' ) }
								</Notice>
							) }
						</>
					) }
					{ 'manual' === relationMode && (
						<TextControl
							label={ __( 'Post IDs (comma-separated)', 'feedwright' ) }
							value={ ( manualIds || [] ).join( ',' ) }
							onChange={ ( next ) =>
								setAttributes( {
									manualIds: next
										.split( ',' )
										.map( ( s ) => parseInt( s.trim(), 10 ) )
										.filter( ( n ) => Number.isInteger( n ) && n > 0 ),
								} )
							}
							help={ __( 'Order is preserved (post__in).', 'feedwright' ) }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Limits', 'feedwright' ) } initialOpen={ false }>
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
						label={ __( 'Max posts per item', 'feedwright' ) }
						min={ 1 }
						max={ 50 }
						value={ postsPerPage }
						onChange={ ( next ) =>
							setAttributes( { postsPerPage: parseInt( next, 10 ) || 3 } )
						}
					/>
					<CheckboxControl
						label={ __( 'Exclude the current item', 'feedwright' ) }
						checked={ !! excludeCurrent }
						onChange={ ( next ) => setAttributes( { excludeCurrent: next } ) }
					/>
					{ 'manual' !== relationMode && (
						<Notice status="info" isDismissible={ false }>
							{ __(
								'Spec-mandated caps (e.g. 3 / 5) should be enforced via the feedwright/sub_query/hard_max filter.',
								'feedwright'
							) }
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
				</PanelBody>
			</InspectorControls>

			<header className="feedwright-block__header">
				<strong>{ label || __( 'Sub Query', 'feedwright' ) }</strong>
				<small>{ summary }</small>
			</header>
			<InnerBlocks
				allowedBlocks={ ALLOWED_CHILDREN }
				template={ TEMPLATE }
				templateLock="all"
			/>
		</div>
	);
}
