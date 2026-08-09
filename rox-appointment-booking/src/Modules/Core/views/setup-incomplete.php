<?php

/**
 * Shown to non-administrator panel users (agents) when the plugin has not been
 * onboarded yet. The onboarding wizard itself is admin-only — its `Complete`
 * endpoint requires `manage_options` — so showing it here would be a dead end.
 */

defined('ABSPATH') || exit;

?>
<div class="wrap">
	<h1><?php echo esc_html__('Rox Appointment Booking', 'rox-appointment-booking'); ?></h1>
	<p><?php echo esc_html__('Setup is not finished yet. Please ask a site administrator to complete the initial setup before you can use this panel.', 'rox-appointment-booking'); ?></p>
</div>
