<?php
/*
Plugin Name: Export Media Library
Plugin URI: https://github.com/massedge/wordpress-plugin-export-media-library
Description: Allows admins to export media library files as a compressed zip archive.
Version: 3.0.1
Author: Mass Edge Inc.
Author URI: https://www.massedge.com/
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// ensure all dependencies met before fully initializing plugin code
require 'lib/DependencyCheck.php';
$massEdgeWordPressPluginEmlDependencyCheck = new MassEdgeWordPressPluginExportMediaLibraryDependencyCheck(__FILE__);
if (!$massEdgeWordPressPluginEmlDependencyCheck->run()) return;

define('MASSEDGE_WORDPRESS_PLUGIN_EXPORT_MEDIA_LIBRARY_PLUGIN_PATH', __FILE__);
require 'run.php';
