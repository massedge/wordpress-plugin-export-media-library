<?php

namespace MassEdge\WordPress\Plugin\ExportMediaLibrary;

use ZipStream\ZipStream;
use ZipStream\File as ZipFile;
use ZipStream\Option\Archive as ArchiveOptions;
use ZipStream\Option\File as FileOptions;
use ZipStream\Option\Method as MethodOptions;

class API {
    const FOLDER_STRUCTURE_NESTED = 'nested';
    const FOLDER_STRUCTURE_FLAT = 'flat';

    static function defaultExportOptions() {
        return [
            'filename' => 'export.zip',
            'root_path' => null, // defaults to `filename` without extension 
            'folder_structure' => self::FOLDER_STRUCTURE_NESTED,
            'compress' => false,
            'upload_basedir' => self::getUploadBasedir(),
            'query_args' => [
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'fields' => 'ids',
                'posts_per_page' => -1,
            ],
            'add_attachment_callback' => function($value, $params) { return $value; },
            'add_attachment_failed_callback' => function($params) {},
            'add_extra_files_callback' => function($params) {},
            'purge_output_buffers' => true,
        ];
    }

    /**
     * Stream zip file comprised of all attachments directly to output stream.
     * @param array $options
     * @return void
     */
    static function export(array $options = array()) {
        $options = array_merge(self::defaultExportOptions(), $options);

        if ($options['root_path'] === null) {
            // default to `filename` without extension 
            $options['root_path'] = pathinfo($options['filename'], PATHINFO_FILENAME);
        }
        // ensure path doesn't end in slash
        if ($options['root_path']) $options['root_path'] = rtrim($options['root_path'], '/\\');

        // compression method
        $compressionMethod = new MethodOptions(
            ($options['compress']) ? MethodOptions::DEFLATE : MethodOptions::STORE
        );
        
        // clear output buffers
        if ($options['purge_output_buffers']) {
            while (ob_get_level()) if (!ob_end_clean()) break;
        }

        // create a new zipstream object
        $archiveOptions = new ArchiveOptions();
        $archiveOptions->setSendHttpHeaders(true);
        $archiveOptions->setFlushOutput(true);
        $zip = new ZipStream($options['filename'], $archiveOptions);

        $query = new \WP_Query();
        $attachmentIds = $query->query($options['query_args']);

        $flatFilenames = [];

        foreach($attachmentIds as $attachmentId) {
            $attachmentPath = get_attached_file($attachmentId);

            if ($attachmentPath) {
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

                $file = ($options['root_path'])
                    ? "{$options['root_path']}/{$file}"
                    : "{$file}";

                $time = @filectime($attachmentPath);
            } else {
                $file = null;
                $time = false;
            }

            // opportunity to manipulate adding of attachment to zip
            $result = $options['add_attachment_callback']([
                'name' => $file,
                'path' => $attachmentPath,
                'options' => [
                    'time' => $time,
                ],
            ], [
                'attachment_id' => $attachmentId,
            ]);

            // skip attachment if result not specified
            if (!$result || empty($result['name']) || empty($result['path'])) continue;

            $fileOptions = new FileOptions();
            $fileOptions->defaultTo($archiveOptions);
            if (!$options['compress']) $fileOptions->setMethod($compressionMethod);
            if (!empty($result['options']['time'])) {
                $date = new \DateTime('@' . $result['options']['time']);
                $fileOptions->setTime($date);
            }
            
            try {
                $zip->addFileFromPath($result['name'], $result['path'], $fileOptions);
            } catch (\Exception $ex) {
                $options['add_attachment_failed_callback']([
                    'name' => $result['name'],
                    'path' => $result['path'],
                    'options' => $result['options'],
                    'exception' => $ex,
                ]);

                // skip files that fail to be added to zip
                continue;
            }
        }

        // give opportunity to add extra files before finishing the stream
        $options['add_extra_files_callback']([
            'add_file_callback' => function($name, $path, array $options = []) use ($zip, $archiveOptions, $compressionMethod) {
                $fileOptions = new FileOptions();
                $fileOptions->defaultTo($archiveOptions);
                $fileOptions->setMethod($compressionMethod);
                if (!empty($options['time'])) {
                    $date = new \DateTime('@' . $options['time']);
                    $fileOptions->setTime($date);
                }

                $zip->addFileFromPath($name, $path, $fileOptions);
            },
        ]);

        // finish the zip stream
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