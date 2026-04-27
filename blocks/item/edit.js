import { __ } from '@wordpress/i18n';
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const ALLOWED_CHILDREN = [ 'feedwright/element', 'feedwright/comment' ];

export default function Edit() {
	const blockProps = useBlockProps( {
		className: 'feedwright-block feedwright-block-item',
	} );
	return (
		<div { ...blockProps }>
			<header className="feedwright-block__header">
				<strong>{ __( 'Item Template', 'feedwright' ) }</strong>
			</header>
			<InnerBlocks
				allowedBlocks={ ALLOWED_CHILDREN }
				templateLock={ false }
			/>
		</div>
	);
}
