<?php
$settings = get_option('lm_booking_settings', []);

// Load services from settings, with defaults if none exist
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

// If still no services found, populate with defaults
if (empty($services)) {
    $defaultServices = [
    [
        'label' => __('Premium Lektorat', 'lm-booking'), 
        'price' => 5.99, 
        'description' => __('Professionelles Lektorat und Korrekturlesen', 'lm-booking'), 
        'featured' => false
    ],
    [
        'label' => __('Mac Formatierung', 'lm-booking'), 
        'price' => 6.99, 
        'description' => __('Akademische Formatierung nach Richtlinien', 'lm-booking'), 
        'featured' => false
    ],
    [
        'label' => __('All-In-Service', 'lm-booking'), 
        'price' => 7.99, 
        'description' => __('Komplettes Lektorat, Formatierung und Überprüfung', 'lm-booking'), 
        'featured' => true
        ]
    ];
    
    // Save defaults to settings
    $settings['services'] = $defaultServices;
    update_option('lm_booking_settings', $settings);
    error_log('LM Booking Settings: Populated default services');
    
    $services = $defaultServices;
}

// Load extras from settings - only use what's explicitly configured
$extras = $settings['extras'] ?? [];

// Load delivery options from settings, with defaults if none exist
$deliveryOptions = $settings['delivery_options'] ?? [];

// If no delivery options array found, try to reconstruct from individual fields
if (empty($deliveryOptions)) {
    $deliveryOptions = [];
    
    foreach ($settings as $key => $value) {
        if (strpos($key, 'delivery_label_') === 0) {
            $deliveryKey = str_replace('delivery_label_', '', $key);
            $label = $value;
            $price = $settings['delivery_price_' . $deliveryKey] ?? 0;
            $days = $settings['delivery_days_' . $deliveryKey] ?? 3; // Get days from new field
            
            if (!empty($label)) {
                $deliveryOptions[$deliveryKey] = [
                    'label' => $label,
                    'days' => intval($days),
                    'surcharge' => floatval($price),
                    'enabled' => true
                ];
            }
        }
    }
}

