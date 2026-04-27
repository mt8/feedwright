import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextControl,
	TextareaControl,
	RadioControl,
	Notice,
} from '@wordpress/components';
import BindingInput from '../_shared/BindingInput';
import AttributeListEditor from '../_shared/AttributeListEditor';

const CHILD_ALLOWED = [ 'feedwright/raw', 'feedwright/element' ];

const XML_NAME_REGEX = /^[A-Za-z_][A-Za-z0-9._-]*(:[A-Za-z_][A-Za-z0-9._-]*)?$/;

const CONTENT_MODES = [
	{ label: __( 'children', 'feedwright' ), value: 'children' },
	{ label: __( 'static text', 'feedwright' ), value: 'static' },
	{ label: __( 'binding', 'feedwright' ), value: 'binding' },
	{ label: __( 'binding (CDATA)', 'feedwright' ), value: 'cdata-binding' },
	{ label: __( 'empty (self-closing)', 'feedwright' ), value: 'empty' },
];

export default function Edit( { attributes, setAttributes, context } ) {
	const inItemContext = !! ( context && context[ 'feedwright/inItemContext' ] );

	const { tagName, staticValue, bindingExpression = '' } = attributes;
	const contentMode = attributes.contentMode || 'static';
	const tag = tagName || __( '(no tag)', 'feedwright' );
	const tagValid = '' === tagName || XML_NAME_REGEX.test( tagName );
	const isChildrenMode = 'children' === contentMode;
	const isEmptyMode = 'empty' === contentMode;
	const isBindingMode = 'binding' === contentMode || 'cdata-binding' === contentMode;
	const isCdataBinding = 'cdata-binding' === contentMode;

	const blockProps = useBlockProps( {
		className:
			'feedwright-block feedwright-block-element' +
			( isChildrenMode ? ' feedwright-block-element--children' : '' ),
	} );

	const attrPairs = ( attributes.attributes || [] ).filter(
		( spec ) => spec && spec.name
	);
	const renderAttrs = () =>
		attrPairs.map( ( spec, index ) => (
			<span key={ index } className="feedwright-block-element__attr">
				{ ' ' }
				<span className="feedwright-block-element__attr-name">
					{ spec.name }
				</span>
				=&quot;
				<span className="feedwright-block-element__attr-value">
					{ spec.value || '' }
				</span>
				&quot;
			</span>
		) );

	let preview;
	switch ( contentMode ) {
		case 'children':
			preview = (
				<div className="feedwright-block-element__children">
					<InnerBlocks
						allowedBlocks={ CHILD_ALLOWED }
						templateLock={ false }
					/>
				</div>
			);
			break;
		case 'binding':
		case 'cdata-binding':
			preview = (
				<code className="feedwright-block-element__binding">
					{ bindingExpression || __( '{{...}}', 'feedwright' ) }
				</code>
			);
			break;
		case 'static':
			preview = (
				<span className="feedwright-block-element__value">
					{ staticValue || __( '(empty)', 'feedwright' ) }
				</span>
			);
			break;
		case 'empty':
		default:
			preview = null;
	}

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Element', 'feedwright' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Tag name', 'feedwright' ) }
						value={ tagName || '' }
						onChange={ ( next ) => setAttributes( { tagName: next } ) }
						help={ __( 'Use prefix:local for namespaced tags. The prefix must be declared on the rss block.', 'feedwright' ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					{ ! tagValid && (
						<Notice status="error" isDismissible={ false }>
							{ __( 'Tag name is not a valid XML Name.', 'feedwright' ) }
						</Notice>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Content', 'feedwright' ) } initialOpen={ true }>
					<RadioControl
						label={ __( 'Content mode', 'feedwright' ) }
						selected={ contentMode }
						options={ CONTENT_MODES }
						onChange={ ( next ) => setAttributes( { contentMode: next } ) }
					/>

					{ 'static' === contentMode && (
						<TextareaControl
							label={ __( 'Static value', 'feedwright' ) }
							value={ staticValue || '' }
							onChange={ ( next ) => setAttributes( { staticValue: next } ) }
							__nextHasNoMarginBottom
						/>
					) }

					{ isBindingMode && (
						<BindingInput
							label={
								isCdataBinding
									? __( 'Binding expression (CDATA)', 'feedwright' )
									: __( 'Binding expression', 'feedwright' )
							}
							value={ bindingExpression }
							onChange={ ( next ) => setAttributes( { bindingExpression: next } ) }
							help={ __( 'Use {{ to insert a binding. Combine free text with multiple bindings.', 'feedwright' ) }
							inItemContext={ inItemContext }
						/>
					) }

					{ 'children' === contentMode && (
						<p style={ { margin: 0, opacity: 0.7 } }>
							{ __( 'This element will be composed of nested element blocks.', 'feedwright' ) }
						</p>
					) }

					{ 'empty' === contentMode && (
						<p style={ { margin: 0, opacity: 0.7 } }>
							{ __( 'Self-closing element with attributes only.', 'feedwright' ) }
						</p>
					) }
				</PanelBody>

				<PanelBody title={ __( 'Attributes', 'feedwright' ) } initialOpen={ false }>
					<AttributeListEditor
						value={ attributes.attributes || [] }
						onChange={ ( next ) => setAttributes( { attributes: next } ) }
						inItemContext={ inItemContext }
					/>
				</PanelBody>
			</InspectorControls>

			<span className="feedwright-block-element__open">
				&lt;{ tag }
				{ renderAttrs() }
				{ isEmptyMode ? ' /' : '' }&gt;
			</span>
			{ preview }
			{ ! isEmptyMode && (
				<span className="feedwright-block-element__close">
					&lt;/{ tag }&gt;
				</span>
			) }
		</div>
	);
}
