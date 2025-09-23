<?php
$settings = get_option('lm_booking_settings', []);
$services = $settings['services'] ?? [
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
        'label' => 'All-In-Service', 
        'price' => 7.99, 
        'description' => 'Komplettes Lektorat, Formatierung und Überprüfung', 
        'featured' => true
    ],
];
$extras = $settings['extras'] ?? [];
$deliveryOptions = $settings['delivery_options'] ?? [
    ['label' => 'Normal', 'days' => 3, 'surcharge' => 0, 'enabled' => true],
    ['label' => 'Schnelle Lieferung', 'days' => 2, 'surcharge' => 15, 'enabled' => true],
    ['label' => 'Express-Lieferung', 'days' => 1, 'surcharge' => 50, 'enabled' => true],
];
$servicesJson = wp_json_encode($services, JSON_PRETTY_PRINT);
?>

<div class="wrap">
    <h1><?php esc_html_e('Lektorat Mac — Buchungseinstellungen', 'lm-booking'); ?></h1>

    <?php settings_errors('lm_booking_settings'); ?>
    
    <!-- Debug: Show current services data -->

    <div class="nav-tab-wrapper">
        <a href="#services" class="nav-tab nav-tab-active" data-tab="services"><?php esc_html_e('Paket-Optionen', 'lm-booking'); ?></a>
        <a href="#extras" class="nav-tab" data-tab="extras"><?php esc_html_e('Extras', 'lm-booking'); ?></a>
        <a href="#delivery" class="nav-tab" data-tab="delivery"><?php esc_html_e('Lieferung', 'lm-booking'); ?></a>
        <a href="#language" class="nav-tab" data-tab="language"><?php esc_html_e('Sprache', 'lm-booking'); ?></a>
        <a href="#emails" class="nav-tab" data-tab="emails"><?php esc_html_e('E-Mails', 'lm-booking'); ?></a>
        <a href="#advanced" class="nav-tab" data-tab="advanced"><?php esc_html_e('Erweitert', 'lm-booking'); ?></a>
    </div>

    <form method="post" action="options.php" id="lm-booking-form">
        <?php settings_fields('lm_booking_group'); ?>
        
        <!-- Hidden field to trigger package processing -->
        <input type="hidden" name="lm_booking_settings[package_processing]" value="1">
        
        <!-- Hidden field to track active tab -->
        <input type="hidden" name="lm_booking_settings[active_tab]" id="active-tab-field" value="services">


        <div id="services-tab" class="tab-content active">
            <h2><?php esc_html_e('Paket-Verwaltung', 'lm-booking'); ?></h2>
            <p class="description"><?php esc_html_e('Verwalten Sie Ihre Service-Pakete mit einer benutzerfreundlichen Oberfläche.', 'lm-booking'); ?></p>
            
            <div id="packages-container">
                <?php foreach ($services as $index => $service): ?>
                <div class="package-item" data-index="<?php echo esc_attr($index); ?>">
                    <div class="package-header">
                        <div class="package-number">#<?php echo esc_html($index + 1); ?></div>
                        <div class="package-title">
                            <input type="text" name="lm_booking_settings[package_label_<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($service['label']); ?>" class="package-label" placeholder="<?php esc_attr_e('Paket-Name', 'lm-booking'); ?>">
                        </div>
                        <div class="package-price-field">
                            <input type="number" name="lm_booking_settings[package_price_<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($service['price']); ?>" class="package-price" step="0.01" min="0" placeholder="5.99">
                            <span class="price-unit">€/page</span>
                        </div>
                        <div class="package-actions">
                            <label class="featured-toggle">
                                <input type="checkbox" name="lm_booking_settings[package_featured_<?php echo esc_attr($index); ?>]" value="1" <?php checked($service['featured'] ?? false, 1); ?> class="package-featured">
                                <span class="toggle-label"><?php esc_html_e('Empfohlen', 'lm-booking'); ?></span>
                                <span class="toggle-slider"></span>
                            </label>
                            <button type="button" class="remove-package" <?php echo count($services ?? []) <= 1 ? 'disabled' : ''; ?> title="<?php esc_attr_e('Paket entfernen', 'lm-booking'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="package-details">
                        <textarea name="lm_booking_settings[package_description_<?php echo esc_attr($index); ?>]" class="package-description" rows="2" placeholder="<?php esc_attr_e('Optionale Beschreibung...', 'lm-booking'); ?>"><?php echo esc_textarea($service['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <p>
                <button type="button" id="add-package" class="button button-primary"><?php esc_html_e('Neues Paket hinzufügen', 'lm-booking'); ?></button>
                <button type="button" id="reset-packages" class="button"><?php esc_html_e('Auf Standard zurücksetzen', 'lm-booking'); ?></button>
            </p>
            
            <!-- Package data will be saved through individual form fields -->
        </div>

        <div id="extras-tab" class="tab-content">
            <h2><?php esc_html_e('Extra Services Management', 'lm-booking'); ?></h2>
            <p class="description"><?php esc_html_e('Manage your additional services with an easy-to-use interface.', 'lm-booking'); ?></p>
            
            <div id="extras-container">
                        <?php 
                        // $extras already defined at top
                        foreach ($extras as $index => $extra): ?>
                <div class="extra-item" data-index="<?php echo esc_attr($index); ?>">
                    <div class="extra-header">
                        <div class="extra-number">#<?php echo esc_html($index + 1); ?></div>
                        <div class="extra-title">
                            <input type="text" name="lm_booking_settings[extra_label_<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($extra['label']); ?>" class="extra-label" placeholder="<?php esc_attr_e('Extra-Service-Name', 'lm-booking'); ?>">
                        </div>
                        <div class="extra-price-field">
                            <input type="number" name="lm_booking_settings[extra_price_<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($extra['price']); ?>" class="extra-price" step="0.01" min="0" placeholder="20.00">
                            <span class="price-unit">€</span>
                        </div>
                        <div class="extra-actions">
                            <button type="button" class="remove-extra" <?php echo count($extras ?? []) <= 1 ? 'disabled' : ''; ?> title="<?php esc_attr_e('Extra entfernen', 'lm-booking'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="extra-packages-section">
                        <div class="package-toggles">
                            <label class="package-toggle-label"><?php esc_html_e('Included in Packages:', 'lm-booking'); ?></label>
                            <div class="package-checkboxes">
                                <?php 
                                // Debug: Show services count
                                // echo '<!-- Services count: ' . count($services) . ' -->';
                                foreach ($services as $serviceIndex => $service): ?>
                                <label class="package-checkbox">
                                    <input type="checkbox" name="lm_booking_settings[extra_packages_<?php echo esc_attr($index); ?>][]" value="<?php echo esc_attr($serviceIndex); ?>" <?php checked(in_array($serviceIndex, $extra['included_packages'] ?? [])); ?> class="package-inclusive-checkbox">
                                    <span class="package-checkbox-label"><?php echo esc_html($service['label']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="extras-controls">
                <button type="button" id="add-extra" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e('Add Extra Service', 'lm-booking'); ?>
                </button>
                <button type="button" id="reset-extras" class="button">
                    <?php esc_html_e('Auf Standard zurücksetzen', 'lm-booking'); ?>
                </button>
            </div>
        </div>

        <div id="delivery-tab" class="tab-content">
            <h2><?php esc_html_e('Delivery Options Management', 'lm-booking'); ?></h2>
            <p class="description"><?php esc_html_e('Manage your delivery timeframes and pricing options.', 'lm-booking'); ?></p>
            
            <div id="delivery-container">
                <?php 
                // $deliveryOptions already defined at top
                foreach ($deliveryOptions as $index => $option): ?>
                <div class="delivery-item" data-index="<?php echo esc_attr($index); ?>">
                    <div class="delivery-header">
                        <div class="delivery-number">#<?php echo esc_html($index + 1); ?></div>
                        <div class="delivery-title">
                            <input type="text" name="lm_booking_settings[delivery_label_<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($option['label']); ?>" class="delivery-label" placeholder="<?php esc_attr_e('Lieferoption-Name', 'lm-booking'); ?>">
                        </div>
                        <div class="delivery-days-field">
                            <input type="number" name="lm_booking_settings[delivery_days_<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($option['days']); ?>" class="delivery-days" min="1" max="30" placeholder="3">
                            <span class="days-unit"><?php esc_html_e('Tage', 'lm-booking'); ?></span>
                        </div>
                        <div class="delivery-surcharge-field">
                            <input type="number" name="lm_booking_settings[delivery_surcharge_<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($option['surcharge']); ?>" class="delivery-surcharge" min="0" max="100" step="0.1" placeholder="0">
                            <span class="surcharge-unit">%</span>
                        </div>
                        <div class="delivery-actions">
                            <label class="delivery-toggle">
                                <input type="checkbox" name="lm_booking_settings[delivery_enabled_<?php echo esc_attr($index); ?>]" value="1" <?php checked($option['enabled'] ?? true, 1); ?> class="delivery-enabled">
                                <span class="toggle-label"><?php esc_html_e('Aktiviert', 'lm-booking'); ?></span>
                                <span class="toggle-slider"></span>
                        </label>
                            <button type="button" class="remove-delivery" <?php echo count($deliveryOptions ?? []) <= 1 ? 'disabled' : ''; ?> title="<?php esc_attr_e('Lieferoption entfernen', 'lm-booking'); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="delivery-controls">
                <button type="button" id="add-delivery" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e('Add Delivery Option', 'lm-booking'); ?>
                </button>
                <button type="button" id="reset-delivery" class="button">
                    <?php esc_html_e('Auf Standard zurücksetzen', 'lm-booking'); ?>
                </button>
            </div>
            
            <div class="delivery-settings">
                <h3><?php esc_html_e('Globale Lieferungseinstellungen', 'lm-booking'); ?></h3>
                <div class="delivery-global-option">
                    <div class="delivery-option-header">
                        <div class="delivery-option-label">
                            <span class="delivery-option-title"><?php esc_html_e('24h Buffer', 'lm-booking'); ?></span>
                            <span class="delivery-option-description"><?php esc_html_e('Add 24-hour buffer to delivery dates', 'lm-booking'); ?></span>
                        </div>
                        <div class="delivery-option-toggle">
                            <label class="delivery-toggle">
                                <input type="checkbox" name="lm_booking_settings[delivery_buffer24h]" value="1" <?php checked($settings['delivery_buffer24h'] ?? true, 1); ?> class="delivery-enabled">
                                <span class="toggle-label"><?php esc_html_e('Aktiviert', 'lm-booking'); ?></span>
                                <span class="toggle-slider"></span>
                        </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="language-tab" class="tab-content">
            <h2><?php esc_html_e('Spracheinstellungen', 'lm-booking'); ?></h2>
            <p class="description"><?php esc_html_e('Konfigurieren Sie die Sprach- und Lokalisierungseinstellungen für Ihr Buchungsformular.', 'lm-booking'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="lm-language"><?php esc_html_e('Application Language', 'lm-booking'); ?></label>
                    </th>
                    <td>
                        <select id="lm-language" name="lm_booking_settings[language]" class="regular-text">
                            <option value="en" <?php selected($settings['language'] ?? 'en', 'en'); ?>><?php esc_html_e('English', 'lm-booking'); ?></option>
                            <option value="de" <?php selected($settings['language'] ?? 'en', 'de'); ?>><?php esc_html_e('German', 'lm-booking'); ?></option>
                            <option value="fr" <?php selected($settings['language'] ?? 'en', 'fr'); ?>><?php esc_html_e('French', 'lm-booking'); ?></option>
                            <option value="es" <?php selected($settings['language'] ?? 'en', 'es'); ?>><?php esc_html_e('Spanish', 'lm-booking'); ?></option>
                            <option value="it" <?php selected($settings['language'] ?? 'en', 'it'); ?>><?php esc_html_e('Italian', 'lm-booking'); ?></option>
                            <option value="nl" <?php selected($settings['language'] ?? 'en', 'nl'); ?>><?php esc_html_e('Dutch', 'lm-booking'); ?></option>
                            <option value="pt" <?php selected($settings['language'] ?? 'en', 'pt'); ?>><?php esc_html_e('Portuguese', 'lm-booking'); ?></option>
                            <option value="ru" <?php selected($settings['language'] ?? 'en', 'ru'); ?>><?php esc_html_e('Russian', 'lm-booking'); ?></option>
                            <option value="zh" <?php selected($settings['language'] ?? 'en', 'zh'); ?>><?php esc_html_e('Chinese', 'lm-booking'); ?></option>
                            <option value="ja" <?php selected($settings['language'] ?? 'en', 'ja'); ?>><?php esc_html_e('Japanese', 'lm-booking'); ?></option>
                            <option value="ko" <?php selected($settings['language'] ?? 'en', 'ko'); ?>><?php esc_html_e('Korean', 'lm-booking'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the primary language for the booking form interface.', 'lm-booking'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="lm-date-format"><?php esc_html_e('Date Format', 'lm-booking'); ?></label>
                    </th>
                    <td>
                        <select id="lm-date-format" name="lm_booking_settings[date_format]" class="regular-text">
                            <option value="Y-m-d" <?php selected($settings['date_format'] ?? 'Y-m-d', 'Y-m-d'); ?>><?php echo date('Y-m-d'); ?> (YYYY-MM-DD)</option>
                            <option value="d-m-Y" <?php selected($settings['date_format'] ?? 'Y-m-d', 'd-m-Y'); ?>><?php echo date('d-m-Y'); ?> (DD-MM-YYYY)</option>
                            <option value="m/d/Y" <?php selected($settings['date_format'] ?? 'Y-m-d', 'm/d/Y'); ?>><?php echo date('m/d/Y'); ?> (MM/DD/YYYY)</option>
                            <option value="d/m/Y" <?php selected($settings['date_format'] ?? 'Y-m-d', 'd/m/Y'); ?>><?php echo date('d/m/Y'); ?> (DD/MM/YYYY)</option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Choose the date format for displaying dates in the booking form.', 'lm-booking'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="lm-currency"><?php esc_html_e('Currency', 'lm-booking'); ?></label>
                    </th>
                    <td>
                        <select id="lm-currency" name="lm_booking_settings[currency]" class="regular-text">
                            <option value="EUR" <?php selected($settings['currency'] ?? 'EUR', 'EUR'); ?>>€ Euro (EUR)</option>
                            <option value="USD" <?php selected($settings['currency'] ?? 'EUR', 'USD'); ?>>$ US Dollar (USD)</option>
                            <option value="GBP" <?php selected($settings['currency'] ?? 'EUR', 'GBP'); ?>>£ British Pound (GBP)</option>
                            <option value="CHF" <?php selected($settings['currency'] ?? 'EUR', 'CHF'); ?>>CHF Swiss Franc (CHF)</option>
                            <option value="CAD" <?php selected($settings['currency'] ?? 'EUR', 'CAD'); ?>>C$ Canadian Dollar (CAD)</option>
                            <option value="AUD" <?php selected($settings['currency'] ?? 'EUR', 'AUD'); ?>>A$ Australian Dollar (AUD)</option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the currency for displaying prices in the booking form.', 'lm-booking'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="lm-timezone"><?php esc_html_e('Timezone', 'lm-booking'); ?></label>
                    </th>
                    <td>
                        <select id="lm-timezone" name="lm_booking_settings[timezone]" class="regular-text">
                            <option value="Europe/Berlin" <?php selected($settings['timezone'] ?? 'Europe/Berlin', 'Europe/Berlin'); ?>>Europe/Berlin (CET/CEST)</option>
                            <option value="Europe/London" <?php selected($settings['timezone'] ?? 'Europe/Berlin', 'Europe/London'); ?>>Europe/London (GMT/BST)</option>
                            <option value="Europe/Paris" <?php selected($settings['timezone'] ?? 'Europe/Berlin', 'Europe/Paris'); ?>>Europe/Paris (CET/CEST)</option>
                            <option value="Europe/Rome" <?php selected($settings['timezone'] ?? 'Europe/Berlin', 'Europe/Rome'); ?>>Europe/Rome (CET/CEST)</option>
                            <option value="Europe/Madrid" <?php selected($settings['timezone'] ?? 'Europe/Berlin', 'Europe/Madrid'); ?>>Europe/Madrid (CET/CEST)</option>
                            <option value="Europe/Amsterdam" <?php selected($settings['timezone'] ?? 'Europe/Berlin', 'Europe/Amsterdam'); ?>>Europe/Amsterdam (CET/CEST)</option>
                            <option value="America/New_York" <?php selected($settings['timezone'] ?? 'Europe/Berlin', 'America/New_York'); ?>>America/New_York (EST/EDT)</option>
                            <option value="America/Los_Angeles" <?php selected($settings['timezone'] ?? 'Europe/Berlin', 'America/Los_Angeles'); ?>>America/Los_Angeles (PST/PDT)</option>
                            <option value="Asia/Tokyo" <?php selected($settings['timezone'] ?? 'Europe/Berlin', 'Asia/Tokyo'); ?>>Asia/Tokyo (JST)</option>
                            <option value="UTC" <?php selected($settings['timezone'] ?? 'Europe/Berlin', 'UTC'); ?>>UTC (Coordinated Universal Time)</option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the timezone for date and time calculations.', 'lm-booking'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div id="emails-tab" class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="lm-email-client"><?php esc_html_e('Client Email Template', 'lm-booking'); ?></label>
                    </th>
                    <td>
                        <textarea id="lm-email-client" name="lm_booking_settings[email_client]" rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['email_client'] ?? ''); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="lm-email-admin"><?php esc_html_e('Admin Email Template', 'lm-booking'); ?></label>
                    </th>
                    <td>
                        <textarea id="lm-email-admin" name="lm_booking_settings[email_admin]" rows="5" cols="50" class="large-text"><?php echo esc_textarea($settings['email_admin'] ?? ''); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Available Placeholders', 'lm-booking'); ?></th>
                    <td>
                        <code>{{name}}</code> <code>{{email}}</code> <code>{{service}}</code> <code>{{words}}</code> <code>{{norm_pages}}</code> <code>{{delivery}}</code> <code>{{delivery_date}}</code> <code>{{extras}}</code> <code>{{base}}</code> <code>{{extras_total}}</code> <code>{{surcharge}}</code> <code>{{total}}</code> <code>{{breakdown}}</code>
                        <p class="description"><?php esc_html_e('Use these placeholders in your email templates.', 'lm-booking'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div id="advanced-tab" class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('GDPR Consent', 'lm-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="lm_booking_settings[gdpr_enabled]" value="1" <?php checked($settings['gdpr_enabled'] ?? true, 1); ?>>
                            <?php esc_html_e('Enable GDPR consent checkbox on form', 'lm-booking'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="lm-admin-recipients"><?php esc_html_e('Admin Recipients', 'lm-booking'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="lm-admin-recipients" name="lm_booking_settings[admin_recipients]" value="<?php echo esc_attr($settings['admin_recipients'] ?? get_option('admin_email')); ?>" class="large-text" placeholder="admin@example.com, manager@example.com">
                        <p class="description"><?php esc_html_e('Comma-separated email addresses to receive booking notifications.', 'lm-booking'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(__('Paket-Einstellungen speichern', 'lm-booking')); ?>
    </form>
</div>

<style>
.nav-tab-wrapper {
    margin-bottom: 20px;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

/* Package Options Styling */
#packages-container {
    margin-bottom: 20px;
}

.package-item {
    background: #fff;
    border: 2px solid #e1e1e1;
    border-radius: 8px;
    margin-bottom: 12px;
    padding: 0;
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.package-item:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1);
}

.package-item:nth-child(odd) {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.package-item:nth-child(even) {
    background: linear-gradient(135deg, #ffffff 0%, #f1f3f4 100%);
}

.package-item:last-child {
    margin-bottom: 0;
}

.package-header {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.8);
    border-bottom: 1px solid #e1e1e1;
    gap: 12px;
}

.package-number {
    background: #0073aa;
    color: white;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    flex-shrink: 0;
}

.package-title {
    flex: 1;
    min-width: 0;
}

.package-label {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 6px 10px;
    font-size: 14px;
    font-weight: 600;
    background: #fff;
    transition: border-color 0.2s ease;
}

.package-label:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.package-price-field {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    margin-right: 24px;
}

.package-price {
    width: 100px;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 6px 8px;
    font-size: 14px;
    text-align: center;
    background: #fff;
    transition: border-color 0.2s ease;
}

.package-price:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.price-unit {
    font-size: 12px;
    color: #666;
    font-weight: 500;
}

.package-actions {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
    min-width: 160px;
    justify-content: flex-end;
    margin-left: 16px;
}

.featured-toggle {
    display: flex;
    align-items: center;
    cursor: pointer;
    position: relative;
    user-select: none;
    gap: 16px;
    min-width: 120px;
}

.featured-toggle input[type="checkbox"] {
    display: none;
}

.toggle-slider {
    position: relative;
    display: inline-block;
    width: 25px;
    height: 22px;
    background: #e0e0e0;
    border-radius: 11px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #d0d0d0;
    cursor: pointer;
    flex-shrink: 0;
}

.toggle-slider:before {
    content: '';
    position: absolute;
    top: 1px;
    left: 1px;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.featured-toggle input[type="checkbox"]:checked ~ .toggle-slider {
    background: #4CAF50;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
}

.featured-toggle input[type="checkbox"]:checked ~ .toggle-slider:before {
    transform: translateX(3px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.toggle-label {
    font-size: 13px;
    font-weight: 500;
    color: #666;
    transition: color 0.3s ease;
    white-space: nowrap;
    min-width: 80px;
    text-align: left;
    order: -1;
}

.featured-toggle input[type="checkbox"]:checked + .toggle-label {
    color: #4CAF50;
    font-weight: 600;
}

.featured-toggle:hover .toggle-slider {
    border-color: #bbb;
}

.featured-toggle input[type="checkbox"]:checked:hover ~ .toggle-slider {
    background: #45a049;
    border-color: #45a049;
}

.remove-package {
    background: none;
    border: none;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    flex-shrink: 0;
    padding: 0;
    border-radius: 4px;
}

.remove-package:hover:not(:disabled) {
    background: rgba(220, 53, 69, 0.1);
    transform: scale(1.1);
}

.remove-package:disabled {
    cursor: not-allowed;
    opacity: 0.3;
}

.remove-package svg {
    width: 16px;
    height: 16px;
    stroke: #dc3545;
    fill: none;
    stroke-width: 2;
}

.package-details {
    padding: 12px 16px;
    background: rgba(248, 249, 250, 0.5);
}

.package-description {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 13px;
    resize: vertical;
    min-height: 40px;
    background: #fff;
    transition: border-color 0.2s ease;
}

.package-description:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.package-description::placeholder {
    color: #999;
    font-style: italic;
}

/* Extras Management Styles */
.extra-item {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 12px;
    padding: 16px;
    transition: all 0.2s ease;
}

.extra-item:nth-child(even) {
    background: #f5f5f5;
}

.extra-item:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1);
}

.extra-header {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.extra-number {
    background: #0073aa;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}

.extra-title {
    flex: 1;
    min-width: 200px;
}

.extra-label {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.extra-label:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.extra-price-field {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    margin-right: 24px;
}

.extra-price {
    width: 100px;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.extra-price:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.extra-packages-section {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e0e0e0;
}

.extra-packages-field {
    display: flex;
    flex-direction: column;
    min-width: 200px;
    margin-right: 24px;
}

.package-toggles {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.package-toggle-label {
    font-size: 12px;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.package-checkboxes {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.package-checkbox {
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 12px;
}

.package-checkbox input[type="checkbox"] {
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
}

.package-checkbox input[type="checkbox"]:checked {
    background: #28a745;
    border-color: #28a745;
}

.package-checkbox input[type="checkbox"]:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 8px;
    font-weight: bold;
}

.package-checkbox-label {
    color: #555;
    font-size: 12px;
}

.inclusive-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
}

.inclusive-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    border: 2px solid #ddd;
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
    position: relative;
    appearance: none;
    -webkit-appearance: none;
    transition: all 0.2s ease;
}

.inclusive-checkbox input[type="checkbox"]:checked {
    background: #28a745;
    border-color: #28a745;
}

.inclusive-checkbox input[type="checkbox"]:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.inclusive-label {
    color: #333;
    font-weight: 500;
}

.extra-actions {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
    min-width: 60px;
    justify-content: flex-end;
    margin-left: 16px;
}

.remove-extra {
    background: none;
    border: none;
    width: 24px;
    height: 24px;
    cursor: pointer;
    color: #dc3545;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.remove-extra:hover:not(:disabled) {
    background: #dc3545;
    color: white;
    transform: scale(1.1);
}

.remove-extra:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.remove-extra svg {
    width: 16px;
    height: 16px;
}

.extras-controls {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    gap: 10px;
    align-items: center;
}

.extras-controls .button {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Delivery Management Styles */
.delivery-item {
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 12px;
    padding: 16px;
    transition: all 0.2s ease;
}

.delivery-item:nth-child(even) {
    background: #f5f5f5;
}

.delivery-item:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1);
}

.delivery-header {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.delivery-number {
    background: #0073aa;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 600;
    flex-shrink: 0;
}

.delivery-title {
    flex: 1;
    min-width: 200px;
}

.delivery-label {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.delivery-label:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.delivery-days-field {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    margin-right: 16px;
}

.delivery-days {
    width: 80px;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.delivery-days:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.days-unit {
    font-size: 12px;
    color: #666;
    font-weight: 500;
}

.delivery-surcharge-field {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
    margin-right: 16px;
}

.delivery-surcharge {
    width: 80px;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 14px;
    transition: border-color 0.2s ease;
}

.delivery-surcharge:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.surcharge-unit {
    font-size: 12px;
    color: #666;
    font-weight: 500;
}

.delivery-actions {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
    min-width: 160px;
    justify-content: flex-end;
    margin-left: 16px;
}

.delivery-toggle {
    display: flex;
    align-items: center;
    cursor: pointer;
    position: relative;
    user-select: none;
    gap: 16px;
    min-width: 120px;
}

.delivery-toggle input[type="checkbox"] {
    display: none;
}

.delivery-toggle .toggle-slider {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 22px;
    background: #e0e0e0;
    border-radius: 11px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid #d0d0d0;
    cursor: pointer;
    flex-shrink: 0;
}

.delivery-toggle .toggle-slider:before {
    content: '';
    position: absolute;
    top: 1px;
    left: 1px;
    width: 18px;
    height: 18px;
    background: white;
    border-radius: 50%;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

.delivery-toggle input[type="checkbox"]:checked ~ .toggle-slider {
    background: #4CAF50;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
}

.delivery-toggle input[type="checkbox"]:checked ~ .toggle-slider:before {
    transform: translateX(22px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.delivery-toggle .toggle-label {
    font-size: 13px;
    font-weight: 500;
    color: #666;
    transition: color 0.3s ease;
    white-space: nowrap;
    min-width: 80px;
    text-align: left;
    order: -1;
}

.delivery-toggle input[type="checkbox"]:checked + .toggle-label {
    color: #4CAF50;
    font-weight: 600;
}

.delivery-toggle:hover .toggle-slider {
    border-color: #bbb;
}

.delivery-toggle input[type="checkbox"]:checked:hover ~ .toggle-slider {
    background: #45a049;
    border-color: #45a049;
}

.remove-delivery {
    background: none;
    border: none;
    width: 24px;
    height: 24px;
    cursor: pointer;
    color: #dc3545;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s ease;
}

.remove-delivery:hover:not(:disabled) {
    background: #dc3545;
    color: white;
    transform: scale(1.1);
}

.remove-delivery:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.remove-delivery svg {
    width: 16px;
    height: 16px;
}

.delivery-controls {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    gap: 10px;
    align-items: center;
}

.delivery-controls .button {
    display: flex;
    align-items: center;
    gap: 6px;
}

.delivery-settings {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #e0e0e0;
}

.delivery-settings h3 {
    margin-top: 0;
    color: #333;
}

.delivery-global-option {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-top: 15px;
}

.delivery-option-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.delivery-option-label {
    flex: 1;
}

.delivery-option-title {
    display: block;
    font-size: 16px;
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.delivery-option-description {
    display: block;
    font-size: 14px;
    color: #666;
    line-height: 1.4;
}

.delivery-option-toggle {
    flex-shrink: 0;
}

</style>

<script>
jQuery(document).ready(function($) {
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        
        $(this).addClass('nav-tab-active');
        $('#' + $(this).data('tab') + '-tab').addClass('active');
        
        // Update hidden field with current active tab
        $('#active-tab-field').val($(this).data('tab'));
        
        // Update form action URL to include tab parameter
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('tab', $(this).data('tab'));
        $('#lm-booking-form').attr('action', 'options.php' + currentUrl.search);
    });

    $('#reset-services').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to reset services to defaults?', 'lm-booking')); ?>')) {
            const defaultServices = <?php echo wp_json_encode([
                ['label' => __('Premium Lektorat', 'lm-booking'), 'price' => 5.99, 'description' => '', 'featured' => false],
                ['label' => __('Mac Formatierung', 'lm-booking'), 'price' => 6.99, 'description' => '', 'featured' => false],
                ['label' => __('All-In-Service', 'lm-booking'), 'price' => 7.99, 'description' => '', 'featured' => true],
            ]); ?>;
            $('#lm-services').val(JSON.stringify(defaultServices, null, 2));
        }
    });

    $('#reset-extras').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to reset extras to defaults?', 'lm-booking')); ?>')) {
            const defaultExtras = <?php echo wp_json_encode([]); ?>;
            $('#lm-extras').val(JSON.stringify(defaultExtras, null, 2));
        }
    });


    // Package Options functionality
    let packageIndex = <?php echo count($services ?? []); ?>;

    // Update package numbering when items change
    function updatePackageNumbers() {
        $('.package-item').each(function(index) {
            $(this).find('.package-number').text('#' + (index + 1));
            $(this).attr('data-index', index);
        });
    }

    // Add new package
    $('#add-package').on('click', function() {
        const packageHtml = `
            <div class="package-item" data-index="${packageIndex}">
                <div class="package-header">
                    <div class="package-number">#${packageIndex + 1}</div>
                    <div class="package-title">
                        <input type="text" name="lm_booking_settings[package_label_${packageIndex}]" class="package-label" placeholder="<?php esc_attr_e('Paket-Name', 'lm-booking'); ?>">
                    </div>
                    <div class="package-price-field">
                        <input type="number" name="lm_booking_settings[package_price_${packageIndex}]" class="package-price" step="0.01" min="0" placeholder="5.99">
                        <span class="price-unit">€/page</span>
                    </div>
                    <div class="package-actions">
                        <label class="featured-toggle">
                            <input type="checkbox" name="lm_booking_settings[package_featured_${packageIndex}]" value="1" class="package-featured">
                            <span class="toggle-label"><?php esc_html_e('Empfohlen', 'lm-booking'); ?></span>
                            <span class="toggle-slider"></span>
                        </label>
                        <button type="button" class="remove-package" title="<?php esc_attr_e('Paket entfernen', 'lm-booking'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="package-details">
                    <textarea name="lm_booking_settings[package_description_${packageIndex}]" class="package-description" rows="2" placeholder="<?php esc_attr_e('Optionale Beschreibung...', 'lm-booking'); ?>"></textarea>
                </div>
            </div>
        `;
        
        $('#packages-container').append(packageHtml);
        packageIndex++;
        updateRemoveButtons();
        updatePackageNumbers();
    });

    // Remove package
    $(document).on('click', '.remove-package', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Delete button clicked');
        
        const $packageItem = $(this).closest('.package-item');
        const packageName = $packageItem.find('.package-label').val() || '<?php echo esc_js(__('dieses Paket', 'lm-booking')); ?>';
        
        if (confirm('<?php echo esc_js(__('Sind Sie sicher, dass Sie löschen möchten', 'lm-booking')); ?> "' + packageName + '"? <?php echo esc_js(__('Diese Aktion kann nicht rückgängig gemacht werden.', 'lm-booking')); ?>')) {
            console.log('Package deletion confirmed');
            $packageItem.fadeOut(300, function() {
                $(this).remove();
                updateRemoveButtons();
                updatePackageNumbers();
            });
        }
    });

    // Update remove buttons state
    function updateRemoveButtons() {
        const packageCount = $('.package-item').length;
        $('.remove-package').prop('disabled', packageCount <= 1);
    }

    // Reset packages to defaults
    $('#reset-packages').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to reset packages to defaults?', 'lm-booking')); ?>')) {
            const defaultPackages = <?php echo wp_json_encode([
                ['label' => __('Premium Lektorat', 'lm-booking'), 'price' => 5.99, 'description' => '', 'featured' => false],
                ['label' => __('Mac Formatierung', 'lm-booking'), 'price' => 6.99, 'description' => '', 'featured' => false],
                ['label' => __('All-In-Service', 'lm-booking'), 'price' => 7.99, 'description' => '', 'featured' => true],
            ]); ?>;
            
            $('#packages-container').empty();
            packageIndex = 0;
            
            defaultPackages.forEach(function(pkg) {
                const packageHtml = `
                    <div class="package-item" data-index="${packageIndex}">
                        <div class="package-header">
                            <div class="package-number">#${packageIndex + 1}</div>
                            <div class="package-title">
                                <input type="text" name="lm_booking_settings[package_label_${packageIndex}]" value="${pkg.label}" class="package-label" placeholder="<?php esc_attr_e('Paket-Name', 'lm-booking'); ?>">
                            </div>
                            <div class="package-price-field">
                                <input type="number" name="lm_booking_settings[package_price_${packageIndex}]" value="${pkg.price}" class="package-price" step="0.01" min="0" placeholder="5.99">
                                <span class="price-unit">€/page</span>
                            </div>
                            <div class="package-actions">
                                <label class="featured-toggle">
                                    <input type="checkbox" name="lm_booking_settings[package_featured_${packageIndex}]" value="1" class="package-featured" ${pkg.featured ? 'checked' : ''}>
                                    <span class="toggle-label"><?php esc_html_e('Empfohlen', 'lm-booking'); ?></span>
                                    <span class="toggle-slider"></span>
                                </label>
                                <button type="button" class="remove-package" title="<?php esc_attr_e('Paket entfernen', 'lm-booking'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="package-details">
                            <textarea name="lm_booking_settings[package_description_${packageIndex}]" class="package-description" rows="2" placeholder="<?php esc_attr_e('Optional description...', 'lm-booking'); ?>">${pkg.description}</textarea>
                        </div>
                    </div>
                `;
                
                $('#packages-container').append(packageHtml);
                packageIndex++;
            });
            
            updateRemoveButtons();
            updatePackageNumbers();
        }
    });

    // Update package numbering when any package field changes
    $(document).on('input change', '.package-label, .package-price, .package-description, .package-featured', function() {
        updatePackageNumbers();
    });

    // Handle featured toggle click
    $(document).on('click', '.featured-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('Featured toggle clicked');
        
        const checkbox = $(this).find('.package-featured');
        const isChecked = checkbox.is(':checked');
        
        // Always uncheck all first
        $('.package-featured').prop('checked', false);
        
        // If this one wasn't checked, check it
        if (!isChecked) {
            checkbox.prop('checked', true);
            console.log('Checked featured');
        } else {
            console.log('Unchecked featured');
        }
        
        // Trigger change event to ensure form processing
        checkbox.trigger('change');
        updatePackageNumbers();
    });

    // Ensure only one package can be featured - backup handler
    $(document).on('change', '.package-featured', function() {
        if ($(this).is(':checked')) {
            // Uncheck all other featured checkboxes
            $('.package-featured').not(this).prop('checked', false);
        }
        updatePackageNumbers();
    });

    // Debug form submission
    $('form').on('submit', function() {
        console.log('Form submitted with data:');
        const formData = new FormData(this);
        for (let [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }
        
        // Also log package fields specifically
        console.log('Package fields:');
        $('.package-label').each(function() {
            console.log('Label: ' + $(this).val());
        });
        $('.package-price').each(function() {
            console.log('Price: ' + $(this).val());
        });
        $('.package-featured').each(function() {
            console.log('Featured: ' + $(this).is(':checked'));
        });
    });

    // Extras Management
    let extraIndex = $('.extra-item').length;
    
    // Add extra service
    $('#add-extra').on('click', function() {
        // Generate package checkboxes HTML
        let packageCheckboxes = '';
        <?php foreach ($services as $serviceIndex => $service): ?>
        packageCheckboxes += `
            <label class="package-checkbox">
                <input type="checkbox" name="lm_booking_settings[extra_packages_${extraIndex}][]" value="<?php echo esc_attr($serviceIndex); ?>" class="package-inclusive-checkbox">
                <span class="package-checkbox-label"><?php echo esc_js($service['label']); ?></span>
            </label>
        `;
        <?php endforeach; ?>
        
        const extraHtml = `
            <div class="extra-item" data-index="${extraIndex}">
                <div class="extra-header">
                    <div class="extra-number">#${extraIndex + 1}</div>
                    <div class="extra-title">
                        <input type="text" name="lm_booking_settings[extra_label_${extraIndex}]" class="extra-label" placeholder="<?php esc_attr_e('Extra-Service-Name', 'lm-booking'); ?>">
                    </div>
                    <div class="extra-price-field">
                        <input type="number" name="lm_booking_settings[extra_price_${extraIndex}]" class="extra-price" step="0.01" min="0" placeholder="20.00">
                        <span class="price-unit">€</span>
                    </div>
                    <div class="extra-actions">
                        <button type="button" class="remove-extra" title="<?php esc_attr_e('Extra entfernen', 'lm-booking'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="extra-packages-section">
                    <div class="package-toggles">
                        <label class="package-toggle-label"><?php esc_html_e('Included in Packages:', 'lm-booking'); ?></label>
                        <div class="package-checkboxes">
                            ${packageCheckboxes}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('#extras-container').append(extraHtml);
        extraIndex++;
        updateExtraNumbers();
        updateExtraRemoveButtons();
    });
    
    // Remove extra service
    $(document).on('click', '.remove-extra', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $extraItem = $(this).closest('.extra-item');
        const extraName = $extraItem.find('.extra-label').val() || '<?php echo esc_js(__('diesen Extra-Service', 'lm-booking')); ?>';
        
        if (confirm('<?php echo esc_js(__('Sind Sie sicher, dass Sie löschen möchten', 'lm-booking')); ?> "' + extraName + '"? <?php echo esc_js(__('Diese Aktion kann nicht rückgängig gemacht werden.', 'lm-booking')); ?>')) {
            $extraItem.fadeOut(300, function() {
                $(this).remove();
                updateExtraNumbers();
                updateExtraRemoveButtons();
            });
        }
    });
    
    // Reset extras to defaults
    $('#reset-extras').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to reset extras to defaults?', 'lm-booking')); ?>')) {
            const defaultExtras = <?php echo wp_json_encode([]); ?>;
            
            $('#extras-container').empty();
            extraIndex = 0;
            
            defaultExtras.forEach(function(extra) {
                // Generate package checkboxes HTML
                let packageCheckboxes = '';
                <?php foreach ($services as $serviceIndex => $service): ?>
                packageCheckboxes += `
                    <label class="package-checkbox">
                        <input type="checkbox" name="lm_booking_settings[extra_packages_${extraIndex}][]" value="<?php echo esc_attr($serviceIndex); ?>" class="package-inclusive-checkbox">
                        <span class="package-checkbox-label"><?php echo esc_js($service['label']); ?></span>
                    </label>
                `;
                <?php endforeach; ?>
                
                const extraHtml = `
                    <div class="extra-item" data-index="${extraIndex}">
                        <div class="extra-header">
                            <div class="extra-number">#${extraIndex + 1}</div>
                            <div class="extra-title">
                                <input type="text" name="lm_booking_settings[extra_label_${extraIndex}]" value="${extra.label}" class="extra-label" placeholder="<?php esc_attr_e('Extra-Service-Name', 'lm-booking'); ?>">
                            </div>
                            <div class="extra-price-field">
                                <input type="number" name="lm_booking_settings[extra_price_${extraIndex}]" value="${extra.price}" class="extra-price" step="0.01" min="0" placeholder="20.00">
                                <span class="price-unit">€</span>
                            </div>
                            <div class="extra-actions">
                                <button type="button" class="remove-extra" title="<?php esc_attr_e('Extra entfernen', 'lm-booking'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="extra-packages-section">
                            <div class="package-toggles">
                                <label class="package-toggle-label"><?php esc_html_e('Included in Packages:', 'lm-booking'); ?></label>
                                <div class="package-checkboxes">
                                    ${packageCheckboxes}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#extras-container').append(extraHtml);
                extraIndex++;
            });
            
            updateExtraNumbers();
            updateExtraRemoveButtons();
        }
    });
    
    // Update extra numbers
    function updateExtraNumbers() {
        $('.extra-item').each(function(index) {
            $(this).find('.extra-number').text('#' + (index + 1));
            $(this).attr('data-index', index);
        });
    }
    
    // Update extra remove buttons
    function updateExtraRemoveButtons() {
        const extraCount = $('.extra-item').length;
        $('.remove-extra').prop('disabled', extraCount <= 1);
    }
    
    // Delivery Management
    let deliveryIndex = $('.delivery-item').length;
    
    // Add delivery option
    $('#add-delivery').on('click', function() {
        const deliveryHtml = `
            <div class="delivery-item" data-index="${deliveryIndex}">
                <div class="delivery-header">
                    <div class="delivery-number">#${deliveryIndex + 1}</div>
                    <div class="delivery-title">
                        <input type="text" name="lm_booking_settings[delivery_label_${deliveryIndex}]" class="delivery-label" placeholder="<?php esc_attr_e('Lieferoption-Name', 'lm-booking'); ?>">
                    </div>
                    <div class="delivery-days-field">
                        <input type="number" name="lm_booking_settings[delivery_days_${deliveryIndex}]" class="delivery-days" min="1" max="30" placeholder="3">
                        <span class="days-unit"><?php esc_html_e('days', 'lm-booking'); ?></span>
                    </div>
                    <div class="delivery-surcharge-field">
                        <input type="number" name="lm_booking_settings[delivery_surcharge_${deliveryIndex}]" class="delivery-surcharge" min="0" max="100" step="0.1" placeholder="0">
                        <span class="surcharge-unit">%</span>
                    </div>
                    <div class="delivery-actions">
                        <label class="delivery-toggle">
                            <input type="checkbox" name="lm_booking_settings[delivery_enabled_${deliveryIndex}]" value="1" class="delivery-enabled" checked>
                            <span class="toggle-label"><?php esc_html_e('Enabled', 'lm-booking'); ?></span>
                            <span class="toggle-slider"></span>
                        </label>
                        <button type="button" class="remove-delivery" title="<?php esc_attr_e('Lieferoption entfernen', 'lm-booking'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        $('#delivery-container').append(deliveryHtml);
        deliveryIndex++;
        updateDeliveryNumbers();
        updateDeliveryRemoveButtons();
    });
    
    // Remove delivery option
    $(document).on('click', '.remove-delivery', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const $deliveryItem = $(this).closest('.delivery-item');
        const deliveryName = $deliveryItem.find('.delivery-label').val() || '<?php echo esc_js(__('diese Lieferoption', 'lm-booking')); ?>';
        
        if (confirm('<?php echo esc_js(__('Sind Sie sicher, dass Sie löschen möchten', 'lm-booking')); ?> "' + deliveryName + '"? <?php echo esc_js(__('Diese Aktion kann nicht rückgängig gemacht werden.', 'lm-booking')); ?>')) {
            $deliveryItem.fadeOut(300, function() {
                $(this).remove();
                updateDeliveryNumbers();
                updateDeliveryRemoveButtons();
            });
        }
    });
    
    // Reset delivery to defaults
    $('#reset-delivery').on('click', function() {
        if (confirm('<?php echo esc_js(__('Are you sure you want to reset delivery options to defaults?', 'lm-booking')); ?>')) {
            const defaultDeliveryOptions = <?php echo wp_json_encode([
                ['label' => __('Normal', 'lm-booking'), 'days' => 3, 'surcharge' => 0, 'enabled' => true],
                ['label' => __('Schnelle Lieferung', 'lm-booking'), 'days' => 2, 'surcharge' => 15, 'enabled' => true],
                ['label' => __('Express-Lieferung', 'lm-booking'), 'days' => 1, 'surcharge' => 50, 'enabled' => true],
            ]); ?>;
            
            $('#delivery-container').empty();
            deliveryIndex = 0;
            
            defaultDeliveryOptions.forEach(function(option) {
                const deliveryHtml = `
                    <div class="delivery-item" data-index="${deliveryIndex}">
                        <div class="delivery-header">
                            <div class="delivery-number">#${deliveryIndex + 1}</div>
                            <div class="delivery-title">
                                <input type="text" name="lm_booking_settings[delivery_label_${deliveryIndex}]" value="${option.label}" class="delivery-label" placeholder="<?php esc_attr_e('Lieferoption-Name', 'lm-booking'); ?>">
                            </div>
                            <div class="delivery-days-field">
                                <input type="number" name="lm_booking_settings[delivery_days_${deliveryIndex}]" value="${option.days}" class="delivery-days" min="1" max="30" placeholder="3">
                                <span class="days-unit"><?php esc_html_e('Tage', 'lm-booking'); ?></span>
                            </div>
                            <div class="delivery-surcharge-field">
                                <input type="number" name="lm_booking_settings[delivery_surcharge_${deliveryIndex}]" value="${option.surcharge}" class="delivery-surcharge" min="0" max="100" step="0.1" placeholder="0">
                                <span class="surcharge-unit">%</span>
                            </div>
                            <div class="delivery-actions">
                                <label class="delivery-toggle">
                                    <input type="checkbox" name="lm_booking_settings[delivery_enabled_${deliveryIndex}]" value="1" class="delivery-enabled" ${option.enabled ? 'checked' : ''}>
                                    <span class="toggle-label"><?php esc_html_e('Aktiviert', 'lm-booking'); ?></span>
                                    <span class="toggle-slider"></span>
                                </label>
                                <button type="button" class="remove-delivery" title="<?php esc_attr_e('Lieferoption entfernen', 'lm-booking'); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                $('#delivery-container').append(deliveryHtml);
                deliveryIndex++;
            });
            
            updateDeliveryNumbers();
            updateDeliveryRemoveButtons();
        }
    });
    
    // Update delivery numbers
    function updateDeliveryNumbers() {
        $('.delivery-item').each(function(index) {
            $(this).find('.delivery-number').text('#' + (index + 1));
            $(this).attr('data-index', index);
        });
    }
    
    // Update delivery remove buttons
    function updateDeliveryRemoveButtons() {
        const deliveryCount = $('.delivery-item').length;
        $('.remove-delivery').prop('disabled', deliveryCount <= 1);
    }

    // Initialize
    updateRemoveButtons();
    updateExtraNumbers();
    updateExtraRemoveButtons();
    
    // Restore active tab from URL parameter or settings
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || '<?php echo esc_js($settings['active_tab'] ?? 'services'); ?>';
    
    if (activeTab && activeTab !== 'services') {
        // Remove active class from default tab
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        
        // Activate the specified tab
        $(`.nav-tab[data-tab="${activeTab}"]`).addClass('nav-tab-active');
        $(`#${activeTab}-tab`).addClass('active');
        
        // Update hidden field
        $('#active-tab-field').val(activeTab);
    }
    
    // Set initial form action URL with current tab
    const currentUrl = new URL(window.location);
    if (activeTab && activeTab !== 'services') {
        currentUrl.searchParams.set('tab', activeTab);
    }
    $('#lm-booking-form').attr('action', 'options.php' + currentUrl.search);
});
</script>
