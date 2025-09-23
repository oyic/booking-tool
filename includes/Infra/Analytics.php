<?php

namespace LM\Booking\Infra;

class Analytics
{
    public static function trackFormView(): void
    {
        do_action('lm_booking_view_form');
    }

    public static function trackStepChange(string $step): void
    {
        do_action('lm_booking_change_step', $step);
    }

    public static function trackSubmitSuccess(array $data): void
    {
        do_action('lm_booking_submit_success', $data);
    }

    public static function trackSubmitError(string $error, array $data = []): void
    {
        do_action('lm_booking_submit_error', $error, $data);
    }

    public static function trackServiceSelection(string $service): void
    {
        do_action('lm_booking_service_selection', $service);
    }

    public static function trackDeliverySelection(string $delivery): void
    {
        do_action('lm_booking_delivery_selection', $delivery);
    }

    public static function trackExtrasSelection(array $extras): void
    {
        do_action('lm_booking_extras_selection', $extras);
    }
}

