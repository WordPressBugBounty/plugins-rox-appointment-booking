<?php

defined('ABSPATH') || exit;

if (get_option('rox_appointment_booking_onboarded', null) === null) {
    update_option('rox_appointment_booking_onboarded', 0);
}

// The onboarding redirect is NOT set here: workers are replayed by
// maybeUpgrade() on every version bump, which would throw the admin into the
// onboarding screen after each plugin update. It is set from activatePlugin()
// instead, so only a real activation triggers it.
