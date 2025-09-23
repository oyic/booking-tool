<?php

namespace LM\Booking\Domain;

class Pricing
{
    public static function calculate(
        float $servicePrice,
        array $extras = [],
        ?int $words = null,
        ?string $delivery = 'standard'
    ): array {
        // If words is less than 250, treat as 1 page
        if (($words ?? 0) < 250) {
            $normPages = 1;
        } else {
            $normPages = ceil(($words ?? 0) / 250);
        }
        $base = $normPages * $servicePrice;
        // Exclude package inclusive extras from total calculation
        $extrasTotal = array_sum(array_map(function($extra) {
            // Check if extra is included in the selected package
            $isPackageInclusive = ($extra['package_inclusive'] ?? false);
            $includedInSelectedPackage = in_array($selectedPackageIndex, $extra['included_packages'] ?? []);
            $shouldExclude = $isPackageInclusive || $includedInSelectedPackage;
            return $shouldExclude ? 0 : $extra['price'];
        }, $extras));
        $subtotal = $base + $extrasTotal;
        
        // Get delivery surcharge from settings
        $settings = get_option('lm_booking_settings', []);
        $deliveryOptions = $settings['delivery_options'] ?? [];
        
        $surcharge = 0;
        $multiplier = 1.00;
        
        // Find matching delivery option
        foreach ($deliveryOptions as $key => $option) {
            if ($option['days'] . 'd' === $delivery) {
                $surcharge = $option['surcharge'] ?? 0;
                $multiplier = 1 + ($surcharge / 100);
                break;
            }
        }
        
        $total = round($subtotal * $multiplier, 2);

        $breakdown = [
            'normPages' => $normPages,
            'base' => $base,
            'extras' => $extras,
            'extrasTotal' => $extrasTotal,
            'subtotal' => $subtotal,
            'surcharge' => $surcharge,
            'total' => $total,
            'delivery' => $delivery,
            'multiplier' => $multiplier,
        ];

        return $breakdown;
    }

    public static function calculateDeliveryDate(string $delivery, bool $buffer24h = true): string
    {
        $days = 3;
        if ($delivery === '2d') {
            $days = 2;
        } elseif ($delivery === '1d') {
            $days = 1;
        } elseif ($delivery === '3d') {
            $days = 3;
        }
        
        $timestamp = current_time('timestamp');
        $timestamp += $days * DAY_IN_SECONDS;
        
        // Get buffer hours from delivery options
        $settings = get_option('lm_booking_settings', []);
        $deliveryOptions = $settings['delivery_options'] ?? [];
        $bufferHours = 24; // Default buffer
        
        // Find matching delivery option and get its buffer hours
        foreach ($deliveryOptions as $option) {
            if (isset($option['days']) && $option['days'] == $days) {
                $bufferHours = $option['buffer_hours'] ?? 24;
                break;
            }
        }
        
        if ($buffer24h && $bufferHours > 0) {
            $timestamp += $bufferHours * HOUR_IN_SECONDS;
        }
        
        return date('Y-m-d H:i', $timestamp);
    }
}
