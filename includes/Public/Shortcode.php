<?php

namespace LM\Booking\Public;

use LM\Booking\Infra\Analytics;

class Shortcode
{
    public function init(): void
    {
        add_shortcode('lm_booking_form', [$this, 'renderForm']);
        add_shortcode('lm_voucher_signup', [$this, 'renderVoucherSignup']);
    }

    public function renderForm(): string
    {
        Analytics::trackFormView();
        
        ob_start();
        include LM_BOOKING_DIR . 'templates/form.php';
        return ob_get_clean();
    }

    public function renderVoucherSignup($atts): string
    {
        $atts = shortcode_atts([
            'button_text' => __('Anmelden', 'lm-booking'),
            'discount' => '',
            'expiry_days' => ''
        ], $atts);

        Analytics::trackFormView();
        
        ob_start();
        include LM_BOOKING_DIR . 'templates/voucher-signup.php';
        return ob_get_clean();
    }
}
