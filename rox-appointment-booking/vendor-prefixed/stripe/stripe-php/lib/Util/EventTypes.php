<?php

namespace RoxAppointmentBookingVendors\Stripe\Util;

class EventTypes
{
    const v2EventMapping = [
        // The beginning of the section generated from our OpenAPI spec
        \RoxAppointmentBookingVendors\Stripe\Events\V1BillingMeterErrorReportTriggeredEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V1BillingMeterErrorReportTriggeredEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V1BillingMeterNoMeterFoundEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V1BillingMeterNoMeterFoundEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountClosedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountClosedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountCreatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountCreatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationCustomerCapabilityStatusUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationCustomerCapabilityStatusUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationCustomerUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationCustomerUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationMerchantCapabilityStatusUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationMerchantCapabilityStatusUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationMerchantUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationMerchantUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationRecipientCapabilityStatusUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationRecipientCapabilityStatusUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationRecipientUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationRecipientUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingDefaultsUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingDefaultsUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingFutureRequirementsUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingFutureRequirementsUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingIdentityUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingIdentityUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingRequirementsUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingRequirementsUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountLinkReturnedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountLinkReturnedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonCreatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonCreatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonDeletedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonDeletedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonUpdatedEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonUpdatedEvent::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreEventDestinationPingEvent::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreEventDestinationPingEvent::class,
    ];
}
