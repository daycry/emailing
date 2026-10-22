<?php

$root = dirname(__DIR__);
foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app/private', 'bootstrap/cache'] as $directory) {
    $path = $root.'/'.$directory;
    if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
        throw new RuntimeException('Cannot prepare application runtime directory.');
    }
}
