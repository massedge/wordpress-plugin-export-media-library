<?php

// must be loaded by the plugin index.php file
if (!defined('MASSEDGE_WORDPRESS_PLUGIN_EXPORT_MEDIA_LIBRARY_PLUGIN_PATH')) exit;

// If main plugin class can't be found, use the local autoloader. Generally,
// local autoloader would not be required if the plugin is installed using
// composer, while a regular wordpress install will need the local autoloader.
if (!class_exists('MassEdge\\WordPress\\Plugin\\ExportMediaLibrary\\Plugin')) {
    require 'vendor/autoload.php';
}

(new MassEdge\WordPress\Plugin\ExportMediaLibrary\Plugin([
    'pluginPath' => MASSEDGE_WORDPRESS_PLUGIN_EXPORT_MEDIA_LIBRARY_PLUGIN_PATH,
]))
->registerHooks();
