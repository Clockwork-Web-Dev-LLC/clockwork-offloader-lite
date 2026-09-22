=== Clockwork Offloader ===
Contributors: areimann
Tags: s3, amazon-s3, cloud-storage, media, offload, cdn, digitalocean, aws
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offload media files to Amazon S3 or DigitalOcean Spaces with seamless URL rewriting, delete-after-upload local storage reclamation, and custom CDN delivery.

== Description ==

Clockwork Offloader is a fast, reliable WordPress plugin that offloads your media files to cloud object storage (Amazon S3 or DigitalOcean Spaces). Offloading your uploads frees up hosting disk space, speeds up page load times, and enables high-speed global CDN delivery.

= Key Features (Lite) =

* **Cloud Storage Integration**: Offload media to Amazon S3 or DigitalOcean Spaces
* **Automatic Offload**: Automatically offload newly uploaded media files and all generated image thumbnail sizes
* **URL Rewriting**: Dynamically rewrite media URLs in post content, excerpts, and image downsize filters
* **Storage Space Reclamation**: Optional "Delete After Upload" removes local files once confirmed in the cloud
* **Database Tracking**: Dedicated index-optimized database table tracks offloaded files and thumbnail sizes
* **Custom CDN Delivery**: Route media through CloudFront, Cloudflare, or your custom CDN CNAME
* **Secure Credentials**: Configure credentials in the database or securely via `wp-config.php` constants
* **Multisite Support**: Per-site or network-wide configuration on WordPress Multisite networks

= Need Bulk Offload & Advanced Features? =

Clockwork Offloader Pro provides:
* **Bulk Offload & Restore**: Offload entire existing media libraries or download cloud files back to local server
* **Background Queue System**: Scalable batch queue worker with automatic retries and WP-Cron scheduling
* **Assets Pull CDN**: Serve enqueued CSS, JS, and web fonts directly from CloudFront without manual uploads
* **Private Media & Signed URLs**: Expiring pre-signed download links for WooCommerce digital products and Easy Digital Downloads
* **Competitor Migration Tool**: 1-click seamless migration of existing offloaded databases without re-uploading

Learn more at [Clockwork Plugins](https://clockworkplugins.com/plugins/clockwork-offloader).

= Security Features =

* Nonce verification on all administrative actions
* Capability checks (`manage_options` / `manage_network_options`) on all configuration screens
* Input sanitization and strict output escaping
* SQL injection prevention via `$wpdb->prepare()`
* Uploads path traversal guards on all file deletion operations

= Requirements =

* WordPress 5.0 or higher
* PHP 8.2 or higher

== Installation ==

1. In your WordPress admin dashboard, navigate to **Plugins > Add New**.
2. Search for **Clockwork Offloader** (or upload the `.zip` archive).
3. Click **Install Now**, then **Activate**.
4. Navigate to **Settings > Clockwork Offloader** to configure your bucket and credentials.

== Frequently Asked Questions ==

= How do I configure the plugin? =

Go to **Settings > Clockwork Offloader > Settings** and enter your AWS or DigitalOcean credentials. You can store credentials in wp-config.php (recommended) or in the database.

= Can I use DigitalOcean Spaces? =

Yes! The plugin supports both Amazon S3 and DigitalOcean Spaces.

= Will this delete my local files? =

Only if you enable the "Delete After Upload" option. By default, files remain on your server.

= Can I restore files from S3? =

Restoring offloaded files back to your local web server is supported via the bulk tools in Clockwork Offloader Pro.

= Does this work with multisite? =

Yes! The plugin supports both single-site and multisite installations with network-wide or site-specific configurations.

= Is this secure? =

Yes! All credentials can be stored securely in wp-config.php, and all user inputs and database queries are strictly sanitized and escaped.

== Screenshots ==

1. Dashboard with offload statistics and storage breakdown
2. Settings page with credentials and provider configuration
3. Automatic offload and URL rewrite controls
4. Setup wizard

== Changelog ==

= 1.1.2 =
* Modernized admin dashboard and setup workflow.
* Added custom CDN domain routing and automatic path-style addressing for dotted AWS buckets.
* WordPress 6.9 and PHP 8.5 compatibility enhancements.
* Optimized database indexing for offload tracker table.

= 1.0.1 =
* Bug-fix release following audit.
* Fixed thumbnail URL construction and duplicate database row prevention.

= 1.0.0 =
* Initial release
* Support for Amazon S3 and DigitalOcean Spaces
* Auto-offload functionality
* URL rewriting
* Multisite support

== Upgrade Notice ==

= 1.1.2 =
Recommended update with performance enhancements, CDN improvements, and PHP 8.5 compatibility.

