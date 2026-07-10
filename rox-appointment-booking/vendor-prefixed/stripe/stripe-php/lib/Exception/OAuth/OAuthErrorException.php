<?php

namespace RoxAppointmentBookingVendors\Stripe\Exception\OAuth;

/**
 * Implements properties and methods common to all (non-SPL) Stripe OAuth
 * exceptions.
 */
abstract class OAuthErrorException extends \RoxAppointmentBookingVendors\Stripe\Exception\ApiErrorException
{
    protected function constructErrorObject()
    {
        if (null === $this->jsonBody) {
            return null;
        }
        return \RoxAppointmentBookingVendors\Stripe\OAuthErrorObject::constructFrom($this->jsonBody);
    }
}
