=== Export Media Library ===
Contributors: andrej.pavlovic
Tags: export media library, download media library, media library, export, download
Requires at least: 4.7.10
Tested up to: 5.2
Requires PHP: 7.0
Stable tag: 2.2.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Allows users to export media library files as a compressed zip archive.

= Links =
* [Website](https://github.com/massedge/wordpress-plugin-export-media-library)
* [Support](https://github.com/massedge/wordpress-plugin-export-media-library/issues)

== Installation ==

1. Download and activate the plugin through the 'Plugins' screen in WordPress.
2. Go to Media -> Export via the admin menu to access the Export Media Library page.
3. Adjust form options before proceeding with the export.

== Screenshots ==

1. Export Media Library admin page

== Changelog ==

= 2.2.0 =
* added support for PHP 7.0

= 2.1.0 =
* composer - maennchen/zipstream-php - bumped to version 1.1

= 2.0.0 =
* bumped minimum php version to 7.1
* updated zipstream library

= 1.1.0 =
* expose API::export function for easier reuse by 3rd-party code
* set last modify time for each file in zip to match the timestamp on disk

= 1.0.1 =
Fixed title of plugin in readme.

= 1.0.0 =
* Fully functional release.

= 0.0.1 =
* Alpha release.
