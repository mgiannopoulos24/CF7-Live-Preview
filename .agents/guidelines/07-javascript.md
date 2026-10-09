# JavaScript

> Vanilla JS for frontend, jQuery for admin, and data passing patterns.

---

## Vanilla JS (Frontend — Preferred)

For frontend scripts, use vanilla JS with `fetch()` — no jQuery dependency:

```javascript
( function() {
	'use strict';

	/**
	 * {Plugin Name} frontend handler.
	 *
	 * @since 1.0.0
	 */
	const {PluginName} = {
		config: null,

		init() {
			this.config = window.{plugin_name}_inventory_params ?? null;

			if ( ! this.config ) {
				return;
			}

			this.bindEvents();
		},

		bindEvents() {
			document.querySelectorAll( '.{plugin_name}-btn' ).forEach( ( btn ) => {
				btn.addEventListener( 'click', ( e ) => this.handleClick( e ) );
			} );
		},

		async handleClick( e ) {
			e.preventDefault();

			const btn = e.currentTarget;
			btn.disabled = true;

			try {
				const formData = new FormData();
				formData.append( 'action', this.config.action );
				formData.append( 'nonce', this.config.nonce );
				formData.append( 'product_id', btn.dataset.productId );

				const response = await fetch( this.config.ajax_url, {
					method: 'POST',
					credentials: 'same-origin',
					body: formData,
				} );

				const data = await response.json();

				if ( data.success ) {
					this.onSuccess( data.data, btn );
				} else {
					this.onError( data.data?.message ?? 'Unknown error' );
				}
			} catch ( error ) {
				console.error( '[{Plugin Name}]', error );
			} finally {
				btn.disabled = false;
			}
		},

		onSuccess( data, btn ) {
			// Handle success.
		},

		onError( message ) {
			// Handle error.
		},
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => {PluginName}.init() );
	} else {
		{PluginName}.init();
	}
} )();
```

---

## jQuery (Admin Only)

jQuery is acceptable for admin scripts where WordPress already loads it:

```javascript
( function( $ ) {
	'use strict';

	const {PluginName}_Admin = {
		init() {
			this.bindEvents();
		},

		bindEvents() {
			$( document ).on( 'click', '.{plugin_name}-admin-btn', ( e ) => this.handleClick( e ) );
		},

		async handleClick( e ) {
			e.preventDefault();

			try {
				const response = await $.post( {plugin_name}_inventory_admin_params.ajax_url, {
					action: {plugin_name}_inventory_admin_params.action,
					nonce: {plugin_name}_inventory_admin_params.nonce,
				} );

				if ( response.success ) {
					// Handle success.
				} else {
					console.error( response.data.message );
				}
			} catch ( error ) {
				console.error( '[{Plugin Name} Admin]', error );
			}
		},
	};

	$( () => {PluginName}_Admin.init() );
} )( jQuery );
```

---

## Data Passing (PHP Side)

### Use `wp_add_inline_script` for Non-Translatable Data

Preserves types (booleans, integers):

```php
wp_enqueue_script(
	'{plugin-name}-checkout',
	Plugin::url( 'assets/js/checkout.js' ),
	[],
	Plugin::VERSION,
	[ 'strategy' => 'defer' ]
);

wp_add_inline_script(
	'{plugin-name}-checkout',
	'window.{plugin_name}_inventory_params = ' . wp_json_encode( [
		'ajax_url'   => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( Plugin::PREFIX . 'ajax' ),
		'action'     => Plugin::PREFIX . 'update_stock',
		'product_id' => $product_id,
		'is_admin'   => current_user_can( 'manage_woocommerce' ),  // Boolean preserved!
	], JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
	'before'
);
```

### Use `wp_localize_script` Only for Translatable Strings

```php
wp_localize_script( '{plugin-name}-admin', '{plugin_name}_inventory_i18n', [
	'confirm_delete' => __( 'Are you sure?', '{plugin-name}' ),
	'saving'         => __( 'Saving…', '{plugin-name}' ),
	'saved'          => __( 'Saved!', '{plugin-name}' ),
] );
```

---

## Script Loading Strategy (WP 6.3+)

```php
wp_enqueue_script(
	'{plugin-name}-frontend',
	Plugin::url( 'assets/js/frontend.js' ),
	[],
	Plugin::VERSION,
	[
		'strategy'  => 'defer',    // or 'async'
		'in_footer' => true,
	]
);
```

---

## Localized JS Variables

Each JS file is enqueued from exactly one place with a known localized object:

- `{plugin-name}-functions.js` (admin) → `{plugin_name}_admin_functions_js_vars`
- `{plugin-name}-frontend.js` (frontend) → `{plugin_name}_pro_js_vars`

### Rules

1. **Never typeof-check the localized object.** It is always defined when the script runs. Use it directly:

   ```js
   // BAD
   var jsVars = (typeof {plugin_name}_admin_functions_js_vars !== 'undefined') ? {plugin_name}_admin_functions_js_vars : {};

   // GOOD
   var jsVars = {plugin_name}_admin_functions_js_vars;
   ```

2. **Never add inline fallback strings for i18n properties.** All translatable strings are provided by `wp_localize_script` in PHP. If a string is needed, add it in the PHP enqueue — not as a JS fallback:

   ```js
   // BAD
   $title.text(jsVars.i18n_some_label || 'Some Label');

   // GOOD
   $title.text(jsVars.i18n_some_label);
   ```

3. **Never cross-reference another file's localized object** (e.g. checking for `{plugin_name}_pro_js_vars` inside `{plugin-name}-functions.js`). Each file uses its own.

---

## Summary

| Context | Library | Data Method |
|---------|---------|-------------|
| Frontend | Vanilla JS + `fetch()` | `wp_add_inline_script` |
| Admin | jQuery allowed | `wp_add_inline_script` + `wp_localize_script` for i18n |
