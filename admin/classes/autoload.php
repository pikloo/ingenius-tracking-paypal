<?php

 /**
 * Autoload all classes in the /admin/classes directory.
 */
spl_autoload_register(
    function ( $class_name ) {
        $base_namespace = 'IngeniusTrackingPaypal\\';

        $len = strlen( $base_namespace );
        if ( strncmp( $base_namespace, $class_name, $len ) !== 0 ) {
                return;
        }

        $relative_class = substr( $class_name, $len );

        $file = plugin_dir_path( __FILE__ ) . 'admin/classes/class-' . strtolower( $relative_class ) . '.php';

        if ( file_exists( $file ) ) {
            require $file;
        }
    }
);