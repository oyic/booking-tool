<?php

namespace LM\Booking\Admin;

class Settings
{
    public function init(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function registerSettings(): void
    {
        register_setting('lm_booking_group', 'lm_booking_settings', [
            'sanitize_callback' => [$this, 'sanitizeMainSettings']
        ]);
    }

    public function sanitizeMainSettings($value): array
    {
        // Get existing settings to preserve data from other tabs
        $existing_settings = get_option('lm_booking_settings', []);
        
        // Handle voucher settings from admin-vouchers.php
        if (isset($_POST['action']) && $_POST['action'] === 'update_settings' && isset($_POST['signup_discount']) && isset($_POST['signup_expiry_days'])) {
            $existing_settings['voucher_signup_discount'] = floatval($_POST['signup_discount']);
            $existing_settings['voucher_signup_expiry_days'] = intval($_POST['signup_expiry_days']);
            return $existing_settings;
        }
        
        if (isset($_POST['lm_booking_settings']) && is_array($_POST['lm_booking_settings'])) {
            $active_tab = $_POST['lm_booking_settings']['active_tab'] ?? '';
            
            // Start with existing settings to preserve other tabs
            $sanitized = $existing_settings;
            
            // Add the active tab indicator
            $sanitized['active_tab'] = sanitize_text_field($active_tab);
            
            // Clear old data for the active tab BEFORE processing new data
            if ($active_tab === 'packages') {
                // Clear old package data
                unset($sanitized['services']);
                foreach ($sanitized as $key => $value) {
                    if (strpos($key, 'package_') === 0) {
                        unset($sanitized[$key]);
                    }
                }
            } elseif ($active_tab === 'extras') {
                // Clear old extras data
                unset($sanitized['extras']);
                foreach ($sanitized as $key => $value) {
                    if (strpos($key, 'extra_') === 0) {
                        unset($sanitized[$key]);
                    }
                }
            } elseif ($active_tab === 'delivery') {
                // Clear old delivery data
                unset($sanitized['delivery_options']);
                foreach ($sanitized as $key => $value) {
                    if (strpos($key, 'delivery_') === 0) {
                        unset($sanitized[$key]);
                    }
                }
            } elseif ($active_tab === 'emails') {
                // Clear old email data
                foreach ($sanitized as $key => $value) {
                    if (in_array($key, ['admin_email', 'customer_email_subject', 'admin_email_subject', 'booking_email_template', 'voucher_email_template'])) {
                        unset($sanitized[$key]);
                    }
                }
            }
            
            foreach ($_POST['lm_booking_settings'] as $key => $val) {
                if ($key === 'active_tab') {
                    continue; // Already handled above
                }
                
                // Only process data for the active tab
                if ($active_tab === 'packages' && (strpos($key, 'package_') === 0 || $key === 'featured_package')) {
                    $sanitized[$key] = sanitize_text_field($val);
                }
                elseif ($active_tab === 'extras' && strpos($key, 'extra_') === 0) {
                    // Handle array fields (like extra_packages_)
                    if (is_array($val)) {
                        $sanitized[$key] = array_map('sanitize_text_field', $val);
                    } else {
                        $sanitized[$key] = sanitize_text_field($val);
                    }
                }
                elseif ($active_tab === 'delivery' && strpos($key, 'delivery_') === 0) {
                    $sanitized[$key] = sanitize_text_field($val);
                }
                elseif ($active_tab === 'language' && in_array($key, ['language', 'date_format', 'currency'])) {
                    $sanitized[$key] = sanitize_text_field($val);
                }
                elseif ($active_tab === 'emails' && in_array($key, ['admin_email', 'customer_email_subject', 'admin_email_subject', 'booking_email_template', 'voucher_email_template'])) {
                    // Handle email templates with wp_kses_post to allow HTML
                    if (in_array($key, ['booking_email_template', 'voucher_email_template'])) {
                        $sanitized[$key] = wp_kses_post($val);
                    } else {
                        $sanitized[$key] = sanitize_text_field($val);
                    }
                }
                elseif ($active_tab === 'advanced' && in_array($key, ['debug_mode', 'strict_validation', 'auto_save'])) {
                    $sanitized[$key] = sanitize_text_field($val);
                }
            }
            
            // Reconstruct arrays from individual fields for the active tab
            $sanitized = $this->reconstructArrays($sanitized, $active_tab);
            
            // Always reconstruct delivery options for display, regardless of active tab
            $sanitized = $this->reconstructDeliveryOptions($sanitized);
        } else {
            // If no POST data, return existing settings
            $sanitized = $existing_settings;
            // Always reconstruct delivery options for display
            $sanitized = $this->reconstructDeliveryOptions($sanitized);
        }
        
        return $sanitized;
    }
    
    private function reconstructArrays(array $settings, string $active_tab): array
    {
        // Reconstruct services array
        if ($active_tab === 'packages') {
            $services = [];
            $featuredIndex = $settings['featured_package'] ?? null;
            
            foreach ($settings as $key => $value) {
                if (strpos($key, 'package_label_') === 0) {
                    $index = str_replace('package_label_', '', $key);
                    $label = $value;
                    $price = $settings['package_price_' . $index] ?? 0;
                    $description = $settings['package_description_' . $index] ?? '';
                    $featured = ($index == $featuredIndex);
                    
                    if (!empty($label)) {
                        $services[] = [
                            'label' => $label,
                            'price' => floatval($price),
                            'description' => $description,
                            'featured' => $featured
                        ];
                    }
                }
            }
            
            if (!empty($services)) {
                $settings['services'] = $services;
            }
        }
        
        // Reconstruct extras array
        if ($active_tab === 'extras') {
            $extras = [];
            
            foreach ($settings as $key => $value) {
                if (strpos($key, 'extra_label_') === 0) {
                    $index = str_replace('extra_label_', '', $key);
                    $label = $value;
                    $price = $settings['extra_price_' . $index] ?? 0;
                    $description = $settings['extra_description_' . $index] ?? '';
                    $packageInclusive = isset($settings['extra_inclusive_' . $index]) && $settings['extra_inclusive_' . $index] === '1';
                    
                    // Get included packages for this extra
                    $includedPackages = [];
                    if (isset($settings['extra_packages_' . $index]) && is_array($settings['extra_packages_' . $index])) {
                        $includedPackages = array_map('intval', $settings['extra_packages_' . $index]);
                    }
                    
                    if (!empty($label)) {
                        $extras[] = [
                            'label' => $label,
                            'price' => floatval($price),
                            'description' => $description,
                            'package_inclusive' => $packageInclusive,
                            'included_packages' => $includedPackages
                        ];
                    }
                }
            }
            
            if (!empty($extras)) {
                $settings['extras'] = $extras;
            }
        }
        
        // Reconstruct delivery options array (only when saving delivery tab)
        if ($active_tab === 'delivery') {
            $settings = $this->reconstructDeliveryOptions($settings);
        }
        
        return $settings;
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
        
        if (!empty($deliveryOptions)) {
            $settings['delivery_options'] = $deliveryOptions;
        }
        
        return $settings;
    }
}