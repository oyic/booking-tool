<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('lm_booking_settings');
flush_rewrite_rules();
