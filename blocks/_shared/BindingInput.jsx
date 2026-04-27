/**
 * Text input that pops a suggestion menu while editing a `{{...}}` binding.
 *
 * Three modes are detected from the cursor position:
 *  - "binding":   cursor is between `{{` and the first `:` / `|`. Suggests
 *                 provider rows from the catalogue.
 *  - "modifier":  cursor is between the first `:` and the next `|` / `}}`.
 *                 Suggests modifier values (date formats, image sizes, term
 *                 separators).
 *  - "processor": cursor is between `|` and the next `:` (or `}}`). Suggests
 *                 built-in binding processors (truncate / allow_tags / ...).
 */

import { useState, useRef, useEffect } from '@wordpress/element';
import { TextControl, Popover, MenuItem, MenuGroup } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useBindingSuggestions from './use-binding-suggestions';
import modifierSuggestions from './modifier-suggestions';
import processorSuggestions from './processor-suggestions';

/**
 * Determine whether the cursor sits inside an open `{{...` binding. Returns
 * meta about the active edit when so, or null otherwise.
 *
 * @param {string} value Current input value.
 * @param {number} caret Current cursor position.
 */
function detectActiveBinding( value, caret ) {
	const before = value.slice( 0, caret );
	const open = before.lastIndexOf( '{{' );
	if ( open < 0 ) {
		return null;
	}
	const between = before.slice( open + 2 );
	if ( between.includes( '}}' ) ) {
		return null;
	}
	if ( /[\n\r]/.test( between ) ) {
		return null;
	}

	// If the user has typed at least one `|`, we're past the modifier area.
	const lastPipe = between.lastIndexOf( '|' );
	if ( lastPipe >= 0 ) {
		const partial = between.slice( lastPipe + 1 );
		// The `:` separates processor name from its arg; once typed, suppress
		// the name suggestions (the user is filling the arg).
		const inArg = partial.includes( ':' );
		return {
			start: open,
			mode: 'processor',
			partial,
			inArg,
		};
	}

	const colon = between.indexOf( ':' );
	if ( colon >= 0 ) {
		return {
			start: open,
			mode: 'modifier',
			stem: between.slice( 0, colon ),
			modifier: between.slice( colon + 1 ),
		};
	}
	return {
		start: open,
		mode: 'binding',
		query: between,
	};
}

/**
 * @param {Object}   props
 * @param {string}   props.label
 * @param {string}   props.value
 * @param {Function} props.onChange
 * @param {string}   [props.help]
 * @param {string}   [props.placeholder]
 * @param {boolean}  [props.inItemContext] True when rendered inside an item template; controls
 *                                         which provider rows are suggested.
 */
