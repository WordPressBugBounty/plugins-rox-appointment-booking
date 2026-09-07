<?php

/**
 * Customer dashboard page helpers.
 *
 * The plugin ships its own frontend dashboard page (the `[rox_appointment_dashboard]`
 * shortcode) so a site owner never has to build one by hand. The page is created
 * once — see `22-create_customer_dashboard_page-worker.php` and
 * `CustomerPanel\Services\CustomerPanelPage` — and its id lives in the
 * `rox_appointment_booking_dashboard_page_id` option.
 *
 * @package RoxAppointmentBooking
 * @subpackage functions
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

if (!function_exists('rox_appointment_booking_dashboard_page_id')) {
	/**
	 * Id of the page holding the customer dashboard shortcode, or 0 when the page
	 * has not been resolved yet.
	 *
	 * @return int
	 */
	function rox_appointment_booking_dashboard_page_id() {
		return (int) get_option('rox_appointment_booking_dashboard_page_id', 0);
	}
}

if (!function_exists('rox_appointment_booking_dashboard_url')) {
	/**
	 * Permalink of the customer dashboard page.
	 *
	 * Returns an empty string when there is nothing to link to — the page was
	 * never created, or the site owner has since trashed or deleted it. The page
	 * is deliberately not re-created in that case: deleting it is a decision, not
	 * an accident. Callers decide their own fallback (the logout redirect uses
	 * `wp_login_url()`).
	 *
	 * @return string
	 */
	function rox_appointment_booking_dashboard_url() {
		$page_id = rox_appointment_booking_dashboard_page_id();
		if (!$page_id) {
			return '';
		}

		// On a multilingual site the stored id is the source page; point at the
		// translation for the language in play so an e-mail link or logout
		// redirect does not drop the visitor onto the wrong language. Falls back
		// to the source id when there is no translation, and is a plain
		// passthrough with no multilingual plugin installed.
		if (function_exists('rox_appointment_booking_translated_post_id')) {
			$page_id = rox_appointment_booking_translated_post_id($page_id, 'page');
		}

		$page = get_post($page_id);
		if (!$page || $page->post_status !== 'publish') {
			return '';
		}

		$permalink = get_permalink($page);

		return $permalink ? $permalink : '';
	}
}
