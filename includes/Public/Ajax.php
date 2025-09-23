<?php

namespace LM\Booking\Public;

use LM\Booking\Infra\Repo;
use LM\Booking\Infra\Email;
use LM\Booking\Infra\Analytics;
use LM\Booking\Domain\Validator;

class Ajax
{
    private Repo $repo;
    private Email $email;

    public function __construct()
    {
        $this->repo = new Repo();
        $this->email = new Email();
    }

    public function init(): void
    {
        add_action('wp_ajax_lm_booking_submit', [$this, 'handleSubmit']);
        add_action('wp_ajax_nopriv_lm_booking_submit', [$this, 'handleSubmit']);
        add_action('wp_ajax_lm_convert_to_text', [$this, 'convertToText']);
        add_action('wp_ajax_nopriv_lm_convert_to_text', [$this, 'convertToText']);
        add_action('wp_ajax_lm_voucher_signup', [$this, 'handleVoucherSignup']);
        add_action('wp_ajax_nopriv_lm_voucher_signup', [$this, 'handleVoucherSignup']);
        add_action('wp_ajax_lm_validate_voucher', [$this, 'handleVoucherValidation']);
        add_action('wp_ajax_nopriv_lm_validate_voucher', [$this, 'handleVoucherValidation']);
        add_action('wp_ajax_lm_check_email', [$this, 'checkEmailDuplicate']);
        add_action('wp_ajax_nopriv_lm_check_email', [$this, 'checkEmailDuplicate']);
        add_action('wp_ajax_lm_confirm_email', [$this, 'handleEmailConfirmation']);
        add_action('wp_ajax_nopriv_lm_confirm_email', [$this, 'handleEmailConfirmation']);
    }