export default function BindingInput( { label, value, onChange, help, placeholder, inItemContext = false } ) {
	const [ caret, setCaret ] = useState( 0 );
	const inputRef = useRef( null );

	const active = detectActiveBinding( value || '', caret );

	const bindingSuggestions = useBindingSuggestions( {
		inItemContext,
		query: active && 'binding' === active.mode ? active.query : '',
	} );
	const modifierRows = active && 'modifier' === active.mode
		? modifierSuggestions( active.stem, active.modifier )
		: [];
	const processorRows = active && 'processor' === active.mode && ! active.inArg
		? processorSuggestions( active.partial )
		: [];

	const isBindingOpen = !! active && 'binding' === active.mode && bindingSuggestions.length > 0;
	const isModifierOpen = !! active && 'modifier' === active.mode && modifierRows.length > 0;
	const isProcessorOpen = !! active && 'processor' === active.mode && processorRows.length > 0;
	const isOpen = isBindingOpen || isModifierOpen || isProcessorOpen;

	useEffect( () => {
		if ( ! inputRef.current ) {
			return;
		}
		const el = inputRef.current.querySelector( 'input' );
		if ( ! el ) {
			return;
		}
		const handler = ( e ) => setCaret( e.target.selectionStart ?? 0 );
		el.addEventListener( 'click', handler );
		el.addEventListener( 'keyup', handler );
		return () => {
			el.removeEventListener( 'click', handler );
			el.removeEventListener( 'keyup', handler );
		};
	}, [] );

	const handleChange = ( next ) => {
		onChange( next );
		const el = inputRef.current?.querySelector( 'input' );
		if ( el ) {
			setCaret( el.selectionStart ?? next.length );
		}
	};

	const focusAtPosition = ( pos ) => {
		setTimeout( () => {
			const el = inputRef.current?.querySelector( 'input' );
			if ( el ) {
				el.focus();
				el.setSelectionRange( pos, pos );
				setCaret( pos );
			}
		}, 0 );
	};

	const insertBinding = ( expression ) => {
		if ( ! active || 'binding' !== active.mode ) {
			onChange( ( value || '' ) + '{{' + expression + '}}' );
			return;
		}
		const before = value.slice( 0, active.start );
		const after = value.slice( active.start + 2 + active.query.length );
		const inserted = before + '{{' + expression + '}}' + after;
		onChange( inserted );
		focusAtPosition( ( before + '{{' + expression + '}}' ).length );
	};

	/**
	 * Insert text at the cursor, auto-closing the binding with `}}` if there
	 * is no closer ahead. Returns the new caret position.
	 *
	 * @param {string} text Content to insert at the cursor.
	 */
	const insertAtCaretInsideBinding = ( text ) => {
		const beforeCaret = ( value || '' ).slice( 0, caret );
		const afterCaret = ( value || '' ).slice( caret );
		const isUnclosed = afterCaret.indexOf( '}}' ) === -1;
		const insertion = text + ( isUnclosed ? '}}' : '' );
		onChange( beforeCaret + insertion + afterCaret );
		focusAtPosition( caret + text.length );
	};

	const insertModifier = ( modifierValue ) => {
		if ( ! active || 'modifier' !== active.mode ) {
			return;
		}
		insertAtCaretInsideBinding( modifierValue );
	};

	const insertProcessor = ( proc ) => {
		if ( ! active || 'processor' !== active.mode ) {
			return;
		}
		// Replace the partial typed after the last `|` with the picked
		// processor name (and a trailing `:` when it expects an arg).
		const beforeCaret = ( value || '' ).slice( 0, caret );
		const afterCaret = ( value || '' ).slice( caret );
		const partialLen = active.partial.length;
		const beforePartial = beforeCaret.slice( 0, beforeCaret.length - partialLen );
		const text = proc.name + ( proc.takesArg ? ':' : '' );
		const isUnclosed = afterCaret.indexOf( '}}' ) === -1;
		const insertion = text + ( isUnclosed ? '}}' : '' );
		onChange( beforePartial + insertion + afterCaret );
		focusAtPosition( beforePartial.length + text.length );
	};

	return (
		<div className="feedwright-binding-input" ref={ inputRef }>
			<TextControl
				label={ label }
				value={ value || '' }
				onChange={ handleChange }
				help={ help }
				placeholder={ placeholder || __( 'Type {{ to insert a binding', 'feedwright' ) }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			{ isOpen && (
				<Popover position="bottom left" focusOnMount={ false }>
					{ isBindingOpen && (
						<MenuGroup label={ __( 'Binding suggestions', 'feedwright' ) }>
							{ bindingSuggestions.map( ( row ) => (
								<MenuItem
									key={ row.expression }
									onClick={ () => insertBinding( row.expression ) }
								>
									<code>{ row.expression }</code>
									<span style={ { marginLeft: 8, opacity: 0.7 } }>{ row.label }</span>
								</MenuItem>
							) ) }
						</MenuGroup>
					) }
					{ isModifierOpen && (
						<MenuGroup label={ __( 'Format suggestions', 'feedwright' ) }>
							{ modifierRows.map( ( row ) => (
								<MenuItem
									key={ row.value }
									onClick={ () => insertModifier( row.value ) }
								>
									<code>{ row.value }</code>
									<span style={ { marginLeft: 8, opacity: 0.7 } }>{ row.label }</span>
								</MenuItem>
							) ) }
						</MenuGroup>
					) }
					{ isProcessorOpen && (
						<MenuGroup label={ __( 'Processor suggestions', 'feedwright' ) }>
							{ processorRows.map( ( row ) => (
								<MenuItem
									key={ row.name }
									onClick={ () => insertProcessor( row ) }
								>
									<code>
										{ row.name }
										{ row.takesArg ? ':' + ( row.argHint || 'arg' ) : '' }
									</code>
									<span style={ { marginLeft: 8, opacity: 0.7 } }>{ row.label }</span>
								</MenuItem>
							) ) }
						</MenuGroup>
					) }
				</Popover>
			) }
		</div>
	);
}
