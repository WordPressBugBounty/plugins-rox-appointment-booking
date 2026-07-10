<?php

/**
 * Media upload helper functions  Booking Engine
 *
 * This file contains utility functions for uploading media files
 * to the WordPress media library.
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

if (! defined('ABSPATH')) exit; // Exit if accessed directly

// if function not exists
if (!function_exists('rox_appointment_booking_media_upload')) {

    /**
     * Uploads a media file to the WordPress media library.
     *
     * @param string $file_path The absolute path to the file to be uploaded.
     * @param string $file_name The desired name for the uploaded file.
     * @return int|WP_Error The attachment ID on success, or WP_Error on failure.
     */
    function rox_appointment_booking_media_upload($file_path, $file_name)
    {

        if (!function_exists('wp_handle_upload')) {

            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
        }

        if (!file_exists($file_path)) {

            return new WP_Error('file_missing', 'File does not exist.');
        }

        $file = array(
            'name' => $file_name,
            'type' => mime_content_type($file_path),
            'tmp_name' => $file_path,
            'error' => 0,
            'size' => filesize($file_path),
        );

        $overrides = array(
            'test_form' => false,
            'test_size' => true,
            'test_type' => true,
        );

        $upload = wp_handle_sideload($file, $overrides);

        if (isset($upload['error'])) {

            return new WP_Error('upload_error', $upload['error']);
        }

        $filetype = wp_check_filetype($upload['file'], null);

        $attachment = array(
            'guid' => $upload['url'],
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name(pathinfo($file_name, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $upload['file']);

        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);

        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }
}
