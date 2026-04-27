import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	TextareaControl,
	CheckboxControl,
} from '@wordpress/components';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'feedwright-block feedwright-block-raw',
	} );
	const { value, asCdata, interpolate } = attributes;

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Raw value', 'feedwright' ) } initialOpen={ true }>
					<TextareaControl
						label={ __( 'Value', 'feedwright' ) }
						value={ value || '' }
						onChange={ ( next ) => setAttributes( { value: next } ) }
						help={ __( 'Inserted verbatim. Combine with bindings to interpolate runtime values.', 'feedwright' ) }
						__nextHasNoMarginBottom
					/>
					<CheckboxControl
						label={ __( 'Wrap in CDATA', 'feedwright' ) }
						checked={ !! asCdata }
						onChange={ ( next ) => setAttributes( { asCdata: next } ) }
					/>
					<CheckboxControl
						label={ __( 'Resolve {{bindings}}', 'feedwright' ) }
						checked={ !! interpolate }
						onChange={ ( next ) => setAttributes( { interpolate: next } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<small>
				{ asCdata
					? __( 'Raw (CDATA)', 'feedwright' )
					: __( 'Raw', 'feedwright' ) }
			</small>
			<pre className="feedwright-block-raw__value">
				{ value || __( '(empty)', 'feedwright' ) }
			</pre>
		</div>
	);
}
