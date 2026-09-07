<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\Services;

use RoxAppointmentBooking\Modules\Agent\Services\AgentService;
use RoxAppointmentBooking\Modules\Service\Data\ServiceModel;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Appointment\Data\AppointmentModel;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Assembles the logged-in customer's bookings into the exact shape the Customer
 * Panel's My Bookings view (BookingsView / BookingCard / HeroNextAppointment +
 * detail drawer) already consumes — replacing the Phase B mock data. Everything
 * is scoped to a single customer id (resolved server-side by the caller); this
 * service never reads a client-supplied id.
 */
class CustomerBookingsService
{
    public static $loadable = true;

    /** @var AgentService */
    private $agentService;

    /** @var string Currency symbol for price formatting. */
    private string $currency;

    public function __construct()
    {
        $this->agentService = new AgentService();
        $code = rox_appointment_booking_payment_settings('payment_currency', 'USD');
        $this->currency = function_exists('rox_appointment_booking__get_currency_symbol')
            ? rox_appointment_booking__get_currency_symbol($code)
            : '$';
    }

    /**
     * Grouped bookings payload for a customer:
     * { nextAppointment, counts:{upcoming,past,cancelled}, groups:{upcoming,past,cancelled} }
     *
     * @param int $customerId
     * @return array
     */
    public function getGroupedForCustomer(int $customerId): array
    {
        $appointments = AppointmentModel::query()
            ->where('customer_id', $customerId)
            ->orderBy('date', 'ASC')
            ->get()
            ->toArray();

        $today = current_time('Y-m-d');
        $groups = ['upcoming' => [], 'past' => [], 'cancelled' => []];

        foreach ($appointments as $appt) {
            $status = strtolower($appt['status'] ?? '');
            $date = $appt['date'] ?? (isset($appt['start_time']) ? gmdate('Y-m-d', strtotime($appt['start_time'])) : '');

            if ($status === 'cancelled') {
                $bucket = 'cancelled';
            } elseif ($status === 'completed' || ($date && $date < $today)) {
                $bucket = 'past';
            } else {
                $bucket = 'upcoming';
            }

            $groups[$bucket][] = $this->mapBooking($appt, $bucket, $today);
        }

        // Newest-first for past/cancelled, soonest-first for upcoming.
        $groups['past'] = array_reverse($groups['past']);
        $groups['cancelled'] = array_reverse($groups['cancelled']);

        return [
            'nextAppointment' => $this->buildNextAppointment($groups['upcoming']),
            'counts' => [
                'upcoming' => count($groups['upcoming']),
                'past' => count($groups['past']),
                'cancelled' => count($groups['cancelled']),
            ],
            'groups' => $groups,
        ];
    }

