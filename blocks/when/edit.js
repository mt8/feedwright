import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl, Notice } from '@wordpress/components';
import BindingInput from '../_shared/BindingInput';

const ALLOWED_CHILDREN = [
	'feedwright/element',
	'feedwright/raw',
	'feedwright/comment',
	'feedwright/sub-query',
	'feedwright/when',
];

export default function Edit( { attributes, setAttributes, context } ) {
	const inItemContext = !! ( context && context[ 'feedwright/inItemContext' ] );

	const { label = '', expression = '', negate = false } = attributes;

	const blockProps = useBlockProps( {
		className: 'feedwright-block feedwright-block-when',
	} );

	const summary = expression
		? ( negate ? '!= ' : '== ' ) + ' ' + expression
		: __( '(no expression)', 'feedwright' );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Condition', 'feedwright' ) } initialOpen={ true }>
					<TextControl
						label={ __( 'Editor label', 'feedwright' ) }
						value={ label }
						onChange={ ( next ) => setAttributes( { label: next } ) }
						help={ __( 'Used in the editor only; not emitted in XML.', 'feedwright' ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<BindingInput
						label={ __( 'Expression', 'feedwright' ) }
						value={ expression }
						onChange={ ( next ) => setAttributes( { expression: next } ) }
						help={ __( 'Inner blocks render only when this expression resolves to a non-empty string. Use the map / default / first processors to shape the truthiness.', 'feedwright' ) }
						inItemContext={ inItemContext }
					/>
					<ToggleControl
						label={ __( 'Negate (render when empty instead)', 'feedwright' ) }
						checked={ !! negate }
						onChange={ ( next ) => setAttributes( { negate: next } ) }
					/>
					{ '' === expression && (
						<Notice status="info" isDismissible={ false }>
							{ __( 'No expression set: under default behavior nothing is rendered. Add an expression, or toggle Negate to render unconditionally.', 'feedwright' ) }
						</Notice>
					) }
				</PanelBody>
			</InspectorControls>

			<header className="feedwright-block__header">
				<strong>{ label || __( 'When', 'feedwright' ) }</strong>
				<small>{ summary }</small>
			</header>
			<InnerBlocks
				allowedBlocks={ ALLOWED_CHILDREN }
				templateLock={ false }
			/>
		</div>
	);
}
