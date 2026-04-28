import { __ } from '@wordpress/i18n';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const ALLOWED_CHILDREN = [
	'feedwright/element',
	'feedwright/item-query',
	'feedwright/when',
	'feedwright/comment',
];

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'feedwright-block feedwright-block-channel',
	} );
	return (
		<div { ...blockProps }>
			<header className="feedwright-block__header">
				<strong>&lt;channel&gt;</strong>
			</header>
			<InnerBlocks
				allowedBlocks={ ALLOWED_CHILDREN }
				templateLock={ false }
			/>
		</div>
	);
}
