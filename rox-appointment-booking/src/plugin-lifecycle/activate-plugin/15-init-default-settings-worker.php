<?php
defined('ABSPATH') || exit;

// Initialize default working hours schedule if not already set
$rox_appointment_booking_existing_schedule = get_option('rox_appointment_booking_weekly_schedule', false);
$payment_settings = get_option('rox_appointment_booking_payments_settings', null);

// Only set default if option doesn't exist yet
if ($rox_appointment_booking_existing_schedule === false) {
    $rox_appointment_booking_days = [
        "Monday",
        "Tuesday",
        "Wednesday",
        "Thursday",
        "Friday",
        "Saturday",
        "Sunday"
    ];

    $rox_appointment_booking_default_schedule = [];
    foreach ($rox_appointment_booking_days as $rox_appointment_booking_day) {
        $rox_appointment_booking_is_weekend = in_array($rox_appointment_booking_day, ["Saturday", "Sunday"]);
        $rox_appointment_booking_default_schedule[] = [
            "day_name" => $rox_appointment_booking_day,
            "day_off" => $rox_appointment_booking_is_weekend,
            "schedule" => ["08:00:00", "17:00:00"],
            "breaks" => [["13:00:00", "14:00:00"]]
        ];
    }

    // Save as JSON string
    $rox_appointment_booking_json_schedule = json_encode($rox_appointment_booking_default_schedule);
    update_option('rox_appointment_booking_weekly_schedule', $rox_appointment_booking_json_schedule);
}

/**
 * Initialize default payment settings if not already set
*/
if ($payment_settings === null || $payment_settings === []) {
    update_option(
        'rox_appointment_booking_payments_settings',
        [
            'pay_later_payment_option_enable' => true,
        ]
    );
}

/**
 * By default location module is enabled
 */
update_option(
    'rox_appointment_booking_location_settings',
    [
        'location_module_enable' => true,
    ]
);

