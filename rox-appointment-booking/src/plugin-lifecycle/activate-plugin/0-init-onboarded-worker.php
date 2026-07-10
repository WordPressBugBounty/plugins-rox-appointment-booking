<?php

defined('ABSPATH') || exit;

if (get_option('rox_appointment_booking_onboarded', null) === null) {
    update_option('rox_appointment_booking_onboarded', 0);
}

set_transient('rox_appointment_booking_activation_redirect', 1, 60);
