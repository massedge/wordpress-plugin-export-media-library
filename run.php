<?php

// must be loaded by the plugin index.php file
if (!defined('MASSEDGE_WORDPRESS_PLUGIN_EXPORT_MEDIA_LIBRARY_PLUGIN_PATH')) exit;

//require 'lib/autoload.php';
require 'vendor/autoload.php';

(new MassEdge\WordPress\Plugin\ExportMediaLibrary\Plugin([
    'pluginPath' => MASSEDGE_WORDPRESS_PLUGIN_EXPORT_MEDIA_LIBRARY_PLUGIN_PATH,
]))
->registerHooks();
