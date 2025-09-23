<?php
/**
 * Plugin Name: Lektorat Mac Booking
 * Plugin URI: https://lektorat-mac.com
 * Description: A lightweight booking request plugin for Lektorat Mac services.
 * Version: 1.0.0
 * Author: Lektorat Mac
 * Author URI: https://lektorat-mac.com
 * Text Domain: lm-booking
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 8.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LM_BOOKING_VERSION', '1.0.0');
define('LM_BOOKING_DIR', plugin_dir_path(__FILE__));
define('LM_BOOKING_URL', plugin_dir_url(__FILE__));
define('LM_BOOKING_FILE', __FILE__);

require_once LM_BOOKING_DIR . 'includes/Autoloader.php';

LM\Booking\Autoloader::register();

add_action('plugins_loaded', function () {
    $plugin = new LM\Booking\Plugin();
    $plugin->boot();
});

register_activation_hook(__FILE__, function () {
    require_once LM_BOOKING_DIR . 'includes/Activator.php';
    \LM\Booking\Activator::activate();
});

register_deactivation_hook(__FILE__, function () {
    require_once LM_BOOKING_DIR . 'includes/Activator.php';
    \LM\Booking\Activator::deactivate();
});
