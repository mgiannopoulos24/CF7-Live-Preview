/**
 * CF7 Live Preview — preview frame.
 *
 * Receives rendered form HTML from the admin screen, swaps it in, re-runs
 * Contact Form 7's initialisation, and reports its height back so the
 * parent can size the iframe.
 *
 * Messages are accepted only from the parent window on this same origin:
 * the HTML is inserted unescaped, so anything else could inject markup.
 *
 * Submitting goes through CF7's own script, which the server has pointed at
 * cf7lp/v1/preview: the submission is validated and answered like a real
 * one, but never stored or mailed. Each form carries hidden copies of the
 * editor's template, locale and messages so the server can rebuild it.
 */
( function () {
	'use strict';

	const root = document.getElementById( 'cf7lp-root' );

	if ( ! root || window.parent === window ) {
		return;
	}

	const config = window.cf7lpFrame || { validate: false, fields: {} };

	const post = ( message ) => {
		window.parent.postMessage( message, window.location.origin );
	};

	const initForms = () => {
		root.querySelectorAll( '.wpcf7 > form' ).forEach( ( form ) => {
			if ( typeof window.wpcf7?.init === 'function' ) {
				window.wpcf7.init( form );
				form.closest( '.wpcf7' ).classList.replace( 'no-js', 'js' );
			}

			initAddons( form );
		} );
	};

	const hidden = ( name, value ) => {
		// A textarea keeps newlines, which a hidden input may not.
		const field = document.createElement( 'textarea' );
		field.name = name;
		field.value = value;
		field.hidden = true;
		field.setAttribute( 'aria-hidden', 'true' );
		field.tabIndex = -1;
		return field;
	};

	const addContext = ( context ) => {
		const { fields } = config;

		root.querySelectorAll( '.wpcf7 > form' ).forEach( ( form ) => {
			form.append( hidden( fields.form, context.form ) );
			form.append( hidden( fields.locale, context.locale || '' ) );

			Object.entries( context.messages || {} ).forEach( ( [ key, value ] ) => {
				form.append( hidden( `${ fields.messages }[${ key }]`, String( value ) ) );
			} );
		} );
	};

	// Add-ons that set forms up once at page load, and have an API for forms
	// added later. Run after CF7's own initialisation.
	const initAddons = ( form ) => {
		// Conditional Fields for CF7.
		if ( typeof window.wpcf7cf?.initForm === 'function' && window.jQuery ) {
			window.wpcf7cf.initForm( window.jQuery( form ) );
		}
	};

	const render = ( html, context ) => {
		root.innerHTML = html;

		if ( config.validate && context && typeof context.form === 'string' ) {
			addContext( context );
		}

		initForms();

		document.dispatchEvent(
			new CustomEvent( 'cf7lp_after_render', { detail: { root } } )
		);
	};

	window.addEventListener( 'message', ( event ) => {
		if (
			event.origin !== window.location.origin ||
			event.source !== window.parent ||
			typeof event.data !== 'object' ||
			event.data === null
		) {
			return;
		}

		if ( event.data.type === 'cf7lp:render' && typeof event.data.html === 'string' ) {
			render( event.data.html, event.data.context );
		}
	} );

	// Without validation support, or without CF7's script to handle it, a
	// submit would go nowhere useful (the form's action is a REST URL), so
	// it is stopped. Capture phase on window runs before CF7's listener.
	window.addEventListener(
		'submit',
		( event ) => {
			const handledByCf7 = config.validate && typeof window.wpcf7?.submit === 'function';

			if ( root.contains( event.target ) && ! handledByCf7 ) {
				event.preventDefault();
				event.stopImmediatePropagation();
			}
		},
		true
	);

	// Measure the content rather than the document: themes often give
	// html/body a min-height of 100vh, which would never let the frame shrink.
	const content = root.closest( 'main' ) || root;
	let lastHeight = 0;

	const reportHeight = () => {
		const style = window.getComputedStyle( document.body );
		const height = Math.ceil(
			content.getBoundingClientRect().bottom +
				window.scrollY +
				parseFloat( style.marginBottom || 0 ) +
				parseFloat( style.paddingBottom || 0 )
		);

		if ( height !== lastHeight ) {
			lastHeight = height;
			post( { type: 'cf7lp:height', height } );
		}
	};

	if ( typeof window.ResizeObserver === 'function' ) {
		new window.ResizeObserver( reportHeight ).observe( content );
	} else {
		window.addEventListener( 'resize', reportHeight );
		document.addEventListener( 'cf7lp_after_render', reportHeight );
	}

	post( { type: 'cf7lp:ready' } );
} )();
