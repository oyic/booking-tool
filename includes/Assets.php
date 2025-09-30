<?php

namespace LM\Booking;

class Assets
{
    public function init(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        if (!is_singular() && !is_page()) {
            return;
        }

        global $post;
        if (!$post || (!has_shortcode($post->post_content, 'lm_booking_form') && !has_shortcode($post->post_content, 'lm_voucher_signup'))) {
            return;
        }

        wp_enqueue_style(
            'lm-booking-form',
            LM_BOOKING_URL . 'assets/css/form.css',
            [],
            LM_BOOKING_VERSION
        );

        wp_enqueue_script(
            'lm-booking-form',
            LM_BOOKING_URL . 'assets/js/form.js',
            ['jquery'],
            LM_BOOKING_VERSION,
            true
        );

        $settings = get_option('lm_booking_settings', []);
        
        // Load services, extras, and delivery options from settings
        $services = $settings['services'] ?? [];
        $extras = $settings['extras'] ?? [];
        $deliveryOptions = $settings['delivery_options'] ?? [];
        
        // If delivery options are not properly loaded, try to reconstruct them
        if (empty($deliveryOptions)) {
            $deliveryOptions = $this->reconstructDeliveryOptions($settings);
        }
        
        // Ensure delivery options is always an array
        if (!is_array($deliveryOptions)) {
            $deliveryOptions = [];
        }
        
        wp_localize_script('lm-booking-form', 'lmBookingAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lm_booking_nonce'),
            'voucher_nonce' => wp_create_nonce('lm_voucher_validation'),
            'services' => $services,
            'extras' => $extras,
            'delivery' => [
                'enabled' => $settings['delivery_enabled'] ?? true,
                'buffer24h' => $settings['delivery_buffer24h'] ?? true,
                'options' => $deliveryOptions,
            ],
            'i18n' => [
                'steps' => [
                    'package' => __('Dein Paket', 'lm-booking'),
                    'extras' => __('Extras', 'lm-booking'),
                    'information' => __('Deine Informationen', 'lm-booking'),
                ],
                'buttons' => [
                    'next' => __('Weiter', 'lm-booking'),
                    'back' => __('Zurück', 'lm-booking'),
                    'toExtras' => __('Zu den Extras', 'lm-booking'),
                    'lastStep' => __('Letzter Schritt', 'lm-booking'),
                    'getOffer' => __('Angebot erhalten', 'lm-booking'),
                    'backToHome' => __('Zurück zur Startseite', 'lm-booking'),
                    'uploadFile' => __('Datei hochladen', 'lm-booking'),
                ],
                'labels' => [
                    'words' => __('Wörter', 'lm-booking'),
                    'normPages' => __('Normenseiten', 'lm-booking'),
                    'delivery' => __('Lieferung', 'lm-booking'),
                    'extras' => __('Extra Leistungen', 'lm-booking'),
                    'base' => __('Basis', 'lm-booking'),
                    'surcharge' => __('Aufschlag', 'lm-booking'),
                    'total' => __('Gesamtpreis', 'lm-booking'),
                    'deliveryDate' => __('Lieferdatum', 'lm-booking'),
                    'package' => __('Dein Paket', 'lm-booking'),
                    'duration' => __('Gewählte Zeitdauer', 'lm-booking'),
                    'noExtras' => __('keine ausgewählt', 'lm-booking'),
                    'configuration' => __('Deine Konfiguration', 'lm-booking'),
                    'estimatedDelivery' => __('Voraussichtliche lieferung bis zum', 'lm-booking'),
                ],
                'delivery' => [
                    'express' => __('Express Lieferung', 'lm-booking'),
                    'fast' => __('Schnelle Lieferung', 'lm-booking'),
                    'normal' => __('Normale Lieferung', 'lm-booking'),
                    'expressDesc' => __('1 Tag / 50% Aufschlag', 'lm-booking'),
                    'fastDesc' => __('2 Tage / 15% Aufschlag', 'lm-booking'),
                    'normalDesc' => __('3 Tage', 'lm-booking'),
                ],
                'validation' => [
                    'required' => __('Dieses Feld ist erforderlich', 'lm-booking'),
                    'invalidEmail' => __('Bitte geben Sie eine gültige E-Mail-Adresse ein', 'lm-booking'),
                    'minWords' => __('Bitte laden Sie zuerst eine Datei hoch, um fortzufahren', 'lm-booking'),
                    'consentRequired' => __('Sie müssen den AGB und der Datenschutzerklärung zustimmen', 'lm-booking'),
                ],
                'success' => [
                    'title' => __('Buchung erfolgreich abgeschlossen', 'lm-booking'),
                    'thankYou' => __('Vielen Dank für Ihre Anfrage!', 'lm-booking'),
                    'submitted' => __('Ihre Angaben wurden erfolgreich an unser Team übermittelt. Wir prüfen Ihre Unterlagen sorgfältig und setzen uns schnellstmöglich mit Ihnen in Verbindung. Innerhalb der nächsten 24 Stunden erhalten Sie von uns eine Rückmeldung sowie die passende Rechnung.', 'lm-booking'),
                    'contact' => __('Sollten währenddessen Fragen auftreten, können Sie uns jederzeit kontaktieren – wir helfen Ihnen gerne weiter.', 'lm-booking'),
                    'welcome' => __('Wir freuen uns darauf, Sie bald bei uns willkommen zu heißen und wünschen Ihnen bis dahin alles Gute.', 'lm-booking'),
                ],
                'help' => __('Hilfe', 'lm-booking'),
            ],
        ]);
    }
    
    /**
     * Reconstruct delivery options array from individual fields
     */
    private function reconstructDeliveryOptions(array $settings): array
    {
        $deliveryOptions = [];
        
        foreach ($settings as $key => $value) {
            if (strpos($key, 'delivery_label_') === 0) {
                $deliveryKey = str_replace('delivery_label_', '', $key);
                $label = $value;
                $days = $settings['delivery_days_' . $deliveryKey] ?? 3;
                
                // Handle both 'delivery_surcharge_' and 'delivery_price_' field names
                $surcharge = $settings['delivery_surcharge_' . $deliveryKey] ?? $settings['delivery_price_' . $deliveryKey] ?? 0;
                
                $bufferHours = $settings['delivery_buffer_hours_' . $deliveryKey] ?? 24;
                $enabled = isset($settings['delivery_enabled_' . $deliveryKey]) && $settings['delivery_enabled_' . $deliveryKey] === '1';
                
                if (!empty($label)) {
                    $deliveryOptions[] = [
                        'label' => $label,
                        'days' => intval($days),
                        'surcharge' => floatval($surcharge),
                        'buffer_hours' => intval($bufferHours),
                        'enabled' => $enabled
                    ];
                }
            }
        }
        
        return $deliveryOptions;
    }
}
