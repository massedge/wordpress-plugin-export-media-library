<?php

namespace MassEdge\WordPress\Plugin\ExportMediaLibrary;

use ZipStream\ZipStream;

class Plugin {
    protected $options = [];

    function __construct($options) {
        $this->options = $options;
    }

    function registerHooks() {
        if (!empty($_POST['asdf'])) {
            $this->download('example.zip');
            die();
        }

        add_action('admin_menu', function() {
            add_submenu_page('upload.php', 'Export Media Library', 'Export', 'read', 'mass-edge-export-media-library', [$this, 'page']);
        });
    }

    function page() {
        ob_start();
        ?>

        <form action="" method="post" target="_blank">
            <input type="hidden" name="asdf" value="asdf"></input>
            <button type="submit">Submit</button>
        </form>

        <?php
        echo ob_get_clean();
    }

    function download($name) {
        # create a new zipstream object
        $zip = new ZipStream($name, [
            // WORKAROUND: treat each file as large in order to use STORE method (and not deflate)
            // It is assumed that most items will be images and/or videos, so compression isn't really necessary
            ZipStream::OPTION_LARGE_FILE_SIZE => 1,
        ]);

        $query = new \WP_Query();
        $attachmentIds = $query->query([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        foreach($attachmentIds as $attachmentId) {
            $file = get_post_meta($attachmentId, '_wp_attached_file', true);
            $attachmentPath = get_attached_file($attachmentId);

            try {
                $zip->addFileFromPath($file, $attachmentPath);
            } catch (\Exception $ex) {
                // skip files that fail to be added to zip
                continue;
            }
        }

        # finish the zip stream
        $zip->finish();
    }
}
