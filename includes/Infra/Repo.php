<?php

namespace LM\Booking\Infra;

class Repo
{
    public function saveBooking(array $data): int
    {
        
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $service = $data['service'] ?? '';
        $words = absint($data['words'] ?? 0);
        $delivery = sanitize_text_field($data['delivery'] ?? 'standard');
        $extras = is_array($data['extras'] ?? []) ? $data['extras'] : [];
        $total = floatval($data['total'] ?? 0);
        $breakdown = sanitize_text_field($data['breakdown'] ?? '');
        $normPages = absint($data['norm_pages'] ?? 0);
        $deliveryDate = sanitize_text_field($data['delivery_date'] ?? '');
        $consent = $data['consent'] ?? false;
        $voucherCode = sanitize_text_field($data['voucher_code'] ?? '');
        $voucherDiscount = absint($data['voucher_discount'] ?? 0);
        $note = sanitize_textarea_field($data['note'] ?? '');
        $originalTotal = floatval($data['original_total'] ?? $total);


        $postTitle = sprintf(
            '%s - %s',
            $name,
            current_time('Y-m-d H:i:s')
        );

        $postId = wp_insert_post([
            'post_title' => $postTitle,
            'post_type' => 'lm_booking',
            'post_status' => 'publish',
            'post_content' => '',
        ]);

        if (is_wp_error($postId)) {
            return 0;
        }


        update_post_meta($postId, '_lm_booking_customer_name', $name);
        update_post_meta($postId, '_lm_booking_customer_email', $email);
        update_post_meta($postId, '_lm_booking_service', $service);
        update_post_meta($postId, '_lm_booking_words', $words);
        update_post_meta($postId, '_lm_booking_norm_pages', $normPages);
        update_post_meta($postId, '_lm_booking_delivery', $delivery);
        update_post_meta($postId, '_lm_booking_delivery_date', $deliveryDate);
        
        update_post_meta($postId, '_lm_booking_extras', wp_json_encode($extras));
        
        // Calculate final total (with voucher discount if applicable)
        $finalTotal = $total;
        if (!empty($voucherCode) && $voucherDiscount > 0) {
            // Use the original total from frontend to calculate the discount
            $discountAmount = $originalTotal * ($voucherDiscount / 100);
            $finalTotal = $originalTotal - $discountAmount;
        }
        
        update_post_meta($postId, '_lm_booking_total', round($finalTotal, 2));
        update_post_meta($postId, '_lm_booking_breakdown', $breakdown);
        update_post_meta($postId, '_lm_booking_consent', $consent ? 1 : 0);
        update_post_meta($postId, '_lm_booking_file_uploaded', !empty($data['document']) ? 1 : 0);
        update_post_meta($postId, '_lm_booking_country', sanitize_text_field($data['country'] ?? ''));
        update_post_meta($postId, '_lm_booking_program', sanitize_text_field($data['program'] ?? ''));
        update_post_meta($postId, '_lm_booking_note', $note);
        update_post_meta($postId, '_lm_booking_status', 'pending');
        
        // Save voucher information if applicable
        if (!empty($voucherCode)) {
            update_post_meta($postId, '_lm_booking_voucher_code', $voucherCode);
            update_post_meta($postId, '_lm_booking_voucher_discount', $voucherDiscount);
            
            // Calculate discount amount using the original total from frontend
            $discountAmount = $originalTotal * ($voucherDiscount / 100);
            
            update_post_meta($postId, '_lm_booking_original_total', round($originalTotal, 2));
            update_post_meta($postId, '_lm_booking_discount_amount', round($discountAmount, 2));
        }
        
        // Save document data if uploaded
        if (!empty($data['document']) && is_array($data['document'])) {
            update_post_meta($postId, '_lm_booking_document', wp_json_encode($data['document']));
        }

        // Verify the meta was saved
        $savedExtras = get_post_meta($postId, '_lm_booking_extras', true);
        $savedTotal = get_post_meta($postId, '_lm_booking_total', true);

        return $postId;
    }

