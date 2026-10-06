/* PFont admin. Progressive enhancement only: every form works without JavaScript. */
( function () {
	'use strict';

	var RTL_TEXT = /[\u0590-\u08ff\ufb1d-\ufdff\ufe70-\ufeff]/;

	function one( selector, root ) {
		return ( root || document ).querySelector( selector );
	}

	function all( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	document.addEventListener( 'click', function ( event ) {
		var link = event.target.closest( '[data-pfont-confirm]' );
		if ( link && ! window.confirm( link.getAttribute( 'data-pfont-confirm' ) ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-pfont-copy]' );
		if ( ! button ) {
			return;
		}
		var source = one( button.getAttribute( 'data-pfont-copy' ) );
		var label = one( '[data-pfont-copy-label]', button );
		if ( ! source || ! label ) {
			return;
		}
		var original = label.textContent;
		var done = function () {
			label.textContent = button.getAttribute( 'data-copied' );
			window.setTimeout( function () {
				label.textContent = original;
			}, 1600 );
		};
		var fallback = function () {
			var range = document.createRange();
			var selection = window.getSelection();
			range.selectNodeContents( source );
			selection.removeAllRanges();
			selection.addRange( range );
			try {
				if ( document.execCommand( 'copy' ) ) {
					done();
				}
			} catch ( error ) {
				/* The text stays selected so it can be copied by hand. */
			}
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			/* Permission can be refused (policies, http admin); fall back instead of failing silently. */
			navigator.clipboard.writeText( source.textContent ).then( done, fallback );
			return;
		}
		fallback();
	} );

	if ( window.location.hash ) {
		var linked = document.getElementById( window.location.hash.slice( 1 ) );
		if ( linked && 'DETAILS' === linked.tagName ) {
			linked.open = true;
		}
	}

	initLibrary();
	initForm();
	initTester();

	function initLibrary() {
		var list = one( '[data-pfont-list]' );
		if ( ! list ) {
			return;
		}
		var toolbar = one( '[data-pfont-toolbar]' );
		var samples = JSON.parse( list.getAttribute( 'data-samples' ) || '{}' );
		var rows = all( '[data-pfont-row]', list );
		var count = one( '[data-pfont-count]', list );
		var empty = one( '[data-pfont-empty]', list );
		var savebar = one( '[data-pfont-savebar]', list );
		var state = { query: '', text: '', script: 'auto' };

		if ( toolbar ) {
			toolbar.hidden = false;
		}
		if ( savebar ) {
			savebar.hidden = true;
		}

		function render() {
			var shown = 0;
			rows.forEach( function ( row ) {
				var visible = ! state.query || -1 !== row.getAttribute( 'data-name' ).indexOf( state.query );
				var sample = one( '[data-pfont-sample]', row );
				var script = 'auto' === state.script ? sample.getAttribute( 'data-script' ) : state.script;
				var text = state.text || samples[ script ] || '';
				row.hidden = ! visible;
				shown += visible ? 1 : 0;
				sample.textContent = text;
				sample.setAttribute( 'dir', RTL_TEXT.test( text ) ? 'rtl' : 'ltr' );
			} );
			if ( count ) {
				var template = list.getAttribute( 1 === rows.length ? 'data-one' : 'data-many' );
				count.textContent = template.replace( '%1$s', shown ).replace( '%2$s', rows.length );
			}
			if ( empty ) {
				empty.hidden = shown > 0;
			}
		}

		function onToolbar( event ) {
			var field = event.target;
			if ( field.matches( '[data-pfont-size]' ) ) {
				list.style.setProperty( '--pfont-sample-size', field.value + 'px' );
				one( '[data-pfont-size-out]', toolbar ).textContent = field.value + 'px';
				return;
			}
			if ( field.matches( '[data-pfont-filter]' ) ) {
				state.query = field.value.trim().toLowerCase();
			} else if ( field.matches( '[data-pfont-preview-text]' ) ) {
				state.text = field.value;
			} else if ( field.matches( '[data-pfont-script]' ) ) {
				state.script = field.value;
			}
			render();
		}

		if ( toolbar ) {
			toolbar.addEventListener( 'input', onToolbar );
			toolbar.addEventListener( 'change', onToolbar );
		}
		list.addEventListener( 'change', function ( event ) {
			if ( event.target.matches( '.pfont-switch' ) ) {
				list.submit();
			}
		} );
	}

	function initForm() {
		var form = one( '[data-pfont-form]' );
		if ( ! form ) {
			return;
		}

		function currentSource() {
			var checked = one( 'input[name="pfont[source]"]:checked', form );
			return checked ? checked.value : 'cdn';
		}

		function toggleSections() {
			var source = currentSource();
			all( '[data-pfont-source]', form ).forEach( function ( section ) {
				var target = section.getAttribute( 'data-pfont-source' );
				section.hidden = 'all' !== target && target !== source;
			} );
		}

		function fill( field, value ) {
			if ( field && value && ( '' === field.value || '1' === field.getAttribute( 'data-pfont-auto' ) ) ) {
				field.value = value;
				field.setAttribute( 'data-pfont-auto', '1' );
			}
		}

		function applyPreset() {
			var select = one( '#pfont-preset', form );
			if ( ! select || 'cdn' !== currentSource() ) {
				return;
			}
			var option = select.options[ select.selectedIndex ];
			var allowed = ( option.getAttribute( 'data-weights' ) || '' ).split( ',' ).filter( Boolean );
			all( 'input[name="pfont[weights][]"]', form ).forEach( function ( box ) {
				var ok = ! allowed.length || -1 !== allowed.indexOf( box.value );
				box.disabled = ! ok;
				if ( ! ok ) {
					box.checked = false;
				}
			} );
			if ( option.value ) {
				fill( one( '#pfont-family', form ), option.getAttribute( 'data-family' ) );
				fill( one( '#pfont-name', form ), option.getAttribute( 'data-family' ) );
				fill( one( '#pfont-fallback', form ), option.getAttribute( 'data-fallback' ) );
			}
		}

		form.addEventListener( 'change', function ( event ) {
			if ( 'pfont[source]' === event.target.name ) {
				toggleSections();
				applyPreset();
			}
			if ( 'pfont-preset' === event.target.id ) {
				applyPreset();
			}
		} );

		all( '#pfont-family, #pfont-name, #pfont-fallback', form ).forEach( function ( field ) {
			field.addEventListener( 'input', function () {
				field.setAttribute( 'data-pfont-auto', '0' );
			} );
		} );

		var rows = one( '[data-pfont-rows]', form );
		var template = document.getElementById( 'pfont-row-template' );
		var next = rows ? rows.children.length : 0;

		form.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-pfont-add-row]' ) && rows && template ) {
				event.preventDefault();
				rows.insertAdjacentHTML( 'beforeend', template.innerHTML.replace( /__i__/g, String( next++ ) ) );
			}
			var remove = event.target.closest( '[data-pfont-remove-row]' );
			if ( remove ) {
				event.preventDefault();
				remove.closest( 'tr' ).remove();
			}
		} );

		toggleSections();
		applyPreset();
	}

	function initTester() {
		var tester = one( '[data-pfont-tester]' );
		if ( ! tester ) {
			return;
		}
		var samples = JSON.parse( tester.getAttribute( 'data-samples' ) || '{}' );
		var family = tester.getAttribute( 'data-family' );
		var weight = one( '[data-pfont-t-weight]', tester );
		var weightName = one( '[data-pfont-t-weight-name]', tester );
		var stops = weight.getAttribute( 'data-stops' ).split( ',' ).map( Number );
		var variable = '1' === weight.getAttribute( 'data-variable' );
		var italic = one( '[data-pfont-t-italic]', tester );
		var size = one( '[data-pfont-t-size]', tester );
		var lineHeight = one( '[data-pfont-t-lh]', tester );
		var canvas = one( '[data-pfont-t-canvas]', tester );
		var css = one( '[data-pfont-t-css]', tester );
		var script = one( '[data-pfont-t-script]', tester );
		var defaults = {
			weight: Number( tester.getAttribute( 'data-weight' ) ),
			italic: false,
			size: Number( size.value ),
			lineHeight: Number( lineHeight.value ),
			align: 'start',
			script: script ? script.value : 'latin',
		};
		var state = {};

		function fillText() {
			var texts = samples[ state.script ] || {};
			all( '[data-pfont-t-text]', tester ).forEach( function ( element ) {
				element.textContent = texts[ element.getAttribute( 'data-pfont-t-text' ) ] || '';
				element.setAttribute( 'lang', 'fa' === state.script ? 'fa' : 'en' );
				if ( ! canvas.contains( element ) ) {
					element.setAttribute( 'dir', 'fa' === state.script ? 'rtl' : 'ltr' );
				}
			} );
		}

		function render() {
			all( '[data-pfont-t-apply]', tester ).forEach( function ( element ) {
				element.style.fontWeight = state.weight;
				element.style.fontStyle = state.italic ? 'italic' : 'normal';
			} );
			canvas.style.setProperty( '--pfont-t-size', state.size + 'px' );
			canvas.style.lineHeight = state.lineHeight;
			canvas.style.textAlign = state.align;
			canvas.setAttribute( 'dir', state.dir );
			weight.value = variable ? state.weight : Math.max( 0, stops.indexOf( state.weight ) );
			weightName.value = String( Math.round( state.weight / 100 ) * 100 );
			size.value = state.size;
			lineHeight.value = state.lineHeight;
			if ( italic ) {
				italic.checked = state.italic;
			}
			all( '[data-pfont-t-out]', tester ).forEach( function ( output ) {
				var key = output.getAttribute( 'data-pfont-t-out' );
				output.textContent = 'size' === key ? state.size + 'px' : ( 'lh' === key ? state.lineHeight.toFixed( 2 ) : state.weight );
			} );
			all( '[data-pfont-t-italic-btn]', tester ).forEach( function ( button ) {
				button.setAttribute( 'aria-pressed', String( state.italic ) );
			} );
			all( '[data-pfont-t-align]', tester ).forEach( function ( button ) {
				button.setAttribute( 'aria-pressed', String( button.getAttribute( 'data-pfont-t-align' ) === state.align ) );
			} );
			all( '[data-pfont-t-dir]', tester ).forEach( function ( button ) {
				button.setAttribute( 'aria-pressed', String( 'rtl' === state.dir ) );
			} );
			css.textContent = 'font-family: ' + family + '; font-weight: ' + state.weight + ';' + ( state.italic ? ' font-style: italic;' : '' ) + ' font-size: ' + state.size + 'px; line-height: ' + state.lineHeight + ';';
		}

		function set( key, value ) {
			state[ key ] = value;
			render();
		}

		function reset() {
			state = Object.assign( {}, defaults );
			state.dir = 'fa' === state.script ? 'rtl' : 'ltr';
			if ( script ) {
				script.value = state.script;
			}
			fillText();
			render();
		}

		weight.addEventListener( 'input', function () {
			set( 'weight', variable ? Number( weight.value ) : stops[ Number( weight.value ) ] );
		} );
		weightName.addEventListener( 'change', function () {
			set( 'weight', Number( weightName.value ) );
		} );
		size.addEventListener( 'input', function () {
			set( 'size', Number( size.value ) );
		} );
		lineHeight.addEventListener( 'input', function () {
			set( 'lineHeight', Number( lineHeight.value ) );
		} );
		if ( italic ) {
			italic.addEventListener( 'change', function () {
				set( 'italic', italic.checked );
			} );
		}
		if ( script ) {
			script.addEventListener( 'change', function () {
				state.script = script.value;
				state.dir = 'fa' === state.script ? 'rtl' : 'ltr';
				fillText();
				render();
			} );
		}
		tester.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( 'button' );
			if ( ! button || button.disabled ) {
				return;
			}
			if ( button.hasAttribute( 'data-pfont-t-step' ) ) {
				set( 'size', Math.min( 120, Math.max( 12, state.size + Number( button.getAttribute( 'data-pfont-t-step' ) ) ) ) );
			} else if ( button.hasAttribute( 'data-pfont-t-align' ) ) {
				set( 'align', button.getAttribute( 'data-pfont-t-align' ) );
			} else if ( button.hasAttribute( 'data-pfont-t-dir' ) ) {
				set( 'dir', 'rtl' === state.dir ? 'ltr' : 'rtl' );
			} else if ( button.hasAttribute( 'data-pfont-t-italic-btn' ) ) {
				set( 'italic', ! state.italic );
			} else if ( button.hasAttribute( 'data-pfont-t-reset' ) ) {
				reset();
			}
		} );

		reset();
	}
}() );
