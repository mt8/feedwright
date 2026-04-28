import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';
import metadata from './block.json';
import Edit from './edit';
import variations from './variations';

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => <InnerBlocks.Content />,
	__experimentalLabel: ( attributes, { context } ) => {
		if ( context !== 'list-view' ) {
			return undefined;
		}
		const tag =
			typeof attributes?.tagName === 'string'
				? attributes.tagName.trim()
				: '';
		return tag || undefined;
	},
} );

variations.forEach( ( variation ) => {
	registerBlockVariation( metadata.name, variation );
} );
