<?php

namespace LM\Booking\Public;

use LM\Booking\Infra\Repo;
use LM\Booking\Infra\Email;

class Confirmation
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
        add_action('init', [$this, 'handleEmailConfirmation']);
    }

    public function handleEmailConfirmation(): void
    {
        // Check if this is a confirmation request
        if (!isset($_GET['action']) || $_GET['action'] !== 'lm_confirm_email') {
            return;
        }

        if (!isset($_GET['token'])) {
            $this->showConfirmationError('Bestätigungstoken fehlt.');
            return;
        }

        $token = sanitize_text_field($_GET['token']);
        
        try {
            // Check if token exists before attempting confirmation
            $confirmations = get_option('lm_booking_email_confirmations', []);
            if (!isset($confirmations[$token])) {
                $this->showConfirmationError('Ungültiger oder bereits verwendeter Bestätigungstoken.');
                return;
            }
            
            // Confirm the email (this will remove the token after successful use)
            $confirmation = $this->repo->confirmEmail($token);
            
            if (!$confirmation) {
                $this->showConfirmationError('Ungültiger oder abgelaufener Bestätigungstoken.');
                return;
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
                $this->showConfirmationError('E-Mail bestätigt, aber Gutschein-Versand fehlgeschlagen. Bitte kontaktieren Sie den Support.');
                return;
            }
            
            $this->showConfirmationSuccess($voucher);
            
        } catch (Exception $e) {
            $this->showConfirmationError('Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
        }
    }

    private function saveToBookingLists(array $voucher): void
    {
        // No longer creating WordPress posts for voucher signups
        // Everything is now stored in lm_booking_vouchers only
        return;
    }

    private function showConfirmationSuccess(array $voucher): void
    {
        $discount = $voucher['discount'];
        $code = $voucher['code'];
        $expiry = date('d.m.Y', strtotime($voucher['expiry']));
        $expiryDays = ceil((strtotime($voucher['expiry']) - time()) / (60 * 60 * 24));
        
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Mail bestätigt - Lektorat Mac</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 50px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: #28a745; color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .success-box { background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 20px 0; }
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
            <h1>✅ E-Mail bestätigt!</h1>
            <p>Ihr Gutschein wurde gesendet</p>
        </div>
        
        <div class="content">
            <div class="success-box">
                <h2>🎉 Herzlichen Glückwunsch!</h2>
                <p>Ihre E-Mail-Adresse wurde erfolgreich bestätigt und Ihr Rabatt-Gutschein wurde an Sie gesendet.</p>
            </div>
            
            <div class="voucher-box">
                <div class="discount-text">' . $discount . '% RABATT</div>
                <div class="voucher-code">' . esc_html($code) . '</div>
                <p><strong>Ihr Gutscheincode</strong></p>
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

        echo $html;
        exit;
    }

    private function showConfirmationError(string $message): void
    {
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bestätigung fehlgeschlagen - Lektorat Mac</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 50px auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: #dc3545; color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; }
        .error-box { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 14px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>❌ Bestätigung fehlgeschlagen</h1>
        </div>
        
        <div class="content">
            <div class="error-box">
                <h2>Es ist ein Fehler aufgetreten</h2>
                <p>' . esc_html($message) . '</p>
                <p>Bitte versuchen Sie es erneut oder kontaktieren Sie uns, falls das Problem weiterhin besteht.</p>
            </div>
            
            <p>Falls Sie Fragen haben, zögern Sie nicht, uns zu kontaktieren.</p>
        </div>
        
        <div class="footer">
            <p><strong>Lektorat Mac</strong> | Professionelle Lektorat-Services</p>
            <p>E-Mail: info@lektorat-mac.de</p>
        </div>
    </div>
</body>
</html>';

        echo $html;
        exit;
    }
}
