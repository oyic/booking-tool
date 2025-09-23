<?php

namespace LM\Booking;

class Activator
{
    public static function activate(): void
    {
        self::setupDefaultGermanSettings();
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    private static function setupDefaultGermanSettings(): void
    {
        // Always set German defaults on activation (overwrites existing settings)
        $settings = self::getDefaultGermanSettings();
        update_option('lm_booking_settings', $settings);
        
        // Log the activation
    }

    private static function getDefaultGermanSettings(): array
    {
        return [
            'services' => [
                [
                    'label' => 'Premium Lektorat',
                    'price' => 5.99,
                    'description' => 'Professionelles Lektorat und Korrekturlesen',
                    'featured' => false
                ],
                [
                    'label' => 'Mac Formatierung',
                    'price' => 6.99,
                    'description' => 'Akademische Formatierung nach Richtlinien',
                    'featured' => false
                ],
                [
                    'label' => 'All-In Service',
                    'price' => 7.99,
                    'description' => 'Komplettes Lektorat, Formatierung und Überprüfung',
                    'featured' => true
                ],
            ],
            'extras' => [],
            'delivery_options' => [
                'normal' => ['label' => '3 Tage (Normal)', 'days' => 3, 'surcharge' => 0, 'enabled' => true],
                'fast' => ['label' => '2 Tage', 'days' => 2, 'surcharge' => 15, 'enabled' => true],
                'express' => ['label' => '1 Tag', 'days' => 1, 'surcharge' => 50, 'enabled' => true],
            ],
            'delivery' => [
                'buffer24h' => true,
                'weekend_delivery' => false,
            ],
            'i18n' => [
                'labels' => [
                    'noExtras' => 'Keine Extras',
                    'selectService' => 'Service auswählen',
                    'selectDelivery' => 'Lieferzeit auswählen',
                    'totalPrice' => 'Gesamtpreis',
                    'deliveryDate' => 'Lieferdatum',
                ],
                'buttons' => [
                    'toExtras' => 'Zu den Extras',
                    'lastStep' => 'Letzter Schritt',
                    'getOffer' => 'Angebot erhalten',
                    'next' => 'Weiter',
                    'backToHome' => 'Zurück zur Startseite',
                ],
                'validation' => [
                    'required' => 'Dieses Feld ist erforderlich',
                    'invalidEmail' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein',
                    'consentRequired' => 'Sie müssen den AGB zustimmen',
                ],
            ]
        ];
    }

    /**
     * Force reset to German defaults (for development/testing)
     */
    public static function resetToGermanDefaults(): void
    {
        self::setupDefaultGermanSettings();
        
        // Clear any cached data
        delete_transient('lm_booking_stats');
        delete_transient('lm_booking_list');
        
    }

    /**
     * Check if settings exist and are populated
     */
    public static function hasSettings(): bool
    {
        $settings = get_option('lm_booking_settings', []);
        return !empty($settings) && isset($settings['services']);
    }
}
