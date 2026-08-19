<?php

namespace RoxAppointmentBooking\Modules\FrontendBookingPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

defined('ABSPATH') || exit;

/**
 * Class PaymentFormStructure
 *
 * @package RoxAppointmentBooking\Modules\FrontendBookingPanel\REST
 * @description Provides public payment form structure via REST API.
 */
class PaymentFormStructure extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;

    /**
     * REST route for the public payment form structure.
     *
     * @var string
     */
    public static string $route = '/public/structure/payment-form';

    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/public/structure/payment-form';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Check whether the current user can access this endpoint.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        return true;
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $payment_settings = rox_appointment_booking_payment_settings();
        $publishableKey = sanitize_text_field($payment_settings['stripe_publishable_key'] ?? '');
        $stripeEnable = sanitize_text_field($payment_settings['stripe_payment_gateway_enable'] ?? '');
        $stripeConnectionStatus = sanitize_text_field($payment_settings['stripe_connection_status'] ?? '');
        $payLaterEnable = sanitize_text_field($payment_settings['pay_later_payment_option_enable'] ?? '');
        
        $structure = [
            'stripeEnable' => false,
            'payLaterEnable' => false,
            'currency' => sanitize_text_field($payment_settings['payment_currency'] ?? 'usd'),
            'fields' => [
                ['type' => 'email', 'label' => esc_html__('Email', 'rox-appointment-booking'), 'name' => 'email', 'required' => true],
                ['type' => 'number', 'label' => esc_html__('Amount', 'rox-appointment-booking'), 'name' => 'amount', 'required' => true, 'min' => 0.50, 'max' => 999999.99],
                ['type' => 'stripe_card', 'label' => esc_html__('Card Details', 'rox-appointment-booking'), 'name' => 'card_element', 'required' => true]
            ],
            'submit_url' => rest_url(ROX_APPOINTMENT_BOOKING_TEXT_DOMAIN . '/v1/payment'),
            'nonce' => wp_create_nonce('wp_rest')
        ];

        if (!empty($payLaterEnable)){
            $structure['payLaterEnable'] = true;
        }

        if (!empty($stripeEnable) && !empty($publishableKey) && $stripeConnectionStatus === 'connected') {
            $structure['stripeEnable'] = true;
            $structure['stripe_key'] = $publishableKey;
        }

        // Let Pro (or any add-on) extend the payment form with its own
        // redirect-based method (e.g. WooCommerce). With Pro inactive this
        // is a no-op and $structure is unchanged.
        $structure = apply_filters('rox_appointment_booking_payment_form_structure', $structure);

        return rox_appointment_booking_rest_response(
            data: $structure,
            message: esc_html__('Form structure retrieved successfully', 'rox-appointment-booking')
        );
    }
}
