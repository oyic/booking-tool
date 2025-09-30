<?php
$settings = get_option('lm_booking_settings', []);

// Set locale based on language setting
$selected_language = $settings['language'] ?? 'en';
if ($selected_language !== 'en') {
    $locale = $selected_language . '_' . strtoupper($selected_language);
    if ($selected_language === 'de') {
        $locale = 'de_DE';
    }
    add_filter('locale', function() use ($locale) {
        return $locale;
    });
    // Reload text domain with new locale
    unload_textdomain('lm-booking');
    load_plugin_textdomain('lm-booking', false, dirname(plugin_basename(__FILE__)) . '/../languages/');
}
// Load services from settings
$services = $settings['services'] ?? [];

// If no services array found, try to reconstruct from individual fields
if (empty($services)) {
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
}

// If still no services found, use German defaults
if (empty($services)) {
    $services = [
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
        ]
    ];
}

// Load extras from settings
$extras = $settings['extras'] ?? [];

// If no extras array found, try to reconstruct from individual fields
if (empty($extras)) {
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
}

// If still no extras found, use German defaults
if (empty($extras)) {
    $extras = [
        ['label' => 'Inhaltsverzeichnis erstellen', 'price' => 50, 'description' => ''],
        ['label' => 'Wissenschaftliche Textformatierung', 'price' => 50, 'description' => ''],
        ['label' => 'Literaturverzeichnis überprüfen', 'price' => 60, 'description' => ''],
        ['label' => 'Zitationsprüfung', 'price' => 60, 'description' => ''],
        ['label' => 'Textkohärenz überprüfen', 'price' => 65, 'description' => ''],
        ['label' => 'Präsentation überprüfen', 'price' => 250, 'description' => ''],
        ['label' => 'Plagiatsprüfung', 'price' => 90, 'description' => ''],
        ['label' => 'Gendergerechte Sprache', 'price' => 65, 'description' => ''],
    ];
}

// Load delivery options from settings
$deliveryOptions = $settings['delivery_options'] ?? [];

// Debug: Log what we found
error_log('Form Template - delivery_options from settings: ' . print_r($deliveryOptions, true));
error_log('Form Template - delivery_options empty check: ' . (empty($deliveryOptions) ? 'YES' : 'NO'));

// Always try to reconstruct from individual fields to ensure we have the latest data
$reconstructedDeliveryOptions = [];
foreach ($settings as $key => $value) {
    if (strpos($key, 'delivery_label_') === 0) {
        $deliveryKey = str_replace('delivery_label_', '', $key);
        $label = $value;
        $price = $settings['delivery_price_' . $deliveryKey] ?? 0;
        $days = $settings['delivery_days_' . $deliveryKey] ?? 3; // Get actual days from settings
        
        error_log('Form Template - Found delivery field: ' . $key . ' = ' . $value . ', days = ' . $days . ', price = ' . $price);
        
        if (!empty($label)) {
            $reconstructedDeliveryOptions[] = [
                'label' => $label,
                'days' => intval($days),
                'surcharge' => floatval($price),
                'enabled' => true
            ];
        }
    }
}

// Use reconstructed options if we found any, otherwise use the ones from settings
if (!empty($reconstructedDeliveryOptions)) {
    $deliveryOptions = $reconstructedDeliveryOptions;
    error_log('Form Template - Using reconstructed delivery options: ' . print_r($deliveryOptions, true));
} else {
    error_log('Form Template - No reconstructed options found, using settings: ' . print_r($deliveryOptions, true));
}

// If still no delivery options found, use German defaults
if (empty($deliveryOptions)) {
    error_log('Form Template - No delivery options found, using defaults');
    $deliveryOptions = [
        ['label' => 'Normal', 'days' => 3, 'surcharge' => 0, 'enabled' => true],
        ['label' => 'Schnelle Lieferung', 'days' => 2, 'surcharge' => 15, 'enabled' => true],
        ['label' => 'Express-Lieferung', 'days' => 1, 'surcharge' => 50, 'enabled' => true],
    ];
}

// Final debug log
error_log('Form Template - Final delivery options: ' . print_r($deliveryOptions, true));
error_log('Form Template - Delivery options count: ' . count($deliveryOptions));
?>