    public function createEmailConfirmation(string $email, array $voucherData): string
    {
        global $wpdb;
        
        // Generate unique confirmation token
        $token = wp_generate_password(32, false);
        
        // Store confirmation data
        $confirmationData = [
            'email' => $email,
            'token' => $token,
            'voucher_data' => $voucherData,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
            'confirmed' => false
        ];
        
        // Store in WordPress options (simple approach)
        $confirmations = get_option('lm_booking_email_confirmations', []);
        $confirmations[$token] = $confirmationData;
        update_option('lm_booking_email_confirmations', $confirmations);
        
        return $token;
    }
    
    public function confirmEmail(string $token): array|false
    {
        $confirmations = get_option('lm_booking_email_confirmations', []);
        
        if (!isset($confirmations[$token])) {
            return false;
        }
        
        $confirmation = $confirmations[$token];
        
        // Check if already confirmed (prevent reuse)
        if ($confirmation['confirmed']) {
            return false;
        }
        
        // Check if expired
        if (strtotime($confirmation['expires_at']) < time()) {
            // Remove expired confirmation
            unset($confirmations[$token]);
            update_option('lm_booking_email_confirmations', $confirmations);
            return false;
        }
        
        // Mark as confirmed and remove the token (single use)
        $confirmation['confirmed'] = true;
        $confirmation['confirmed_at'] = current_time('mysql');
        
        // Remove the token after successful confirmation (single use)
        unset($confirmations[$token]);
        update_option('lm_booking_email_confirmations', $confirmations);
        
        return $confirmation;
    }
    
    public function cleanupExpiredConfirmations(): void
    {
        $confirmations = get_option('lm_booking_email_confirmations', []);
        $now = time();
        
        foreach ($confirmations as $token => $confirmation) {
            if (strtotime($confirmation['expires_at']) < $now) {
                unset($confirmations[$token]);
            }
        }
        
        update_option('lm_booking_email_confirmations', $confirmations);
    }

    public function isTokenUsed(string $token): bool
    {
        $confirmations = get_option('lm_booking_email_confirmations', []);
        
        if (!isset($confirmations[$token])) {
            return true; // Token doesn't exist, consider it "used"
        }
        
        return $confirmations[$token]['confirmed'] ?? false;
    }

    public function deleteVoucherCompletely(string $voucherId, string $email = ''): bool
    {
        try {
            // 1. Remove from main vouchers list (single source of truth)
            $vouchers = get_option('lm_booking_vouchers', []);
            $voucherToDelete = null;
            
            foreach ($vouchers as $index => $voucher) {
                if ($voucher['id'] === $voucherId) {
                    $voucherToDelete = $voucher;
                    unset($vouchers[$index]);
                    break;
                }
            }
            
            if ($voucherToDelete) {
                update_option('lm_booking_vouchers', array_values($vouchers));
            }
            
            // 2. Remove email confirmations for this email (if provided)
            if (!empty($email)) {
                $confirmations = get_option('lm_booking_email_confirmations', []);
                foreach ($confirmations as $token => $confirmation) {
                    if ($confirmation['email'] === $email) {
                        unset($confirmations[$token]);
                    }
                }
                update_option('lm_booking_email_confirmations', $confirmations);
            }
            
            // 3. Clear any related transients/cache
            delete_transient('lm_booking_stats');
            delete_transient('lm_booking_list');
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteVoucherByEmail(string $email): bool
    {
        try {
            // Remove all vouchers for this email from the single source of truth
            $vouchers = get_option('lm_booking_vouchers', []);
            $emailLower = strtolower($email);
            
            $vouchers = array_filter($vouchers, function($voucher) use ($emailLower) {
                return strtolower($voucher['email']) !== $emailLower;
            });
            
            update_option('lm_booking_vouchers', array_values($vouchers));
            
            // Also remove email confirmations for this email
            $confirmations = get_option('lm_booking_email_confirmations', []);
            foreach ($confirmations as $token => $confirmation) {
                if ($confirmation['email'] === $email) {
                    unset($confirmations[$token]);
                }
            }
            update_option('lm_booking_email_confirmations', $confirmations);
            
            // Clear cache
            delete_transient('lm_booking_stats');
            delete_transient('lm_booking_list');
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
}