// If still no delivery options found, populate with defaults
if (empty($deliveryOptions)) {
    $defaultDelivery = [
        'normal' => ['label' => '3 Tage (Normal)', 'days' => 3, 'surcharge' => 0, 'enabled' => true],
        'fast' => ['label' => '2 Tage', 'days' => 2, 'surcharge' => 15, 'enabled' => true],
        'express' => ['label' => '1 Tag', 'days' => 1, 'surcharge' => 50, 'enabled' => true],
    ];
    
    // Save defaults to settings
    $settings['delivery_options'] = $defaultDelivery;
    update_option('lm_booking_settings', $settings);
    error_log('LM Booking Settings: Populated default delivery options');
    
    $deliveryOptions = $defaultDelivery;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Lektorat Mac — Buchungseinstellungen', 'lm-booking'); ?></h1>
    
    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully!', 'lm-booking'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php 
    $active_tab = $settings['active_tab'] ?? 'packages';
    // Debug: Show what tab is active
    echo "<!-- DEBUG: Active tab from settings: " . ($settings['active_tab'] ?? 'not set') . " -->";
    echo "<!-- DEBUG: Final active tab: " . $active_tab . " -->";
    ?>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="#packages" class="nav-tab <?php echo $active_tab === 'packages' ? 'nav-tab-active' : ''; ?>" data-tab="packages"><?php esc_html_e('Paket-Optionen', 'lm-booking'); ?></a>
        <a href="#extras" class="nav-tab <?php echo $active_tab === 'extras' ? 'nav-tab-active' : ''; ?>" data-tab="extras"><?php esc_html_e('Extras', 'lm-booking'); ?></a>
        <a href="#delivery" class="nav-tab <?php echo $active_tab === 'delivery' ? 'nav-tab-active' : ''; ?>" data-tab="delivery"><?php esc_html_e('Lieferung', 'lm-booking'); ?></a>
        <a href="#language" class="nav-tab <?php echo $active_tab === 'language' ? 'nav-tab-active' : ''; ?>" data-tab="language"><?php esc_html_e('Sprache', 'lm-booking'); ?></a>
        <a href="#emails" class="nav-tab <?php echo $active_tab === 'emails' ? 'nav-tab-active' : ''; ?>" data-tab="emails"><?php esc_html_e('E-Mails', 'lm-booking'); ?></a>
        <a href="#advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>" data-tab="advanced"><?php esc_html_e('Erweitert', 'lm-booking'); ?></a>
    </nav>
    
    <!-- Services/Packages Tab -->
    <div id="packages-tab" class="tab-content <?php echo $active_tab === 'packages' ? 'active' : ''; ?>">
        <form method="post" action="options.php" id="packages-form">
        <?php settings_fields('lm_booking_group'); ?>
            <input type="hidden" name="lm_booking_settings[active_tab]" value="packages">
            <h2><?php esc_html_e('Paket-Optionen', 'lm-booking'); ?></h2>
            <p class="description"><?php esc_html_e('Verwalten Sie Ihre Service-Pakete mit einer benutzerfreundlichen Oberfläche.', 'lm-booking'); ?></p>
            
            <div class="lm-settings-section">
                <div class="lm-section-header">
                    <h3 class="lm-section-title"><?php esc_html_e('Service-Pakete', 'lm-booking'); ?></h3>
                    <button type="button" id="add-package" class="lm-add-button"><?php esc_html_e('Paket hinzufügen', 'lm-booking'); ?></button>
            </div>
            
            <div id="packages-container">
            <?php foreach ($services as $index => $service): ?>
                    <div class="lm-package-row" data-index="<?php echo $index; ?>">
                        <div class="lm-package-inputs">
                            <div class="lm-input-group">
                                <label class="lm-input-label"><?php esc_html_e('Name', 'lm-booking'); ?></label>
                                <input type="text" name="lm_booking_settings[package_label_<?php echo $index; ?>]" value="<?php echo esc_attr($service['label']); ?>" placeholder="<?php esc_attr_e('Paket-Name', 'lm-booking'); ?>" class="lm-package-name">
                            </div>
                            <div class="lm-input-group">
                                <label class="lm-input-label"><?php esc_html_e('Preis', 'lm-booking'); ?></label>
                                <input type="number" name="lm_booking_settings[package_price_<?php echo $index; ?>]" value="<?php echo esc_attr($service['price']); ?>" step="0.01" min="0" placeholder="0.00" class="lm-package-price">
                            </div>
                            <div class="lm-input-group lm-featured-group">
                                <label class="lm-input-label"><?php esc_html_e('Empfohlen', 'lm-booking'); ?></label>
                                <label class="lm-toggle-switch">
                                    <input type="radio" name="lm_booking_settings[featured_package]" value="<?php echo $index; ?>" <?php checked($service['featured']); ?>>
                                    <span class="lm-toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                        <button type="button" class="lm-delete-icon remove-package" data-index="<?php echo $index; ?>" title="<?php esc_attr_e('Dieses Paket entfernen', 'lm-booking'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3,6 5,6 21,6"></polyline>
                                <path d="M19,6v14a2,2 0 0,1-2,2H7a2,2 0 0,1-2-2V6m3,0V4a2,2 0 0,1,2-2h4a2,2 0 0,1,2,2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="lm-package-description-row" data-index="<?php echo $index; ?>">
                        <div class="lm-description-group">
                            <label class="lm-input-label"><?php esc_html_e('Beschreibung', 'lm-booking'); ?></label>
                            <textarea name="lm_booking_settings[package_description_<?php echo $index; ?>]" placeholder="<?php esc_attr_e('Paket-Beschreibung', 'lm-booking'); ?>" class="lm-package-description"><?php echo esc_textarea($service['description']); ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php submit_button(__('Paket-Einstellungen speichern', 'lm-booking')); ?>
        </form>
        </div>
        
        <!-- Extras Tab -->
    <div id="extras-tab" class="tab-content <?php echo $active_tab === 'extras' ? 'active' : ''; ?>">
        <form method="post" action="options.php" id="extras-form">
            <?php settings_fields('lm_booking_group'); ?>
            <input type="hidden" name="lm_booking_settings[active_tab]" value="extras">
            <h2><?php esc_html_e('Extra Services', 'lm-booking'); ?></h2>
            <p class="description"><?php esc_html_e('Manage your additional services with an easy-to-use interface.', 'lm-booking'); ?></p>
            
            <div class="lm-settings-section">
                <div class="lm-section-header">
                    <h3 class="lm-section-title"><?php esc_html_e('Extra-Services', 'lm-booking'); ?></h3>
                    <button type="button" id="add-extra" class="lm-add-button"><?php esc_html_e('Extra hinzufügen', 'lm-booking'); ?></button>
            </div>
            
            <div id="extras-container">
            <?php foreach ($extras as $index => $extra): ?>
                    <div class="lm-extra-row" data-index="<?php echo $index; ?>">
                        <div class="lm-extra-inputs">
                            <div class="lm-input-group">
                                <label class="lm-input-label"><?php esc_html_e('Name', 'lm-booking'); ?></label>
                                <input type="text" name="lm_booking_settings[extra_label_<?php echo $index; ?>]" value="<?php echo esc_attr($extra['label']); ?>" placeholder="<?php esc_attr_e('Extra-Service-Name', 'lm-booking'); ?>" class="lm-extra-name">
                            </div>
                            <div class="lm-input-group">
                                <label class="lm-input-label"><?php esc_html_e('Preis', 'lm-booking'); ?></label>
                                <input type="number" name="lm_booking_settings[extra_price_<?php echo $index; ?>]" value="<?php echo esc_attr($extra['price']); ?>" step="0.01" min="0" placeholder="0.00" class="lm-extra-price">
                            </div>
                            <div class="lm-input-group lm-service-checkboxes">
                                <label class="lm-input-label"><?php esc_html_e('Included in Packages:', 'lm-booking'); ?></label>
                                <div class="lm-service-toggles">
                                    <?php foreach ($services as $serviceIndex => $service): ?>
                                    <label class="lm-service-toggle">
                                        <input type="checkbox" name="lm_booking_settings[extra_packages_<?php echo $index; ?>][]" value="<?php echo esc_attr($serviceIndex); ?>" <?php checked(in_array($serviceIndex, $extra['included_packages'] ?? [])); ?> class="lm-service-checkbox">
                                        <span class="lm-service-label"><?php echo esc_html($service['label']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="lm-delete-icon remove-extra" data-index="<?php echo $index; ?>" title="<?php esc_attr_e('Diesen Extra-Service entfernen', 'lm-booking'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3,6 5,6 21,6"></polyline>
                                <path d="M19,6v14a2,2 0 0,1-2,2H7a2,2 0 0,1-2-2V6m3,0V4a2,2 0 0,1,2-2h4a2,2 0 0,1,2,2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="lm-extra-description-row" data-index="<?php echo $index; ?>">
                        <div class="lm-description-group">
                            <label class="lm-input-label"><?php esc_html_e('Beschreibung', 'lm-booking'); ?></label>
                            <textarea name="lm_booking_settings[extra_description_<?php echo $index; ?>]" placeholder="<?php esc_attr_e('Extra-Service-Beschreibung', 'lm-booking'); ?>" class="lm-extra-description"><?php echo esc_textarea($extra['description']); ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php submit_button(__('Extra-Einstellungen speichern', 'lm-booking')); ?>
        </form>
        </div>
        
        <!-- Delivery Tab -->
    <div id="delivery-tab" class="tab-content <?php echo $active_tab === 'delivery' ? 'active' : ''; ?>">
        <form method="post" action="options.php" id="delivery-form">
            <?php settings_fields('lm_booking_group'); ?>
            <input type="hidden" name="lm_booking_settings[active_tab]" value="delivery">
            <h2><?php esc_html_e('Lieferoptionen', 'lm-booking'); ?></h2>
            <p class="description"><?php esc_html_e('Verwalten Sie Ihre Lieferzeiträume und Preiseinstellungen.', 'lm-booking'); ?></p>
            
            <div class="lm-settings-section">
                <div class="lm-section-header">
                    <h3 class="lm-section-title"><?php esc_html_e('Lieferoptionen', 'lm-booking'); ?></h3>
                    <button type="button" id="add-delivery" class="lm-add-button"><?php esc_html_e('Lieferoption hinzufügen', 'lm-booking'); ?></button>
                </div>
            
            <div id="delivery-container">
                    <?php foreach ($deliveryOptions as $key => $delivery): ?>
                    <div class="lm-delivery-row" data-key="<?php echo $key; ?>">
                        <div class="lm-delivery-inputs">
                            <div class="lm-input-group">
                                <label class="lm-input-label"><?php esc_html_e('Name', 'lm-booking'); ?></label>
                                <input type="text" name="lm_booking_settings[delivery_label_<?php echo $key; ?>]" value="<?php echo esc_attr($delivery['label']); ?>" placeholder="<?php esc_attr_e('Lieferoption', 'lm-booking'); ?>" class="lm-delivery-name">
                            </div>
                            <div class="lm-input-group">
                                <label class="lm-input-label"><?php esc_html_e('Tage', 'lm-booking'); ?></label>
                                <input type="number" name="lm_booking_settings[delivery_days_<?php echo $key; ?>]" value="<?php echo esc_attr($delivery['days'] ?? 3); ?>" min="1" max="30" placeholder="3" class="lm-delivery-days">
                            </div>
                            <div class="lm-input-group">
                                <label class="lm-input-label"><?php esc_html_e('Aufschlag %', 'lm-booking'); ?></label>
                                <input type="number" name="lm_booking_settings[delivery_price_<?php echo $key; ?>]" value="<?php echo esc_attr($delivery['surcharge'] ?? $delivery['price'] ?? 0); ?>" step="0.01" min="0" placeholder="0.00" class="lm-delivery-price">
                            </div>
                            <div class="lm-input-group">
                                <label class="lm-input-label"><?php esc_html_e('Beschreibung', 'lm-booking'); ?></label>
                                <input type="text" name="lm_booking_settings[delivery_description_<?php echo $key; ?>]" value="<?php echo esc_attr($delivery['description'] ?? ''); ?>" placeholder="<?php esc_attr_e('Optionale Beschreibung', 'lm-booking'); ?>" class="lm-delivery-description">
                            </div>
                            <div class="lm-input-group">
                                <label class="lm-input-label"><?php esc_html_e('Puffer Stunden', 'lm-booking'); ?></label>
                                <input type="number" name="lm_booking_settings[delivery_buffer_<?php echo $key; ?>]" value="<?php echo esc_attr($delivery['buffer_hours'] ?? 24); ?>" min="0" max="168" placeholder="24" class="lm-delivery-buffer">
                            </div>
                        </div>
                        <button type="button" class="lm-delete-icon remove-delivery" data-key="<?php echo $key; ?>" title="<?php esc_attr_e('Diese Lieferoption entfernen', 'lm-booking'); ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3,6 5,6 21,6"></polyline>
                                <path d="M19,6v14a2,2 0 0,1-2,2H7a2,2 0 0,1-2-2V6m3,0V4a2,2 0 0,1,2-2h4a2,2 0 0,1,2,2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php submit_button(__('Lieferungseinstellungen speichern', 'lm-booking')); ?>
        </form>
        </div>
        
        <!-- Language Tab -->
    <div id="language-tab" class="tab-content <?php echo $active_tab === 'language' ? 'active' : ''; ?>">
        <form method="post" action="options.php" id="language-form">
            <?php settings_fields('lm_booking_group'); ?>
            <input type="hidden" name="lm_booking_settings[active_tab]" value="language">
            <h2><?php esc_html_e('Spracheinstellungen', 'lm-booking'); ?></h2>
            <p class="description"><?php esc_html_e('Konfigurieren Sie die Sprach- und Lokalisierungseinstellungen für Ihr Buchungsformular.', 'lm-booking'); ?></p>
            
            <div class="lm-settings-section">
                <h3 class="lm-section-title"><?php esc_html_e('Lokalisierungseinstellungen', 'lm-booking'); ?></h3>
                
                <div class="lm-field-row">
                    <div class="lm-field-label">
                        <?php esc_html_e('Default Language', 'lm-booking'); ?>
                    </div>
                    <div class="lm-field-input">
                        <select name="lm_booking_settings[language]">
                            <option value="en" <?php selected($settings['language'] ?? 'en', 'en'); ?>><?php esc_html_e('English', 'lm-booking'); ?></option>
                            <option value="de" <?php selected($settings['language'] ?? 'en', 'de'); ?>><?php esc_html_e('German', 'lm-booking'); ?></option>
                            <option value="fr" <?php selected($settings['language'] ?? 'en', 'fr'); ?>><?php esc_html_e('French', 'lm-booking'); ?></option>
                        </select>
                        <div class="lm-field-description"><?php esc_html_e('Default language for the booking form', 'lm-booking'); ?></div>
                    </div>
                </div>
                
                <div class="lm-field-row">
                    <div class="lm-field-label">
                        <?php esc_html_e('Date Format', 'lm-booking'); ?>
                    </div>
                    <div class="lm-field-input">
                        <select name="lm_booking_settings[date_format]">
                            <option value="Y-m-d" <?php selected($settings['date_format'] ?? 'Y-m-d', 'Y-m-d'); ?>>YYYY-MM-DD</option>
                            <option value="d/m/Y" <?php selected($settings['date_format'] ?? 'Y-m-d', 'd/m/Y'); ?>>DD/MM/YYYY</option>
                            <option value="m/d/Y" <?php selected($settings['date_format'] ?? 'Y-m-d', 'm/d/Y'); ?>>MM/DD/YYYY</option>
                        </select>
                        <div class="lm-field-description"><?php esc_html_e('How dates are displayed in the form', 'lm-booking'); ?></div>
                    </div>
                </div>
                
                <div class="lm-field-row">
                    <div class="lm-field-label">
                        <?php esc_html_e('Currency', 'lm-booking'); ?>
                    </div>
                    <div class="lm-field-input">
                        <select name="lm_booking_settings[currency]">
                            <option value="EUR" <?php selected($settings['currency'] ?? 'EUR', 'EUR'); ?>>Euro (€)</option>
                            <option value="USD" <?php selected($settings['currency'] ?? 'EUR', 'USD'); ?>>US Dollar ($)</option>
                            <option value="GBP" <?php selected($settings['currency'] ?? 'EUR', 'GBP'); ?>>British Pound (£)</option>
                        </select>
                        <div class="lm-field-description"><?php esc_html_e('Currency symbol for pricing', 'lm-booking'); ?></div>
                    </div>
                </div>
            </div>
            <?php submit_button(__('Spracheinstellungen speichern', 'lm-booking')); ?>
        </form>
        </div>
        
        <!-- Emails Tab -->
    <div id="emails-tab" class="tab-content <?php echo $active_tab === 'emails' ? 'active' : ''; ?>">
        <form method="post" action="options.php" id="emails-form">
            <?php settings_fields('lm_booking_group'); ?>
            <input type="hidden" name="lm_booking_settings[active_tab]" value="emails">
            <h2><?php esc_html_e('E-Mail-Einstellungen', 'lm-booking'); ?></h2>
            <p class="description"><?php esc_html_e('Konfigurieren Sie E-Mail-Benachrichtigungen und Vorlagen für Ihr Buchungssystem.', 'lm-booking'); ?></p>
            
            <div class="lm-settings-section">
                <h3 class="lm-section-title"><?php esc_html_e('E-Mail-Konfiguration', 'lm-booking'); ?></h3>
                
                <div class="lm-field-row">
                    <div class="lm-field-label">
                        <?php esc_html_e('Admin-E-Mail', 'lm-booking'); ?>
                    </div>
                    <div class="lm-field-input">
                        <input type="email" name="lm_booking_settings[admin_email]" value="<?php echo esc_attr($settings['admin_email'] ?? get_option('admin_email')); ?>" placeholder="<?php esc_attr_e('admin@beispiel.de', 'lm-booking'); ?>">
                        <div class="lm-field-description"><?php esc_html_e('E-Mail-Adresse zum Empfang von Buchungsbenachrichtigungen', 'lm-booking'); ?></div>
                    </div>
                </div>
                
                <div class="lm-field-row">
                    <div class="lm-field-label">
                        <?php esc_html_e('Admin-E-Mail-Betreff', 'lm-booking'); ?>
                    </div>
                    <div class="lm-field-input">
                        <input type="text" name="lm_booking_settings[admin_email_subject]" value="<?php echo esc_attr($settings['admin_email_subject'] ?? 'Neue Buchung erhalten'); ?>" placeholder="<?php esc_attr_e('Neue Buchung erhalten', 'lm-booking'); ?>">
                        <div class="lm-field-description"><?php esc_html_e('Betreffzeile für Admin-Benachrichtigungs-E-Mails', 'lm-booking'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="lm-settings-section">
                <h3 class="lm-section-title"><?php esc_html_e('E-Mail-Vorlagen', 'lm-booking'); ?></h3>
                
                <div class="lm-template-grid">
                    <!-- Booking Email Template -->
                    <div class="lm-template-column">
                        <div class="lm-template-header">
                            <h4><?php esc_html_e('Buchungsbestätigung', 'lm-booking'); ?></h4>
                            <div class="lm-template-badge booking"><?php esc_html_e('Buchung', 'lm-booking'); ?></div>
                        </div>
                        
                        <div class="lm-field-row">
                            <div class="lm-field-label">
                                <?php esc_html_e('E-Mail-Betreff', 'lm-booking'); ?>
                            </div>
                            <div class="lm-field-input">
                                <input type="text" name="lm_booking_settings[booking_email_subject]" value="<?php echo esc_attr($settings['booking_email_subject'] ?? 'Ihre Buchungsbestätigung & Rechnung - Lektorat Mac'); ?>" placeholder="<?php esc_attr_e('Ihre Buchungsbestätigung & Rechnung - Lektorat Mac', 'lm-booking'); ?>">
                            </div>
                        </div>
                        
                        <div class="lm-field-row">
                            <div class="lm-field-label">
                                <?php esc_html_e('E-Mail-Vorlage', 'lm-booking'); ?>
                            </div>
                            <div class="lm-field-input">
                                <div class="lm-field-description">
                                    <?php esc_html_e('Verfügbare Platzhalter:', 'lm-booking'); ?><br>
                                    <code>{customer_name}</code>, <code>{booking_id}</code>, <code>{service_name}</code>, <code>{total_price}</code>, <code>{invoice_number}</code>, <code>{due_date}</code>, <code>{invoice_date}</code>, <code>{service_details}</code>, <code>{payment_info}</code>
                                </div>
                                <?php
                                wp_editor(
                                    $settings['booking_email_template'] ?? '',
                                    'booking_email_template',
                                    [
                                        'textarea_name' => 'lm_booking_settings[booking_email_template]',
                                        'textarea_rows' => 20,
                                        'media_buttons' => false,
                                        'teeny' => true,
                                        'tinymce' => [
                                            'toolbar1' => 'bold,italic,underline,|,bullist,numlist,|,link,unlink,|,undo,redo',
                                            'toolbar2' => '',
                                            'content_css' => plugins_url('assets/css/email-editor.css', LM_BOOKING_FILE)
                                        ]
                                    ]
                                );
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Voucher Email Template -->
                    <div class="lm-template-column">
                        <div class="lm-template-header">
                            <h4><?php esc_html_e('Gutschein-E-Mail', 'lm-booking'); ?></h4>
                            <div class="lm-template-badge voucher"><?php esc_html_e('Gutschein', 'lm-booking'); ?></div>
                        </div>
                        
                        <div class="lm-field-row">
                            <div class="lm-field-label">
                                <?php esc_html_e('E-Mail-Betreff', 'lm-booking'); ?>
                            </div>
                            <div class="lm-field-input">
                                <input type="text" name="lm_booking_settings[voucher_email_subject]" value="<?php echo esc_attr($settings['voucher_email_subject'] ?? 'Ihr Rabatt-Gutschein - Lektorat Mac'); ?>" placeholder="<?php esc_attr_e('Ihr Rabatt-Gutschein - Lektorat Mac', 'lm-booking'); ?>">
                            </div>
                        </div>
                        
                        <div class="lm-field-row">
                            <div class="lm-field-label">
                                <?php esc_html_e('E-Mail-Vorlage', 'lm-booking'); ?>
                            </div>
                            <div class="lm-field-input">
                                <div class="lm-field-description">
                                    <?php esc_html_e('Verfügbare Platzhalter:', 'lm-booking'); ?><br>
                                    <code>{customer_name}</code>, <code>{voucher_code}</code>, <code>{discount}</code>, <code>{expiry_date}</code>, <code>{expiry_days}</code>
                                </div>
                                <?php
                                wp_editor(
                                    $settings['voucher_email_template'] ?? '',
                                    'voucher_email_template',
                                    [
                                        'textarea_name' => 'lm_booking_settings[voucher_email_template]',
                                        'textarea_rows' => 20,
                                        'media_buttons' => false,
                                        'teeny' => true,
                                        'tinymce' => [
                                            'toolbar1' => 'bold,italic,underline,|,bullist,numlist,|,link,unlink,|,undo,redo',
                                            'toolbar2' => '',
                                            'content_css' => plugins_url('assets/css/email-editor.css', LM_BOOKING_FILE)
                                        ]
                                    ]
                                );
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php submit_button(__('E-Mail-Einstellungen speichern', 'lm-booking')); ?>
        </form>
        </div>
        
        <!-- Advanced Tab -->
    <div id="advanced-tab" class="tab-content <?php echo $active_tab === 'advanced' ? 'active' : ''; ?>">
        <form method="post" action="options.php" id="advanced-form">
            <?php settings_fields('lm_booking_group'); ?>
            <input type="hidden" name="lm_booking_settings[active_tab]" value="advanced">
            <h2><?php esc_html_e('Erweiterte Einstellungen', 'lm-booking'); ?></h2>
            <p class="description"><?php esc_html_e('Konfigurieren Sie erweiterte Optionen und Integrationen.', 'lm-booking'); ?></p>
            
            <div class="lm-settings-section">
                <h3 class="lm-section-title"><?php esc_html_e('Erweiterte Optionen', 'lm-booking'); ?></h3>
                
                <div class="lm-field-row">
                    <div class="lm-field-label">
                        <?php esc_html_e('Debug Mode', 'lm-booking'); ?>
                    </div>
                    <div class="lm-field-input">
                        <label class="lm-toggle-switch">
                            <input type="checkbox" name="lm_booking_settings[debug_mode]" value="1" <?php checked($settings['debug_mode'] ?? false); ?>>
                            <span class="lm-toggle-slider"></span>
                        </label>
                        <div class="lm-field-description"><?php esc_html_e('Enable debug logging for troubleshooting', 'lm-booking'); ?></div>
                    </div>
                </div>
                
                <div class="lm-field-row">
                    <div class="lm-field-label">
                        <?php esc_html_e('Strict Validation', 'lm-booking'); ?>
                    </div>
                    <div class="lm-field-input">
                        <label class="lm-toggle-switch">
                            <input type="checkbox" name="lm_booking_settings[strict_validation]" value="1" <?php checked($settings['strict_validation'] ?? false); ?>>
                            <span class="lm-toggle-slider"></span>
                        </label>
                        <div class="lm-field-description"><?php esc_html_e('Enable strict form validation rules', 'lm-booking'); ?></div>
                    </div>
                </div>
                
                <div class="lm-field-row">
                    <div class="lm-field-label">
                        <?php esc_html_e('Auto Save', 'lm-booking'); ?>
                    </div>
                    <div class="lm-field-input">
                        <label class="lm-toggle-switch">
                            <input type="checkbox" name="lm_booking_settings[auto_save]" value="1" <?php checked($settings['auto_save'] ?? false); ?>>
                            <span class="lm-toggle-slider"></span>
                        </label>
                        <div class="lm-field-description"><?php esc_html_e('Automatically save form data as user types', 'lm-booking'); ?></div>
                    </div>
                </div>
            </div>
            <?php submit_button(__('Erweiterte Einstellungen speichern', 'lm-booking')); ?>
        </form>
        </div>
</div>

<style>
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.lm-settings-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin-bottom: 20px;
    padding: 20px;
}

.lm-settings-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e0e0e0;
}

.lm-field-row {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.lm-field-row:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.lm-field-label {
    width: 200px;
    font-weight: 600;
    color: #23282d;
}

.lm-field-input {
    flex: 1;
    margin-left: 20px;
}

.lm-field-input input[type="text"],
.lm-field-input input[type="number"],
.lm-field-input textarea,
.lm-field-input select {
    width: 100%;
    max-width: 400px;
}

.lm-field-input textarea {
    height: 80px;
    resize: vertical;
}

.lm-field-input input[type="checkbox"] {
    margin-right: 8px;
}

.lm-toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.lm-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.lm-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 24px;
}

.lm-toggle-slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .lm-toggle-slider {
    background-color: #0073aa;
}

input:checked + .lm-toggle-slider:before {
    transform: translateX(26px);
}

.lm-add-button {
    background: #0073aa;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 14px;
}

.lm-add-button:hover {
    background: #005a87;
}

.lm-remove-button {
    background: #dc3232;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
    margin-left: 10px;
}

.lm-remove-button:hover {
    background: #a00;
}

.lm-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.lm-section-title {
    margin: 0;
    color: #23282d;
}

.lm-field-description {
    font-style: italic;
    color: #666;
    font-size: 13px;
    margin-top: 5px;
}

.lm-saved-highlight {
    background: #46b450 !important;
    color: white !important;
    animation: lm-pulse 0.5s ease-in-out;
}

@keyframes lm-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* Odd-Even Row Highlighting */
.lm-field-row:nth-child(odd) {
    background-color: #f9f9f9;
    border-left: 3px solid #0073aa;
}

.lm-field-row:nth-child(even) {
    background-color: #ffffff;
    border-left: 3px solid #e0e0e0;
}

.lm-field-row {
    margin-bottom: 2px;
    padding: 8px 12px;
    border-radius: 4px;
    transition: background-color 0.2s ease;
    display: flex;
    align-items: center;
    gap: 15px;
}

.lm-field-row:hover {
    background-color: #f0f8ff !important;
    border-left-color: #0073aa !important;
}

/* Ensure proper spacing between sections */
.lm-settings-section .lm-field-row:first-child {
    margin-top: 0;
}

.lm-settings-section .lm-field-row:last-child {
    margin-bottom: 0;
}

/* Compact field styling */
.lm-field-label {
    min-width: 120px;
    font-weight: 600;
    font-size: 13px;
    color: #333;
    flex-shrink: 0;
}

.lm-field-input {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
}

.lm-field-input input,
.lm-field-input select,
.lm-field-input textarea {
    flex: 1;
    max-width: 200px;
    padding: 6px 10px;
    font-size: 13px;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.lm-field-description {
    font-size: 11px;
    color: #666;
    font-style: italic;
    margin: 0;
    flex: 1;
    /* max-width: 300px; */
}

.lm-remove-button {
    padding: 4px 8px;
    font-size: 11px;
    background: #dc3232;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    flex-shrink: 0;
}

.lm-remove-button:hover {
    background: #a00;
}

.lm-add-button {
    padding: 6px 12px;
    font-size: 12px;
    background: #0073aa;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
}

.lm-add-button:hover {
    background: #005a87;
}

/* Delivery section specific styling */
.lm-delivery-row {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 12px;
    margin-bottom: 8px;
    background: #f9f9f9;
    border-left: 3px solid #0073aa;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.lm-delivery-row:nth-child(even) {
    background: #ffffff;
    border-left-color: #e0e0e0;
}

.lm-delivery-row:hover {
    background-color: #f0f8ff !important;
    border-left-color: #0073aa !important;
}

.lm-delivery-inputs {
    display: flex;
    gap: 20px;
    flex: 1;
    align-items: center;
}

.lm-input-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 120px;
}

.lm-input-label {
    font-size: 11px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0;
}

.lm-delivery-name,
.lm-delivery-price {
    padding: 6px 10px;
    font-size: 13px;
    border: 1px solid #ddd;
    border-radius: 3px;
    background: white;
    transition: border-color 0.2s ease;
}

.lm-delivery-name {
    min-width: 200px;
    max-width: 300px;
    width: 250px;
}

.lm-delivery-days {
    min-width: 80px;
    max-width: 100px;
    width: 90px;
}

.lm-delivery-price {
    min-width: 100px;
    max-width: 120px;
    width: 110px;
}

.lm-delivery-name:focus,
.lm-delivery-days:focus,
.lm-delivery-price:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
}

.lm-delete-icon {
    background: #dc3232;
    border: none;
    border-radius: 4px;
    padding: 8px;
    cursor: pointer;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.lm-delete-icon:hover {
    background: #a00;
    transform: scale(1.05);
}

.lm-delete-icon svg {
    width: 16px;
    height: 16px;
}

/* Packages section specific styling */
.lm-package-row {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 12px;
    margin-bottom: 2px;
    background: #f9f9f9;
    border-left: 3px solid #0073aa;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.lm-package-row:nth-child(even) {
    background: #ffffff;
    border-left-color: #e0e0e0;
}

.lm-package-row:hover {
    background-color: #f0f8ff !important;
    border-left-color: #0073aa !important;
}

.lm-package-inputs {
    display: flex;
    gap: 20px;
    flex: 1;
    align-items: center;
}

.lm-package-inputs .lm-input-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.lm-package-name {
    min-width: 200px;
    max-width: 300px;
    width: 250px;
}

.lm-package-price {
    min-width: 100px;
    max-width: 120px;
    width: 110px;
}

.lm-featured-group {
    min-width: 80px;
    align-items: center;
    justify-content: center;
}

.lm-featured-group .lm-input-label {
    text-align: center;
    margin-bottom: 8px;
}

/* Package description row */
.lm-package-description-row {
    margin-bottom: 8px;
    padding: 8px 12px;
    background: #f5f5f5;
    border-left: 3px solid #e0e0e0;
    border-radius: 4px;
    margin-left: 15px;
}

.lm-description-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.lm-package-description {
    min-width: 100%;
    width: 100%;
    min-height: 50px;
    resize: vertical;
}

/* Extras section specific styling */
.lm-extra-row {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 12px;
    margin-bottom: 2px;
    background: #f9f9f9;
    border-left: 3px solid #0073aa;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.lm-extra-row:nth-child(even) {
    background: #ffffff;
    border-left-color: #e0e0e0;
}

.lm-extra-row:hover {
    background-color: #f0f8ff !important;
    border-left-color: #0073aa !important;
}

.lm-extra-inputs {
    display: flex;
    gap: 20px;
    flex: 1;
    align-items: flex-start;
    flex-wrap: wrap;
}

.lm-extra-inputs .lm-input-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.lm-service-checkboxes {
    min-width: 200px;
    max-width: 300px;
}

.lm-service-toggles {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 120px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px;
    background: #f9f9f9;
}

.lm-service-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 12px;
    padding: 2px 0;
}

.lm-service-toggle:hover {
    background: #e8f4f8;
    border-radius: 3px;
    padding: 2px 4px;
    margin: 0 -4px;
}

.lm-service-checkbox {
    width: 14px;
    height: 14px;
    border: 2px solid #ddd;
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
    position: relative;
    appearance: none;
    -webkit-appearance: none;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.lm-service-checkbox:checked {
    background: #28a745;
    border-color: #28a745;
}

.lm-service-checkbox:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 8px;
    font-weight: bold;
}

.lm-service-label {
    color: #555;
    font-size: 12px;
    line-height: 1.2;
}

.lm-extra-name {
    min-width: 200px;
    max-width: 300px;
    width: 250px;
}

.lm-extra-price {
    min-width: 100px;
    max-width: 120px;
    width: 110px;
}

/* Extra description row */
.lm-extra-description-row {
    margin-bottom: 8px;
    padding: 8px 12px;
    background: #f5f5f5;
    border-left: 3px solid #e0e0e0;
    border-radius: 4px;
    margin-left: 15px;
}

.lm-extra-description {
    min-width: 100%;
    width: 100%;
    min-height: 50px;
    resize: vertical;
}

/* Shared input styling for packages and extras */
.lm-package-name,
.lm-package-price,
.lm-package-description,
.lm-extra-name,
.lm-extra-price,
.lm-extra-description {
    padding: 6px 10px;
    font-size: 13px;
    border: 1px solid #ddd;
    border-radius: 3px;
    background: white;
    transition: border-color 0.2s ease;
}

.lm-package-name:focus,
.lm-package-price:focus,
.lm-package-description:focus,
.lm-extra-name:focus,
.lm-extra-price:focus,
.lm-extra-description:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 2px rgba(0, 115, 170, 0.1);
}

