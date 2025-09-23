<?php

namespace LM\Booking;

class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): void
    {
        if (strpos($class, 'LM\\Booking\\') !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen('LM\\Booking\\'));
        $file = LM_BOOKING_DIR . 'includes/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
