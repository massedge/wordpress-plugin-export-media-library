<?php

namespace MassEdge\WordPress\Plugin\ExportMediaLibrary\Module;

use MassEdge\WordPress\Plugin\ExportMediaLibrary\API;

class AdminPageExport extends Base {
    const FIELD_SUBMIT_DOWNLOAD = 'massedge-wp-plugin-eml-ape-submit-download';
    const FIELD_NONCE_DOWNLOAD_ACTION = 'massedge-wp-eml-ape-download';
    const FIELD_FOLDER_STRUCTURE = 'folder_structure';
    const FIELD_COMPRESS = 'compress';

    const REQUIRED_CAPABILITY = 'upload_files';

    function registerHooks() {
        add_action('admin_menu', function() {
            add_submenu_page(
                'upload.php',
                'Export Media Library',
                'Export',
                self::REQUIRED_CAPABILITY,
                'mass-edge-export-media-library',
                [$this, 'page']
            );
        });

        add_action('admin_init', function() {
            // check if download submitted
            if (empty($_POST[self::FIELD_SUBMIT_DOWNLOAD])) return;

            // capability check
            if (!current_user_can(self::REQUIRED_CAPABILITY)) return;

            // nonce check
            if (!check_admin_referer(self::FIELD_NONCE_DOWNLOAD_ACTION)) return;

            // create name for download
            $filename = self::getExportFilename(get_option('blogname'));

            // set folder structure
            $folderStructure = (
                    empty($_POST[self::FIELD_FOLDER_STRUCTURE]) ||
                    !in_array($_POST[self::FIELD_FOLDER_STRUCTURE], [API::FOLDER_STRUCTURE_NESTED, API::FOLDER_STRUCTURE_FLAT])
                )
                ? API::FOLDER_STRUCTURE_NESTED
                : $_POST['folder_structure'];

            // set compress option
            $compress = !empty($_POST[self::FIELD_COMPRESS]);

            try {
                API::export([
                    'filename' => $filename,
                    'folder_structure' => $folderStructure,
                    'compress' => $compress,
                    'add_attachment_callback' => function($value, $params) {
                        return apply_filters('massedge-wp-eml/export/add_attachment', $value, $params);
                    },
                    'add_attachment_failed_callback' => function($params) {
                        do_action('massedge-wp-eml/export/add_attachment_failed', $params);
                    },
                    'add_extra_files_callback' => function($params) {
                        do_action('massedge-wp-eml/export/add_extra_files', $params);
                    }
                ]);
            } catch (\Exception $ex) {
                add_action('admin_notices', function() use ($ex) {
                    echo sprintf('<div class="error"><p>%s</p></div>', esc_html($ex->getMessage()));
                });
            }

            // done
            die();
        });
    }

    function page() {
        ob_start();
        ?>
<div class="wrap">
    <h1>Export Media Library</h1>

    <form action="" method="post" target="_blank">
        <?php wp_nonce_field(self::FIELD_NONCE_DOWNLOAD_ACTION) ?>

        <table class="form-table">
            <tr>
                <th>Folder Structure</th>
                <td>
                    <select name="<?php echo esc_attr(self::FIELD_FOLDER_STRUCTURE) ?>">
                        <option value="<?php echo esc_attr(API::FOLDER_STRUCTURE_FLAT) ?>">Single folder with all files</option>
                        <option value="<?php echo esc_attr(API::FOLDER_STRUCTURE_NESTED) ?>">Nested folders</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Compress</th>
                <td>
                    <select name="<?php echo esc_attr(self::FIELD_COMPRESS) ?>">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                    <p class="description">Enabling compression can decrease the size of the zip download, but requires more processing on the server.</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary" name="<?php echo self::FIELD_SUBMIT_DOWNLOAD ?>" value="1">Download Zip</button>
        </p>
    </form>
</div>
        <?php
        echo ob_get_clean();
    }

    private static function getExportFilename($blogname) {
        $name = mb_strtolower($blogname);
        $name = preg_replace('/\s+/', '_', $name);
        $name = preg_replace('/[^\w\d_]/iu','', $name);
        
        $unsanatizedFilename = implode('-', [
            'media_library_export',
            $name,
            current_time('Y_m_d_H_i_s', true),
        ]) . '.zip';

        return sanitize_file_name($unsanatizedFilename);
    }
}
