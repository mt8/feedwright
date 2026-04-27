import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, TextareaControl } from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'feedwright-block feedwright-block-comment',
	} );
	const { text } = attributes;
	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'XML Comment', 'feedwright' ) } initialOpen={ true }>
					<TextareaControl
						label={ __( 'Text', 'feedwright' ) }
						value={ text || '' }
						onChange={ ( next ) => setAttributes( { text: next } ) }
						help={ __( 'Rendered as <!-- text -->. The string "--" is replaced with "- -" to satisfy XML rules.', 'feedwright' ) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>

			<code className="feedwright-block-comment__value">
				&lt;!-- { text || __( '(empty comment)', 'feedwright' ) } --&gt;
			</code>
		</div>
	);
}
