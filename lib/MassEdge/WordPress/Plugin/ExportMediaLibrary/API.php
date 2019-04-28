<?php

namespace MassEdge\WordPress\Plugin\ExportMediaLibrary;

use ZipStream\ZipStream;

class API {
    const FOLDER_STRUCTURE_NESTED = 'nested';
    const FOLDER_STRUCTURE_FLAT = 'flat';

    /**
     * Stream zip file comprised of all attachments directly to output stream.
     * @param array $options
     * @return void
     */
    static function export(array $options = array()) {
        $options = array_merge([
            'filename' => 'export.zip',
            'folder_structure' => self::FOLDER_STRUCTURE_NESTED,
            'compress' => false,
            'upload_basedir' => self::getUploadBasedir(),
        ], $options);

        # create a new zipstream object
        $zip = new ZipStream($options['filename'], [
            // WORKAROUND: treat each file as large in order to use STORE method, thereby avoiding compression
            ZipStream::OPTION_LARGE_FILE_SIZE => ($options['compress']) ? 20 * 1024 * 1024 : 1,
        ]);

        $query = new \WP_Query();
        $attachmentIds = $query->query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        $flatFilenames = [];
        $rootFolderName = pathinfo($options['filename'], PATHINFO_FILENAME);

        foreach($attachmentIds as $attachmentId) {
            $attachmentPath = get_attached_file($attachmentId);

            if (!$attachmentPath) continue;

            switch($options['folder_structure']) {
                case self::FOLDER_STRUCTURE_NESTED:
                    // check if attachment in upload folder
                    if (substr($attachmentPath, 0, strlen($options['upload_basedir'])) === $options['upload_basedir']) {
                        $file = substr($attachmentPath, strlen($options['upload_basedir']) + 1);
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
                $zip->addFileFromPath("{$rootFolderName}/{$file}", $attachmentPath);
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