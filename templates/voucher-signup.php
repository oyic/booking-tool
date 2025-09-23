<?php
/**
 * Voucher Signup Form Template
 * Simple horizontal layout with honeypot protection
 */

// Get shortcode attributes
$atts = shortcode_atts([
    'button_text' => 'Anmelden',
    'discount' => '',
    'expiry_days' => ''
], $atts);

// Get settings for default values
$settings = get_option('lm_booking_settings', []);
$default_discount = floatval($settings['voucher_signup_discount'] ?? 15);
$default_expiry = intval($settings['voucher_signup_expiry_days'] ?? 30);

// Use shortcode attributes if provided, otherwise use settings
$discount = !empty($atts['discount']) ? floatval($atts['discount']) : $default_discount;
$expiry_days = !empty($atts['expiry_days']) ? intval($atts['expiry_days']) : $default_expiry;
?>

<div class="lm-voucher-signup-container">
    <form id="lm-voucher-form" class="lm-voucher-form">
        <?php wp_nonce_field('lm_voucher_signup', 'lm_voucher_nonce'); ?>
        
        <!-- Honeypot field -->
        <input type="text" name="lm_website_url" class="lm-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true" />
        
        <!-- Form fields -->
        <div class="lm-voucher-fields">
            <input 
                type="email" 
                id="lm-voucher-email" 
                name="email" 
                placeholder="<?php esc_attr_e('Ihre E-Mail-Adresse', 'lm-booking'); ?>"
                required
            />
            <button type="submit" id="lm-voucher-submit">
                <span class="lm-btn-text"><?php echo esc_html($atts['button_text']); ?></span>
                <span class="lm-btn-loading" style="display: none;"><?php esc_html_e('Wird gesendet...', 'lm-booking'); ?></span>
            </button>
        </div>
    </form>
    
    <!-- Success notification -->
    <div id="lm-voucher-success" class="lm-voucher-success" style="display: none;">
        <div class="lm-success-icon">📧</div>
        <div class="lm-success-content">
            <h3><?php esc_html_e('Bestätigungs-E-Mail gesendet!', 'lm-booking'); ?></h3>
            <p><?php esc_html_e('Wir haben Ihnen eine Bestätigungs-E-Mail gesendet. Bitte überprüfen Sie Ihr E-Mail-Postfach und klicken Sie auf den Bestätigungslink, um Ihren Gutschein zu erhalten.', 'lm-booking'); ?></p>
            <div class="lm-confirmation-info">
                <p><strong><?php esc_html_e('Nächste Schritte:', 'lm-booking'); ?></strong></p>
                <ol>
                    <li><?php esc_html_e('Überprüfen Sie Ihr E-Mail-Postfach', 'lm-booking'); ?></li>
                    <li><?php esc_html_e('Klicken Sie auf den Bestätigungslink in der E-Mail', 'lm-booking'); ?></li>
                    <li><?php esc_html_e('Ihr Gutschein wird automatisch gesendet', 'lm-booking'); ?></li>
                </ol>
            </div>
        </div>
    </div>
    
    <!-- Error notification -->
    <div id="lm-voucher-error" class="lm-voucher-error" style="display: none;">
        <div class="lm-error-icon">⚠</div>
        <div class="lm-error-content">
            <h3><?php esc_html_e('Fehler', 'lm-booking'); ?></h3>
            <p id="lm-error-message"></p>
        </div>
    </div>
</div>

<style>
/* Container */
.lm-voucher-signup-container {
    max-width: 500px;
    margin: 0 auto;
}

/* Form */
.lm-voucher-form {
    margin-bottom: 20px;
}

.lm-voucher-fields {
    display: flex;
    gap: 10px;
    align-items: center;
}

.lm-voucher-fields input[type="email"] {
    flex: 1;
    padding: 12px 16px;
    border: 2px solid #ddd;
    border-radius: 6px;
    font-size: 16px;
    outline: none;
    transition: border-color 0.3s ease;
}

.lm-voucher-fields input[type="email"]:focus {
    border-color: #28a745;
}

