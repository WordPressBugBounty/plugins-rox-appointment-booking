<?php

// File generated from our OpenAPI spec
namespace RoxAppointmentBookingVendors\Stripe\Events;

/**
 * @property \Stripe\EventData\V2CoreAccountLinkReturnedEventData $data data associated with the event
 */
class V2CoreAccountLinkReturnedEvent extends \RoxAppointmentBookingVendors\Stripe\V2\Core\Event
{
    const LOOKUP_TYPE = 'v2.core.account_link.returned';
    public static function constructFrom($values, $opts = null, $apiMode = 'v2')
    {
        $evt = parent::constructFrom($values, $opts, $apiMode);
        if (null !== $evt->data) {
            $evt->data = \RoxAppointmentBookingVendors\Stripe\EventData\V2CoreAccountLinkReturnedEventData::constructFrom($evt->data, $opts, $apiMode);
        }
        return $evt;
    }
}