/* Responsive adjustments */
@media (max-width: 1200px) {
    .lm-package-inputs,
    .lm-extra-inputs {
        flex-direction: column;
        gap: 15px;
    }
    
    .lm-package-name,
    .lm-extra-name {
        max-width: 100%;
        width: 100%;
    }
    
    .lm-service-checkboxes {
        max-width: 100%;
        width: 100%;
    }
    
    .lm-package-description-row,
    .lm-extra-description-row {
        margin-left: 0;
    }
}

@media (max-width: 1000px) {
    .lm-delivery-inputs {
        flex-direction: column;
        gap: 15px;
    }
    
    .lm-delivery-name,
    .lm-delivery-days,
    .lm-delivery-price {
        max-width: 100%;
        width: 100%;
    }
}

/* Email Template Grid Layout */
.lm-template-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 20px;
}

.lm-template-column {
    background: #fff;
    border: 1px solid #e1e5e9;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: box-shadow 0.3s ease;
}

.lm-template-column:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.1);
}

.lm-template-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.lm-template-header h4 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #2c3e50;
}

.lm-template-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.lm-template-badge.booking {
    background: linear-gradient(135deg, #0073aa, #005177);
    color: white;
}

.lm-template-badge.voucher {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

/* Enhanced field spacing for email templates only */
.lm-template-column .lm-field-row {
    margin-bottom: 20px !important;
    display: block !important;
    align-items: unset !important;
    padding: 0 !important;
    border: none !important;
    background: transparent !important;
}

.lm-template-column .lm-field-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 8px;
    font-size: 14px;
    display: block;
}

.lm-template-column .lm-field-input {
    margin-bottom: 15px;
    margin-left: 0;
    padding-left: 0;
    display: flex;
    flex-direction: column;
    gap: 15px;
    width: 100%;
}

.lm-template-column .lm-field-description {
    margin: 0;
    font-size: 13px;
    color: #6c757d;
    line-height: 1.4;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #0073aa;
    flex-shrink: 0;
    width: 100%;
    box-sizing: border-box;
}

.lm-template-column .lm-field-description code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 12px;
    color: #e83e8c;
    margin: 0 2px;
    font-family: 'Courier New', monospace;
}