.lm-voucher-fields button {
    padding: 12px 24px;
    background: #28a745;
    color: white;
    border: 2px solid #28a745;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.3s ease;
    white-space: nowrap;
}

.lm-voucher-fields button:hover {
    background: #218838;
    border-color: #218838;
}

.lm-voucher-fields button:disabled {
    background: #6c757d;
    border-color: #6c757d;
    cursor: not-allowed;
}

/* Honeypot */
.lm-honeypot {
    position: absolute !important;
    left: -9999px !important;
    top: -9999px !important;
    width: 1px !important;
    height: 1px !important;
    opacity: 0 !important;
    visibility: hidden !important;
    pointer-events: none !important;
}

/* Success notification */
.lm-voucher-success {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #d4edda;
    border: 2px solid #c3e6cb;
    border-radius: 8px;
    color: #155724;
}

.lm-success-icon {
    width: 40px;
    height: 40px;
    background: #28a745;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: bold;
    flex-shrink: 0;
}

.lm-success-content {
    flex: 1;
}

.lm-success-content h3 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
    color: #155724;
}

.lm-success-content p {
    margin: 0 0 12px 0;
    font-size: 14px;
    line-height: 1.4;
}

.lm-confirmation-info {
    background: #fff;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #c3e6cb;
    margin-top: 15px;
}

.lm-confirmation-info p {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #155724;
}

.lm-confirmation-info ol {
    margin: 0;
    padding-left: 20px;
    font-size: 14px;
    color: #155724;
}

.lm-confirmation-info li {
    margin-bottom: 5px;
}

/* Error notification */
.lm-voucher-error {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #f8d7da;
    border: 2px solid #f5c6cb;
    border-radius: 8px;
    color: #721c24;
}

.lm-error-icon {
    width: 40px;
    height: 40px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: bold;
    flex-shrink: 0;
}

.lm-error-content {
    flex: 1;
}

.lm-error-content h3 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
    color: #721c24;
}

.lm-error-content p {
    margin: 0;
    font-size: 14px;
    line-height: 1.4;
}

/* Responsive */
@media (max-width: 480px) {
    .lm-voucher-fields {
        flex-direction: column;
        gap: 12px;
    }
    
    .lm-voucher-fields input[type="email"],
    .lm-voucher-fields button {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Form submission
    $('#lm-voucher-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $btn = $('#lm-voucher-submit');
        const $btnText = $btn.find('.lm-btn-text');
        const $btnLoading = $btn.find('.lm-btn-loading');
        const $success = $('#lm-voucher-success');
        const $error = $('#lm-voucher-error');
        const email = $('#lm-voucher-email').val().trim();
        
        // Hide previous notifications
        $success.hide();
        $error.hide();
        
        // Validate email
        if (!email || !isValidEmail(email)) {
            showError('<?php esc_js(__('Bitte geben Sie eine gültige E-Mail-Adresse ein.', 'lm-booking')); ?>');
            return;
        }
        
        // Show loading state
        $btn.prop('disabled', true);
        $btnText.hide();
        $btnLoading.show();
        
        // Submit via AJAX
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'lm_voucher_signup',
                email: email,
                lm_voucher_nonce: $('#lm_voucher_nonce').val(),
                lm_website_url: $('input[name="lm_website_url"]').val()
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data);
                } else {
                    showError(response.data.message || '<?php esc_js(__('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'lm-booking')); ?>');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = '<?php esc_js(__('Netzwerkfehler. Bitte versuchen Sie es erneut.', 'lm-booking')); ?>';
                
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.data && errorResponse.data.message) {
                        errorMessage = errorResponse.data.message;
                    }
                } catch (e) {
                    // Use default error message
                }
                
                showError(errorMessage);
            },
            complete: function() {
                // Hide loading state
                $btn.prop('disabled', false);
                $btnText.show();
                $btnLoading.hide();
            }
        });
    });
    
    function showSuccess(data) {
        $('#lm-voucher-success').show();
        $('#lm-voucher-form').hide();
    }
    
    function showError(message) {
        $('#lm-error-message').text(message);
        $('#lm-voucher-error').show();
    }
    
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
});
</script>