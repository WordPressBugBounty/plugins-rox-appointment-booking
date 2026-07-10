<?php

namespace RoxAppointmentBooking\Modules\Payment\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Supports\Traits\RoxAppointmentBookingFilter;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Customer\Data\CustomerModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;
use RoxAppointmentBooking\Modules\Agent\Data\AgentModel;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;

/**
 * Class GetPayment
 *
 * @package RoxAppointmentBooking\Modules\Payment\REST
 * @description Handles retrieving payment data via REST API.
 */
class GetPayment extends AbstractREST
{
    use RoxAppointmentBookingFilter;
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for this endpoint.
     *
     * @var string
     */
    public static string $route = 'payment(?:/(?P<id>\d+))?';

    /**
     * Get the methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Get fields that can be searched by the filter trait.
     *
     * @return array
     */
    protected function getSearchableFields(): array
    {
        return ['id','amount','transaction_id','status','payment_method'];
    }

    /**
     * Format payment data for list view.
     *
     * @since 1.0.0
     *
     * @param PaymentModel $payment
     *
     * @return array
     */
    protected function getPaymentData(PaymentModel $payment): array
    {
        $customerName = 'Guest';
        $customerData = null;
        $order = $payment->order_id ? OrderModel::find($payment->order_id) : null;

        if ($payment->customer_id) {
            $customer = CustomerModel::find($payment->customer_id);
            if ($customer) {
                $customerName = $customer->getFullName();
                $customerData = [
                    'id' => $customer->getID(),
                    'name' => $customerName,
                    'email' => $customer->email ?? '',
                    'thumbnail' => $customer->thumbnail_id ? wp_get_attachment_url($customer->thumbnail_id) : ''
                ];
            }
        }

        $paymentDate = '';
        if ($payment->payment_time) {
            $paymentDate = wp_date('F j, Y - g:i A', strtotime($payment->payment_time));
        }

        $currencySymbol = rox_appointment_booking__get_currency_symbol(rox_appointment_booking_payment_settings('payment_currency'));
        if (!$currencySymbol) {
            $currencySymbol = '$';
        }
        $formattedTotal = $currencySymbol . number_format($payment->amount, 2);

        $effectivePaymentStatus = $this->resolveEffectivePaymentStatus($payment, $order);
        $isCompleted = $effectivePaymentStatus === PaymentModel::STATUS_PAID;
        $dueAmount = $isCompleted ? 0 : $payment->amount;
        $formattedDue = $currencySymbol . number_format($dueAmount, 2);
        $order = $payment->order_id ? OrderModel::find($payment->order_id) : null;

        // Build pricing breakdown items
        $pricingItems = [];
        $taxRate = 0;

        $order = $payment->order_id ? OrderModel::find($payment->order_id) : null;

        if ($order) {
            // Service charges (subtotal)
            if ($order->subtotal > 0) {
                $pricingItems[] = [
                    'label' => 'Service Charges',
                    'amount' => $order->subtotal,
                    'className' => ''
                ];
            }
            
            // Calculate tax rate percentage if tax_amount exists
            if ($order->tax_amount > 0 && $order->subtotal > 0) {
                $taxRate = ($order->tax_amount / $order->subtotal) * 100;
            }
            
            // Tax/VAT
            if ($order->tax_amount > 0) {
                $taxLabel = $taxRate > 0 ? sprintf('VAT (%.1f%%)', $taxRate) : 'Tax';
                $pricingItems[] = [
                    'label' => $taxLabel,
                    'amount' => $order->tax_amount,
                    'className' => ''
                ];
            }
            
            // Discount
            if ($order->discount_amount > 0) {
                $pricingItems[] = [
                    'label' => 'Discount',
                    'amount' => $order->discount_amount,
                    'className' => 'discount'
                ];
            }
            
            // Subtotal (service charges + tax - discount)
            $subtotal = ($order->subtotal + $order->tax_amount) - $order->discount_amount;
            $pricingItems[] = [
                'label' => 'Subtotal',
                'amount' => $subtotal,
                'className' => 'subtotal-row'
            ];
            
            // Total Paid
            $totalPaid = $order->total_amount - $dueAmount;
            $pricingItems[] = [
                'label' => 'Total Paid',
                'amount' => $totalPaid,
                'className' => 'paid'
            ];
            
            // Due
            if ($dueAmount > 0) {
                $pricingItems[] = [
                    'label' => 'Due',
                    'amount' => $dueAmount,
                    'className' => 'due-row'
                ];
            }
        }

        return [
            'id' => $payment->getID(),
            'transaction_id' => $payment->transaction_id ?? '',
            'customer' => $customerData ?? ['name' => $customerName],
            'method' => $payment->payment_method ? ucfirst(str_replace('_', ' ', $payment->payment_method)) : '',
            'total' => $formattedTotal,
            'due' => $formattedDue,
            'payment_status' => $effectivePaymentStatus,
            'payment_date' => $paymentDate,
        ];
    }

