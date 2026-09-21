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
		var link = event.target.closest( '[data-ucf-confirm]' );
		if ( link && ! window.confirm( link.getAttribute( 'data-ucf-confirm' ) ) ) {
			event.preventDefault();
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-ucf-copy]' );
		if ( ! button ) {
			return;
		}
		var source = one( button.getAttribute( 'data-ucf-copy' ) );
		var label = one( '[data-ucf-copy-label]', button );
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
		var list = one( '[data-ucf-list]' );
		if ( ! list ) {
			return;
		}
		var toolbar = one( '[data-ucf-toolbar]' );
		var samples = JSON.parse( list.getAttribute( 'data-samples' ) || '{}' );
		var rows = all( '[data-ucf-row]', list );
		var count = one( '[data-ucf-count]', list );
		var empty = one( '[data-ucf-empty]', list );
		var savebar = one( '[data-ucf-savebar]', list );
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
				var sample = one( '[data-ucf-sample]', row );
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
			if ( field.matches( '[data-ucf-size]' ) ) {
				list.style.setProperty( '--ucf-sample-size', field.value + 'px' );
				one( '[data-ucf-size-out]', toolbar ).textContent = field.value + 'px';
				return;
			}
			if ( field.matches( '[data-ucf-filter]' ) ) {
				state.query = field.value.trim().toLowerCase();
			} else if ( field.matches( '[data-ucf-preview-text]' ) ) {
				state.text = field.value;
			} else if ( field.matches( '[data-ucf-script]' ) ) {
				state.script = field.value;
			}
			render();
		}

		if ( toolbar ) {
			toolbar.addEventListener( 'input', onToolbar );
			toolbar.addEventListener( 'change', onToolbar );
		}
		list.addEventListener( 'change', function ( event ) {
			if ( event.target.matches( '.ucf-switch' ) ) {
				list.submit();
			}
		} );
	}

	function initForm() {
		var form = one( '[data-ucf-form]' );
		if ( ! form ) {
			return;
		}

		function currentSource() {
			var checked = one( 'input[name="ucf[source]"]:checked', form );
			return checked ? checked.value : 'cdn';
		}

		function toggleSections() {
			var source = currentSource();
			all( '[data-ucf-source]', form ).forEach( function ( section ) {
				var target = section.getAttribute( 'data-ucf-source' );
				section.hidden = 'all' !== target && target !== source;
			} );
		}

		function fill( field, value ) {
			if ( field && value && ( '' === field.value || '1' === field.getAttribute( 'data-ucf-auto' ) ) ) {
				field.value = value;
				field.setAttribute( 'data-ucf-auto', '1' );
			}
		}

		function applyPreset() {
			var select = one( '#ucf-preset', form );
			if ( ! select || 'cdn' !== currentSource() ) {
				return;
			}
			var option = select.options[ select.selectedIndex ];
			var allowed = ( option.getAttribute( 'data-weights' ) || '' ).split( ',' ).filter( Boolean );
			all( 'input[name="ucf[weights][]"]', form ).forEach( function ( box ) {
				var ok = ! allowed.length || -1 !== allowed.indexOf( box.value );
				box.disabled = ! ok;
				if ( ! ok ) {
					box.checked = false;
				}
			} );
			if ( option.value ) {
				fill( one( '#ucf-family', form ), option.getAttribute( 'data-family' ) );
				fill( one( '#ucf-name', form ), option.getAttribute( 'data-family' ) );
				fill( one( '#ucf-fallback', form ), option.getAttribute( 'data-fallback' ) );
			}
		}

		form.addEventListener( 'change', function ( event ) {
			if ( 'ucf[source]' === event.target.name ) {
				toggleSections();
				applyPreset();
			}
			if ( 'ucf-preset' === event.target.id ) {
				applyPreset();
			}
		} );

		all( '#ucf-family, #ucf-name, #ucf-fallback', form ).forEach( function ( field ) {
			field.addEventListener( 'input', function () {
				field.setAttribute( 'data-ucf-auto', '0' );
			} );
		} );

		var rows = one( '[data-ucf-rows]', form );
		var template = document.getElementById( 'ucf-row-template' );
		var next = rows ? rows.children.length : 0;

		form.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-ucf-add-row]' ) && rows && template ) {
				event.preventDefault();
				rows.insertAdjacentHTML( 'beforeend', template.innerHTML.replace( /__i__/g, String( next++ ) ) );
			}
			var remove = event.target.closest( '[data-ucf-remove-row]' );
			if ( remove ) {
				event.preventDefault();
				remove.closest( 'tr' ).remove();
			}
		} );

		toggleSections();
		applyPreset();
	}

	function initTester() {
		var tester = one( '[data-ucf-tester]' );
		if ( ! tester ) {
			return;
		}
		var samples = JSON.parse( tester.getAttribute( 'data-samples' ) || '{}' );
		var family = tester.getAttribute( 'data-family' );
		var weight = one( '[data-ucf-t-weight]', tester );
		var weightName = one( '[data-ucf-t-weight-name]', tester );
		var stops = weight.getAttribute( 'data-stops' ).split( ',' ).map( Number );
		var variable = '1' === weight.getAttribute( 'data-variable' );
		var italic = one( '[data-ucf-t-italic]', tester );
		var size = one( '[data-ucf-t-size]', tester );
		var lineHeight = one( '[data-ucf-t-lh]', tester );
		var canvas = one( '[data-ucf-t-canvas]', tester );
		var css = one( '[data-ucf-t-css]', tester );
		var script = one( '[data-ucf-t-script]', tester );
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
			all( '[data-ucf-t-text]', tester ).forEach( function ( element ) {
				element.textContent = texts[ element.getAttribute( 'data-ucf-t-text' ) ] || '';
				element.setAttribute( 'lang', 'fa' === state.script ? 'fa' : 'en' );
				if ( ! canvas.contains( element ) ) {
					element.setAttribute( 'dir', 'fa' === state.script ? 'rtl' : 'ltr' );
				}
			} );
		}

		function render() {
			all( '[data-ucf-t-apply]', tester ).forEach( function ( element ) {
				element.style.fontWeight = state.weight;
				element.style.fontStyle = state.italic ? 'italic' : 'normal';
			} );
			canvas.style.setProperty( '--ucf-t-size', state.size + 'px' );
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
			all( '[data-ucf-t-out]', tester ).forEach( function ( output ) {
				var key = output.getAttribute( 'data-ucf-t-out' );
				output.textContent = 'size' === key ? state.size + 'px' : ( 'lh' === key ? state.lineHeight.toFixed( 2 ) : state.weight );
			} );
			all( '[data-ucf-t-italic-btn]', tester ).forEach( function ( button ) {
				button.setAttribute( 'aria-pressed', String( state.italic ) );
			} );
			all( '[data-ucf-t-align]', tester ).forEach( function ( button ) {
				button.setAttribute( 'aria-pressed', String( button.getAttribute( 'data-ucf-t-align' ) === state.align ) );
			} );
			all( '[data-ucf-t-dir]', tester ).forEach( function ( button ) {
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
			if ( button.hasAttribute( 'data-ucf-t-step' ) ) {
				set( 'size', Math.min( 120, Math.max( 12, state.size + Number( button.getAttribute( 'data-ucf-t-step' ) ) ) ) );
			} else if ( button.hasAttribute( 'data-ucf-t-align' ) ) {
				set( 'align', button.getAttribute( 'data-ucf-t-align' ) );
			} else if ( button.hasAttribute( 'data-ucf-t-dir' ) ) {
				set( 'dir', 'rtl' === state.dir ? 'ltr' : 'rtl' );
			} else if ( button.hasAttribute( 'data-ucf-t-italic-btn' ) ) {
				set( 'italic', ! state.italic );
			} else if ( button.hasAttribute( 'data-ucf-t-reset' ) ) {
				reset();
			}
		} );

		reset();
	}
}() );