    /**
     * Map one appointment row to a booking card + detail object.
     */
    private function mapBooking(array $appt, string $bucket, string $today): array
    {
        $id = (int) ($appt['id'] ?? 0);
        $date = $appt['date'] ?? (isset($appt['start_time']) ? gmdate('Y-m-d', strtotime($appt['start_time'])) : '');
        $status = strtolower($appt['status'] ?? '');
        $paymentStatus = strtolower($appt['payment_status'] ?? 'unpaid');

        $service = !empty($appt['service_id']) ? ServiceModel::find((int) $appt['service_id']) : null;
        $agent = !empty($appt['agent_id']) ? $this->agentService->getAgent((int) $appt['agent_id']) : null;
        $location = $this->getLocation($appt['location_id'] ?? null);
        $order = $this->getOrderForBooking($id);

        // The live catalogue name, translated — not the booking's stored
        // snapshot. Per the multilingual plan's decision 0.4 the snapshot keeps
        // the *price* that was charged, while names follow the catalogue so a
        // customer reading the panel in German never sees an English service.
        $serviceTitle = $service
            ? rox_appointment_booking_translate('service', $service->getID(), 'title', (string) ($service->title ?? ''))
            : esc_html__('Service', 'rox-appointment-booking');
        $agentName = $agent ? $agent->full_name : '';
        $locationTitle = $location['title'] ?? '';

        $durationMinutes = $this->durationMinutes($appt, $service);
        [$pillStatus, $statusLabel] = $this->statusMeta($status);

        // This appointment's own service (+ extra services) price — an order
        // covering multiple appointments must not put its combined total on
        // every single card, or a 3-service order shows the same full amount
        // three times over.
        $amount = $this->appointmentLineTotal($appt, $service);
        $price = $this->money($amount);

        // What "Pay Now" actually charges. Each appointment gets its own
        // payment row now (see PaymentProcessingService::savePayment()), so
        // paying this card only settles this appointment's own line total —
        // it does NOT touch the other appointments sharing the same order.
        // A deposit (Pro feature) is the one exception: it's collected once
        // for the whole order and can't be attributed to a single line, so a
        // partially-paid line still falls back to the order's own remaining
        // balance (matches PayBooking's legacy order_id path).
        $isPartiallyPaid = $paymentStatus === 'partially_paid';
        if ($isPartiallyPaid && $order) {
            $detailAmount = (float) ($order->total_amount ?? 0);
            $dueAmount = (float) ($order->amount_due_later ?? $detailAmount);
        } else {
            $detailAmount = $amount;
            $dueAmount = $amount;
        }

        // "with {agent} · {location}" — drop whichever side is missing.
        $withParts = array_filter([
            $agentName ? sprintf(esc_html__('with %s', 'rox-appointment-booking'), $agentName) : '',
            $locationTitle,
        ]);
        $withText = implode(' · ', $withParts);

        $startLabel = !empty($appt['start_time']) ? gmdate('g:i A', strtotime($appt['start_time'])) : '';
        $endLabel = !empty($appt['end_time']) ? gmdate('g:i A', strtotime($appt['end_time'])) : '';
        $timeMeta = $startLabel ? ('🕐 ' . $startLabel . ($endLabel ? ' – ' . $endLabel : '')) : '';
        $durationLabel = $this->formatDuration($durationMinutes);

        $meta = array_values(array_filter([
            $timeMeta,
            $durationLabel ? ('⏱ ' . $durationLabel) : '',
            $location['address'] ? ('📍 ' . $location['address']) : ('💳 ' . $this->paymentWord($paymentStatus)),
        ]));

        return [
            'id' => $id,
            // Raw fields the reschedule/cancel/pay/book-again actions need (not
            // shown directly).
            'order_id' => $order ? (int) $order->getID() : null,
            'service_id' => !empty($appt['service_id']) ? (int) $appt['service_id'] : null,
            'agent_id' => !empty($appt['agent_id']) ? (int) $appt['agent_id'] : null,
            'category_id' => !empty($appt['category_id']) ? (int) $appt['category_id'] : null,
            'location_id' => !empty($appt['location_id']) ? (int) $appt['location_id'] : null,
            'date' => $date,
            'start_time_raw' => !empty($appt['start_time']) ? gmdate('H:i:s', strtotime($appt['start_time'])) : null,
            'month' => $date ? gmdate('M', strtotime($date)) : '',
            'day' => $date ? gmdate('d', strtotime($date)) : '',
            'weekday' => $date ? gmdate('l', strtotime($date)) : '',
            'status' => $pillStatus,
            'statusLabel' => $statusLabel,
            'relativeLabel' => $bucket === 'upcoming' ? $this->relativeLabel($date, $today) : null,
            'title' => $serviceTitle,
            'with' => $withText,
            'meta' => $meta,
            'price' => $price,
            'priceStrikethrough' => $status === 'cancelled',
            // What "Pay Now" actually charges — this appointment's own line
            // total, or the order's remaining balance for a deposit-paid line.
            'due_amount' => $dueAmount,
            'due_amount_formatted' => $this->money($dueAmount),
            // Only set when a deposit was already paid — the card shows the
            // remaining balance as its headline number instead of the full price.
            'balance_due_formatted' => $isPartiallyPaid ? $this->money($dueAmount) : null,
            'completed' => $status === 'completed',
            'actions' => $this->actionsFor($bucket, $status, $paymentStatus),
            'detail' => $this->buildDetail($appt, [
                'service' => $serviceTitle,
                'agent' => $agentName,
                'location' => $location,
                'durationLabel' => $durationLabel,
                'order' => $order,
                'amount' => $detailAmount,
                'price' => $price,
                'paymentStatus' => $paymentStatus,
                'dueAmount' => $dueAmount,
                'isPartiallyPaid' => $isPartiallyPaid,
            ]),
        ];
    }

