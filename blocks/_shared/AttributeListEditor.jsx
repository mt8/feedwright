/**
 * Row-based editor for the `attributes` array on the `feedwright/element`
 * block. Each row holds { name, valueMode: 'static' | 'binding', value }.
 */

import {
	Button,
	TextControl,
	RadioControl,
	Notice,
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import BindingInput from './BindingInput';

const XML_NAME_REGEX = /^[A-Za-z_][A-Za-z0-9._-]*(:[A-Za-z_][A-Za-z0-9._-]*)?$/;

function isValidXmlName( name ) {
	return typeof name === 'string' && XML_NAME_REGEX.test( name );
}

/**
 * @param {Object}   props
 * @param {Array}    props.value           Current attributes array.
 * @param {Function} props.onChange        Receives the next array.
 * @param {boolean}  [props.inItemContext] Forwarded to BindingInput so suggestions
 *                                         match the surrounding context.
 */
export default function AttributeListEditor( { value, onChange, inItemContext = false } ) {
	const rows = Array.isArray( value ) ? value : [];

	const update = ( index, patch ) => {
		const next = rows.map( ( row, i ) => ( i === index ? { ...row, ...patch } : row ) );
		onChange( next );
	};

	const remove = ( index ) => {
		onChange( rows.filter( ( _, i ) => i !== index ) );
	};

	const move = ( index, delta ) => {
		const target = index + delta;
		if ( target < 0 || target >= rows.length ) {
			return;
		}
		const next = rows.slice();
		[ next[ index ], next[ target ] ] = [ next[ target ], next[ index ] ];
		onChange( next );
	};

	const add = () => {
		onChange( [ ...rows, { name: '', valueMode: 'static', value: '' } ] );
	};

	return (
		<VStack spacing={ 4 }>
			<p style={ { margin: 0 } }>
				<strong>{ __( 'Attributes', 'feedwright' ) }</strong>
			</p>

			{ rows.length === 0 && (
				<p style={ { margin: 0, opacity: 0.6 } }>
					{ __( 'No attributes set.', 'feedwright' ) }
				</p>
			) }

			{ rows.map( ( row, index ) => {
				const nameValid = '' === row.name || isValidXmlName( row.name );
				return (
					<div
						key={ index }
						className="feedwright-attribute-row"
						style={ {
							border: '1px solid #ddd',
							borderRadius: 4,
							padding: 8,
							display: 'flex',
							flexDirection: 'column',
							gap: 8,
						} }
					>
						<HStack alignment="topLeft">
							<TextControl
								label={ __( 'Name', 'feedwright' ) }
								value={ row.name || '' }
								onChange={ ( next ) => update( index, { name: next } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						</HStack>
						{ ! nameValid && (
							<Notice status="error" isDismissible={ false }>
								{ __( 'Attribute name is not a valid XML Name.', 'feedwright' ) }
							</Notice>
						) }

						<RadioControl
							label={ __( 'Value mode', 'feedwright' ) }
							selected={ row.valueMode || 'static' }
							options={ [
								{ label: __( 'Static text', 'feedwright' ), value: 'static' },
								{ label: __( 'Binding expression', 'feedwright' ), value: 'binding' },
							] }
							onChange={ ( next ) => update( index, { valueMode: next } ) }
						/>

						{ 'binding' === row.valueMode ? (
							<BindingInput
								label={ __( 'Value (binding)', 'feedwright' ) }
								value={ row.value || '' }
								onChange={ ( next ) => update( index, { value: next } ) }
								inItemContext={ inItemContext }
							/>
						) : (
							<TextControl
								label={ __( 'Value', 'feedwright' ) }
								value={ row.value || '' }
								onChange={ ( next ) => update( index, { value: next } ) }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
						) }

						<HStack justify="space-between">
							<HStack spacing={ 1 }>
								<Button
									size="small"
									variant="tertiary"
									onClick={ () => move( index, -1 ) }
									disabled={ 0 === index }
								>
									{ __( 'Up', 'feedwright' ) }
								</Button>
								<Button
									size="small"
									variant="tertiary"
									onClick={ () => move( index, 1 ) }
									disabled={ index === rows.length - 1 }
								>
									{ __( 'Down', 'feedwright' ) }
								</Button>
							</HStack>
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
				{ __( '+ Add attribute', 'feedwright' ) }
			</Button>
		</VStack>
	);
}
