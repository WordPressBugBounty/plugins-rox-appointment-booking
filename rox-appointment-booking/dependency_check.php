<?php

if ( ! defined( 'ABSPATH' ) ) exit;

// if function not exists
if (!function_exists('rox_appointment_booking_check_dependency')) {
    function rox_appointment_booking_check_dependency()
    {
        $failed_messages = [];
        $dependencies = [
            // check for php version
            'PHP' => [
                'version' => '8.0',
                'condition' => (version_compare(PHP_VERSION, '8.0', '>='))
            ],
            'WordPress' => [
                'version' => '6.0',
                'condition' => (version_compare(get_bloginfo('version'), '6.0', '>='))
            ],
        ];

        foreach ($dependencies as $dependency => $config) {
            if (!$config['condition']) {
                $failed_messages[] = sprintf(
                    /* translators: %1$s: platform name, %2$s: version number */
                    esc_html__('The %1$s version is not supported or installed. Please upgrade/ install your %1$s to at least %2$s.', 'rox-appointment-booking'),
                    $dependency,
                    $config['version']
                );
            }
        }
        return $failed_messages;
    }
}