    /**
     * Detail-drawer payload (Booking Information / Payment / Notes).
     */
    private function buildDetail(array $appt, array $ctx): array
    {
        $order = $ctx['order'];

        $info = array_values(array_filter([
            ['label' => esc_html__('Service', 'rox-appointment-booking'), 'value' => $ctx['service']],
            $ctx['agent'] ? ['label' => esc_html__('Provider', 'rox-appointment-booking'), 'value' => $ctx['agent']] : null,
            $ctx['durationLabel'] ? ['label' => esc_html__('Duration', 'rox-appointment-booking'), 'value' => $ctx['durationLabel']] : null,
            $ctx['location']['title'] ? ['label' => esc_html__('Location', 'rox-appointment-booking'), 'value' => $ctx['location']['title']] : null,
            $ctx['location']['address'] ? ['label' => esc_html__('Address', 'rox-appointment-booking'), 'value' => $ctx['location']['address']] : null,
        ]));

        $payment = [];
        if ($order) {
            $tax = (float) ($order->tax_amount ?? 0);
            $discount = (float) ($order->discount_amount ?? 0);
            $payment[] = ['label' => esc_html__('Service charge', 'rox-appointment-booking'), 'value' => $this->money($ctx['amount'])];
            if ($tax > 0) {
                $payment[] = ['label' => esc_html__('Tax', 'rox-appointment-booking'), 'value' => $this->money($tax)];
            }
            if ($discount > 0) {
                $payment[] = ['label' => esc_html__('Discount', 'rox-appointment-booking'), 'value' => '-' . $this->money($discount)];
            }
        } else {
            $payment[] = ['label' => esc_html__('Service charge', 'rox-appointment-booking'), 'value' => $ctx['price']];
        }

        // A deposit already paid — break out how much was paid vs. what's
        // still owed, same as the Order/Appointment admin views.
        if (!empty($ctx['isPartiallyPaid'])) {
            $payment[] = ['label' => esc_html__('Deposit Paid', 'rox-appointment-booking'), 'value' => $this->money($ctx['amount'] - $ctx['dueAmount'])];
            $payment[] = ['label' => esc_html__('Balance Due', 'rox-appointment-booking'), 'value' => $this->money($ctx['dueAmount'])];
        }

        // The headline "Total" row shows what's actually still owed once a
        // deposit is paid, not the full price — matches the booking card.
        $totalAmount = !empty($ctx['isPartiallyPaid']) ? $this->money($ctx['dueAmount']) : $ctx['price'];

        return [
            'info' => $info,
            'payment' => $payment,
            'paymentTotal' => $totalAmount . ' · ' . $this->paymentWord($ctx['paymentStatus']),
            'paymentTotalColor' => $this->paymentColor($ctx['paymentStatus']),
            'notes' => $appt['internal_notes'] ?? '',
        ];
    }

    /** Soonest upcoming booking → hero appointment summary (never null). */
    private function buildNextAppointment(array $upcoming): array
    {
        if (empty($upcoming)) {
            return [
                'title' => esc_html__('You have no upcoming appointments', 'rox-appointment-booking'),
                'sub' => esc_html__('Book a new appointment to get started.', 'rox-appointment-booking'),
            ];
        }

        $next = $upcoming[0];
        $rel = $next['relativeLabel'] ? strtolower($next['relativeLabel']) : esc_html__('soon', 'rox-appointment-booking');
        $time = $next['meta'][0] ?? '';
        $time = trim(str_replace('🕐', '', $time));
        $timePart = $time ? sprintf(esc_html__(' at %s', 'rox-appointment-booking'), explode('–', $time)[0]) : '';

        return [
            'title' => sprintf(esc_html__('Your next appointment is %1$s%2$s', 'rox-appointment-booking'), $rel, $timePart),
            'sub' => trim($next['title'] . ' ' . $next['with']),
        ];
    }

    private function actionsFor(string $bucket, string $status, string $paymentStatus): array
    {
        if ($bucket === 'past' || $bucket === 'cancelled') {
            return [['type' => 'bookAgain', 'label' => esc_html__('Book Again', 'rox-appointment-booking'), 'variant' => 'secondary']];
        }

        if ($status === 'pending' && $paymentStatus !== 'paid') {
            return [['type' => 'payNow', 'label' => esc_html__('Pay Now', 'rox-appointment-booking'), 'variant' => 'primary']];
        }

        // The booking card's Reschedule/Cancel buttons follow the same admin
        // switches as the detail drawer's (Settings > Booking). With both off
        // the card keeps its price and stays clickable, just without actions.
        return array_values(array_filter([
            rox_appointment_booking_customer_can_reschedule()
                ? ['type' => 'reschedule', 'label' => esc_html__('Reschedule', 'rox-appointment-booking'), 'variant' => 'secondary']
                : null,
            rox_appointment_booking_customer_can_cancel()
                ? ['type' => 'cancel', 'label' => esc_html__('Cancel', 'rox-appointment-booking'), 'variant' => 'secondary']
                : null,
        ]));
    }

    /** @return array{0:string,1:string} [pillStatus, label] */
    private function statusMeta(string $status): array
    {
        switch ($status) {
            case 'approved':  return ['approved', '✓ ' . esc_html__('Approved', 'rox-appointment-booking')];
            case 'pending':   return ['pending', '⏱ ' . esc_html__('Pending Approval', 'rox-appointment-booking')];
            case 'completed': return ['completed', '✓ ' . esc_html__('Completed', 'rox-appointment-booking')];
            case 'cancelled': return ['cancelled', '✕ ' . esc_html__('Cancelled', 'rox-appointment-booking')];
            case 'no_show':   return ['cancelled', '✕ ' . esc_html__('No Show', 'rox-appointment-booking')];
            default:          return ['pending', ucfirst($status)];
        }
    }

