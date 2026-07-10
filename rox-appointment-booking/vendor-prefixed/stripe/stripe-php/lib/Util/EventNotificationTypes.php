<?php

namespace RoxAppointmentBookingVendors\Stripe\Util;

class EventNotificationTypes
{
    const v2EventMapping = [
        // The beginning of the section generated from our OpenAPI spec
        \RoxAppointmentBookingVendors\Stripe\Events\V1BillingMeterErrorReportTriggeredEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V1BillingMeterErrorReportTriggeredEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V1BillingMeterNoMeterFoundEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V1BillingMeterNoMeterFoundEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountClosedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountClosedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountCreatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountCreatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationCustomerCapabilityStatusUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationCustomerCapabilityStatusUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationCustomerUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationCustomerUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationMerchantCapabilityStatusUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationMerchantCapabilityStatusUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationMerchantUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationMerchantUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationRecipientCapabilityStatusUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationRecipientCapabilityStatusUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationRecipientUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingConfigurationRecipientUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingDefaultsUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingDefaultsUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingFutureRequirementsUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingFutureRequirementsUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingIdentityUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingIdentityUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingRequirementsUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountIncludingRequirementsUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountLinkReturnedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountLinkReturnedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonCreatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonCreatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonDeletedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonDeletedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonUpdatedEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreAccountPersonUpdatedEventNotification::class,
        \RoxAppointmentBookingVendors\Stripe\Events\V2CoreEventDestinationPingEventNotification::LOOKUP_TYPE => \RoxAppointmentBookingVendors\Stripe\Events\V2CoreEventDestinationPingEventNotification::class,
    ];
}
