/**
 * CF7 Live Preview — preview modals on the Contact Form 7 editor.
 *
 * "Preview form" renders textarea#wpcf7-form through cf7lp/v1/render and
 * shows it in an iframe that loads the site's front end, so the theme's
 * styles apply. "Preview email" composes the Mail tab's template through
 * cf7lp/v1/mail with sample values; nothing is ever sent.
 *
 * The modals live on <body>, outside CF7's <form>: inside it, Enter in a
 * sample field would save the form, and CF7 would count edits to the sample
 * fields as unsaved changes.
 */
( function () {
	'use strict';

	const config = window.cf7lpAdmin;
	const { __ } = window.wp.i18n;
	const apiFetch = window.wp.apiFetch;

	const textarea = document.querySelector( 'textarea#wpcf7-form' );
	const formPanel = document.getElementById( 'form-panel' );
	const mailPanel = document.getElementById( 'mail-panel' );

	if ( ! config || ! textarea || ! formPanel ) {
		return;
	}

	const DEBOUNCE_MS = 400;
	const STORAGE_KEY = 'cf7lp-preferences';
	const WIDTHS = {
		mobile: { label: __( 'Mobile', 'cf7-live-preview' ), icon: 'smartphone', width: 375 },
		tablet: { label: __( 'Tablet', 'cf7-live-preview' ), icon: 'tablet', width: 768 },
		// Fills the modal.
		full: { label: __( 'Full', 'cf7-live-preview' ), icon: 'desktop', width: null },
	};

	/*
	 * Helpers
	 */

	const preferences = ( () => {
		// Settings > CF7 Live Preview, until the user picks a width.
		const defaults = { width: config.defaultWidth || 'full' };

		try {
			const stored = JSON.parse( window.localStorage.getItem( STORAGE_KEY ) );
			return { ...defaults, ...( stored || {} ) };
		} catch ( e ) {
			return defaults;
		}
	} )();

	const savePreferences = () => {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( preferences ) );
		} catch ( e ) {
			// Storage unavailable; the choice just isn't remembered.
		}
	};

	const el = ( tag, attrs = {}, children = [] ) => {
		const node = document.createElement( tag );

		Object.entries( attrs ).forEach( ( [ name, value ] ) => {
			if ( name === 'text' ) {
				node.textContent = value;
			} else if ( value !== false && value !== null && value !== undefined ) {
				node.setAttribute( name, value === true ? '' : value );
			}
		} );

		children.forEach( ( child ) => child !== null && node.append( child ) );

		return node;
	};

	// Labels sit in a span, not a bare text node, so they get the icons'
	// line-height instead of the button's and both centre on one axis.
	const labelSpan = ( text ) => el( 'span', { class: 'cf7lp-label', text } );

	const debounce = ( fn, wait ) => {
		let timer = null;

		const debounced = ( ...args ) => {
			window.clearTimeout( timer );
			timer = window.setTimeout( () => fn( ...args ), wait );
		};
		debounced.cancel = () => window.clearTimeout( timer );

		return debounced;
	};

	/**
	 * Fetch that drops the previous request when a newer one starts.
	 *
	 * @return {(options: object) => Promise<object|undefined>} Resolves
	 *         undefined for a request that was superseded.
	 */
	const latestRequest = () => {
		let controller = null;

		return async ( options ) => {
			if ( controller ) {
				controller.abort();
			}

			controller = new window.AbortController();

			try {
				return await apiFetch( { ...options, signal: controller.signal } );
			} catch ( error ) {
				if ( error?.name === 'AbortError' ) {
					return undefined;
				}
				throw error;
			}
		};
	};

	const locale = () => document.getElementById( 'wpcf7-locale' )?.value || '';

	// Unsaved settings of add-ons that the form depends on; see
	// CF7_Live_Preview_Compat. Only keys whose editor field exists are sent.
	const addonContext = () => {
		const context = {};
		const conditions = document.getElementById( 'wpcf7cf-settings-text' );

		// Conditional Fields' rule builder writes this textarea on "change";
		// sync it now in case a rule field still has focus.
		if ( conditions && ! document.getElementById( 'wpcf7cf-text-only-checkbox' )?.checked ) {
			window.wpcf7cf?.copyFieldsToText?.();
		}

		if ( conditions ) {
			context.cf_conditions = conditions.value;
		}

		return context;
	};

	// The Messages tab, as { key: text }; the preview shows these on submit.
	const messages = () => {
		const result = {};

		document.querySelectorAll( '[name^="wpcf7-messages["]' ).forEach( ( field ) => {
			const key = /^wpcf7-messages\[([a-z0-9_]+)\]$/.exec( field.name )?.[ 1 ];

			if ( key ) {
				result[ key ] = field.value;
			}
		} );

		return result;
	};
	const formTitle = () => document.getElementById( 'title' )?.value || '';

	/*
	 * Pill switcher: a segmented control whose highlight slides to the
	 * selected item.
	 */

	const createPill = ( { label, items, selected, onChange } ) => {
		const indicator = el( 'span', { class: 'cf7lp-pill-indicator', 'aria-hidden': 'true' } );
		const buttons = items.map( ( item ) =>
			el(
				'button',
				{
					type: 'button',
					'data-value': item.value,
					'aria-pressed': 'false',
				},
				[
					item.icon
						? el( 'span', {
								class: `dashicons dashicons-${ item.icon }`,
								'aria-hidden': 'true',
						  } )
						: null,
					labelSpan( item.label ),
				]
			)
		);
		const node = el( 'div', { class: 'cf7lp-pill', role: 'group', 'aria-label': label }, [
			indicator,
			...buttons,
		] );

		let value = selected;

		const place = () => {
			const button = buttons.find( ( b ) => b.dataset.value === value );

			if ( ! button || ! button.offsetWidth ) {
				return;
			}

			indicator.style.width = `${ button.offsetWidth }px`;
			indicator.style.transform = `translateX(${ button.offsetLeft }px)`;

			// Animate changes, not the first placement after becoming visible.
			if ( ! node.classList.contains( 'is-animated' ) ) {
				window.requestAnimationFrame( () => node.classList.add( 'is-animated' ) );
			}
		};

		const set = ( next ) => {
			value = next;
			buttons.forEach( ( b ) => b.setAttribute( 'aria-pressed', String( b.dataset.value === value ) ) );
			place();
		};

		buttons.forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				// aria-disabled rather than disabled, so the button stays
				// focusable and its reason (title) can be read out.
				if ( button.getAttribute( 'aria-disabled' ) === 'true' ) {
					return;
				}

				if ( button.dataset.value !== value ) {
					set( button.dataset.value );
					onChange( value );
				}
			} );
		} );

		if ( typeof window.ResizeObserver === 'function' ) {
			// Also catches the modal opening: closed, everything measures 0.
			new window.ResizeObserver( place ).observe( node );
		}

		set( value );

		return { node, set, buttons };
	};

	/*
	 * Modal shell
	 */

	const createModal = ( { title, className, actions = [] } ) => {
		const closeButton = el(
			'button',
			{
				type: 'button',
				class: 'cf7lp-modal-close',
				'aria-label': __( 'Close', 'cf7-live-preview' ),
				// Focused on open, rather than the first action.
				autofocus: true,
			},
			[ el( 'span', { class: 'dashicons dashicons-no-alt', 'aria-hidden': 'true' } ) ]
		);
		const heading = el( 'h2', { class: 'cf7lp-modal-title', id: `${ className }-title`, text: title } );
		const body = el( 'div', { class: 'cf7lp-modal-body' } );
		const footer = el( 'div', { class: 'cf7lp-modal-footer' } );
		const status = el( 'p', { class: 'cf7lp-status', role: 'status' } );
		const dialog = el(
			'dialog',
			{ class: `cf7lp-modal ${ className }`, 'aria-labelledby': heading.id },
			[
				el( 'header', { class: 'cf7lp-modal-header' }, [ heading, ...actions, closeButton ] ),
				status,
				body,
				footer,
			]
		);

		closeButton.addEventListener( 'click', () => dialog.close() );

		// A click on the backdrop lands on the dialog element itself.
		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		document.body.append( dialog );

		const setStatus = ( text, isError = false ) => {
			status.textContent = text;
			status.classList.toggle( 'is-error', isError );
		};

		return { dialog, body, footer, setStatus };
	};

	// A button beside the panel's heading.
	const createTrigger = ( panel, text, icon ) => {
		const button = el( 'button', { type: 'button', class: 'button cf7lp-trigger', 'aria-haspopup': 'dialog' }, [
			el( 'span', { class: `dashicons dashicons-${ icon }`, 'aria-hidden': 'true' } ),
			labelSpan( text ),
		] );
		const heading = panel.querySelector( 'h2' );

		if ( heading ) {
			const row = el( 'div', { class: 'cf7lp-trigger-row' } );
			heading.before( row );
			row.append( heading, button );
		} else {
			panel.prepend( button );
		}

		return button;
	};

	/*
	 * Form preview
	 */

	const setupFormPreview = () => {
		const frameOrigin = new URL( config.frameUrl, window.location.href ).origin;
		const refreshButton = el( 'button', { type: 'button', class: 'button' }, [
			el( 'span', { class: 'dashicons dashicons-update', 'aria-hidden': 'true' } ),
			labelSpan( __( 'Refresh', 'cf7-live-preview' ) ),
		] );
		const modal = createModal( {
			title: __( 'Form preview', 'cf7-live-preview' ),
			className: 'cf7lp-form-modal',
			actions: [ refreshButton ],
		} );

		const iframe = el( 'iframe', {
			class: 'cf7lp-iframe',
			title: __( 'Contact form preview', 'cf7-live-preview' ),
		} );
		// The stage takes the frame's on-screen (scaled) size; see sizeFrame().
		const stage = el( 'div', { class: 'cf7lp-stage' }, [ iframe ] );
		const viewport = el( 'div', { class: 'cf7lp-viewport' }, [ stage ] );
		modal.body.append( viewport );

		let contentHeight = 300;

		/*
		 * A device width wider than the modal (Tablet on a small screen) is
		 * scaled down to fit rather than cropped: the frame keeps its real
		 * width, so the theme's media queries still see that viewport.
		 */
		const sizeFrame = () => {
			const target = WIDTHS[ preferences.width ]?.width ?? null;
			const available = viewport.clientWidth;
			const scale = target && available ? Math.min( 1, available / target ) : 1;

			iframe.style.width = target ? `${ target }px` : '100%';
			iframe.style.height = `${ contentHeight }px`;
			iframe.style.transform = scale < 1 ? `scale(${ scale })` : '';

			stage.style.width = target ? `${ target * scale }px` : '100%';
			stage.style.height = `${ contentHeight * scale }px`;
		};

		const widths = createPill( {
			label: __( 'Preview width', 'cf7-live-preview' ),
			items: Object.entries( WIDTHS ).map( ( [ value, { label, icon } ] ) => ( {
				value,
				label,
				icon,
			} ) ),
			selected: WIDTHS[ preferences.width ] ? preferences.width : 'full',
			onChange: ( value ) => {
				preferences.width = value;
				savePreferences();
				sizeFrame();
			},
		} );
		modal.footer.append( widths.node );

		if ( typeof window.ResizeObserver === 'function' ) {
			new window.ResizeObserver( sizeFrame ).observe( viewport );
		}

		let frameReady = false;
		let lastHtml = null;
		let lastContext = null;
		const request = latestRequest();

		const sendToFrame = () => {
			if ( frameReady && lastHtml !== null ) {
				iframe.contentWindow.postMessage(
					{ type: 'cf7lp:render', html: lastHtml, context: lastContext },
					frameOrigin
				);
			}
		};

		const render = async () => {
			modal.setStatus( __( 'Updating…', 'cf7-live-preview' ) );

			// What the frame needs to validate a submission of this form.
			const context = { form: textarea.value, locale: locale(), messages: messages() };

			try {
				const response = await request( {
					path: config.renderPath,
					method: 'POST',
					data: { form: context.form, locale: context.locale, addons: addonContext() },
				} );

				if ( response === undefined ) {
					return;
				}

				lastHtml = response.html;
				lastContext = context;
				sendToFrame();
				modal.setStatus(
					response.error ? __( 'The form could not be rendered.', 'cf7-live-preview' ) : '',
					response.error
				);
			} catch ( error ) {
				modal.setStatus(
					error?.message || __( 'The preview could not be updated.', 'cf7-live-preview' ),
					true
				);
			}
		};

		// Loads (or reloads) the front end; it renders once it reports ready.
		const loadFrame = () => {
			frameReady = false;
			lastHtml = null;
			iframe.src = config.frameUrl;
		};

		window.addEventListener( 'message', ( event ) => {
			if (
				event.origin !== frameOrigin ||
				event.source !== iframe.contentWindow ||
				typeof event.data !== 'object' ||
				event.data === null
			) {
				return;
			}

			if ( event.data.type === 'cf7lp:ready' ) {
				frameReady = true;
				if ( lastHtml === null ) {
					render();
				} else {
					sendToFrame();
				}
			} else if ( event.data.type === 'cf7lp:height' && Number.isFinite( event.data.height ) ) {
				contentHeight = Math.max( 0, event.data.height );
				sizeFrame();
			}
		} );

		refreshButton.addEventListener( 'click', loadFrame );

		createTrigger( formPanel, __( 'Preview form', 'cf7-live-preview' ), 'visibility' ).addEventListener(
			'click',
			() => {
				modal.dialog.showModal();
				sizeFrame();

				// The front end loads on first open only, not with the editor.
				if ( ! iframe.getAttribute( 'src' ) ) {
					loadFrame();
				} else {
					render();
				}
			}
		);

		// The frame only talks to its own origin; a front end on another
		// origin (for example an HTTPS-only admin) can't be previewed.
		if ( frameOrigin !== window.location.origin ) {
			modal.setStatus(
				__( 'The preview needs the site and its admin on the same address.', 'cf7-live-preview' ),
				true
			);
		}
	};

	/*
	 * Email preview
	 */

	const setupMailPreview = () => {
		const templates = [
			{ value: 'mail', label: __( 'Mail', 'cf7-live-preview' ), id: 'wpcf7-mail' },
			{ value: 'mail_2', label: __( 'Mail (2)', 'cf7-live-preview' ), id: 'wpcf7-mail-2' },
		];

		const modal = createModal( {
			title: __( 'Email preview', 'cf7-live-preview' ),
			className: 'cf7lp-mail-modal',
		} );

		const headerRows = el( 'dl', { class: 'cf7lp-mail-headers' } );
		const bodyWrap = el( 'div', { class: 'cf7lp-mail-body' } );
		const sampleList = el( 'div', { class: 'cf7lp-samples-list' } );
		const resetSamples = el( 'button', {
			type: 'button',
			class: 'button-link',
			text: __( 'Reset to defaults', 'cf7-live-preview' ),
		} );
		const samplesBox = el( 'details', { class: 'cf7lp-samples' }, [
			el( 'summary', { text: __( 'Sample values', 'cf7-live-preview' ) } ),
			el( 'p', {
				class: 'description',
				text: __(
					'The email is filled with these values instead of a real submission. Edit them to try other inputs.',
					'cf7-live-preview'
				),
			} ),
			sampleList,
			resetSamples,
		] );

		modal.body.append(
			el( 'article', { class: 'cf7lp-mail' }, [ headerRows, bodyWrap ] ),
			samplesBox
		);

		let current = 'mail';
		let overrides = {};
		let lastFieldsKey = '';
		const request = latestRequest();

		const pill = createPill( {
			label: __( 'Mail template', 'cf7-live-preview' ),
			items: templates,
			selected: current,
			onChange: ( value ) => {
				current = value;
				render();
			},
		} );
		modal.footer.append( pill.node );

		const readTemplate = ( id ) => {
			const value = ( suffix ) => document.getElementById( `${ id }-${ suffix }` )?.value ?? '';
			const checked = ( suffix ) => !! document.getElementById( `${ id }-${ suffix }` )?.checked;

			return {
				recipient: value( 'recipient' ),
				sender: value( 'sender' ),
				subject: value( 'subject' ),
				additional_headers: value( 'additional-headers' ),
				body: value( 'body' ),
				attachments: value( 'attachments' ),
				use_html: checked( 'use-html' ),
				exclude_blank: checked( 'exclude-blank' ),
			};
		};

		const headerRow = ( label, value ) => [
			el( 'dt', { text: label } ),
			el( 'dd', { text: value || '—' } ),
		];

		const showMail = ( mail ) => {
			const rows = [
				...headerRow( __( 'From', 'cf7-live-preview' ), mail.sender ),
				...headerRow( __( 'To', 'cf7-live-preview' ), mail.recipient ),
				...headerRow( __( 'Subject', 'cf7-live-preview' ), mail.subject ),
			];

			mail.additional_headers
				.split( '\n' )
				.filter( ( line ) => line.includes( ':' ) )
				.forEach( ( line ) => {
					const at = line.indexOf( ':' );
					rows.push( ...headerRow( line.slice( 0, at ).trim(), line.slice( at + 1 ).trim() ) );
				} );

			if ( mail.attachments.length ) {
				rows.push( ...headerRow( __( 'Attachments', 'cf7-live-preview' ), mail.attachments.join( ', ' ) ) );
			}

			headerRows.replaceChildren( ...rows );

			if ( mail.use_html ) {
				// No allow-scripts: the email's markup is shown, never run.
				// allow-same-origin only lets this page measure its height.
				const frame = el( 'iframe', {
					class: 'cf7lp-mail-html',
					title: __( 'Email body', 'cf7-live-preview' ),
					sandbox: 'allow-same-origin',
				} );
				frame.addEventListener( 'load', () => {
					const doc = frame.contentDocument;
					if ( doc ) {
						frame.style.height = `${ doc.documentElement.scrollHeight }px`;
					}
				} );
				frame.srcdoc = mail.body;
				bodyWrap.replaceChildren( frame );
			} else {
				bodyWrap.replaceChildren( el( 'pre', { class: 'cf7lp-mail-text', text: mail.body } ) );
			}
		};

		const showFields = ( fields ) => {
			// Rebuild only when the set of fields changes, so typing in a
			// sample field keeps its focus.
			const key = fields.map( ( f ) => `${ f.type }:${ f.name }` ).join( '|' );

			if ( key === lastFieldsKey ) {
				return;
			}
			lastFieldsKey = key;

			if ( ! fields.length ) {
				sampleList.replaceChildren(
					el( 'p', { text: __( 'This form has no fields to fill in.', 'cf7-live-preview' ) } )
				);
				return;
			}

			sampleList.replaceChildren(
				...fields.map( ( field ) => {
					const id = `cf7lp-sample-${ field.name }`;
					const input =
						field.type === 'textarea'
							? el( 'textarea', { id, rows: 3 } )
							: el( 'input', { id, type: 'text' } );

					input.value = field.value;
					input.addEventListener( 'input', () => {
						overrides[ field.name ] = input.value;
						rerender();
					} );

					return el( 'div', { class: 'cf7lp-sample' }, [
						el( 'label', { for: id }, [ el( 'code', { text: `[${ field.name }]` } ) ] ),
						input,
					] );
				} )
			);
		};

		const render = async () => {
			rerender.cancel();
			modal.setStatus( __( 'Updating…', 'cf7-live-preview' ) );

			const template = templates.find( ( t ) => t.value === current );

			try {
				const response = await request( {
					path: config.mailPath,
					method: 'POST',
					data: {
						form: textarea.value,
						mail: readTemplate( template.id ),
						samples: overrides,
						title: formTitle(),
						locale: locale(),
					},
				} );

				if ( response === undefined ) {
					return;
				}

				if ( response.error ) {
					modal.setStatus( response.error, true );
					return;
				}

				showMail( response.mail );
				showFields( response.fields );
				modal.setStatus( '' );
			} catch ( error ) {
				modal.setStatus(
					error?.message || __( 'The email preview could not be updated.', 'cf7-live-preview' ),
					true
				);
			}
		};

		const rerender = debounce( render, DEBOUNCE_MS );

		resetSamples.addEventListener( 'click', () => {
			overrides = {};
			lastFieldsKey = '';
			render();
		} );

		createTrigger( mailPanel, __( 'Preview email', 'cf7-live-preview' ), 'email-alt' ).addEventListener(
			'click',
			() => {
				// Mail (2) is offered only while it is switched on.
				const mail2Active = !! document.getElementById( 'wpcf7-mail-2-active' )?.checked;
				const mail2Button = pill.buttons.find( ( b ) => b.dataset.value === 'mail_2' );

				mail2Button.setAttribute( 'aria-disabled', String( ! mail2Active ) );
				mail2Button.title = mail2Active
					? ''
					: __( 'Mail (2) is not in use. Tick "Use Mail (2)" in the Mail tab to preview it.', 'cf7-live-preview' );

				if ( ! mail2Active && current === 'mail_2' ) {
					current = 'mail';
					pill.set( current );
				}

				// The form's fields may have changed since it was last open.
				lastFieldsKey = '';
				modal.dialog.showModal();
				render();
			}
		);
	};

	setupFormPreview();

	if ( mailPanel && config.mailPath ) {
		setupMailPreview();
	}
} )();
