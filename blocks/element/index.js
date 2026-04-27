import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import { registerPlugin } from '@wordpress/plugins';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import variations from './variations';
import XmlPreviewPanel from '../_shared/XmlPreviewPanel';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
} );

variations.forEach( ( variation ) => {
	registerBlockVariation( metadata.name, variation );
} );

registerPlugin( 'feedwright-xml-preview', {
	render: XmlPreviewPanel,
} );
