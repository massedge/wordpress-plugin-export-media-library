<?php

class MassEdgeWordPressPluginExportMediaLibraryDependencyCheck {
    const PLUGIN_NAME = 'Export Media Library';

    const MINIMUM_PHP_VERSION_REQUIRED = '7.1';

    private $pluginPath;
    private $adminNoticePluginDisabledMessage;

    function __construct($pluginPath) {
        $this->pluginPath = $pluginPath;
    }

    function run() {
        // ensure minimum php version
        if (version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION_REQUIRED, '<')) {
            return self::checkFailed(sprintf(
                '%s plugin requires PHP %s or higher. You’re still on %s.',
                self::PLUGIN_NAME,
                self::MINIMUM_PHP_VERSION_REQUIRED,
                PHP_VERSION
            ));
        }

        return true;
    }

    private function checkFailed($message) {
        if(!function_exists('is_plugin_active') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active(plugin_basename($this->pluginPath))) {
            deactivate_plugins(plugin_basename($this->pluginPath));

            // NOTE: don't use anonymous functions just in case
            $this->adminNoticePluginDisabledMessage = sprintf('%s Disabled plugin to avoid further issues.', $message);
            add_action('admin_notices', array($this, 'adminNoticePluginDisabled'));
        } else {
            echo $message;
            die();
        }
        
        return false;
    }

    function adminNoticePluginDisabled() {
        echo sprintf('<div class="error"><p>%s</p></div>', esc_html($this->adminNoticePluginDisabledMessage));
    }
}
