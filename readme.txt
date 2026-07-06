=== Clockwork Offloader ===
Contributors: areimann
Tags: s3, amazon-s3, cloud-storage, media, offload, cdn, digitalocean, aws
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offload media files to Amazon S3 or DigitalOcean Spaces with optional URL rewriting, delete-after-upload, bulk migration tools, and restore functionality.

== Description ==

Clockwork Offloader is a powerful WordPress plugin that helps you offload your media files to cloud storage services like Amazon S3 or DigitalOcean Spaces. This reduces server storage usage, improves site performance, and enables CDN delivery of your media files.

= Key Features =

* **Cloud Storage Support**: Upload media files to Amazon S3 or DigitalOcean Spaces
* **Auto-Offload**: Automatically offload new media uploads (optional)
* **URL Rewriting**: Optionally rewrite media URLs to point to cloud storage
* **Delete After Upload**: Option to delete files from server after upload to save space
* **Bulk Offload**: Offload existing media files in bulk
* **Restore Functionality**: Restore files from cloud storage back to local server
* **Database Tracking**: Track offload status for all files
* **CDN Support**: Optional CDN domain configuration
* **Queue System**: Background processing for large uploads
* **Multisite Support**: Network-wide or per-site configuration

= Security Features =

* All AJAX requests are protected with nonce verification
* User capability checks on all admin functions
* Input sanitization on all user data
* Output escaping on all displayed data
* SQL injection prevention using prepared statements
* File path validation to prevent directory traversal

= Storage Options =

* **wp-config.php**: Store credentials securely in wp-config.php (recommended)
* **Database**: Store credentials in WordPress database

= Requirements =

* WordPress 5.0 or higher
* PHP 8.2 or higher
* AWS SDK for PHP v3 (installed via Composer)
* Symfony Filesystem ^7.4.0 (installed via Composer)

== Installation ==

1. Upload the plugin to `/wp-content/plugins/clockwork-offloader/`
2. Install dependencies by running `composer install` in the plugin directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your S3 credentials in Settings > Clockwork Offloader > Settings

== Frequently Asked Questions ==

= How do I configure the plugin? =

Go to **Settings > Clockwork Offloader > Settings** and enter your AWS or DigitalOcean credentials. You can store credentials in wp-config.php (recommended) or in the database.

= Can I use DigitalOcean Spaces? =

Yes! The plugin supports both Amazon S3 and DigitalOcean Spaces.

= Will this delete my local files? =

Only if you enable the "Delete After Upload" option. By default, files remain on your server.

= Can I restore files from S3? =

Yes! Use the "Restore" feature in the Bulk Offload section to download files back to your server.

= Does this work with multisite? =

Yes! The plugin supports both single-site and multisite installations.

= Is this secure? =

Yes! All credentials can be stored in wp-config.php (outside the web root), and all user inputs are sanitized and validated.

== Screenshots ==

1. Dashboard with statistics
2. Settings page
3. Bulk offload interface
4. Setup wizard

== Changelog ==

= 1.0.0 =
* Initial release
* Support for Amazon S3 and DigitalOcean Spaces
* Auto-offload functionality
* URL rewriting
* Bulk offload and restore
* Queue system for background processing
* Multisite support

== Upgrade Notice ==

= 1.0.0 =
Initial release of Clockwork Offloader.

