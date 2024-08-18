<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite1980c7b1ed633e10a7a9d2cb81978d2
{
    public static $prefixLengthsPsr4 = array (
        'I' => 
        array (
            'IngeniusTrackingPaypal\\Includes\\' => 32,
            'IngeniusTrackingPaypal\\Admin\\' => 29,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'IngeniusTrackingPaypal\\Includes\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
        'IngeniusTrackingPaypal\\Admin\\' => 
        array (
            0 => __DIR__ . '/../..' . '/admin',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite1980c7b1ed633e10a7a9d2cb81978d2::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite1980c7b1ed633e10a7a9d2cb81978d2::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInite1980c7b1ed633e10a7a9d2cb81978d2::$classMap;

        }, null, ClassLoader::class);
    }
}
