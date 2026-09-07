<?php

/**
 * Booking Engine Multilingual Helper Functions
 *
 * Thin wrappers over MultilingualService so call sites never reference a
 * multilingual plugin directly. Every one of these is a safe passthrough when
 * no multilingual plugin is active, which is why no caller needs an
 * `if (defined('ICL_SITEPRESS_VERSION'))` guard.
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

use RoxAppointmentBooking\Modules\Multilingual\Services\MultilingualService;

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('rox_appointment_booking_multilingual')) {
	/**
	 * Retrieve the shared multilingual service.
	 *
	 * @since 1.0.0
	 *
	 * @return MultilingualService
	 */
	function rox_appointment_booking_multilingual()
	{
		return MultilingualService::instance();
	}
}

if (!function_exists('rox_appointment_booking_register_string')) {
	/**
	 * Register one field of one record as a translatable source string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $entity Entity key, e.g. `service`.
	 * @param int    $id     Record ID.
	 * @param string $field  Column name, e.g. `title`.
	 * @param string $value  Source value in the default language.
	 * @param string $suffix Optional entry key for a repeatable field, e.g. one
	 *                       option label of a select custom field.
	 *
	 * @return void
	 */
	function rox_appointment_booking_register_string($entity, $id, $field, $value, $suffix = '')
	{
		rox_appointment_booking_multilingual()->registerString((string) $entity, (int) $id, (string) $field, (string) $value, (string) $suffix);
	}
}

if (!function_exists('rox_appointment_booking_register_record_strings')) {
	/**
	 * Register every translatable field of a record in one call.
	 *
	 * @since 1.0.0
	 *
	 * @param string $entity Entity key.
	 * @param int    $id     Record ID.
	 * @param array  $values Field values keyed by column name.
	 *
	 * @return void
	 */
	function rox_appointment_booking_register_record_strings($entity, $id, $values)
	{
		rox_appointment_booking_multilingual()->registerRecord((string) $entity, (int) $id, (array) $values);
	}
}

if (!function_exists('rox_appointment_booking_translate')) {
	/**
	 * Translate one field of one record, falling back to the source value.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $entity Entity key.
	 * @param int         $id     Record ID.
	 * @param string      $field  Column name.
	 * @param string      $value  Source value, used as the fallback.
	 * @param string|null $lang   Target language, or null for the current one.
	 * @param string      $suffix Optional entry key for a repeatable field.
	 *
	 * @return string
	 */
	function rox_appointment_booking_translate($entity, $id, $field, $value, $lang = null, $suffix = '')
	{
		return rox_appointment_booking_multilingual()->translate(
			(string) $entity,
			(int) $id,
			(string) $field,
			(string) $value,
			$lang === null ? null : (string) $lang,
			(string) $suffix
		);
	}
}

if (!function_exists('rox_appointment_booking_unregister_strings')) {
	/**
	 * Drop every source string belonging to a record.
	 *
	 * @since 1.0.0
	 *
	 * @param string $entity Entity key.
	 * @param int    $id     Record ID.
	 *
	 * @return void
	 */
	function rox_appointment_booking_unregister_strings($entity, $id)
	{
		rox_appointment_booking_multilingual()->unregisterStrings((string) $entity, (int) $id);
	}
}

if (!function_exists('rox_appointment_booking_with_language')) {
	/**
	 * Run a callback with a different language active, then restore.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $lang     Language code; invalid values fall back to
	 *                              the default language.
	 * @param callable    $callback Work to run.
	 *
	 * @return mixed
	 */
	function rox_appointment_booking_with_language($lang, callable $callback)
	{
		return rox_appointment_booking_multilingual()->withLanguage(
			$lang === null ? null : (string) $lang,
			$callback
		);
	}
}

if (!function_exists('rox_appointment_booking_translated_post_id')) {
	/**
	 * Retrieve the id of a post's translation in the active language.
	 *
	 * For real WordPress posts/pages the plugin links to, so a link handed to a
	 * German visitor lands on the German page. Returns the given id unchanged
	 * when there is no translation, or no multilingual plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id   Source post id.
	 * @param string $post_type Post type, e.g. 'page'.
	 *
	 * @return int
	 */
	function rox_appointment_booking_translated_post_id($post_id, $post_type = 'page')
	{
		return rox_appointment_booking_multilingual()->translatedPostId((int) $post_id, (string) $post_type);
	}
}

if (!function_exists('rox_appointment_booking_current_language')) {
	/**
	 * Retrieve the language code active for this request.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	function rox_appointment_booking_current_language()
	{
		return rox_appointment_booking_multilingual()->currentLanguage();
	}
}

if (!function_exists('rox_appointment_booking_default_language')) {
	/**
	 * Retrieve the site's default (source) language code.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	function rox_appointment_booking_default_language()
	{
		return rox_appointment_booking_multilingual()->defaultLanguage();
	}
}

if (!function_exists('rox_appointment_booking_sanitize_language')) {
	/**
	 * Reduce an untrusted language code to one the site actually has.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $lang Raw language code, e.g. from a request param.
	 *
	 * @return string
	 */
	function rox_appointment_booking_sanitize_language($lang)
	{
		return rox_appointment_booking_multilingual()->sanitizeLanguage($lang === null ? null : (string) $lang);
	}
}

if (!function_exists('rox_appointment_booking_is_default_language')) {
	/**
	 * Determine whether the request is running in the default language.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function rox_appointment_booking_is_default_language()
	{
		return rox_appointment_booking_multilingual()->isDefaultLanguage();
	}
}
