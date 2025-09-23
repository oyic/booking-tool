<?php

namespace LM\Booking;

class PostTypes
{
    public function init(): void
    {
        add_action('init', [$this, 'registerBookingPostType']);
    }

    public function registerBookingPostType(): void
    {
        register_post_type('lm_booking', [
            'labels' => [
                'name' => __('Bookings', 'lm-booking'),
                'singular_name' => __('Booking', 'lm-booking'),
                'menu_name' => __('Bookings', 'lm-booking'),
                'add_new' => __('Add New', 'lm-booking'),
                'add_new_item' => __('Add New Booking', 'lm-booking'),
                'edit_item' => __('Edit Booking', 'lm-booking'),
                'new_item' => __('New Booking', 'lm-booking'),
                'view_item' => __('View Booking', 'lm-booking'),
                'search_items' => __('Search Bookings', 'lm-booking'),
                'not_found' => __('No bookings found', 'lm-booking'),
                'not_found_in_trash' => __('No bookings found in trash', 'lm-booking'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // Hide from default menu - we'll use our custom menu
            'menu_icon' => 'dashicons-media-text',
            'supports' => ['title'],
            'capability_type' => 'post',
            'capabilities' => [
                'create_posts' => 'manage_options',
            ],
            'map_meta_cap' => true,
        ]);
    }
}
