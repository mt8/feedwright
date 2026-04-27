/**
 * Row-based editor for `feedwright/rss` namespaces. Each row is { prefix, uri }.
 */

import {
	Button,
	TextControl,
	Notice,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const PREFIX_REGEX = /^[A-Za-z_][A-Za-z0-9._-]*$/;

function isValidPrefix( value ) {
	return typeof value === 'string' && PREFIX_REGEX.test( value );
}

/**
 * @param {Object}   props
 * @param {Array}    props.value    Array of { prefix, uri }.
 * @param {Function} props.onChange Receives the next array.
 */
export default function NamespaceEditor( { value, onChange } ) {
	const rows = Array.isArray( value ) ? value : [];

	const update = ( index, patch ) => {
		onChange( rows.map( ( row, i ) => ( i === index ? { ...row, ...patch } : row ) ) );
	};

	const remove = ( index ) => {
		onChange( rows.filter( ( _, i ) => i !== index ) );
	};

	const add = () => {
		onChange( [ ...rows, { prefix: '', uri: '' } ] );
	};

	return (
		<VStack spacing={ 4 }>
			<p style={ { margin: 0 } }>
				<strong>{ __( 'XML Namespaces', 'feedwright' ) }</strong>
			</p>
			<p style={ { margin: 0, opacity: 0.7 } }>
				{ __(
					'Declared on the <rss> element. Each prefix can be used as <prefix:tag> in elements and attributes.',
					'feedwright'
				) }
			</p>

			{ rows.length === 0 && (
				<p style={ { margin: 0, opacity: 0.6 } }>
					{ __( 'No namespaces declared.', 'feedwright' ) }
				</p>
			) }

			{ rows.map( ( row, index ) => {
				const prefixValid = '' === row.prefix || isValidPrefix( row.prefix );
				return (
					<div
						key={ index }
						style={ {
							border: '1px solid #ddd',
							borderRadius: 4,
							padding: 8,
							display: 'flex',
							flexDirection: 'column',
							gap: 8,
						} }
					>
						<HStack>
							<TextControl
								label={ __( 'Prefix', 'feedwright' ) }
								value={ row.prefix || '' }
								onChange={ ( next ) => update( index, { prefix: next } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<TextControl
								label={ __( 'URI', 'feedwright' ) }
								value={ row.uri || '' }
								onChange={ ( next ) => update( index, { uri: next } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</HStack>
						{ ! prefixValid && (
							<Notice status="error" isDismissible={ false }>
								{ __( 'Prefix must start with a letter and contain only letters, digits, dots, dashes, or underscores.', 'feedwright' ) }
							</Notice>
						) }
						<HStack justify="flex-end">
							<Button
								size="small"
								variant="link"
								isDestructive
								onClick={ () => remove( index ) }
							>
								{ __( 'Remove', 'feedwright' ) }
							</Button>
						</HStack>
					</div>
				);
			} ) }

			<Button variant="secondary" onClick={ add }>
				{ __( '+ Add namespace', 'feedwright' ) }
			</Button>
		</VStack>
	);
}