<div class="lm-booking-wizard">
    <div class="lm-wizard-container">
        <div class="lm-wizard-main">
            <nav class="lm-stepper" role="navigation" aria-label="<?php esc_attr_e('Booking progress', 'lm-booking'); ?>">
                <div class="lm-stepper-item" aria-current="step">
                    <div class="lm-stepper-circle">1</div>
                    <span class="lm-stepper-label"><?php esc_html_e('Dein Paket', 'lm-booking'); ?></span>
                </div>
                <div class="lm-stepper-line"></div>
                <div class="lm-stepper-item">
                    <div class="lm-stepper-circle">2</div>
                    <span class="lm-stepper-label"><?php esc_html_e('Extras', 'lm-booking'); ?></span>
                </div>
                <div class="lm-stepper-line"></div>
                <div class="lm-stepper-item">
                    <div class="lm-stepper-circle">3</div>
                    <span class="lm-stepper-label"><?php esc_html_e('Deine Informationen', 'lm-booking'); ?></span>
                </div>
            </nav>

            <form id="lm-booking-form" class="lm-wizard-form" action="https://formspree.io/f/YOUR_FORM_ID" method="POST">
                <div id="lm-step-1" class="lm-step" data-step="1">
                    <h2 class="lm-step-title"><?php esc_html_e('Welches Paket möchtest du?', 'lm-booking'); ?></h2>
                    <div class="lm-package-cards">
                        <?php foreach ($services as $index => $service): ?>
                        <label class="lm-card lm-package-card <?php echo $index === 0 ? 'lm-card-selected' : ''; ?>" data-service="<?php echo esc_attr($service['label']); ?>" data-price="<?php echo esc_attr($service['price']); ?>">
                            <input type="radio" name="service" value="<?php echo esc_attr($service['label']); ?>" class="lm-card-input" <?php echo $index === 0 ? 'checked' : ''; ?>>
                            <?php if (!empty($service['featured'])): ?>
                            <div class="lm-bestseller-badge"><?php esc_html_e('BESTSELLER', 'lm-booking'); ?></div>
                            <?php endif; ?>
                            <div class="lm-card-content">
                                <div class="lm-card-title"><?php echo esc_html($service['label']); ?></div>
                                <?php if (!empty($service['description'])): ?>
                                <div class="lm-card-description"><?php echo esc_html($service['description']); ?></div>
                                <?php endif; ?>
                                <div class="lm-card-price">
                                    <?php echo esc_html(number_format($service['price'], 2, ',', '.')); ?>€
                                    <span class="lm-price-unit">/ <?php esc_html_e('Normsite', 'lm-booking'); ?></span>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <h3 class="lm-step-subtitle"><?php esc_html_e('Wie lange haben wir Zeit?', 'lm-booking'); ?></h3>
                    <div class="lm-delivery-pills">
                        <?php 
                        error_log('Form Template - Rendering delivery pills, count: ' . count($deliveryOptions));
                        foreach ($deliveryOptions as $index => $option): 
                            error_log('Form Template - Processing delivery option ' . $index . ': ' . print_r($option, true));
                        ?>
                            <?php if ($option['enabled']): ?>
                                <label class="lm-pill <?php echo $index === 1 ? 'lm-pill-selected' : ''; ?>">
                                    <input type="radio" name="delivery" value="<?php echo esc_attr($option['days']); ?>d" class="lm-pill-input" <?php echo $index === 1 ? 'checked' : ''; ?>>
                                    <span class="lm-pill-content">
                                        <span class="lm-pill-label"><?php echo esc_html($option['label']); ?></span>
                                        <span class="lm-pill-desc">
                                            <?php echo esc_html($option['days']); ?> <?php esc_html_e('days', 'lm-booking'); ?>
                                            <?php if ($option['surcharge'] > 0): ?>
                                                / <?php echo esc_html($option['surcharge']); ?>% <?php esc_html_e('surcharge', 'lm-booking'); ?>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <p class="lm-delivery-note"><?php esc_html_e('Wichtig: Du erhältst nach dem Abschicken innerhalb von 24 Stunden ein Zahlungslink von uns. Die angegebene Lieferzeiten gelten ab der Zahlung.', 'lm-booking'); ?></p>

                    <h3 class="lm-step-subtitle"><?php esc_html_e('Wie Viele Wörter hat deine Arbeit?', 'lm-booking'); ?></h3>
                    <p class="lm-words-description"><?php esc_html_e('Bitte trage hier die Anzahl der Wörter deiner wissenschaftlichen Arbeit ein. Gezählt werden alle Wörter von der Einleitung bis einschließlich zum Fazit bzw. abschließenden Kapitel. Inhaltsverzeichnis, Deckblatt, Literaturverzeichnis und eventuelle Anhänge oder Quellenangaben werden nicht mitgezählt. Die Angabe dient der formalen Überprüfung deiner Arbeit.', 'lm-booking'); ?></p>
                    <div class="lm-words-input">
                        <input type="range" id="lm-words-slider" class="lm-words-slider" min="250" max="50000" value="250" step="10">
                        <div class="lm-words-display">
                            <input type="number" id="lm-words" name="words" class="lm-words-number" min="250" value="250">
                            <span class="lm-words-label"><?php esc_html_e('Wörter', 'lm-booking'); ?></span>
                        </div>
                    </div>
                    <div class="lm-upload-container">
                        <label class="lm-upload-btn" for="lm-upload-input">
                            <span class="lm-upload-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                </svg>
                            </span>
                            <?php esc_html_e('Datei hochladen*', 'lm-booking'); ?>
                        </label>
                        <p class="lm-upload-note"><?php esc_html_e('Erforderlich: DOC, DOCX, ODT oder RTF (max. 10MB)', 'lm-booking'); ?></p>
                    </div>
                </div>

                <div id="lm-step-2" class="lm-step" data-step="2" style="display: none;">
                    <h2 class="lm-step-title"><?php esc_html_e('Extras für die perfekte Arbeit:', 'lm-booking'); ?></h2>
                    <div class="lm-extras-list">
                        <?php foreach ($extras as $extra): ?>
                        <label class="lm-extras-item">
                            <input type="checkbox" name="extras[]" value="<?php echo esc_attr(wp_json_encode($extra)); ?>" class="lm-extras-checkbox" data-price="<?php echo esc_attr($extra['price']); ?>">
                            <div class="lm-extras-content">
                                <span class="lm-extras-label"><?php echo esc_html($extra['label']); ?></span>
                                <span class="lm-extras-price"><?php echo esc_html($extra['price'] == floor($extra['price']) ? number_format($extra['price'], 0, ',', '') : number_format($extra['price'], 2, ',', '')); ?>€</span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="lm-step-3" class="lm-step" data-step="3" style="display: none;">
                    <h2 class="lm-step-title"><?php esc_html_e('Deine Informationen:', 'lm-booking'); ?></h2>
                    
                    <!-- Voucher Section -->
                    <div class="lm-voucher-section">
                        <h3 class="lm-step-subtitle"><?php esc_html_e('Gutscheincode (optional):', 'lm-booking'); ?></h3>
                        <div class="lm-voucher-input">
                            <input type="text" id="lm-voucher-code" class="lm-input" placeholder="<?php esc_attr_e('Gutscheincode eingeben', 'lm-booking'); ?>" maxlength="20">
                            <button type="button" id="lm-apply-voucher" class="lm-voucher-btn"><?php esc_html_e('Anwenden', 'lm-booking'); ?></button>
                        </div>
                        <div id="lm-voucher-message" class="lm-voucher-message"></div>
                    </div>
                    
                    <div class="lm-customer-form">
                        <div class="lm-field">
                            <input type="text" id="lm-name" name="name" class="lm-input" placeholder="<?php esc_attr_e('Vor- und Nachname', 'lm-booking'); ?>" required>
                        </div>
                        <div class="lm-field">
                            <input type="email" id="lm-email" name="email" class="lm-input" placeholder="<?php esc_attr_e('E-Mail-Adresse*', 'lm-booking'); ?>" required>
                        </div>
                        <div class="lm-field">
                            <select id="lm-country" name="country" class="lm-select" required>
                                <option value=""><?php esc_html_e('Land*', 'lm-booking'); ?></option>
                                <option value="de"><?php esc_html_e('Deutschland', 'lm-booking'); ?></option>
                                <option value="at"><?php esc_html_e('Österreich', 'lm-booking'); ?></option>
                                <option value="ch"><?php esc_html_e('Schweiz', 'lm-booking'); ?></option>
                            </select>
                        </div>
                        <div class="lm-field">
                            <select id="lm-program" name="program" class="lm-select" required>
                                <option value=""><?php esc_html_e('Studiengang*', 'lm-booking'); ?></option>
                                <option value="bachelor_thesis"><?php esc_html_e('Bachelor Thesis', 'lm-booking'); ?></option>
                                <option value="masters_thesis"><?php esc_html_e('Master\'s Thesis', 'lm-booking'); ?></option>
                                <option value="phd_dissertation"><?php esc_html_e('PhD Dissertation', 'lm-booking'); ?></option>
                                <option value="research_paper"><?php esc_html_e('Research Paper', 'lm-booking'); ?></option>
                                <option value="term_paper"><?php esc_html_e('Term Paper', 'lm-booking'); ?></option>
                            </select>
                        </div>
                        <div class="lm-field">
                            <textarea id="lm-note" name="note" class="lm-textarea" placeholder="<?php esc_attr_e('Bemerkung', 'lm-booking'); ?>" rows="4"></textarea>
                        </div>
                    </div>
                </div>

                <div id="lm-step-success" class="lm-step" data-step="success" style="display: none;">
                    <h2 class="lm-step-title">
                        <?php esc_html_e('Buchung erfolgreich abgeschlossen', 'lm-booking'); ?> 🎉
                    </h2>
                    <div class="lm-success-content">
                        <p><?php esc_html_e('Vielen Dank für Ihre Anfrage!', 'lm-booking'); ?></p>
                        <p><?php esc_html_e('Ihre Angaben wurden erfolgreich an unser Team übermittelt. Wir prüfen Ihre Unterlagen sorgfältig und setzen uns schnellstmöglich mit Ihnen in Verbindung. Innerhalb der nächsten 24 Stunden erhalten Sie von uns eine Rückmeldung sowie die passende Rechnung.', 'lm-booking'); ?></p>
                        <p><?php esc_html_e('Sollten währenddessen Fragen auftreten, können Sie uns jederzeit kontaktieren – wir helfen Ihnen gerne weiter.', 'lm-booking'); ?></p>
                        <p><?php esc_html_e('Wir freuen uns darauf, Sie bald bei uns willkommen zu heißen und wünschen Ihnen bis dahin alles Gute.', 'lm-booking'); ?></p>
                    </div>
                </div>

                <input type="hidden" name="total" id="lm-total" value="0">
                <input type="hidden" name="breakdown" id="lm-breakdown" value="">
                <input type="hidden" name="norm_pages" id="lm-norm-pages" value="0">
                <input type="hidden" name="delivery_date" id="lm-delivery-date" value="">
                <input type="hidden" name="consent" id="lm-consent" value="1">
                <input type="hidden" name="document" id="lm-document-hidden" value="">

                <div class="lm-error" aria-live="polite" style="display: none;"></div>
            </form>
            
            <!-- File input completely outside the form -->
            <input type="file" id="lm-upload-input" accept=".doc,.docx,.rtf" style="display: none;">
        </div>

        <div class="lm-wizard-summary">
            <div class="lm-summary-card">
                <h3 class="lm-summary-title"><?php esc_html_e('Deine Konfiguration:', 'lm-booking'); ?></h3>
                <div class="lm-summary-total">
                    <span class="lm-total-amount" id="lm-total-display">€0</span>
                    <span class="lm-total-label"><?php esc_html_e('/ Gesamtpreis', 'lm-booking'); ?></span>
                </div>
                <div class="lm-summary-voucher" id="lm-summary-voucher" style="display: none;">
                    <div class="lm-voucher-info">
                        <span class="lm-voucher-label"><?php esc_html_e('Gutschein-Rabatt:', 'lm-booking'); ?></span>
                        <span class="lm-voucher-amount" id="lm-voucher-discount">-€0</span>
                    </div>
                    <div class="lm-voucher-code" id="lm-voucher-code-display"></div>
                </div>
                <div class="lm-summary-collapsible" id="lm-summary-collapsible">
                    <div class="lm-summary-delivery">
                        <div class="lm-delivery-label"><?php esc_html_e('Voraussichtliche lieferung bis zum', 'lm-booking'); ?></div>
                        <div class="lm-delivery-date" id="lm-delivery-date-display">-</div>
                    </div>
                    <div class="lm-summary-details">
                        <div class="lm-summary-item">
                            <span class="lm-summary-icon">✓</span>
                            <span class="lm-summary-text"><?php esc_html_e('Dein Paket:', 'lm-booking'); ?> <span id="lm-summary-package">-</span></span>
                        </div>
                        <div class="lm-summary-item">
                            <span class="lm-summary-icon">✓</span>
                            <span class="lm-summary-text"><?php esc_html_e('Gewählte Zeitdauer:', 'lm-booking'); ?> <span id="lm-summary-delivery">-</span></span>
                        </div>
                        <div class="lm-summary-item">
                            <span class="lm-summary-icon">✓</span>
                            <span class="lm-summary-text"><?php esc_html_e('Extra Leistungen:', 'lm-booking'); ?> <span id="lm-summary-extras"><?php esc_html_e('keine ausgewählt', 'lm-booking'); ?></span></span>
                        </div>
                        <div class="lm-summary-item">
                            <span class="lm-summary-icon">✓</span>
                            <span class="lm-summary-text"><?php esc_html_e('Normenseiten:', 'lm-booking'); ?> <span id="lm-summary-pages">-</span></span>
                        </div>
                    </div>
                </div>
                <div class="lm-voucher-section" id="lm-voucher-section" style="display: none;">
                    <h3 class="lm-step-subtitle">Gutscheincode (optional):</h3>
                    <div class="lm-voucher-input">
                        <input type="text" id="lm-voucher-code" class="lm-input" placeholder="Gutscheincode eingeben" maxlength="20">
                        <button type="button" id="lm-apply-voucher" class="lm-voucher-btn">Anwenden</button>
                    </div>
                    <div id="lm-voucher-message" class="lm-voucher-message"></div>
                </div>
                <div class="lm-summary-consent" id="lm-summary-consent" style="display: none;">
                    <label class="lm-consent-checkbox">
                        <input type="checkbox" id="lm-consent-checkbox">
                        <span class="lm-consent-text">
                            <?php printf(
                                esc_html__('Mit der Bestellung erklären Sie sich mit unserer %s und unseren %s einverstanden.', 'lm-booking'),
                                '<a href="/privacy-policy/" target="_blank">' . esc_html__('Datenschutzerklärung', 'lm-booking') . '</a>',
                                '<a href="/terms/" target="_blank">' . esc_html__('AGB', 'lm-booking') . '</a>'
                            ); ?>
                        </span>
                    </label>
                </div>
                <button type="button" class="lm-cta" id="lm-cta-primary">
                    <?php esc_html_e('Zu den Extras', 'lm-booking'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Development Dialog -->
<div class="lm-dev-dialog-overlay" id="lm-dev-dialog-overlay" style="display: none;">
    <div class="lm-dev-dialog">
        <div class="lm-dev-dialog-header">
            <h3 class="lm-dev-dialog-title"><?php esc_html_e('Feature in Entwicklung', 'lm-booking'); ?></h3>
            <button type="button" class="lm-dev-dialog-close" id="lm-dev-dialog-close">&times;</button>
        </div>
        <div class="lm-dev-dialog-content">
            <div class="lm-dev-dialog-message">
                <?php esc_html_e('Diese Funktion befindet sich noch in der Entwicklung und ist derzeit nicht verfügbar.', 'lm-booking'); ?>
            </div>
            <div class="lm-dev-dialog-actions">
                <button type="button" class="lm-dev-dialog-btn lm-dev-dialog-btn-primary" id="lm-dev-dialog-confirm"><?php esc_html_e('Verstanden', 'lm-booking'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.lm-dev-dialog-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    max-width: 100%;
}

.lm-dev-dialog {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    min-width: 400px;
    max-width: 90vw;
    max-height: 90vh;
    overflow-y: auto;
}

.lm-dev-dialog-header {
    padding: 20px 20px 0 20px;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
    position: relative;
}

.lm-dev-dialog-title {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.lm-dev-dialog-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lm-dev-dialog-close:hover {
    color: #333;
}

.lm-dev-dialog-content {
    padding: 0 20px 20px 20px;
}

.lm-dev-dialog-message {
    margin-bottom: 20px;
    color: #666;
    line-height: 1.5;
}

.lm-dev-dialog-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.lm-dev-dialog-btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
}

.lm-dev-dialog-btn-primary {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

.lm-dev-dialog-btn-primary:hover {
    background: #005a87;
    border-color: #005a87;
}
</style>