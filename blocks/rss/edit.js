import { __ } from '@wordpress/i18n';
import { useBlockProps, InnerBlocks, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import NamespaceEditor from '../_shared/NamespaceEditor';

const ALLOWED_CHILDREN = [ 'feedwright/channel' ];
const TEMPLATE = [ [ 'feedwright/channel' ] ];

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps( {
		className: 'feedwright-block feedwright-block-rss',
	} );

	const namespaces = Array.isArray( attributes.namespaces ) ? attributes.namespaces : [];
	const namespaceCount = namespaces.length;

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
