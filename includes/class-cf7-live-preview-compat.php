<?php
/**
 * Compatibility with Contact Form 7 add-ons
 *
 * @package CF7_Live_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7_Live_Preview_Compat class
 *
 * Add-ons that keep per-form settings outside the form template store them
 * against the saved form's ID. The preview renders an unsaved form with no
 * ID, so the editor sends those settings along ("add-on context") and they
 * are swapped in for the duration of a render.
 *
 * @package CF7_Live_Preview
 */
class CF7_Live_Preview_Compat {

	/**
	 * Add-on context keys accepted from the editor, and their schema
	 *
	 * @return array
	 */
	public static function context_schema() {
		return array(
			// Conditional Fields for CF7: the "Conditional fields" tab, as text.
			'cf_conditions' => array( 'type' => 'string' ),
		);
	}

	/**
	 * Apply add-on context for the duration of a callback
	 *
	 * @param array    $context  Add-on context from the editor.
	 * @param callable $callback Work to do with the context applied.
	 * @return mixed The callback's return value.
	 */
	public static function with_context( $context, $callback ) {
		$filters = array();

		if ( isset( $context['cf_conditions'] ) && self::conditional_fields_active() ) {
			$conditions = CF7CF::parse_conditions( (string) $context['cf_conditions'] );

			$filters['wpcf7_form_hidden_fields'] = static function ( $fields ) use ( $conditions ) {
				return self::replace_cf_conditions( $fields, $conditions );
			};
		}

		foreach ( $filters as $hook => $filter ) {
			// After Conditional Fields' own filter (priority 10).
			add_filter( $hook, $filter, 20 );
		}

		try {
			return $callback();
		} finally {
			foreach ( $filters as $hook => $filter ) {
				remove_filter( $hook, $filter, 20 );
			}
		}
	}

	/**
	 * Whether Conditional Fields for CF7 is active, with the API used here
	 *
	 * @return bool
	 */
	public static function conditional_fields_active() {
		return class_exists( 'CF7CF' ) && method_exists( 'CF7CF', 'parse_conditions' );
	}

	/**
	 * Put the editor's conditions into Conditional Fields' options field
	 *
	 * @param array $fields     Hidden fields of the form.
	 * @param array $conditions Parsed conditions.
	 * @return array
	 */
	private static function replace_cf_conditions( $fields, $conditions ) {
		if ( ! isset( $fields['_wpcf7cf_options'] ) ) {
			return $fields;
		}

		$options = json_decode( (string) $fields['_wpcf7cf_options'], true );

		if ( ! is_array( $options ) ) {
			return $fields;
		}

		$options['conditions']      = $conditions;
		$fields['_wpcf7cf_options'] = (string) wp_json_encode( $options );

		return $fields;
	}
}