/* TinyMCE Editor Styling */
.lm-template-column .wp-editor-wrap {
    border: 1px solid #ddd;
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
    margin: 0;
    padding: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.lm-template-column .wp-editor-wrap .wp-editor-container {
    width: 100%;
    margin: 0;
    padding: 0;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.lm-template-column .wp-editor-wrap textarea {
    width: 100% !important;
    min-height: 400px !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 15px !important;
    box-sizing: border-box !important;
}

.lm-template-column .wp-editor-wrap .mce-tinymce {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.lm-template-column .wp-editor-wrap .mce-edit-area {
    min-height: 400px !important;
    height: 400px !important;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.lm-template-column .wp-editor-wrap .mce-edit-area iframe {
    width: 100% !important;
    height: 100% !important;
    min-height: 400px !important;
    flex: 1;
    border: none;
}

.lm-template-column .wp-editor-wrap .mce-toolbar {
    width: 100% !important;
    flex-shrink: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
}

.lm-template-column .wp-editor-wrap .mce-statusbar {
    width: 100% !important;
    flex-shrink: 0;
    display: flex;
    align-items: center;
}

.lm-template-column .wp-editor-wrap .wp-editor-tabs {
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    width: 100%;
    margin: 0;
    padding: 0;
    flex-shrink: 0;
    display: flex;
}

.lm-template-column .wp-editor-wrap .wp-switch-editor {
    background: #f8f9fa;
    border: none;
    padding: 8px 12px;
    font-size: 13px;
    margin: 0;
    flex-shrink: 0;
    cursor: pointer;
}

.lm-template-column .wp-editor-wrap .wp-switch-editor.switch-tmce {
    background: #0073aa;
    color: white;
}

/* Ensure editor content fills the available space */
.lm-template-column .wp-editor-wrap .mce-container {
    width: 100% !important;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.lm-template-column .wp-editor-wrap .mce-panel {
    width: 100% !important;
    flex-shrink: 0;
}

.lm-template-column .wp-editor-wrap .mce-menubar {
    width: 100% !important;
    flex-shrink: 0;
}

.lm-template-column .wp-editor-wrap .mce-toolbar-grp {
    width: 100% !important;
    flex-shrink: 0;
}

.lm-template-column .wp-editor-wrap .mce-container-body.mce-flow-layout {
    width: 100% !important;
    display: flex !important;
    flex-direction: row !important;
}

.lm-template-column .wp-editor-wrap .mce-container-body {
    width: 100% !important;
    display: flex !important;
    flex-direction: column;
}

.lm-template-column .wp-editor-wrap .mce-flow-layout {
    width: 100% !important;
    display: flex !important;
    flex-direction: column;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .lm-template-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .lm-template-column {
        padding: 20px;
    }
    
    .lm-template-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .lm-template-column .wp-editor-wrap textarea {
        min-height: 300px !important;
        height: 300px !important;
    }
    
    .lm-template-column .wp-editor-wrap .mce-edit-area {
        min-height: 300px !important;
        height: 300px !important;
    }
    
    .lm-template-column .wp-editor-wrap .mce-edit-area iframe {
        min-height: 300px !important;
        height: 300px !important;
    }
    
    .lm-template-column .lm-field-input {
        gap: 10px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        $('#' + tab + '-tab').addClass('active');
    });
    
    // Initialize tabs on page load
    function initializeTabs() {
        // Hide all tab content first
        $('.tab-content').removeClass('active');
        
        // Show the active tab content
        var activeTab = '<?php echo esc_js($active_tab); ?>';
        $('#' + activeTab + '-tab').addClass('active');
    }
    
    // Initialize tabs when document is ready
    initializeTabs();
    
    // Ensure the correct tab is active after page load
    setTimeout(function() {
        var activeTab = '<?php echo esc_js($active_tab); ?>';
        console.log('Ensuring tab is active:', activeTab);
        $('.nav-tab').removeClass('nav-tab-active');
        $('.nav-tab[data-tab="' + activeTab + '"]').addClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        $('#' + activeTab + '-tab').addClass('active');
        
        // Add a brief highlight effect to show which tab was saved
        <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
        $('.nav-tab[data-tab="' + activeTab + '"]').addClass('lm-saved-highlight');
        setTimeout(function() {
            $('.nav-tab').removeClass('lm-saved-highlight');
        }, 2000);
        <?php endif; ?>
    }, 100);
    
    // Package management
    let packageIndex = <?php echo count($services ?? []); ?>;
    
    $('#add-package').on('click', function() {
        var packageHtml = `
            <div class="lm-package-row" data-index="${packageIndex}">
                <div class="lm-package-inputs">
                    <div class="lm-input-group">
                        <label class="lm-input-label"><?php esc_html_e('Name', 'lm-booking'); ?></label>
                        <input type="text" name="lm_booking_settings[package_label_${packageIndex}]" value="Neues Paket" placeholder="<?php esc_attr_e('Paket-Name', 'lm-booking'); ?>" class="lm-package-name">
                    </div>
                    <div class="lm-input-group">
                        <label class="lm-input-label"><?php esc_html_e('Preis', 'lm-booking'); ?></label>
                        <input type="number" name="lm_booking_settings[package_price_${packageIndex}]" value="0.00" step="0.01" min="0" placeholder="0.00" class="lm-package-price">
                    </div>
                    <div class="lm-input-group lm-featured-group">
                        <label class="lm-input-label"><?php esc_html_e('Empfohlen', 'lm-booking'); ?></label>
                        <label class="lm-toggle-switch">
                            <input type="radio" name="lm_booking_settings[featured_package]" value="${packageIndex}">
                            <span class="lm-toggle-slider"></span>
                        </label>
                    </div>
                </div>
                <button type="button" class="lm-delete-icon remove-package" data-index="${packageIndex}" title="<?php esc_attr_e('Dieses Paket entfernen', 'lm-booking'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3,6 5,6 21,6"></polyline>
                        <path d="M19,6v14a2,2 0 0,1-2,2H7a2,2 0 0,1-2-2V6m3,0V4a2,2 0 0,1,2-2h4a2,2 0 0,1,2,2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </button>
            </div>
            <div class="lm-package-description-row" data-index="${packageIndex}">
                <div class="lm-description-group">
                    <label class="lm-input-label"><?php esc_html_e('Beschreibung', 'lm-booking'); ?></label>
                    <textarea name="lm_booking_settings[package_description_${packageIndex}]" placeholder="<?php esc_attr_e('Paket-Beschreibung', 'lm-booking'); ?>" class="lm-package-description"><?php esc_attr_e('Paket-Beschreibung', 'lm-booking'); ?></textarea>
                </div>
            </div>
        `;
        $('#packages-container').append(packageHtml);
        packageIndex++;
    });
    
    $(document).on('click', '.remove-package', function() {
        var index = $(this).data('index');
        $('.lm-package-row[data-index="' + index + '"]').remove();
        $('.lm-package-description-row[data-index="' + index + '"]').remove();
    });
    
    // Handle featured package radio buttons - only one can be selected
    $(document).on('change', 'input[name="lm_booking_settings[featured_package]"]', function() {
        $('input[name="lm_booking_settings[featured_package]"]').not(this).prop('checked', false);
    });
    
    // Extra management
    let extraIndex = <?php echo count($extras ?? []); ?>;
    
    $('#add-extra').on('click', function() {
        var extraHtml = `
            <div class="lm-extra-row" data-index="${extraIndex}">
                <div class="lm-extra-inputs">
                    <div class="lm-input-group">
                        <label class="lm-input-label"><?php esc_html_e('Name', 'lm-booking'); ?></label>
                        <input type="text" name="lm_booking_settings[extra_label_${extraIndex}]" value="Neuer Extra-Service" placeholder="<?php esc_attr_e('Extra-Service-Name', 'lm-booking'); ?>" class="lm-extra-name">
                    </div>
                    <div class="lm-input-group">
                        <label class="lm-input-label"><?php esc_html_e('Preis', 'lm-booking'); ?></label>
                        <input type="number" name="lm_booking_settings[extra_price_${extraIndex}]" value="0.00" step="0.01" min="0" placeholder="0.00" class="lm-extra-price">
                    </div>
                    <div class="lm-input-group lm-service-checkboxes">
                        <label class="lm-input-label"><?php esc_html_e('Included in Packages:', 'lm-booking'); ?></label>
                        <div class="lm-service-toggles">
                            <?php foreach ($services as $serviceIndex => $service): ?>
                            <label class="lm-service-toggle">
                                <input type="checkbox" name="lm_booking_settings[extra_packages_${extraIndex}][]" value="<?php echo esc_attr($serviceIndex); ?>" class="lm-service-checkbox">
                                <span class="lm-service-label"><?php echo esc_html($service['label']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="lm-delete-icon remove-extra" data-index="${extraIndex}" title="<?php esc_attr_e('Diesen Extra-Service entfernen', 'lm-booking'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3,6 5,6 21,6"></polyline>
                        <path d="M19,6v14a2,2 0 0,1-2,2H7a2,2 0 0,1-2-2V6m3,0V4a2,2 0 0,1,2-2h4a2,2 0 0,1,2,2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </button>
            </div>
            <div class="lm-extra-description-row" data-index="${extraIndex}">
                <div class="lm-description-group">
                    <label class="lm-input-label"><?php esc_html_e('Beschreibung', 'lm-booking'); ?></label>
                    <textarea name="lm_booking_settings[extra_description_${extraIndex}]" placeholder="<?php esc_attr_e('Extra-Service-Beschreibung', 'lm-booking'); ?>" class="lm-extra-description"><?php esc_attr_e('Extra-Service-Beschreibung', 'lm-booking'); ?></textarea>
                </div>
            </div>
        `;
        $('#extras-container').append(extraHtml);
        extraIndex++;
    });
    
    $(document).on('click', '.remove-extra', function() {
        var index = $(this).data('index');
        $('.lm-extra-row[data-index="' + index + '"]').remove();
        $('.lm-extra-description-row[data-index="' + index + '"]').remove();
    });
    
    // Delivery management
    $('#add-delivery').on('click', function() {
        var deliveryKey = 'delivery_' + Date.now();
        var deliveryHtml = `
            <div class="lm-delivery-row" data-key="${deliveryKey}">
                <div class="lm-delivery-inputs">
                    <div class="lm-input-group">
                        <label class="lm-input-label"><?php esc_html_e('Name', 'lm-booking'); ?></label>
                        <input type="text" name="lm_booking_settings[delivery_label_${deliveryKey}]" value="Neue Lieferoption" placeholder="<?php esc_attr_e('Lieferoption', 'lm-booking'); ?>" class="lm-delivery-name">
                    </div>
                    <div class="lm-input-group">
                        <label class="lm-input-label"><?php esc_html_e('Tage', 'lm-booking'); ?></label>
                        <input type="number" name="lm_booking_settings[delivery_days_${deliveryKey}]" value="3" min="1" max="30" placeholder="3" class="lm-delivery-days">
                    </div>
                    <div class="lm-input-group">
                        <label class="lm-input-label"><?php esc_html_e('Aufschlag %', 'lm-booking'); ?></label>
                        <input type="number" name="lm_booking_settings[delivery_price_${deliveryKey}]" value="0.00" step="0.01" min="0" placeholder="0.00" class="lm-delivery-price">
                    </div>
                    <div class="lm-input-group">
                        <label class="lm-input-label"><?php esc_html_e('Beschreibung', 'lm-booking'); ?></label>
                        <input type="text" name="lm_booking_settings[delivery_description_${deliveryKey}]" value="" placeholder="<?php esc_attr_e('Optionale Beschreibung', 'lm-booking'); ?>" class="lm-delivery-description">
                    </div>
                    <div class="lm-input-group">
                        <label class="lm-input-label"><?php esc_html_e('Puffer Stunden', 'lm-booking'); ?></label>
                        <input type="number" name="lm_booking_settings[delivery_buffer_${deliveryKey}]" value="24" min="0" max="168" placeholder="24" class="lm-delivery-buffer">
                    </div>
                </div>
                <button type="button" class="lm-delete-icon remove-delivery" data-key="${deliveryKey}" title="<?php esc_attr_e('Diese Lieferoption entfernen', 'lm-booking'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3,6 5,6 21,6"></polyline>
                        <path d="M19,6v14a2,2 0 0,1-2,2H7a2,2 0 0,1-2-2V6m3,0V4a2,2 0 0,1,2-2h4a2,2 0 0,1,2,2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </button>
            </div>
        `;
        $('#delivery-container').append(deliveryHtml);
    });
    
    $(document).on('click', '.remove-delivery', function() {
        var key = $(this).data('key');
        $('.lm-delivery-row[data-key="' + key + '"]').remove();
    });
});
</script>