    private function paymentWord(string $paymentStatus): string
    {
        switch ($paymentStatus) {
            case 'paid':           return esc_html__('Fully Paid', 'rox-appointment-booking');
            case 'partially_paid': return esc_html__('Partially Paid', 'rox-appointment-booking');
            case 'refunded':       return esc_html__('Refunded', 'rox-appointment-booking');
            case 'failed':         return esc_html__('Payment Failed', 'rox-appointment-booking');
            case 'processing':     return esc_html__('Processing', 'rox-appointment-booking');
            default:               return esc_html__('Not Paid', 'rox-appointment-booking');
        }
    }

    private function paymentColor(string $paymentStatus): string
    {
        switch ($paymentStatus) {
            case 'paid':           return 'var(--green-text)';
            case 'partially_paid': return 'var(--yellow-text)';
            case 'refunded':       return 'var(--teal-text)';
            case 'failed':         return 'var(--red-text)';
            default:               return 'var(--yellow-text)';
        }
    }

    private function relativeLabel(string $date, string $today): ?string
    {
        if (!$date) {
            return null;
        }
        $days = (int) floor((strtotime($date) - strtotime($today)) / DAY_IN_SECONDS);
        if ($days <= 0) {
            return esc_html__('Today', 'rox-appointment-booking');
        }
        if ($days === 1) {
            return esc_html__('Tomorrow', 'rox-appointment-booking');
        }
        // translators: %d = number of days
        return sprintf(esc_html__('In %d days', 'rox-appointment-booking'), $days);
    }

    private function durationMinutes(array $appt, ?ServiceModel $service): int
    {
        if (!empty($appt['start_time']) && !empty($appt['end_time'])) {
            $diff = (int) round((strtotime($appt['end_time']) - strtotime($appt['start_time'])) / 60);
            if ($diff > 0) {
                return $diff;
            }
        }
        return $service ? (int) ($service->duration ?? 0) : 0;
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }
        if ($minutes >= 60) {
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;
            return $m === 0 ? $h . 'h' : $h . 'h ' . $m . 'm';
        }
        return $minutes . 'm';
    }

    private function money(float $amount): string
    {
        return $this->currency . number_format($amount, 2);
    }

    /**
     * Location title + a single-line address from the core location table.
     *
     * @param int|null $locationId
     * @return array{title:string,address:string}
     */
    private function getLocation($locationId): array
    {
        if (empty($locationId)) {
            return ['title' => '', 'address' => ''];
        }
        global $wpdb;
        $table = ROX_APPOINTMENT_BOOKING_DB_PREFIX . ROX_APPOINTMENT_BOOKING_PREFIX . '_location';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no core Location model; table created by core.
        $row = $wpdb->get_row($wpdb->prepare("SELECT title, address FROM $table WHERE id = %d", (int) $locationId), ARRAY_A);
        if (!$row) {
            return ['title' => '', 'address' => ''];
        }
        // Reads the location table directly rather than through a model, so it
        // needs the same translation the public location endpoint applies.
        $locationId = (int) $locationId;
        return [
            'title'   => rox_appointment_booking_translate('location', $locationId, 'title', (string) ($row['title'] ?? '')),
            'address' => rox_appointment_booking_translate('location', $locationId, 'address', (string) ($row['address'] ?? '')),
        ];
    }

    /**
     * One appointment's own line total: its service price plus any Pro
     * extra-service prices. Mirrors
     * FrontendBookingPanel\Services\AppointmentService::calculateAppointmentLineTotal()
     * so the price shown here matches what was actually charged for this line.
     */
    private function appointmentLineTotal(array $appt, ?ServiceModel $service): float
    {
        $lineTotal = $service ? (float) $service->price : 0.0;

        $extraServiceIds = $appt['extra_services'] ?? [];
        if (!empty($extraServiceIds) && is_array($extraServiceIds) && class_exists('\\RoxAppointmentBookingPro\\Modules\\ExtraService\\Data\\ExtraServiceModel')) {
            foreach ($extraServiceIds as $extraId) {
                $extraService = \RoxAppointmentBookingPro\Modules\ExtraService\Data\ExtraServiceModel::find(intval($extraId));
                if ($extraService) {
                    $lineTotal += (float) $extraService->price;
                }
            }
        }

        return $lineTotal;
    }

    private function getOrderForBooking(int $bookingId): ?OrderModel
    {
        if ($bookingId <= 0) {
            return null;
        }
        return OrderModel::whereRaw('JSON_CONTAINS(booking_ids, %s)', [wp_json_encode($bookingId)])->first();
    }
}
