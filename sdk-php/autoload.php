<?php
/**
 * Autoloader for installs without Composer (the platform plugins bundle the SDK as lib/paknic-sdk/).
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'DomainRegistrar\\SDK\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
