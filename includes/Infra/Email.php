<?php

namespace LM\Booking\Infra;

class Email
{
    public function sendClient(int $postId): bool|\WP_Error
    {
        $email = get_post_meta($postId, '_lm_booking_customer_email', true);
        $name = get_post_meta($postId, '_lm_booking_customer_name', true);
        $service = get_post_meta($postId, '_lm_booking_service', true);
        $words = get_post_meta($postId, '_lm_booking_words', true);
        $normPages = get_post_meta($postId, '_lm_booking_norm_pages', true);
        $delivery = get_post_meta($postId, '_lm_booking_delivery', true);
        $deliveryDate = get_post_meta($postId, '_lm_booking_delivery_date', true);
        $extras = get_post_meta($postId, '_lm_booking_extras', true);
        $total = get_post_meta($postId, '_lm_booking_total', true);
        $breakdown = get_post_meta($postId, '_lm_booking_breakdown', true);
        $voucherCode = get_post_meta($postId, '_lm_booking_voucher_code', true);
        $voucherDiscount = get_post_meta($postId, '_lm_booking_voucher_discount', true);
        $originalTotal = get_post_meta($postId, '_lm_booking_original_total', true);
        $discountAmount = get_post_meta($postId, '_lm_booking_discount_amount', true);
        $country = get_post_meta($postId, '_lm_booking_country', true);
        $program = get_post_meta($postId, '_lm_booking_program', true);
        $note = get_post_meta($postId, '_lm_booking_note', true);

        if (!$email || !$name) {
            return new \WP_Error('missing_data', 'Missing customer email or name');
        }

        $settings = get_option('lm_booking_settings', []);
        $template = $settings['booking_email_template'] ?? $this->getDefaultClientTemplate();
        
        // Modify template to show voucher information if applicable
        if (!empty($voucherCode)) {
            $template = $this->addVoucherInfoToTemplate($template, $originalTotal, $total, $discountAmount, $voucherCode, $voucherDiscount);
        }

        $extrasList = $this->formatExtrasList($extras, $service);
        $breakdownData = json_decode($breakdown, true);

        $settings = get_option('lm_booking_settings', []);
        $subject = $settings['booking_email_subject'] ?? __('Your booking request & invoice - Lektorat Mac', 'lm-booking');
        
        // Get delivery date and format it
        $deliveryDate = get_post_meta($postId, '_lm_booking_delivery_date', true);
        $delivery = get_post_meta($postId, '_lm_booking_delivery', true);
        $formattedDeliveryDate = $this->formatDeliveryDate($deliveryDate);
        
        // Replace placeholders in the template
        $message = str_replace([
            '{customer_name}',
            '{customer_email}',
            '{customer_country}',
            '{customer_program}',
            '{customer_note}',
            '{booking_id}',
            '{service_name}',
            '{service_details}',
            '{total_price}',
            '{original_price}',
            '{discount_amount}',
            '{voucher_code}',
            '{voucher_discount}',
            '{invoice_number}',
            '{invoice_date}',
            '{due_date}',
            '{payment_info}',
            '{delivery_time}',
            '{delivery_date}'
        ], [
            $name,
            $email,
            $this->formatCountry($country ?? ''),
            $this->formatProgram($program ?? ''),
            $note ? '<div class="customer-detail"><strong>Notizen:</strong> ' . esc_html($note) . '</div>' : '',
            $this->generateInvoiceNumber($postId),
            $service,
            $this->formatServiceDetails($service, $words, $normPages, $extrasList, $breakdownData, $extras),
            number_format($total, 2), // This is the final total (discounted if voucher applied)
            $originalTotal ? number_format($originalTotal, 2) : number_format($total, 2), // Original total before discount
            $discountAmount ? number_format($discountAmount, 2) : '0.00', // Amount saved
            $voucherCode ?: '',
            $voucherDiscount ? $voucherDiscount . '%' : '',
            $this->generateInvoiceNumber($postId),
            current_time('d.m.Y'),
            date('d.m.Y', strtotime('+14 days')),
            $this->formatPaymentInfo(),
            $this->formatDelivery($delivery),
            $formattedDeliveryDate
        ], $template);

        // Use the comprehensive invoice HTML template if no custom template is set
        if (empty($settings['booking_email_template'])) {
            $htmlMessage = $this->generateInvoiceHtml($postId, $message);
        } else {
            // Wrap the template with CSS styles for proper email formatting
            $htmlMessage = $this->wrapEmailTemplateWithStyles($message, 'booking');
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $result = wp_mail($email, $subject, $htmlMessage, $headers);
        
        if (!$result) {
            return new \WP_Error('send_failed', 'Failed to send client email');
        }

        return true;
    }

    public function sendAdmin(int $postId): bool|\WP_Error
    {
        $email = get_post_meta($postId, '_lm_booking_customer_email', true);
        $name = get_post_meta($postId, '_lm_booking_customer_name', true);
        $service = get_post_meta($postId, '_lm_booking_service', true);
        $words = get_post_meta($postId, '_lm_booking_words', true);
        $normPages = get_post_meta($postId, '_lm_booking_norm_pages', true);
        $delivery = get_post_meta($postId, '_lm_booking_delivery', true);
        $deliveryDate = get_post_meta($postId, '_lm_booking_delivery_date', true);
        $extras = get_post_meta($postId, '_lm_booking_extras', true);
        $total = get_post_meta($postId, '_lm_booking_total', true);
        $breakdown = get_post_meta($postId, '_lm_booking_breakdown', true);
        $documentData = get_post_meta($postId, '_lm_booking_document', true);

        if (!$email || !$name) {
            return new \WP_Error('missing_data', 'Missing customer email or name');
        }

        $settings = get_option('lm_booking_settings', []);
        $template = $settings['email_admin'] ?? $this->getDefaultAdminTemplate();

        $extrasList = $this->formatExtrasList($extras, $service);
        $breakdownData = json_decode($breakdown, true);

        // Get document info for email
        $documentInfo = '';
        $attachmentPath = null;
        $attachmentName = null;
        if (!empty($documentData)) {
            $document = json_decode($documentData, true);
            if (is_array($document) && isset($document['original_name'], $document['path'])) {
                // Create download link with original filename
                $uploadDir = wp_upload_dir();
                $downloadUrl = $uploadDir['baseurl'] . '/lektorat-mac/' . basename($document['path']);
                
                $documentInfo = sprintf(
                    __("\n\nUploaded Document:\nOriginal Name: %s\nFile Size: %s\nDownload Link: <a href=\"%s\" download=\"%s\">%s</a>", 'lm-booking'),
                    esc_html($document['original_name']),
                    esc_html($this->formatFileSize($document['size'] ?? 0)),
                    esc_url($downloadUrl),
                    esc_attr($document['original_name']),
                    esc_html($document['original_name'])
                );
                
                // Set attachment path and original name for wp_mail
                if (file_exists($document['path'])) {
                    $attachmentPath = $document['path'];
                    $attachmentName = $document['original_name'];
                }
            }
        }

        $subject = __('New booking request', 'lm-booking');
        $message = $this->replacePlaceholders($template, [
            'name' => $name,
            'email' => $email,
            'service' => $service,
            'words' => number_format($words),
            'norm_pages' => $normPages,
            'delivery' => $this->formatDelivery($delivery),
            'delivery_date' => $this->formatDeliveryDate($deliveryDate),
            'extras' => $extrasList,
            'base' => number_format($breakdownData['base'] ?? 0, 2),
            'extras_total' => number_format($breakdownData['extrasTotal'] ?? 0, 2),
            'surcharge' => number_format($breakdownData['surcharge'] ?? 0, 2),
            'total' => number_format($total, 2),
            'breakdown' => $this->formatBreakdown($breakdownData),
            'document_info' => $documentInfo,
        ]);

        $adminEmails = $this->getAdminRecipients();
        $htmlMessage = $this->generateHtmlMessage($message);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $success = true;
        foreach ($adminEmails as $adminEmail) {
            // Prepare attachments array with original filename
            $attachments = [];
            if ($attachmentPath && $attachmentName) {
                // Create temporary copy with original filename for attachment
                $tempDir = wp_upload_dir()['basedir'] . '/temp/';
                if (!file_exists($tempDir)) {
                    wp_mkdir_p($tempDir);
                }
                
                $tempPath = $tempDir . sanitize_file_name($attachmentName);
                if (copy($attachmentPath, $tempPath)) {
                    $attachments[] = $tempPath;
                }
            }
            
            $result = wp_mail($adminEmail, $subject, $htmlMessage, $headers, $attachments);
            
            // Clean up temporary file
            if (!empty($attachments) && file_exists($attachments[0])) {
                unlink($attachments[0]);
            }
            
            if (!$result) {
                $success = false;
            }
        }

        if (!$success) {
            return new \WP_Error('send_failed', 'Failed to send admin email to some recipients');
        }

        return true;
    }

    private function replacePlaceholders(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        return $template;
    }

    private function formatServiceDetails(string $service, int $words, int $normPages, string $extrasList, array $breakdownData, string $extras): string
    {
        $html = '<div class="service-item">
            <span class="service-name">' . esc_html($service) . '</span>
            <span class="service-price">' . number_format($breakdownData['base'] ?? 0, 2) . '€</span>
        </div>';
        
        if ($words > 0) {
            $html .= '<div class="service-item">
                <span class="service-name">Wörter: ' . number_format($words) . '</span>
                <span class="service-price">-</span>
            </div>';
        }
        
        if ($normPages > 0) {
            $html .= '<div class="service-item">
                <span class="service-name">Normseiten: ' . $normPages . '</span>
                <span class="service-price">-</span>
            </div>';
        }
        
        if (!empty($extrasList) && $extrasList !== __('None', 'lm-booking')) {
            // Parse extras for individual display
            $extrasArray = json_decode($extras, true);
            
            // Use the corrected extras total from the breakdown data
            // (The breakdown should now have the correct total excluding inclusive extras)
            if (($breakdownData['extrasTotal'] ?? 0) > 0) {
                $html .= '<div class="service-item">
                    <span class="service-name">Extras Total</span>
                    <span class="service-price">' . number_format($breakdownData['extrasTotal'], 2) . '€</span>
                </div>';
            }
            
            // Add individual extras
            if (is_array($extrasArray)) {
                foreach ($extrasArray as $extra) {
                    if (isset($extra['label'], $extra['price'])) {
                        // Check if this extra is actually inclusive for the selected package
                        $includedPackages = $extra['included_packages'] ?? [];
                        $selectedPackageIndex = $this->getSelectedPackageIndex($service);
                        $isInclusive = in_array($selectedPackageIndex, $includedPackages);
                        
                        if ($isInclusive) {
                            // Show inclusive extra with special styling
                            $html .= '<div class="service-item" style="margin-left: 20px;">
                                <span class="service-name">• ' . esc_html($extra['label']) . ' <span style="background-color: #ffc107; color: #212529; padding: 2px 6px; border-radius: 3px; font-size: 11px;">(inklusive)</span></span>
                                <span class="service-price" style="text-decoration: line-through; color: #6c757d;">' . number_format($extra['price'], 2) . '€</span>
                            </div>';
                        } else {
                            // Show regular extra with price
                            $html .= '<div class="service-item" style="margin-left: 20px;">
                                <span class="service-name">• ' . esc_html($extra['label']) . '</span>
                                <span class="service-price">' . number_format($extra['price'], 2) . '€</span>
                            </div>';
                        }
                    }
                }
            }
        }
        
        return $html;
    }

    private function formatPaymentInfo(): string
    {
        return '<p>Bitte überweisen Sie den Betrag bis zum Fälligkeitsdatum auf unser Konto. Die Bankverbindung finden Sie in der angehängten Rechnung.</p>';
    }

    private function wrapEmailTemplateWithStyles(string $content, string $type): string
    {
        $title = $type === 'booking' ? 'Rechnung & Buchungsbestätigung' : '🎉 Ihr Rabatt-Gutschein';
        $headerColor = $type === 'booking' ? 'linear-gradient(135deg, #0073aa 0%, #005177 100%)' : 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lektorat Mac - E-Mail</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8f9fa; }
        .container { max-width: 650px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .header { background: ' . $headerColor . '; color: white; padding: 40px 30px; text-align: center; position: relative; }
        .header::before { content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url("data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.05\"%3E%3Ccircle cx=\"30\" cy=\"30\" r=\"2\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat; }
        .logo { font-size: 28px; font-weight: 700; margin-bottom: 8px; position: relative; z-index: 1; }
        .email-title { margin: 0; font-size: 22px; font-weight: 500; position: relative; z-index: 1; }
        .content { padding: 40px 30px; }
        .greeting { font-size: 18px; margin-bottom: 25px; color: #2c3e50; }
        .email-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; }
        .email-column { display: flex; flex-direction: column; gap: 20px; }
        .info-sections { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; }
        @media (max-width: 600px) { .email-grid, .info-sections { grid-template-columns: 1fr; gap: 20px; } }
        .invoice-meta { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #0073aa; }
        .meta-item { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .meta-item:last-child { margin-bottom: 0; }
        .meta-label { font-weight: 600; color: #495057; }
        .meta-value { color: #0073aa; font-weight: 500; }
        .customer-info { background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 25px; border-radius: 10px; margin: 25px 0; }
        .customer-info h3 { margin: 0 0 15px 0; color: #1976d2; font-size: 18px; }
        .customer-detail { margin-bottom: 8px; }
        .customer-detail strong { color: #1976d2; }
        .service-details { margin: 25px 0; }
        .service-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #e9ecef; }
        .service-item:last-child { border-bottom: none; }
        .service-name { font-weight: 600; color: #2c3e50; }
        .service-price { color: #0073aa; font-weight: 600; }
        .total-section { background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; padding: 30px; border-radius: 12px; margin: 30px 0; text-align: center; }
        .total-label { font-size: 18px; margin-bottom: 10px; opacity: 0.9; }
        .total-amount { font-size: 32px; font-weight: 700; margin: 0; }
        .lm-original-price { font-size: 24px; font-weight: 500; color: rgba(255,255,255,0.7); text-decoration: line-through; margin-right: 8px; }
        .lm-discounted-price { font-size: 36px; font-weight: 700; color: #28a745; margin-right: 8px; }
        .lm-discount-info { font-size: 16px; font-weight: 600; color: #28a745; background: rgba(40, 167, 69, 0.1); padding: 4px 8px; border-radius: 4px; margin-left: 8px; }
        .voucher-info { margin-top: 15px; font-size: 14px; opacity: 0.9; }
        .payment-info { background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #ffc107; }
        .payment-info h4 { margin: 0 0 10px 0; color: #856404; }
        .delivery-info { background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #17a2b8; }
        .delivery-info h4 { margin: 0 0 10px 0; color: #0c5460; }
        .voucher-box { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 40px; border-radius: 15px; text-align: center; margin: 30px 0; position: relative; overflow: hidden; }
        .voucher-box::before { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); animation: shimmer 3s ease-in-out infinite; }
        @keyframes shimmer { 0%, 100% { transform: rotate(0deg); } 50% { transform: rotate(180deg); } }
        .discount-text { font-size: 36px; font-weight: 700; margin-bottom: 15px; position: relative; z-index: 1; }
        .voucher-code { font-size: 28px; font-weight: 700; background: rgba(255,255,255,0.2); padding: 15px 25px; border-radius: 10px; margin: 15px 0; letter-spacing: 2px; position: relative; z-index: 1; }
        .code-label { font-size: 14px; opacity: 0.9; margin-top: 10px; position: relative; z-index: 1; }
        .instructions { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #28a745; }
        .instructions h3 { margin: 0 0 15px 0; color: #28a745; font-size: 18px; }
        .instructions ol { margin: 0; padding-left: 20px; }
        .instructions li { margin-bottom: 8px; color: #495057; }
        .highlight { background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 25px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #ffc107; }
        .highlight h4 { margin: 0 0 15px 0; color: #856404; font-size: 16px; }
        .highlight ul { margin: 0; padding-left: 20px; }
        .highlight li { margin-bottom: 8px; color: #856404; }
        .services-list { background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 25px; border-radius: 10px; margin: 25px 0; }
        .services-list h3 { margin: 0 0 15px 0; color: #1976d2; font-size: 18px; }
        .services-list ul { margin: 0; padding-left: 20px; }
        .services-list li { margin-bottom: 8px; color: #1976d2; }
        .footer { margin-top: 40px; padding: 30px; background: #f8f9fa; border-top: 1px solid #dee2e6; text-align: center; }
        .footer p { margin: 5px 0; color: #6c757d; }
        .footer strong { color: ' . ($type === 'booking' ? '#0073aa' : '#28a745') . '; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, ' . ($type === 'booking' ? '#28a745 0%, #20c997 100%' : '#0073aa 0%, #005177 100%') . '); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .cta-button:hover { background: linear-gradient(135deg, ' . ($type === 'booking' ? '#218838 0%, #1e7e34 100%' : '#005177 0%, #003d5c 100%') . '); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Lektorat Mac</div>
            <h1 class="email-title">' . esc_html($title) . '</h1>
        </div>

        <div class="content">
            ' . $content . '
        </div>

        <div class="footer">
            <p><strong>Lektorat Mac</strong> | Professionelle Lektorat-Services</p>
            <p>Vielen Dank, dass Sie unsere Services gewählt haben. Wir freuen uns auf die Zusammenarbeit mit Ihnen!</p>
            <p>Falls Sie Fragen haben, kontaktieren Sie uns gerne unter <strong>info@lektorat-mac.de</strong></p>
        </div>
    </div>
</body>
</html>';
    }

    private function wrapEmailTemplate(string $content, string $type): string
    {
        $title = $type === 'booking' ? 'Rechnung & Buchungsbestätigung' : '🎉 Ihr Rabatt-Gutschein';
        $headerColor = $type === 'booking' ? 'linear-gradient(135deg, #0073aa 0%, #005177 100%)' : 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
        
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lektorat Mac - E-Mail</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f8f9fa; }
        .container { max-width: 650px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .header { background: ' . $headerColor . '; color: white; padding: 40px 30px; text-align: center; position: relative; }
        .header::before { content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url("data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"0.05\"%3E%3Ccircle cx=\"30\" cy=\"30\" r=\"2\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat; }
        .logo { font-size: 28px; font-weight: 700; margin-bottom: 8px; position: relative; z-index: 1; }
        .email-title { margin: 0; font-size: 22px; font-weight: 500; position: relative; z-index: 1; }
        .content { padding: 40px 30px; }
        .greeting { font-size: 18px; margin-bottom: 25px; color: #2c3e50; }
        .email-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 30px 0; }
        .email-column { display: flex; flex-direction: column; gap: 20px; }
        .info-sections { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 30px 0; }
        @media (max-width: 600px) { .email-grid, .info-sections { grid-template-columns: 1fr; gap: 20px; } }
        .invoice-meta { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #0073aa; }
        .meta-item { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .meta-item:last-child { margin-bottom: 0; }
        .meta-label { font-weight: 600; color: #495057; }
        .meta-value { color: #0073aa; font-weight: 500; }
        .customer-info { background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 25px; border-radius: 10px; margin: 25px 0; }
        .customer-info h3 { margin: 0 0 15px 0; color: #1976d2; font-size: 18px; }
        .customer-detail { margin-bottom: 8px; }
        .customer-detail strong { color: #1976d2; }
        .service-details { margin: 25px 0; }
        .service-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid #e9ecef; }
        .service-item:last-child { border-bottom: none; }
        .service-name { font-weight: 600; color: #2c3e50; }
        .service-price { color: #0073aa; font-weight: 600; }
        .total-section { background: linear-gradient(135deg, #0073aa 0%, #005177 100%); color: white; padding: 30px; border-radius: 12px; margin: 30px 0; text-align: center; }
        .total-label { font-size: 18px; margin-bottom: 10px; opacity: 0.9; }
        .total-amount { font-size: 32px; font-weight: 700; margin: 0; }
        .lm-original-price { font-size: 24px; font-weight: 500; color: rgba(255,255,255,0.7); text-decoration: line-through; margin-right: 8px; }
        .lm-discounted-price { font-size: 36px; font-weight: 700; color: #28a745; margin-right: 8px; }
        .lm-discount-info { font-size: 16px; font-weight: 600; color: #28a745; background: rgba(40, 167, 69, 0.1); padding: 4px 8px; border-radius: 4px; margin-left: 8px; }
        .voucher-info { margin-top: 15px; font-size: 14px; opacity: 0.9; }
        .payment-info { background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #ffc107; }
        .payment-info h4 { margin: 0 0 10px 0; color: #856404; }
        .delivery-info { background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%); padding: 20px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #17a2b8; }
        .delivery-info h4 { margin: 0 0 10px 0; color: #0c5460; }
        .voucher-box { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 40px; border-radius: 15px; text-align: center; margin: 30px 0; position: relative; overflow: hidden; }
        .voucher-box::before { content: ""; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); animation: shimmer 3s ease-in-out infinite; }
        @keyframes shimmer { 0%, 100% { transform: rotate(0deg); } 50% { transform: rotate(180deg); } }
        .discount-text { font-size: 36px; font-weight: 700; margin-bottom: 15px; position: relative; z-index: 1; }
        .voucher-code { font-size: 28px; font-weight: 700; background: rgba(255,255,255,0.2); padding: 15px 25px; border-radius: 10px; margin: 15px 0; letter-spacing: 2px; position: relative; z-index: 1; }
        .code-label { font-size: 14px; opacity: 0.9; margin-top: 10px; position: relative; z-index: 1; }
        .instructions { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 25px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #28a745; }
        .instructions h3 { margin: 0 0 15px 0; color: #28a745; font-size: 18px; }
        .instructions ol { margin: 0; padding-left: 20px; }
        .instructions li { margin-bottom: 8px; color: #495057; }
        .highlight { background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); padding: 25px; border-radius: 10px; margin: 25px 0; border-left: 4px solid #ffc107; }
        .highlight h4 { margin: 0 0 15px 0; color: #856404; font-size: 16px; }
        .highlight ul { margin: 0; padding-left: 20px; }
        .highlight li { margin-bottom: 8px; color: #856404; }
        .services-list { background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 25px; border-radius: 10px; margin: 25px 0; }
        .services-list h3 { margin: 0 0 15px 0; color: #1976d2; font-size: 18px; }
        .services-list ul { margin: 0; padding-left: 20px; }
        .services-list li { margin-bottom: 8px; color: #1976d2; }
        .footer { margin-top: 40px; padding: 30px; background: #f8f9fa; border-top: 1px solid #dee2e6; text-align: center; }
        .footer p { margin: 5px 0; color: #6c757d; }
        .footer strong { color: ' . ($type === 'booking' ? '#0073aa' : '#28a745') . '; }
        .cta-button { display: inline-block; background: linear-gradient(135deg, ' . ($type === 'booking' ? '#28a745 0%, #20c997 100%' : '#0073aa 0%, #005177 100%') . '); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .cta-button:hover { background: linear-gradient(135deg, ' . ($type === 'booking' ? '#218838 0%, #1e7e34 100%' : '#005177 0%, #003d5c 100%') . '); }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Lektorat Mac</div>
            <h1 class="email-title">' . esc_html($title) . '</h1>
        </div>

        ' . $content . '

        <div class="footer">
            <p><strong>Lektorat Mac</strong> | Professionelle Lektorat-Services</p>
            <p>Vielen Dank, dass Sie unsere Services gewählt haben. Wir freuen uns auf die Zusammenarbeit mit Ihnen!</p>
            <p>Falls Sie Fragen haben, kontaktieren Sie uns gerne unter <strong>info@lektorat-mac.de</strong></p>
        </div>
    </div>
</body>
</html>';
    }

    private function addVoucherInfoToTemplate(string $template, float $originalTotal, float $total, float $discountAmount, string $voucherCode, int $voucherDiscount): string
    {
        // Replace the total-amount div with voucher pricing display
        $voucherPricingHtml = '
                <div class="lm-original-price">' . number_format($originalTotal, 2) . '€</div>
                <div class="lm-discounted-price">' . number_format($total, 2) . '€</div>
                <div class="lm-discount-info">(-' . number_format($discountAmount, 2) . '€)</div>
                <div class="voucher-info">
                    <p><strong>Gutscheincode:</strong> ' . esc_html($voucherCode) . ' (' . $voucherDiscount . '% Rabatt)</p>
                </div>';
        
        // Replace the entire total-amount div with voucher pricing HTML
        $template = str_replace(
            '<div class="total-amount">€{total_price}</div>',
            $voucherPricingHtml,
            $template
        );
        
        return $template;
    }

    private function getDefaultClientTemplate(): string
    {
        return __('Dear {{name}}, thank you for your booking request. Please find your invoice and booking details below.', 'lm-booking');
    }

    private function getDefaultAdminTemplate(): string
    {
        return __('New booking request from {{name}} ({{email}})

Service: {{service}}
Words: {{words}}
Norm Pages: {{norm_pages}}
Delivery: {{delivery}}
Expected Delivery: {{delivery_date}}
Extras: {{extras}}
Total: {{total}}€
Breakdown: {{breakdown}}

{{document_info}}

Please review and process this booking request.', 'lm-booking');
    }

    private function getAdminRecipients(): array
    {
        $settings = get_option('lm_booking_settings', []);
        $recipients = $settings['admin_recipients'] ?? '';
        
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
        
        return empty($validEmails) ? [get_option('admin_email')] : $validEmails;
    }

    private function generateHtmlMessage(string $message): string
    {
        // Convert newlines to <br> tags and allow safe HTML
        $processedMessage = nl2br($message);
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html__('Booking Request', 'lm-booking') . '</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #0073aa;">
        ' . wp_kses($processedMessage, [
            'br' => [],
            'a' => [
                'href' => [],
                'download' => [],
                'target' => []
            ],
            'strong' => [],
            'em' => [],
            'b' => [],
            'i' => []
        ]) . '
    </div>
    <p style="margin-top: 20px; font-size: 12px; color: #666;">
        ' . esc_html__('This email was sent from your website.', 'lm-booking') . '
    </p>
</body>
</html>';
        
        return $html;
    }

    private function formatExtrasList(string $extras, string $service = ''): string
    {
        if (empty($extras)) {
            return __('None', 'lm-booking');
        }

        $extrasArray = json_decode($extras, true);
        if (!is_array($extrasArray)) {
            return __('None', 'lm-booking');
        }

        $formatted = [];
        foreach ($extrasArray as $extra) {
            if (isset($extra['label'])) {
                // Check if this extra is actually inclusive for the selected package
                $includedPackages = $extra['included_packages'] ?? [];
                $selectedPackageIndex = $this->getSelectedPackageIndex($service);
                $isInclusive = in_array($selectedPackageIndex, $includedPackages);
                
                if ($isInclusive) {
                    // For inclusive extras, show with yellow notice styling and no price
                    $formatted[] = '<span style="background-color: #ffc107; color: #212529; padding: 2px 6px; border-radius: 3px; font-size: 11px;">' . esc_html($extra['label']) . ' (inklusive)</span>';
                } else {
                    // For regular extras, show with price
                    $price = $extra['price'] ?? 0;
                    $formatted[] = $extra['label'] . ' (' . number_format($price, 2) . '€)';
                }
            }
        }

        return empty($formatted) ? __('None', 'lm-booking') : implode(', ', $formatted);
    }

    /**
     * Get the package index for a given service name
     */
    private function getSelectedPackageIndex(string $serviceName): int
    {
        $settings = get_option('lm_booking_settings', []);
        $services = $settings['services'] ?? [];
        
        foreach ($services as $index => $service) {
            if ($service['label'] === $serviceName) {
                return $index;
            }
        }
        
        return 0; // Default to first package if not found
    }

    private function formatDelivery(string $delivery): string
    {
        switch ($delivery) {
            case '2d':
                return __('2 Tage (+15%)', 'lm-booking');
            case '1d':
                return __('1 Tag (+50%)', 'lm-booking');
            default:
                return __('3 Tage (Normal)', 'lm-booking');
        }
    }

    private function formatDeliveryDate(string $deliveryDate): string
    {
        if (empty($deliveryDate)) {
            return __('Not specified', 'lm-booking');
        }
        
        // Parse the delivery date and format it nicely
        $timestamp = strtotime($deliveryDate);
        if ($timestamp === false) {
            return $deliveryDate; // Return as-is if parsing fails
        }
        
        // Format as "D., j. M Y, H:i" (e.g., "Mo., 29. Sept. 2025, 00:17")
        return date('D., j. M Y, H:i', $timestamp);
    }

    private function formatBreakdown(array $breakdown): string
    {
        if (empty($breakdown)) {
            return __('No breakdown available', 'lm-booking');
        }

        $parts = [];
        $parts[] = __('Base', 'lm-booking') . ': ' . number_format($breakdown['base'] ?? 0, 2) . '€';
        
        if (!empty($breakdown['extras'])) {
            $parts[] = __('Extras', 'lm-booking') . ': ' . number_format($breakdown['extrasTotal'] ?? 0, 2) . '€';
        }
        
        if (($breakdown['surcharge'] ?? 0) > 0) {
            $parts[] = __('Surcharge', 'lm-booking') . ': ' . number_format($breakdown['surcharge'], 2) . '€';
        }
        
        $parts[] = __('Total', 'lm-booking') . ': ' . number_format($breakdown['total'] ?? 0, 2) . '€';

        return implode(', ', $parts);
    }

    private function generateInvoiceNumber(int $postId): string
    {
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        return sprintf('LM-%s%s%s-%04d', $year, $month, $day, $postId);
    }

    private function formatCountry(string $country): string
    {
        $countries = [
            'de' => __('Germany', 'lm-booking'),
            'at' => __('Austria', 'lm-booking'),
            'ch' => __('Switzerland', 'lm-booking'),
        ];
        return $countries[$country] ?? ucfirst($country);
    }

    private function formatProgram(string $program): string
    {
        $programs = [
            'bachelor' => __('Bachelor', 'lm-booking'),
            'master' => __('Master', 'lm-booking'),
            'phd' => __('PhD', 'lm-booking'),
            'other' => __('Other', 'lm-booking'),
        ];
        return $programs[$program] ?? ucfirst($program);
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function generateInvoiceHtml(int $postId, string $message): string
    {
        $email = get_post_meta($postId, '_lm_booking_customer_email', true);
        $name = get_post_meta($postId, '_lm_booking_customer_name', true);
        $service = get_post_meta($postId, '_lm_booking_service', true);
        $words = get_post_meta($postId, '_lm_booking_words', true);
        $normPages = get_post_meta($postId, '_lm_booking_norm_pages', true);
        $delivery = get_post_meta($postId, '_lm_booking_delivery', true);
        $deliveryDate = get_post_meta($postId, '_lm_booking_delivery_date', true);
        $extras = get_post_meta($postId, '_lm_booking_extras', true);
        $total = get_post_meta($postId, '_lm_booking_total', true);
        $breakdown = get_post_meta($postId, '_lm_booking_breakdown', true);
        $country = get_post_meta($postId, '_lm_booking_country', true);
        $program = get_post_meta($postId, '_lm_booking_program', true);
        $note = get_post_meta($postId, '_lm_booking_note', true);

        $breakdownData = json_decode($breakdown, true);
        $extrasArray = json_decode($extras, true);

        $invoiceNumber = $this->generateInvoiceNumber($postId);
        $invoiceDate = current_time('d.m.Y');
        $dueDate = date('d.m.Y', strtotime('+14 days'));

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . esc_html__('Invoice - Lektorat Mac', 'lm-booking') . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background-color: white; padding: 40px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .header { border-bottom: 3px solid #0073aa; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 28px; font-weight: bold; color: #0073aa; margin-bottom: 10px; }
        .invoice-title { font-size: 24px; color: #333; margin: 0; }
        .invoice-meta { display: flex; justify-content: space-between; margin: 20px 0; }
        .invoice-meta div { flex: 1; }
        .invoice-meta strong { display: block; margin-bottom: 5px; color: #0073aa; }
        .customer-info { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .service-details { margin: 30px 0; }
        .service-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .service-table th, .service-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .service-table th { background-color: #0073aa; color: white; font-weight: bold; }
        .service-table tr:nth-child(even) { background-color: #f8f9fa; }
        .total-section { background-color: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .total-row { display: flex; justify-content: space-between; margin: 8px 0; }
        .total-row.final { font-size: 18px; font-weight: bold; color: #0073aa; border-top: 2px solid #0073aa; padding-top: 10px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
        .payment-info { background-color: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107; }
        .delivery-info { background-color: #d1ecf1; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Lektorat Mac</div>
            <h1 class="invoice-title">' . esc_html__('Invoice & Booking Confirmation', 'lm-booking') . '</h1>
        </div>

        <div class="invoice-meta">
            <div>
                <strong>' . esc_html__('Invoice Number:', 'lm-booking') . '</strong>
                ' . esc_html($invoiceNumber) . '
            </div>
            <div>
                <strong>' . esc_html__('Invoice Date:', 'lm-booking') . '</strong>
                ' . esc_html($invoiceDate) . '
            </div>
            <div>
                <strong>' . esc_html__('Due Date:', 'lm-booking') . '</strong>
                ' . esc_html($dueDate) . '
            </div>
        </div>

        <div class="customer-info">
            <h3>' . esc_html__('Customer Information', 'lm-booking') . '</h3>
            <p><strong>' . esc_html__('Name:', 'lm-booking') . '</strong> ' . esc_html($name) . '</p>
            <p><strong>' . esc_html__('Email:', 'lm-booking') . '</strong> ' . esc_html($email) . '</p>
            <p><strong>' . esc_html__('Country:', 'lm-booking') . '</strong> ' . esc_html($this->formatCountry($country)) . '</p>
            <p><strong>' . esc_html__('Program:', 'lm-booking') . '</strong> ' . esc_html($this->formatProgram($program)) . '</p>
            ' . ($note ? '<p><strong>' . esc_html__('Notes:', 'lm-booking') . '</strong> ' . esc_html($note) . '</p>' : '') . '
        </div>

        <div class="service-details">
            <h3>' . esc_html__('Service Details', 'lm-booking') . '</h3>
            <table class="service-table">
                <thead>
                    <tr>
                        <th>' . esc_html__('Description', 'lm-booking') . '</th>
                        <th>' . esc_html__('Quantity', 'lm-booking') . '</th>
                        <th>' . esc_html__('Unit Price', 'lm-booking') . '</th>
                        <th>' . esc_html__('Total', 'lm-booking') . '</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>' . esc_html($service) . '</td>
                        <td>' . esc_html($normPages) . ' ' . esc_html__('norm pages', 'lm-booking') . '</td>
                        <td>' . esc_html(number_format($breakdownData['base'] / $normPages, 2)) . '€</td>
                        <td>' . esc_html(number_format($breakdownData['base'], 2)) . '€</td>
                    </tr>';

        // Add extras if any
        if (!empty($extrasArray) && is_array($extrasArray)) {
            foreach ($extrasArray as $extra) {
                if (isset($extra['label'], $extra['price'])) {
                    // Check if this extra is actually inclusive for the selected package
                    $includedPackages = $extra['included_packages'] ?? [];
                    $selectedPackageIndex = $this->getSelectedPackageIndex($service);
                    $isInclusive = in_array($selectedPackageIndex, $includedPackages);
                    
                    if ($isInclusive) {
                        // Show inclusive extra with special styling
                        $html .= '
                        <tr style="background-color: #fff3cd;">
                            <td>' . esc_html($extra['label']) . ' <span style="background-color: #ffc107; color: #212529; padding: 2px 6px; border-radius: 3px; font-size: 11px;">(inklusive)</span></td>
                            <td>1</td>
                            <td style="text-decoration: line-through; color: #6c757d;">' . esc_html(number_format($extra['price'], 2)) . '€</td>
                            <td style="text-decoration: line-through; color: #6c757d;">' . esc_html(number_format($extra['price'], 2)) . '€</td>
                        </tr>';
                    } else {
                        // Show regular extra with price
                        $html .= '
                        <tr>
                            <td>' . esc_html($extra['label']) . '</td>
                            <td>1</td>
                            <td>' . esc_html(number_format($extra['price'], 2)) . '€</td>
                            <td>' . esc_html(number_format($extra['price'], 2)) . '€</td>
                        </tr>';
                    }
                }
            }
        }

        $html .= '
                </tbody>
            </table>
        </div>

        <div class="total-section">
            <div class="total-row">
                <span>' . esc_html__('Subtotal:', 'lm-booking') . '</span>
                <span>' . esc_html(number_format($breakdownData['subtotal'] ?? 0, 2)) . '€</span>
            </div>';

        if (($breakdownData['surcharge'] ?? 0) > 0) {
            $html .= '
            <div class="total-row">
                <span>' . esc_html__('Delivery Surcharge (' . $this->formatDelivery($delivery) . '):', 'lm-booking') . '</span>
                <span>' . esc_html(number_format($breakdownData['surcharge'], 2)) . '€</span>
            </div>';
        }

        $html .= '
            <div class="total-row final">
                <span>' . esc_html__('Total Amount:', 'lm-booking') . '</span>
                <span>' . esc_html(number_format($total, 2)) . '€</span>
            </div>
        </div>

        <div class="delivery-info">
            <h4>' . esc_html__('Delivery Information', 'lm-booking') . '</h4>
            <p><strong>' . esc_html__('Delivery Time:', 'lm-booking') . '</strong> ' . esc_html($this->formatDelivery($delivery)) . '</p>
            <p><strong>' . esc_html__('Expected Delivery:', 'lm-booking') . '</strong> ' . esc_html($this->formatDeliveryDate($deliveryDate)) . '</p>
            <p><strong>' . esc_html__('Word Count:', 'lm-booking') . '</strong> ' . esc_html(number_format($words)) . ' ' . esc_html__('words', 'lm-booking') . '</p>
        </div>

        <div class="payment-info">
            <h4>' . esc_html__('Payment Information', 'lm-booking') . '</h4>
            <p>' . esc_html__('Payment is due within 14 days of invoice date. You will receive a payment link via email shortly.', 'lm-booking') . '</p>
            <p><strong>' . esc_html__('Payment Methods:', 'lm-booking') . '</strong> ' . esc_html__('Bank transfer, PayPal, Credit Card', 'lm-booking') . '</p>
        </div>

        <div class="footer">
            <p><strong>Lektorat Mac</strong> | ' . esc_html__('Professional Editing Services', 'lm-booking') . '</p>
            <p>' . esc_html__('Thank you for choosing our services. We look forward to working with you!', 'lm-booking') . '</p>
            <p>' . esc_html__('If you have any questions, please contact us at info@lektorat-mac.de', 'lm-booking') . '</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    public function sendVoucherEmail(array $voucher): bool|\WP_Error
    {
        $email = $voucher['email'];
        $code = $voucher['code'];
        $discount = $voucher['discount'];
        $expiry = $voucher['expiry'];

        $settings = get_option('lm_booking_settings', []);
        $subject = $settings['voucher_email_subject'] ?? sprintf('Ihr %d%% Rabatt-Gutschein - Lektorat Mac', $discount);
        
        $message = $this->generateVoucherEmailFromTemplate($voucher);
        $htmlMessage = $this->wrapEmailTemplateWithStyles($message, 'voucher');

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Lektorat Mac <info@lektorat-mac.de>'
        ];

        $sent = wp_mail($email, $subject, $htmlMessage, $headers);

        if (!$sent) {
            return new \WP_Error('email_failed', 'Failed to send voucher email');
        }

        return true;
    }

    private function generateVoucherEmailFromTemplate(array $voucher): string
    {
        $settings = get_option('lm_booking_settings', []);
        $template = $settings['voucher_email_template'] ?? $this->generateVoucherEmailHtml($voucher);
        
        $code = $voucher['code'];
        $discount = $voucher['discount'];
        $expiry = date('d.m.Y', strtotime($voucher['expiry']));
        $expiryDays = ceil((strtotime($voucher['expiry']) - time()) / (60 * 60 * 24));
        
        // Replace placeholders in the template
        $message = str_replace([
            '{customer_name}',
            '{voucher_code}',
            '{discount}',
            '{expiry_date}',
            '{expiry_days}'
        ], [
            $voucher['email'], // We don't have customer name for vouchers
            $code,
            $discount,
            $expiry,
            $expiryDays
        ], $template);
        
        return $message;
    }

    private function generateVoucherEmailHtml(array $voucher): string
    {
        $code = $voucher['code'];
        $discount = $voucher['discount'];
        $expiry = date('d.m.Y', strtotime($voucher['expiry']));
        $expiryDays = ceil((strtotime($voucher['expiry']) - time()) / (60 * 60 * 24));

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ihr Rabatt-Gutschein</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; }
        .header { background: #28a745; color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .voucher-box { background: #f8f9fa; border: 2px dashed #28a745; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
        .voucher-code { font-size: 32px; font-weight: bold; color: #28a745; letter-spacing: 3px; margin: 10px 0; }
        .discount-text { font-size: 18px; color: #28a745; font-weight: 600; }
        .instructions { background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; }
        .highlight { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Ihr Rabatt-Gutschein</h1>
            <p>Vielen Dank für Ihre Anmeldung!</p>
        </div>
        
        <div class="content">
            <h2>Herzlichen Glückwunsch!</h2>
            <p>Sie haben einen speziellen Rabatt-Gutschein für unsere professionellen Lektorat-Services erhalten.</p>
            
            <div class="voucher-box">
                <div class="discount-text">' . $discount . '% RABATT</div>
                <div class="voucher-code">' . esc_html($code) . '</div>
                <p><strong>Gutscheincode</strong></p>
            </div>
            
            <div class="instructions">
                <h3>So verwenden Sie Ihren Gutschein:</h3>
                <ol>
                    <li>Besuchen Sie unser Buchungsformular auf unserer Website</li>
                    <li>Füllen Sie Ihre Projekt-Details aus</li>
                    <li>Geben Sie den Gutscheincode während des Checkouts ein</li>
                    <li>Genießen Sie Ihren Rabatt!</li>
                </ol>
            </div>
            
            <div class="highlight">
                <p><strong>Wichtig:</strong></p>
                <ul>
                    <li>Dieser Gutschein ist gültig bis ' . $expiry . ' (' . $expiryDays . ' Tage ab heute)</li>
                    <li>Ein Gutschein pro Kunde</li>
                    <li>Kann nicht mit anderen Angeboten kombiniert werden</li>
                    <li>Gültig für alle unsere Lektorat-Services</li>
                </ul>
            </div>
            
            <h3>Unsere Services umfassen:</h3>
            <ul>
                <li>Professionelles akademisches Lektorat</li>
                <li>Formatierung nach akademischen Standards</li>
                <li>Sprachkorrektur und -verbesserung</li>
                <li>Schnelle Lieferoptionen verfügbar</li>
            </ul>
            
            <p>Falls Sie Fragen zu Ihrem Gutschein oder unseren Services haben, zögern Sie nicht, uns zu kontaktieren.</p>
        </div>
        
        <div class="footer">
            <p><strong>Lektorat Mac</strong> | Professionelle Lektorat-Services</p>
            <p>E-Mail: info@lektorat-mac.de</p>
            <p>Vielen Dank, dass Sie unsere Services gewählt haben!</p>
        </div>
    </div>
</body>
</html>';
    }

    public function sendEmailConfirmation(string $email, string $token, array $voucherData): bool|\WP_Error
    {
        $settings = get_option('lm_booking_settings', []);
        $subject = $settings['confirmation_email_subject'] ?? 'Bitte bestätigen Sie Ihre E-Mail-Adresse - Lektorat Mac';
        
        $message = $this->generateConfirmationEmailFromTemplate($email, $token, $voucherData);
        $htmlMessage = $this->wrapEmailTemplateWithStyles($message, 'confirmation');

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Lektorat Mac <info@lektorat-mac.de>'
        ];

        $sent = wp_mail($email, $subject, $htmlMessage, $headers);

        if (!$sent) {
            return new \WP_Error('email_failed', 'Failed to send confirmation email');
        }

        return true;
    }

    private function generateConfirmationEmailFromTemplate(string $email, string $token, array $voucherData): string
    {
        $settings = get_option('lm_booking_settings', []);
        $template = $settings['confirmation_email_template'] ?? $this->generateConfirmationEmailHtml($email, $token, $voucherData);
        
        $discount = $voucherData['discount'];
        $expiry = date('d.m.Y', strtotime($voucherData['expiry']));
        $expiryDays = ceil((strtotime($voucherData['expiry']) - time()) / (60 * 60 * 24));
        
        // Generate confirmation URL
        $confirmationUrl = add_query_arg([
            'action' => 'lm_confirm_email',
            'token' => $token
        ], admin_url('admin-ajax.php'));
        
        // Replace placeholders in the template
        $message = str_replace([
            '{customer_email}',
            '{discount}',
            '{expiry_date}',
            '{expiry_days}',
            '{confirmation_url}'
        ], [
            $email,
            $discount,
            $expiry,
            $expiryDays,
            $confirmationUrl
        ], $template);
        
        return $message;
    }

    private function generateConfirmationEmailHtml(string $email, string $token, array $voucherData): string
    {
        $discount = $voucherData['discount'];
        $expiry = date('d.m.Y', strtotime($voucherData['expiry']));
        $expiryDays = ceil((strtotime($voucherData['expiry']) - time()) / (60 * 60 * 24));
        
        // Generate confirmation URL
        $confirmationUrl = add_query_arg([
            'action' => 'lm_confirm_email',
            'token' => $token
        ], admin_url('admin-ajax.php'));

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail-Adresse bestätigen</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; }
        .header { background: #007cba; color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .confirmation-box { background: #f8f9fa; border: 2px solid #007cba; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0; }
        .confirm-button { background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin: 15px 0; }
        .confirm-button:hover { background: #218838; }
        .instructions { background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; }
        .highlight { background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 E-Mail-Adresse bestätigen</h1>
            <p>Fast geschafft! Bestätigen Sie Ihre E-Mail-Adresse</p>
        </div>
        
        <div class="content">
            <h2>Hallo!</h2>
            <p>Vielen Dank für Ihre Anmeldung für unseren Rabatt-Gutschein. Um Ihren Gutschein zu erhalten, müssen Sie zunächst Ihre E-Mail-Adresse bestätigen.</p>
            
            <div class="confirmation-box">
                <h3>🎁 Ihr Gutschein wartet auf Sie!</h3>
                <p><strong>' . $discount . '% Rabatt</strong> auf alle unsere Lektorat-Services</p>
                <p>Gültig bis: ' . $expiry . ' (' . $expiryDays . ' Tage)</p>
                
                <a href="' . esc_url($confirmationUrl) . '" class="confirm-button">
                    ✅ E-Mail-Adresse bestätigen
                </a>
            </div>
            
            <div class="instructions">
                <h3>So funktioniert es:</h3>
                <ol>
                    <li>Klicken Sie auf den Bestätigungsbutton oben</li>
                    <li>Ihr Gutschein wird automatisch an diese E-Mail-Adresse gesendet</li>
                    <li>Verwenden Sie den Gutscheincode bei Ihrer nächsten Buchung</li>
                </ol>
            </div>
            
            <div class="highlight">
                <p><strong>Wichtig:</strong></p>
                <ul>
                    <li>Diese Bestätigung ist nur 24 Stunden gültig</li>
                    <li>Ein Gutschein pro E-Mail-Adresse</li>
                    <li>Falls der Button nicht funktioniert, kopieren Sie diesen Link:</li>
                    <p style="word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 12px;">' . esc_url($confirmationUrl) . '</p>
                </ul>
            </div>
            
            <p>Falls Sie sich nicht für unseren Newsletter angemeldet haben, können Sie diese E-Mail ignorieren.</p>
        </div>
        
        <div class="footer">
            <p><strong>Lektorat Mac</strong> | Professionelle Lektorat-Services</p>
            <p>E-Mail: info@lektorat-mac.de</p>
            <p>Vielen Dank für Ihr Vertrauen!</p>
        </div>
    </div>
</body>
</html>';
    }
}
