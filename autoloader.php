<?php

/**
 * Autoloader function for Ingenius Tracking Paypal Plugin.
 *
 * @param string $class_name The fully-qualified class name.
 */
function it_autoload($class_name) {

    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/';

    // Namespace prefix for this plugin
    $prefix = 'IngeniusTrackingPaypal\\';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class_name, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class_name, $len);

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
}

spl_autoload_register('it_autoload');
