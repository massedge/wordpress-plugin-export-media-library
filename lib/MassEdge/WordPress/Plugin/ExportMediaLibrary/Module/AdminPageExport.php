<?php

namespace MassEdge\WordPress\Plugin\ExportMediaLibrary\Module;

use ZipStream\ZipStream;

class AdminPageExport extends Base {
    const FIELD_SUBMIT_DOWNLOAD = 'massedge-wp-plugin-eml-ape-submit-download';
    const FIELD_NONCE_DOWNLOAD_ACTION = 'massedge-wp-eml-ape-download';
    const FIELD_FOLDER_STRUCTURE = 'folder_structure';
    const FIELD_COMPRESS = 'compress';

    const FOLDER_STRUCTURE_NESTED = 'nested';
    const FOLDER_STRUCTURE_FLAT = 'flat';

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
            $filename = sprintf('%s_media_library_export', current_time('Y-m-d_H-i-s'));

            // set folder structure
            $folderStructure = (
                    empty($_POST[self::FIELD_FOLDER_STRUCTURE]) ||
                    !in_array($_POST[self::FIELD_FOLDER_STRUCTURE], [self::FOLDER_STRUCTURE_NESTED, self::FOLDER_STRUCTURE_FLAT])
                )
                ? self::FOLDER_STRUCTURE_NESTED
                : $_POST['folder_structure'];

            // set compress option
            $compress = !empty($_POST[self::FIELD_COMPRESS]);

            try {
                // export
                $this->export($filename, $folderStructure, $compress);
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
                        <option value="<?php echo esc_attr(self::FOLDER_STRUCTURE_FLAT) ?>">Single folder with all files</option>
                        <option value="<?php echo esc_attr(self::FOLDER_STRUCTURE_NESTED) ?>">Nested folders</option>
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

    function export($exportName, $folderStructure, $compress) {
        $exportFilename = "{$exportName}.zip";
        $basedir = self::getUploadBasedir();

        # create a new zipstream object
        $zip = new ZipStream($exportFilename, [
            // WORKAROUND: treat each file as large in order to use STORE method, thereby avoiding compression
            ZipStream::OPTION_LARGE_FILE_SIZE => ($compress) ? 20 * 1024 * 1024 : 1,
        ]);

        $query = new \WP_Query();
        $attachmentIds = $query->query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        $flatFilenames = [];

        foreach($attachmentIds as $attachmentId) {
            $attachmentPath = get_attached_file($attachmentId);

            if (!$attachmentPath) continue;

            switch($folderStructure) {
                case self::FOLDER_STRUCTURE_NESTED:
                    // check if attachment in upload folder
                    if (substr($attachmentPath, 0, strlen($basedir)) === $basedir) {
                        $file = substr($attachmentPath, strlen($basedir) + 1);
                    } else {
                        if (0 == strpos( $attachmentPath, '/' )) {
                            $file = substr($attachmentPath, 1);
                        } else if (preg_match( '|^.:\\\|', $attachmentPath )) {
                            $file = substr($attachmentPath, 3);
                        } else {
                            $file = $attachmentPath;
                        }
                    }
                    break;
                
                case self::FOLDER_STRUCTURE_FLAT:
                default:
                    $file = basename($attachmentPath, PATHINFO_BASENAME);
                    $filename = pathinfo($file, PATHINFO_FILENAME);
                    $ext = pathinfo($file, PATHINFO_EXTENSION);

                    // append a number to file name, of another with same name is already present
                    for($i = 0; in_array($file, $flatFilenames); $i++) {
                        $file = $filename . $i . (($ext !== null) ? '.' . $ext : '');
                    }

                    // keep track of file name, so another file doesn't over write it
                    $flatFilenames[] = $file;
            }
            
            try {
                $zip->addFileFromPath("{$exportName}/{$file}", $attachmentPath);
            } catch (\Exception $ex) {
                // skip files that fail to be added to zip
                continue;
            }
        }

        # finish the zip stream
        $zip->finish();
    }

    private static function getUploadBasedir() {
        $uploads = wp_get_upload_dir();
        if ($uploads['error'] !== false) {
            throw new \Exception($uploads['error']);
        }
        return $uploads['basedir'];
    }
}
