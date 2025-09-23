<?php

namespace LM\Booking;

use LM\Booking\Admin\AdminMenu;
use LM\Booking\Admin\Settings;
use LM\Booking\Admin\Columns;
use LM\Booking\Admin\BookingsList;
use LM\Booking\Public\Shortcode;
use LM\Booking\Public\Ajax;
use LM\Booking\Public\Confirmation;

class Plugin
{
    private PostTypes $postTypes;
    private Assets $assets;
    private Shortcode $shortcode;
    private Ajax $ajax;
    private Confirmation $confirmation;
    private AdminMenu $adminMenu;
    private Settings $settings;
    private Columns $columns;
    private BookingsList $bookingsList;

    public function boot(): void
    {
        $this->loadTextDomain();
        
        $this->postTypes = new PostTypes();
        $this->assets = new Assets();
        $this->shortcode = new Shortcode();
        $this->ajax = new Ajax();
        $this->confirmation = new Confirmation();
        $this->adminMenu = new AdminMenu();
        $this->settings = new Settings();
        $this->columns = new Columns();
        $this->bookingsList = new BookingsList();

        $this->postTypes->init();
        $this->assets->init();
        $this->shortcode->init();
        $this->ajax->init();
        $this->confirmation->init();
        $this->adminMenu->init();
        $this->settings->init();
        $this->columns->init();
        $this->bookingsList->init();
    }

    private function loadTextDomain(): void
    {
        add_action('init', function () {
            load_plugin_textdomain(
                'lm-booking',
                false,
                dirname(plugin_basename(__FILE__)) . '/../languages/'
            );
        });
    }
}