    /**
     * Format payment data for single view.
     *
     * @since 1.0.0
     *
     * @param PaymentModel $payment
     *
     * @return array
     */
    protected function getSinglePaymentData(PaymentModel $payment): array
    {
        $customer = null;
        $customerName = 'Guest';
        $customerEmail = '';
        $customerPhone = '';

        if ($payment->customer_id) {
            $customer = CustomerModel::find($payment->customer_id);
            if ($customer) {
                $customerName = $customer->getFullName();
                $customerEmail = $customer->email ?? '';
                $customerPhone = $customer->phone ?? '';
            }
        }


        $order = $payment->order_id ? OrderModel::find($payment->order_id) : null;

        $agent = null;
        $service = null;
        $dateTimeFormatted = '';
        if ($order) {
            $bookingIds = $order->getBookingIds();
            if (!empty($bookingIds)) {
                $booking = AppointmentModel::find($bookingIds[0]);
                if ($booking) {
                    $agent = $booking->agent_id ? AgentModel::find($booking->agent_id) : null;
                    $service = $booking->service_id ? ServiceModel::find($booking->service_id) : null;
                    if ($booking->date && $booking->start_time) {
                        $dateTimeFormatted = wp_date('F j, Y', strtotime($booking->date)) . ' - ' . wp_date('g:i A', strtotime($booking->start_time));
                    }
                }
            }
        }

        $currencySymbol = rox_appointment_booking__get_currency_symbol(rox_appointment_booking_payment_settings('payment_currency'));
        if (!$currencySymbol) {
            $currencySymbol = '$';
        }

        $effectivePaymentStatus = $this->resolveEffectivePaymentStatus($payment, $order);
        $dueAmount = $effectivePaymentStatus === PaymentModel::STATUS_PAID ? 0 : (float) $payment->amount;

        $totalAmount = $order ? (float) ($order->total_amount ?? 0) : 0;
        $serviceChargeAmount = $order ? (float) ($order->subtotal ?? 0) : 0;
        $taxAmount = $order ? (float) ($order->tax_amount ?? 0) : 0;
        $discountAmount = $order ? (float) ($order->discount_amount ?? 0) : 0;

        $serviceLabel = ($service && !empty($service->title))
            ? $service->title . ' (Service Charges)'
            : 'Service Charges';

        $taxRate = 0;
        if ($serviceChargeAmount > 0 && $taxAmount > 0) {
            $taxRate = ($taxAmount / $serviceChargeAmount) * 100;
        }

        $pricingItems = [];
        if ($order) {
            if ($serviceChargeAmount > 0) {
                $pricingItems[] = [
                    'label' => $serviceLabel,
                    'amount' => $serviceChargeAmount,
                    'className' => ''
                ];
            }

            if ($taxAmount > 0) {
                $pricingItems[] = [
                    'label' => $taxRate > 0 ? sprintf('VAT (%.1f%%)', $taxRate) : 'Tax',
                    'amount' => $taxAmount,
                    'className' => ''
                ];
            }

            if ($discountAmount > 0) {
                $pricingItems[] = [
                    'label' => 'Discount',
                    'amount' => -$discountAmount,
                    'className' => 'discount'
                ];
            }

            $subtotalAmount = ($serviceChargeAmount + $taxAmount) - $discountAmount;
            $pricingItems[] = [
                'label' => 'Subtotal',
                'amount' => max($subtotalAmount, 0),
                'className' => 'subtotal-row'
            ];

            $totalPaid = max($totalAmount - $dueAmount, 0);
            $pricingItems[] = [
                'label' => 'Total Paid',
                'amount' => $totalPaid,
                'className' => 'paid'
            ];

            if ($dueAmount > 0) {
                $pricingItems[] = [
                    'label' => 'Due',
                    'amount' => $dueAmount,
                    'className' => 'due-row'
                ];
            }
        }

        $customerItems = [];
        if ($customer) {
            $customerItems = [
                [
                    'label'  => 'Customer',
                    'value'  => $customer->getFullName(),
                    'avatar' => $customer->thumbnail_id ? wp_get_attachment_url($customer->thumbnail_id) : '',
                    'props'  => ['span' => 12]
                ],
                [
                    'label'  => 'Employee',
                    'value'  => $agent ? $agent->getFullName() : '',
                    'avatar' => ($agent && $agent->thumbnail_id) ? wp_get_attachment_url($agent->thumbnail_id) : '',
                    'props'  => ['span' => 12]
                ],
                [
                    'label'  => 'Service',
                    'value'  => $service->title ?? '',
                    'props'  => ['span' => 12]
                ],
                [
                    'label'  => 'Date, Time',
                    'value'  => $dateTimeFormatted,
                    'props'  => ['span' => 12]
                ],
                [
                    'label'  => 'Payment Method',
                    'value'  => $order ? ucfirst(str_replace('_', ' ', $order->payment_method ?? '')) : '',
                    'props'  => ['span' => 12]
                ]
            ];
        }

        
        $paymentDate = '';
        if ($payment->payment_time) {
            $paymentDate = wp_date('F j, Y - g:i A', strtotime($payment->payment_time));
        }

        $formattedAmount = $currencySymbol . number_format($payment->amount, 2);

        $formattedDue = $currencySymbol . number_format($dueAmount, 2);

        // Get effective payment status for badge display
        $effectivePaymentStatus = $this->resolveEffectivePaymentStatus($payment, $order);

        return [
            'id' => $payment->getID(),
            'customer_id' => $payment->customer_id,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'amount' => $payment->amount,
            'amount_formatted' => $formattedAmount,
            'due_amount' => $dueAmount,
            'due_formatted' => $formattedDue,
            'payment_method' => $payment->payment_method,
            'payment_method_formatted' => $payment->payment_method ? ucfirst(str_replace('_', ' ', $payment->payment_method)) : '',
            'status' => $effectivePaymentStatus,
            'transaction_id' => $payment->transaction_id,
            'payment_time' => $payment->payment_time,
            'payment_date_formatted' => $paymentDate,
            'internal_notes' => $payment->internal_notes,
            'created_at' => $payment->created_at,
            'updated_at' => $payment->updated_at,
            'customer_info_section' => [
                'title' => 'Booking INFORMATION',
                'items' => $customerItems
            ],
             'pricing_breakdown_section' => [
            'title' => 'PRICING BREAKDOWN',
            'items' => $pricingItems,
            'currencySymbol' => $currencySymbol,
        ],
        ];
    }



