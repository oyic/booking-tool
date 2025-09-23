<?php

namespace LM\Booking\Domain;

class Validator
{
    public static function validateName(string $name): string|\WP_Error
    {
        $name = sanitize_text_field(trim($name));
        
        if (empty($name)) {
            return new \WP_Error('invalid_name', __('Name is required.', 'lm-booking'));
        }
        
        if (strlen($name) < 2) {
            return new \WP_Error('invalid_name', __('Name must be at least 2 characters long.', 'lm-booking'));
        }
        
        if (strlen($name) > 100) {
            return new \WP_Error('invalid_name', __('Name must not exceed 100 characters.', 'lm-booking'));
        }
        
        return $name;
    }

    public static function validateEmail(string $email): string|\WP_Error
    {
        $email = sanitize_email(trim($email));
        
        if (empty($email)) {
            return new \WP_Error('invalid_email', __('Email is required.', 'lm-booking'));
        }
        
        if (!is_email($email)) {
            return new \WP_Error('invalid_email', __('Please enter a valid email address.', 'lm-booking'));
        }
        
        return $email;
    }

    public static function validateService(string $service): string|\WP_Error
    {
        $service = sanitize_text_field(trim($service));
        
        if (empty($service)) {
            return new \WP_Error('invalid_service', __('Please select a service.', 'lm-booking'));
        }
        
        $settings = get_option('lm_booking_settings', []);
        $services = $settings['services'] ?? [];
        
        $validServices = array_column($services, 'label');
        if (!in_array($service, $validServices, true)) {
            return new \WP_Error('invalid_service', __('Selected service is not available.', 'lm-booking'));
        }
        
        return $service;
    }

    public static function validateTotal($total): float|\WP_Error
    {
        $total = floatval($total);
        
        if ($total < 0) {
            return new \WP_Error('invalid_total', __('Total must be a positive number.', 'lm-booking'));
        }
        
        if ($total > 999999.99) {
            return new \WP_Error('invalid_total', __('Total amount is too large.', 'lm-booking'));
        }
        
        return round($total, 2);
    }

    public static function validateBreakdown(string $breakdown): string|\WP_Error
    {
        $breakdown = sanitize_text_field($breakdown);
        
        if (empty($breakdown)) {
            return '{}';
        }
        
        $decoded = json_decode($breakdown, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('invalid_breakdown', __('Invalid breakdown data format.', 'lm-booking'));
        }
        
        if (!is_array($decoded)) {
            return new \WP_Error('invalid_breakdown', __('Breakdown must be a valid JSON object.', 'lm-booking'));
        }
        
        return $breakdown;
    }

    public static function validateConsent($consent): bool|\WP_Error
    {
        $settings = get_option('lm_booking_settings', []);
        $gdprEnabled = $settings['gdpr_enabled'] ?? true;
        
        if (!$gdprEnabled) {
            return true;
        }
        
        if (!isset($consent) || $consent !== '1') {
            return new \WP_Error('missing_consent', __('Please accept the privacy notice.', 'lm-booking'));
        }
        
        return true;
    }

    public static function validateAdminRecipients(string $recipients): array|\WP_Error
    {
        $recipients = sanitize_text_field($recipients);
        
        if (empty($recipients)) {
            return [get_option('admin_email')];
        }
        
        $emails = array_map('trim', explode(',', $recipients));
        $validEmails = [];
        
        foreach ($emails as $email) {
            if (is_email($email)) {
                $validEmails[] = $email;
            }
        }
        
        if (empty($validEmails)) {
            return new \WP_Error('invalid_recipients', __('No valid email addresses found.', 'lm-booking'));
        }
        
        return $validEmails;
    }

    public static function validateWords($value): int|\WP_Error
    {
        $words = absint($value);
        
        if ($words < 0) {
            return new \WP_Error('invalid_words', __('Word count must be a positive number.', 'lm-booking'));
        }
        
        if ($words > 1000000) {
            return new \WP_Error('invalid_words', __('Word count is too large. Maximum 1,000,000 words.', 'lm-booking'));
        }
        
        return $words;
    }

    public static function validateDelivery($value): string|\WP_Error
    {
        $delivery = sanitize_text_field($value);
        
        $validOptions = ['standard', '3d', '2d', '1d'];
        if (!in_array($delivery, $validOptions, true)) {
            return '3d';
        }
        
        return $delivery;
    }

    public static function validateExtras($value, array $allowedExtras = []): array|\WP_Error
    {
        if (!is_array($value)) {
            return [];
        }
        
        $validExtras = [];
        
        foreach ($value as $extra) {
            if (!is_array($extra) || !isset($extra['label'], $extra['price'])) {
                continue;
            }
            
            $label = sanitize_text_field($extra['label']);
            $price = floatval($extra['price']);
            
            // Accept any extra with valid structure and positive price
            // Don't restrict to only allowed extras from settings
            if (!empty($label) && $price >= 0) {
                $validExtra = [
                    'label' => $label,
                    'price' => $price
                ];
                
                // Preserve inclusive package information
                if (isset($extra['included_packages'])) {
                    $validExtra['included_packages'] = $extra['included_packages'];
                }
                if (isset($extra['package_inclusive'])) {
                    $validExtra['package_inclusive'] = $extra['package_inclusive'];
                }
                if (isset($extra['description'])) {
                    $validExtra['description'] = $extra['description'];
                }
                
                $validExtras[] = $validExtra;
            } else {
            }
        }
        
        return $validExtras;
    }

    public static function validateFileUpload($file): array|\WP_Error
    {
        // Check if file was uploaded
        if (!isset($file) || !is_array($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return new \WP_Error('no_file', __('Please upload a document file.', 'lm-booking'));
        }

        // Check file size (max 10MB)
        $maxSize = 10 * 1024 * 1024; // 10MB in bytes
        if ($file['size'] > $maxSize) {
            return new \WP_Error('file_too_large', __('File is too large. Maximum size: 10MB.', 'lm-booking'));
        }

        // Check file type - DOC, DOCX, RTF, and ODT files
        $allowedMimeTypes = [
            'application/msword', // .doc
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'application/vnd.oasis.opendocument.text', // .odt
            'application/rtf', // .rtf
            'text/rtf' // .rtf alternative MIME type
        ];

        $fileType = $file['type'];
        if (!in_array($fileType, $allowedMimeTypes, true)) {
            return new \WP_Error('invalid_file_type', __('Invalid file type. Please upload a DOC, DOCX, RTF, or ODT file.', 'lm-booking'));
        }

        // Additional security: check file extension
        $allowedExtensions = ['doc', 'docx', 'rtf', 'odt'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions, true)) {
            return new \WP_Error('invalid_file_extension', __('Invalid file extension. Please upload a DOC, DOCX, RTF, or ODT file.', 'lm-booking'));
        }

        return [
            'name' => sanitize_file_name($file['name']),
            'original_name' => $file['name'], // Keep original name for file extension detection
            'type' => $fileType,
            'size' => $file['size'],
            'tmp_name' => $file['tmp_name']
        ];
    }
}