    public function handleSubmit(): void
    {
        
        if (!check_ajax_referer('lm_booking_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed. Please try again.', 'lm-booking')
            ], 400);
        }
        

        // Validate required fields
        
        $name = Validator::validateName($_POST['name'] ?? '');
        if (is_wp_error($name)) {
            wp_send_json_error(['message' => $name->get_error_message()], 400);
        }

        $email = Validator::validateEmail($_POST['email'] ?? '');
        if (is_wp_error($email)) {
            wp_send_json_error(['message' => $email->get_error_message()], 400);
        }

        $service = Validator::validateService($_POST['service'] ?? '');
        if (is_wp_error($service)) {
            wp_send_json_error(['message' => $service->get_error_message()], 400);
        }

        $words = Validator::validateWords($_POST['words'] ?? 0);
        if (is_wp_error($words)) {
            wp_send_json_error(['message' => $words->get_error_message()], 400);
        }

        $delivery = Validator::validateDelivery($_POST['delivery'] ?? '3d');
        if (is_wp_error($delivery)) {
            wp_send_json_error(['message' => $delivery->get_error_message()], 400);
        }

        // Validate optional fields
        $country = sanitize_text_field($_POST['country'] ?? '');
        $program = sanitize_text_field($_POST['program'] ?? '');
        $note = sanitize_textarea_field($_POST['note'] ?? '');

        $settings = get_option('lm_booking_settings', []);
        
        // Get extras from settings
        $allowedExtras = $settings['extras'] ?? [];
        
        // Parse extras from JSON string
        $extrasData = $_POST['extras'] ?? '[]';
        
        $extras = [];
        if (is_string($extrasData)) {
            // Handle double-encoded JSON (quotes are escaped)
            $decodedExtras = json_decode($extrasData, true);
            
            // If decoding failed, try to handle escaped quotes
            if ($decodedExtras === null) {
                $unescapedData = stripslashes($extrasData);
                $decodedExtras = json_decode($unescapedData, true);
            }
            
            if (is_array($decodedExtras)) {
                foreach ($decodedExtras as $extra) {
                    if (isset($extra['label']) && isset($extra['price'])) {
                        $extras[] = $extra;
                    }
                }
            }
        }
        
        
        $extras = Validator::validateExtras($extras, $allowedExtras);
        if (is_wp_error($extras)) {
            wp_send_json_error(['message' => $extras->get_error_message()], 400);
        }
        

        $consent = Validator::validateConsent($_POST['consent'] ?? '');
        if (is_wp_error($consent)) {
            wp_send_json_error(['message' => $consent->get_error_message()], 400);
        }

        // Validate file upload
        $file = $_FILES['document'] ?? null;
        $validatedFile = Validator::validateFileUpload($file);
        if (is_wp_error($validatedFile)) {
            wp_send_json_error(['message' => $validatedFile->get_error_message()], 400);
        }

        // Get services from settings
        $services = $settings['services'] ?? [];
        $selectedService = array_filter($services, function($s) use ($service) {
            return $s['label'] === $service;
        });
        $servicePrice = !empty($selectedService) ? reset($selectedService)['price'] : 0;
        

        // Server-side calculation (authoritative)
        $pricing = \LM\Booking\Domain\Pricing::calculate($servicePrice, $extras, $words, $delivery);
        $serverTotal = $pricing['total'];
        $clientTotal = floatval($_POST['total'] ?? 0);

        // Use server calculation if there's a significant difference
        if (abs($serverTotal - $clientTotal) > 0.01) {
            $total = $serverTotal;
            $breakdown = wp_json_encode($pricing);
        } else {
            $total = $clientTotal;
            $breakdown = $_POST['breakdown'] ?? wp_json_encode($pricing);
        }

        $deliveryDate = \LM\Booking\Domain\Pricing::calculateDeliveryDate(
            $delivery, 
            $settings['delivery_buffer24h'] ?? true
        );

        // Handle file upload
        $uploadedFile = null;
        if ($validatedFile) {
            $uploadedFile = $this->handleFileUpload($validatedFile);
            if (is_wp_error($uploadedFile)) {
                wp_send_json_error(['message' => $uploadedFile->get_error_message()], 500);
            }
        }

        // Get voucher information
        $voucherCode = sanitize_text_field($_POST['voucher_code'] ?? '');
        $voucherDiscount = absint($_POST['voucher_discount'] ?? 0);
        $originalTotal = floatval($_POST['original_total'] ?? $total);
        
        $data = [
            'name' => $name,
            'email' => $email,
            'service' => $service,
            'words' => $words,
            'delivery' => $delivery,
            'extras' => $extras,
            'total' => $total,
            'breakdown' => $breakdown,
            'norm_pages' => $pricing['normPages'],
            'delivery_date' => $deliveryDate,
            'consent' => $consent,
            'country' => $country,
            'program' => $program,
            'note' => $note,
            'document' => $uploadedFile,
            'voucher_code' => $voucherCode,
            'voucher_discount' => $voucherDiscount,
            'original_total' => $originalTotal,
        ];


        $postId = $this->repo->saveBooking($data);

        if (!$postId) {
            wp_send_json_error([
                'message' => __('Failed to save booking. Please try again.', 'lm-booking')
            ], 500);
        }

        // Mark voucher as used if one was applied
        $voucherCode = sanitize_text_field($_POST['voucher_code'] ?? '');
        if (!empty($voucherCode)) {
            $this->markVoucherAsUsed($voucherCode, $postId);
        }

        $clientResult = $this->email->sendClient($postId);
        $adminResult = $this->email->sendAdmin($postId);

        if (is_wp_error($clientResult) || is_wp_error($adminResult)) {
            Analytics::trackSubmitError('Email sending failed', $data);
        } else {
            Analytics::trackSubmitSuccess($data);
        }

        
        wp_send_json_success([
            'message' => __('Buchung erfolgreich abgeschlossen! Vielen Dank für Ihre Anfrage.', 'lm-booking'),
            'booking_id' => $postId,
            'final_total' => $total,
            'delivery_date' => $deliveryDate
        ]);
    }

    private function handleFileUpload(array $file): array|\WP_Error
    {
        // Create upload directory if it doesn't exist
        $uploadDir = wp_upload_dir();
        $bookingDir = $uploadDir['basedir'] . '/lektorat-mac/';
        
        if (!file_exists($bookingDir)) {
            wp_mkdir_p($bookingDir);
            
            // Create .htaccess to prevent execution
            $htaccessContent = "Options -ExecCGI\nAddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
            file_put_contents($bookingDir . '.htaccess', $htaccessContent);
        }

        // Generate unique filename
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueFilename = uniqid('booking_', true) . '.' . $fileExtension;
        $filePath = $bookingDir . $uniqueFilename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return new \WP_Error('upload_failed', __('Failed to save uploaded file.', 'lm-booking'));
        }

        return [
            'original_name' => $file['name'],
            'filename' => $uniqueFilename,
            'path' => $filePath,
            'url' => $uploadDir['baseurl'] . '/lektorat-mac/' . $uniqueFilename,
            'size' => $file['size'],
            'type' => $file['type']
        ];
    }

    public function convertToText(): void
    {
        
        if (!check_ajax_referer('lm_booking_nonce', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed. Please try again.', 'lm-booking')
            ], 400);
        }

        $file = $_FILES['document'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error([
                'message' => __('No file uploaded.', 'lm-booking')
            ], 400);
        }


        // Validate file type
        $validatedFile = Validator::validateFileUpload($file);
        if (is_wp_error($validatedFile)) {
            wp_send_json_error([
                'message' => $validatedFile->get_error_message()
            ], 400);
        }


        // Extract text and convert to plain text
        $text = $this->extractTextFromFile($validatedFile);
        if (empty($text)) {
            wp_send_json_error([
                'message' => __('No text found in the document. The file might be corrupted or in an unsupported format.', 'lm-booking')
            ], 400);
        }


        // Return clean plain text
        wp_send_json_success([
            'text' => $text,
            'message' => __('Document converted to plain text successfully.', 'lm-booking')
        ]);
    }

    private function extractTextFromFile(array $file): string
    {
        $filePath = $file['tmp_name'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        switch ($fileExtension) {
            case 'txt':
                return file_get_contents($filePath);
                
            case 'docx':
                return $this->extractFromDocx($filePath);
                
            case 'doc':
                return $this->extractFromDoc($filePath);
                
            case 'pdf':
                return $this->extractFromPdf($filePath);
                
            case 'rtf':
                return $this->extractFromRtf($filePath);
                
            default:
                return '';
        }
    }
    
    private function extractFromDocx(string $filePath): string
    {
        // Simple DOCX text extraction
        $zip = new \ZipArchive();
        if ($zip->open($filePath) === TRUE) {
            $content = $zip->getFromName('word/document.xml');
            $zip->close();
            
            if ($content) {
                // Remove XML tags and decode entities
                $text = strip_tags($content);
                $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
                return trim($text);
            }
        }
        return '';
    }
    
    private function extractFromDoc(string $filePath): string
    {
        // For .doc files, we'll return empty for now
        // In a real implementation, you'd use a library like phpword
        return '';
    }
    
    private function extractFromPdf(string $filePath): string
    {
        // For PDF files, we'll return empty for now
        // In a real implementation, you'd use a library like pdfparser
        return '';
    }
    
    private function extractFromRtf(string $filePath): string
    {
        // Simple RTF text extraction
        $content = file_get_contents($filePath);
        if ($content) {
            // Remove RTF formatting codes
            $text = preg_replace('/\\[a-z]+\\d*\\s?/', '', $content);
            $text = preg_replace('/[{}]/', '', $text);
            $text = preg_replace('/\\\\[a-z]+\\d*\\s?/', '', $text);
            return trim($text);
        }
        return '';
    }

    public function handleVoucherSignup(): void
    {
        try {
            // Verify nonce
            $nonce = sanitize_text_field($_POST['lm_voucher_nonce'] ?? '');
            if (!wp_verify_nonce($nonce, 'lm_voucher_signup')) {
                wp_send_json_error([
                    'message' => __('Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.', 'lm-booking')
                ], 400);
            }

            // Check honeypot field - if filled, it's likely a bot
            $honeypot = sanitize_text_field($_POST['lm_website_url'] ?? '');
            if (!empty($honeypot)) {
                wp_send_json_error([
                    'message' => __('Ungültige Übermittlung erkannt.', 'lm-booking')
                ], 400);
            }

            // Validate email
            $email = sanitize_email($_POST['email'] ?? '');
            if (!is_email($email)) {
                wp_send_json_error([
                    'message' => __('Bitte geben Sie eine gültige E-Mail-Adresse ein.', 'lm-booking')
                ], 400);
            }

            // Get discount and expiry from settings
            $settings = get_option('lm_booking_settings', []);
            $discount = floatval($settings['voucher_signup_discount'] ?? 15);
            $expiryDays = intval($settings['voucher_signup_expiry_days'] ?? 30);

            // Check if email already has an active voucher
            $vouchers = get_option('lm_booking_vouchers', []);
            $emailLower = strtolower($email);
            
            foreach ($vouchers as $voucher) {
                $voucherEmailLower = strtolower($voucher['email']);
                
                if ($voucherEmailLower === $emailLower) {
                    // Check if voucher is still valid (not expired and not used)
                    $expiryDate = strtotime($voucher['expiry']);
                    $isExpired = $expiryDate < time();
                    
                    if (!$voucher['used'] && !$isExpired) {
                        // Active voucher exists - prompt to use existing one
                        wp_send_json_error([
                            'message' => sprintf(
                                __('Sie haben bereits einen aktiven Gutschein für diese E-Mail-Adresse. Ihr Gutscheincode ist: %s', 'lm-booking'),
                                $voucher['code']
                            )
                        ], 400);
                    } elseif ($voucher['used']) {
                        // Used voucher exists - block signup completely
                        wp_send_json_error([
                            'message' => __('Sie haben bereits einen Gutschein verwendet. Pro E-Mail-Adresse ist nur ein Gutschein erlaubt.', 'lm-booking')
                        ], 400);
                    }
                }
            }

            // Also check if this email has any booking with a voucher (from previous bookings)
            $emailHasUsedVoucher = $this->checkEmailHasUsedVoucher($email);
            if ($emailHasUsedVoucher) {
                wp_send_json_error([
                    'message' => __('Sie haben bereits einen Gutschein verwendet. Pro E-Mail-Adresse ist nur ein Gutschein erlaubt.', 'lm-booking')
                ], 400);
            }

            // Generate voucher code
            $voucherCode = $this->generateVoucherCode();

            // Prepare voucher data for confirmation
            $voucherData = [
                'code' => $voucherCode,
                'discount' => $discount,
                'expiry' => date('Y-m-d', strtotime("+{$expiryDays} days")),
                'expiry_days' => $expiryDays
            ];

            // Create email confirmation token
            $confirmationToken = $this->repo->createEmailConfirmation($email, $voucherData);

            // Send confirmation email instead of voucher
            $emailSent = $this->email->sendEmailConfirmation($email, $confirmationToken, $voucherData);

            if (is_wp_error($emailSent)) {
                wp_send_json_error([
                    'message' => __('Bestätigungs-E-Mail konnte nicht gesendet werden. Bitte kontaktieren Sie den Support.', 'lm-booking')
                ], 500);
            }

            wp_send_json_success([
                'message' => __('Bitte bestätigen Sie Ihre E-Mail-Adresse. Wir haben Ihnen eine Bestätigungs-E-Mail gesendet.', 'lm-booking'),
                'email' => $email,
                'confirmation_sent' => true
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'lm-booking')
            ], 500);
        }
    }

    private function saveToBookingLists(array $voucher): void
    {
        // No longer creating separate booking entries for voucher signups
        // Everything is now stored in lm_booking_vouchers only
        return;
    }

    private function generateVoucherCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        // Generate 8-character code
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[wp_rand(0, strlen($chars) - 1)];
        }
        
        // Ensure uniqueness
        $vouchers = get_option('lm_booking_vouchers', []);
        $existingCodes = array_column($vouchers, 'code');
        
        while (in_array($code, $existingCodes)) {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $chars[wp_rand(0, strlen($chars) - 1)];
            }
        }
        
        return $code;
    }

    public function handleVoucherValidation(): void
    {
        try {
            // Verify nonce
            $nonce = sanitize_text_field($_POST['lm_voucher_nonce'] ?? '');
            if (!wp_verify_nonce($nonce, 'lm_voucher_validation')) {
                wp_send_json_error([
                    'message' => __('Sicherheitsprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.', 'lm-booking')
                ], 400);
            }

            // Get voucher code
            $voucherCode = sanitize_text_field($_POST['voucher_code'] ?? '');
            if (empty($voucherCode)) {
                wp_send_json_error([
                    'message' => __('Bitte geben Sie einen Gutscheincode ein.', 'lm-booking')
                ], 400);
            }

            // Convert to uppercase for consistency
            $voucherCode = strtoupper($voucherCode);

            // Get all vouchers
            $vouchers = get_option('lm_booking_vouchers', []);
            
            // Find matching voucher
            $foundVoucher = null;
            foreach ($vouchers as $voucher) {
                if (strtoupper($voucher['code']) === $voucherCode) {
                    $foundVoucher = $voucher;
                    break;
                }
            }

            if (!$foundVoucher) {
                wp_send_json_error([
                    'message' => __('Ungültiger Gutscheincode.', 'lm-booking')
                ], 400);
            }

            // Check if voucher is expired
            $expiryDate = strtotime($foundVoucher['expiry']);
            $isExpired = $expiryDate < time();
            
            if ($isExpired) {
                wp_send_json_error([
                    'message' => __('Dieser Gutscheincode ist abgelaufen.', 'lm-booking')
                ], 400);
            }

            // Check if voucher is already used
            if ($foundVoucher['used']) {
                wp_send_json_error([
                    'message' => __('Dieser Gutscheincode wurde bereits verwendet.', 'lm-booking')
                ], 400);
            }

            // Check if this email has already used any voucher
            $customerEmail = sanitize_text_field($_POST['customer_email'] ?? '');
            if (!empty($customerEmail)) {
                $emailHasUsedVoucher = $this->checkEmailHasUsedVoucher($customerEmail);
                if ($emailHasUsedVoucher) {
                    wp_send_json_error([
                        'message' => __('Sie haben bereits einen Gutschein verwendet. Pro E-Mail-Adresse ist nur ein Gutschein erlaubt.', 'lm-booking')
                    ], 400);
                }
            }

            // Voucher is valid
            wp_send_json_success([
                'valid' => true,
                'message' => sprintf(
                    __('Gutscheincode "%s" erfolgreich angewendet! %d%% Rabatt erhalten.', 'lm-booking'),
                    $voucherCode,
                    $foundVoucher['discount']
                ),
                'voucher' => [
                    'code' => $foundVoucher['code'],
                    'discount' => $foundVoucher['discount'],
                    'expiry' => $foundVoucher['expiry']
                ]
            ]);

        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => __('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'lm-booking')
            ], 500);
        }
    }

    public function checkEmailDuplicate(): void
    {
        // Verify nonce
        if (!check_ajax_referer('lm_voucher_signup', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed.', 'lm-booking')
            ], 400);
        }

        $email = sanitize_email($_POST['email'] ?? '');
        if (!is_email($email)) {
            wp_send_json_error([
                'message' => __('Invalid email address.', 'lm-booking')
            ], 400);
        }

        // Check for duplicates
        $vouchers = get_option('lm_booking_vouchers', []);
        $emailLower = strtolower($email);
        
        foreach ($vouchers as $voucher) {
            $voucherEmailLower = strtolower($voucher['email']);
            
            if ($voucherEmailLower === $emailLower) {
                $expiryDate = strtotime($voucher['expiry']);
                $isExpired = $expiryDate < time();
                
                if (!$voucher['used'] && !$isExpired) {
                    wp_send_json_success([
                        'duplicate' => true,
                        'message' => sprintf(
                            __('This email already has an active voucher: %s', 'lm-booking'),
                            $voucher['code']
                        ),
                        'voucher_code' => $voucher['code']
                    ]);
                }
            }
        }

        wp_send_json_success([
            'duplicate' => false,
            'message' => __('Email is available.', 'lm-booking')
        ]);
    }

    private function checkEmailHasUsedVoucher(string $email): bool
    {
        // Only check lm_booking_vouchers option - single source of truth
        $vouchers = get_option('lm_booking_vouchers', []);
        $emailLower = strtolower($email);
        
        foreach ($vouchers as $voucher) {
            $voucherEmailLower = strtolower($voucher['email']);
            if ($voucherEmailLower === $emailLower && $voucher['used']) {
                return true;
            }
        }
        
        return false;
    }

    private function markVoucherAsUsed(string $voucherCode, int $bookingId): void
    {
        $vouchers = get_option('lm_booking_vouchers', []);
        
        foreach ($vouchers as &$voucher) {
            if (strtoupper($voucher['code']) === strtoupper($voucherCode)) {
                $voucher['used'] = true;
                $voucher['used_date'] = current_time('mysql');
                $voucher['used_for_booking'] = $bookingId;
                break;
            }
        }
        
        update_option('lm_booking_vouchers', $vouchers);
    }

    public function handleEmailConfirmation(): void
    {
        try {
            $token = sanitize_text_field($_GET['token'] ?? '');
            
            if (empty($token)) {
                wp_send_json_error([
                    'message' => __('Bestätigungstoken fehlt.', 'lm-booking')
                ], 400);
            }
            
            // Check if token exists before attempting confirmation
            $confirmations = get_option('lm_booking_email_confirmations', []);
            if (!isset($confirmations[$token])) {
                wp_send_json_error([
                    'message' => __('Ungültiger oder bereits verwendeter Bestätigungstoken.', 'lm-booking')
                ], 400);
            }
            
            // Confirm the email (this will remove the token after successful use)
            $confirmation = $this->repo->confirmEmail($token);
            
            if (!$confirmation) {
                wp_send_json_error([
                    'message' => __('Ungültiger oder abgelaufener Bestätigungstoken.', 'lm-booking')
                ], 400);
            }
            
            // Create and send the voucher
            $voucherData = $confirmation['voucher_data'];
            $voucher = [
                'id' => uniqid('voucher_'),
                'email' => $confirmation['email'],
                'code' => $voucherData['code'],
                'discount' => $voucherData['discount'],
                'expiry' => $voucherData['expiry'],
                'used' => false,
                'created' => current_time('mysql'),
                'source' => 'signup_form_confirmed'
            ];
            
            // Save voucher to vouchers list
            $vouchers = get_option('lm_booking_vouchers', []);
            $vouchers[] = $voucher;
            update_option('lm_booking_vouchers', $vouchers);
            
            // Also save to booking lists
            $this->saveToBookingLists($voucher);
            
            // Send voucher email
            $emailSent = $this->email->sendVoucherEmail($voucher);
            
            if (is_wp_error($emailSent)) {
                wp_send_json_error([
                    'message' => __('E-Mail bestätigt, aber Gutschein-Versand fehlgeschlagen. Bitte kontaktieren Sie den Support.', 'lm-booking')
                ], 500);
            }
            
            wp_send_json_success([
                'message' => __('E-Mail erfolgreich bestätigt! Ihr Gutschein wurde gesendet.', 'lm-booking'),
                'voucher_code' => $voucher['code'],
                'discount' => $voucher['discount'],
                'expiry' => $voucher['expiry']
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'lm-booking')
            ], 500);
        }
    }

}