    /**
     * Handle REST API request.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!$this->permissionCheck($request)) {
            return rox_appointment_booking_rest_response(
                data: [],
                message: [
                    'error' => [
                        esc_html__('You have no permission to access this route', 'rox-appointment-booking')
                    ]
                ]
            );
        }

        try {
            $id = $request->get_param('id');

            if ($id) {
                $payment = PaymentModel::find($id);
                if (!$payment) {
                    return new WP_Error(
                        'payment_not_found',
                        esc_html__('Payment not found', 'rox-appointment-booking'),
                        ['status' => 404]
                    );
                }

                return rox_appointment_booking_rest_response(
                    data: $this->getSinglePaymentData($payment),
                    message: [
                        'success' => [
                            esc_html__('Payment retrieved successfully', 'rox-appointment-booking')
                        ]
                    ]
                );
            }

            $pagination = $this->getPaginationParams($request);

            $query = PaymentModel::query();
            $query = $this->applyFilters($request, $query);

            $total = $query->count();
            $payments = $query->offset(($pagination['page'] - 1) * $pagination['per_page'])
                ->limit($pagination['per_page'])
                ->orderBy('created_at', 'DESC')
                ->get();

            $formattedPayments = [];
            foreach ($payments as $payment) {
                $formattedPayments[] = $this->getPaymentData($payment);
            }

            return rox_appointment_booking_rest_response(
                data: $formattedPayments,
                message: [
                    'success' => [
                        sprintf(
                            // translators: %d = total number of payments found
                            esc_html__('Found %d payments', 'rox-appointment-booking'),
                            $total
                        )
                    ]
                ],
                options: $this->buildPaginationMeta($total, $pagination['page'], $pagination['per_page'])
            );
        } catch (\Exception $e) {
            return new WP_Error(
                'payment_retrieval_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Check user permissions.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request
     *
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the effective payment status from payment, order, and appointment data.
     *
     * @param PaymentModel $payment Payment model instance.
     * @param OrderModel|null $order Order model instance.
     * @return string
     */
    private function resolveEffectivePaymentStatus(PaymentModel $payment, ?OrderModel $order): string
    {
        $effectiveStatus = $payment->status ?: PaymentModel::STATUS_UNPAID;

        if ($order) {
            if (!empty($order->payment_status)) {
                $effectiveStatus = $order->payment_status;
            }

            $bookingIds = $order->getBookingIds();
            if (!empty($bookingIds)) {
                $booking = AppointmentModel::find($bookingIds[0]);
                if ($booking && !empty($booking->payment_status)) {
                    $effectiveStatus = $booking->payment_status;
                }
            }
        }

        // Always fallback to unpaid if still empty
        $effectiveStatus = $effectiveStatus ?: PaymentModel::STATUS_UNPAID;

        return (string) $effectiveStatus;
    }
}
