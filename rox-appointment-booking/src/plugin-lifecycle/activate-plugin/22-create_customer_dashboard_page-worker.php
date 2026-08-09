<?php

/**
 * Schedules creation of the frontend customer dashboard page.
 *
 * The page itself is NOT created here. Workers run from the activation hook and
 * from maybeUpgrade() on `plugins_loaded` — both too early to safely insert a
 * post (on activation `init` has already fired, on upgrade it has not fired
 * yet), so this worker only raises a flag. `CustomerPanel\Services\CustomerPanelPage`
 * picks it up on the next `init` and does the work.
 *
 * Once the page id option exists the flag is never raised again, so a site owner
 * who deletes the page does not get it re-created on the next update.
 */

defined('ABSPATH') || exit;

if (!get_option('rox_appointment_booking_dashboard_page_id', 0)) {
    update_option('rox_appointment_booking_dashboard_page_pending', 1);
}
