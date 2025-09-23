<?php

namespace LM\Booking\Admin;

class AdminMenu
{
    public function init(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
    }

    public function addAdminMenu(): void
    {
        // Add main menu page
        add_menu_page(
            __('Lektorat Mac', 'lm-booking'),
            __('Lektorat Mac', 'lm-booking'),
            'manage_options',
            'lm-booking',
            [$this, 'renderBookingsPage'],
            'dashicons-media-text',
            30
        );

        // Add Bookings submenu (same as main page)
        add_submenu_page(
            'lm-booking',
            __('Bookings', 'lm-booking'),
            __('Bookings', 'lm-booking'),
            'manage_options',
            'lm-booking',
            [$this, 'renderBookingsPage']
        );

        // Add Settings submenu
        add_submenu_page(
            'lm-booking',
            __('Settings', 'lm-booking'),
            __('Settings', 'lm-booking'),
            'manage_options',
            'lm-booking-settings',
            [$this, 'renderSettingsPage']
        );
        add_submenu_page(
            'lm-booking',
            __('Vouchers', 'lm-booking'),
            __('Vouchers', 'lm-booking'),
            'manage_options',
            'lm-booking-vouchers',
            [$this, 'renderVouchersPage']
        );
    }

    public function renderBookingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'lm-booking'));
        }

        include LM_BOOKING_DIR . 'templates/admin-bookings.php';
    }

    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'lm-booking'));
        }

        include LM_BOOKING_DIR . 'templates/admin-settings-simple.php';
    }

    public function renderVouchersPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'lm-booking'));
        }

        include LM_BOOKING_DIR . 'templates/admin-vouchers.php';
    }

}
