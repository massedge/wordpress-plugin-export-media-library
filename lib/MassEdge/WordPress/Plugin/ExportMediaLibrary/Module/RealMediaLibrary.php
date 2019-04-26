<?php

namespace MassEdge\WordPress\Plugin\ExportMediaLibrary\Module;

/**
 * Resolve filenames by a set of attachment ids.
 * 
 * @private
 */
class HierarchicalRMLFilenameResolver {
    
    private $folders = null;
    
    private $rootFolder = null;
    
    public function __construct($rootFolder) {
        $this->rootFolder = $rootFolder;
    }
    
    /**
     * Exclude the main folder from the attachment path because the exported
     * zip already starts with the exported name as first-level folder.
     */
    public function excludeRoot($folder) {
        return $folder->getId() !== $this->rootFolder->getId();
    }
    
    /**
     * Resolve the filename to RML specific folder.
     */
    public function resolve($file, $attachmentId, $attachmentIds, $attachmentPath) {
        // Read folders of all exported attachment files (performance should be good because it's only performed once)
        if ($this->folders === null) {
            // Get attachment -> folder mapping with a simple SQL
            global $wpdb;
            $attachments_in = implode(',', $attachmentIds); // We do not need to escape because it is the result of WP_Query
            $table_name = $wpdb->prefix . 'realmedialibrary_posts';
            $folders = $wpdb->get_results("SELECT rmlposts.attachment, rmlposts.fid FROM $table_name AS rmlposts WHERE rmlposts.attachment IN ($attachments_in)");
            
            // Only get the pathes of the folders
            $this->folders = array();
            foreach ($folders as $row) {
                $id = (int) $row->attachment;
                $this->folders[$id] = trim(wp_rml_get_object_by_id($row->fid)->getPath('/', '_wp_rml_sanitize_filename', array($this, 'excludeRoot')), '/\\');
            }
        }
        
        $path = $this->folders[$attachmentId];
        $basename = basename($file);
        return empty($path) ? $basename : path_join($path, $basename);
    }
    
}

/**
 * Allows a more intensive compatibility to Real Media Library plugin.
 * 
 * @author MatthiasWeb <support@matthias-web.com>
 */
class RealMediaLibrary extends Base {
    const SCRIPT_HANDLE = 'massedge-wp-export-rml';
    const SCRIPT_PATH = 'assets/js/realmedialibrary.js';
    const SCRIPT_DEPENDENCY = 'real-media-library';
    const AJAX_ACTION = 'massedge-wp-plugin-eml-ape-rml-download';
    const MIN_RML = '4.4.1';
    
    private $active;
    
    private $version;

    function __construct() {
        $this->active = defined('RML_VERSION') && version_compare(RML_VERSION, self::MIN_RML, '>=');
    }

    function registerHooks() {
        if ($this->active) {
            // Get version of this plugin
            $data = get_plugin_data(MASSEDGE_WORDPRESS_PLUGIN_EXPORT_MEDIA_LIBRARY_PLUGIN_PATH, true, false);
            $this->version = $data['Version'];
            
            add_action('RML/Scripts', [$this, 'enqueue_scripts']);
            add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'ajax_download']);
            add_filter('RML/Localize', [$this, 'localize']);
        }
    }
    
    /**
     * Start the download via AJAX action.
     */
    public function ajax_download() {
        check_ajax_referer(self::SCRIPT_HANDLE);
        
        // Do some hecks
        if (!isset($_REQUEST['type'], $_REQUEST['folder'])) {
            wp_send_json_error('No type or folder given.');
        }
        
        $type = $_REQUEST['type'];
        $folder = wp_rml_get_object_by_id($_REQUEST['folder']);
        if (!is_rml_folder($folder)) {
            wp_send_json_error('Invalid folder.');
        }
        $filename = sanitize_file_name($folder->getName());
        $id = $folder->getId();
        $compress = false;
        
        // Export the zip with the given overrides
        $export = new AdminPageExport();
        
        switch($type) {
            // Without subfolders
            case 'wosFlat':
                $export->export($filename, AdminPageExport::FOLDER_STRUCTURE_FLAT, $compress, ['rml_folder' => $id]);
                break;
            case 'wosHierarchical':
                $export->export($filename, AdminPageExport::FOLDER_STRUCTURE_NESTED, $compress, ['rml_folder' => $id]);
                break;
            // With subfolders
            case 'wsFlat':
                $export->export($filename, AdminPageExport::FOLDER_STRUCTURE_FLAT, $compress, ['rml_folder' => $id, 'rml_include_children' => true]);
                break;
            case 'wsHierarchicalRML';
                $resolver = new HierarchicalRMLFilenameResolver($folder);
                add_filter('massedge/wp/eml/export/nested/file', [$resolver, 'resolve'], 10, 4);
                $export->export($filename, AdminPageExport::FOLDER_STRUCTURE_NESTED, $compress, ['rml_folder' => $id, 'rml_include_children' => true]);
                remove_filter('massedge/wp/eml/export/nested/file', [$resolver, 'resolve']);
                break;
            case 'wsHierarchical':
                $export->export($filename, AdminPageExport::FOLDER_STRUCTURE_NESTED, $compress, ['rml_folder' => $id, 'rml_include_children' => true]);
                break;
            default:
                wp_send_json_error('Invalid type.');
        }
    }
    
    /**
     * Add a localized nonce to the rmlOpts variable for AJAX interaction (admin-ajax.php).
     */
    public function localize($arr) {
        return array_merge($arr, [
            'massedge_wp_export' => [
                'nonce' => wp_create_nonce(self::SCRIPT_HANDLE),
                'action' => self::AJAX_ACTION
            ]
        ]);
    }
    
    /**
     * Enqueue a javascript file which creates the context menu in the RML toolbar.
     */
    public function enqueue_scripts($assets) {
        wp_enqueue_script(self::SCRIPT_HANDLE, plugins_url(self::SCRIPT_PATH, MASSEDGE_WORDPRESS_PLUGIN_EXPORT_MEDIA_LIBRARY_PLUGIN_PATH), array(self::SCRIPT_DEPENDENCY), $this->version, true);
    }
}
