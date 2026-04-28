import { __ } from '@wordpress/i18n';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, RadioControl } from '@wordpress/components';
import NamespaceEditor from '../_shared/NamespaceEditor';

const ALLOWED_CHILDREN = [ 'feedwright/channel' ];
const TEMPLATE = [ [ 'feedwright/channel' ] ];

const OUTPUT_MODE_OPTIONS = [
	{
		label: __( 'Strict (recommended) — minified, quotes entity-encoded', 'feedwright' ),
		value: 'strict',
	},
	{
		label: __( 'Compat — pretty-formatted, quotes left as-is', 'feedwright' ),
		value: 'compat',
	},
];

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'feedwright-block feedwright-block-rss',
	} );

	const namespaces = Array.isArray( attributes.namespaces ) ? attributes.namespaces : [];
	const namespaceCount = namespaces.length;
	const outputMode = attributes.outputMode === 'compat' ? 'compat' : 'strict';

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title="<rss>" initialOpen={ true }>
					<TextControl
						label={ __( 'Version', 'feedwright' ) }
						value={ attributes.version || '2.0' }
						onChange={ ( next ) => setAttributes( { version: next } ) }
						help={ __( 'Currently only RSS 2.0 is rendered.', 'feedwright' ) }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
					<RadioControl
						label={ __( 'Output mode', 'feedwright' ) }
						selected={ outputMode }
						options={ OUTPUT_MODE_OPTIONS }
						onChange={ ( next ) => setAttributes( { outputMode: next } ) }
						help={ __( 'Strict matches the requirements of most aggregator submission specs: no inter-element whitespace, and quotation marks in regular text are entity-encoded. CDATA-binding elements keep their CDATA wrapper in both modes — that is the spec-blessed way to embed HTML in body fields.', 'feedwright' ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Namespaces', 'feedwright' ) } initialOpen={ true }>
					<NamespaceEditor
						value={ namespaces }
						onChange={ ( next ) => setAttributes( { namespaces: next } ) }
					/>
				</PanelBody>
			</InspectorControls>

			<header className="feedwright-block__header">
				<strong>&lt;rss&gt;</strong>
				<small>
					{ __( 'version', 'feedwright' ) } { attributes.version }
					{ namespaceCount > 0
						? ` · ${ namespaceCount } ${ __( 'namespace(s)', 'feedwright' ) }`
						: '' }
				</small>
			</header>
			<InnerBlocks
				allowedBlocks={ ALLOWED_CHILDREN }
				template={ TEMPLATE }
				templateLock="all"
			/>
		</div>
	);
}
