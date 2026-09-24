=== Clockwork Offloader ===
Contributors: areimann
Tags: s3, cloud-storage, media, offload, cdn
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offload WordPress media to Amazon S3, Cloudflare R2, or DigitalOcean with URL rewriting, local disk reclamation, and CDN delivery.

== Description ==

Clockwork Offloader is a fast, lightweight WordPress plugin that offloads your media library to cloud object storage (Amazon S3, Cloudflare R2, DigitalOcean Spaces, Wasabi, Backblaze B2, or any S3-compatible service).

Offloading your uploads frees up server disk space, accelerates page load times, and enables blazing-fast delivery through global Content Delivery Networks (CDNs) like CloudFront or Cloudflare.

= Key Features (Free) =

* **Multi-Provider Cloud Storage**: Connect seamlessly to Amazon S3, Cloudflare R2 ($0 egress fees!), DigitalOcean Spaces, Wasabi, Backblaze B2, MinIO, or custom S3-compatible endpoints.
* **Automatic Offload**: Automatically copies newly uploaded media and all generated responsive image sub-sizes (including WebP) to your cloud bucket.
* **Automatic URL Rewriting**: Transparently rewrites media URLs in posts, pages, excerpts, text widgets, and responsive `srcset` attributes to serve directly from S3 or your CDN.
* **Page Builder Support**: Native compatibility with Beaver Builder and Elementor to ensure background images in compiled layout CSS files are rewritten properly.
* **Local Storage Reclamation**: Optional "Delete After Upload" deletes local copies from your web server once confirmed in the cloud — freeing up valuable hosting storage.
* **Media Library Integration**: Visual cloud status column (On Cloud / On Server) and single-click manual offload, download, and delete controls directly in your WordPress Media Library.
* **Custom CDN / CNAME Delivery**: Route your media URLs through a custom domain or CDN (e.g., `cdn.example.com` or CloudFront).
* **Configurable Object Paths**: Keep default WordPress folder structures or define a custom S3 path prefix.
* **Secure Credentials**: Store credentials in the database or lock them down securely in `wp-config.php` constants.
* **Multisite Compatible**: Supports network-wide global inheritance or independent per-subsite configuration across WordPress Multisite networks.

= Clockwork Offloader Pro =

Need high-volume bulk tools and advanced workflows? Clockwork Offloader Pro adds:
* **Bulk Offload & Download**: Offload your entire existing Media Library in batches or pull all cloud files back to your server.
* **Asynchronous Queue**: Enterprise background job processor with automated retries and server throttle protection.
* **Assets Pull CDN**: Automatically deliver theme/plugin CSS, JavaScript, and web fonts through CloudFront without manual uploads.
* **Private Media & Signed URLs**: Expiring pre-signed download links for WooCommerce and Easy Digital Downloads digital products.
* **1-Click Competitor Migrator**: Migrate existing offloaded databases seamlessly without re-uploading files.

Learn more at [Clockwork Plugins](https://clockworkplugins.com/plugins/clockwork-offloader).

= Security Features =

* Strict nonce verification across all administrative actions and AJAX requests
* Capability checks (`manage_options` / `manage_network_options`) on all administration screens
* Strict input sanitization and output escaping throughout
* SQL injection prevention via `$wpdb->prepare()`
* Path-traversal protection on all file operations

== Installation ==

1. In your WordPress admin dashboard, navigate to **Plugins > Add New**.
2. Search for **Clockwork Offloader** (or upload the `.zip` archive).
3. Click **Install Now**, then **Activate**.
4. Navigate to **Settings > Clockwork Offloader** to connect your storage provider and bucket.

== Frequently Asked Questions ==

= How do I configure storage credentials? =

Go to **Settings > Clockwork Offloader > Settings** and select your provider (AWS S3, Cloudflare R2, DigitalOcean, Wasabi, etc.). You can enter your credentials in the settings view or lock them in `wp-config.php` (recommended for production).

= Can I use Cloudflare R2 or Wasabi? =

Yes! Clockwork Offloader natively supports Cloudflare R2 ($0 egress fees), Wasabi, DigitalOcean Spaces, Backblaze B2, MinIO, and AWS S3.

= Will this delete my local files? =

Only if you toggle the "Delete After Upload" option. By default, files remain on your local web server.

= Can I restore files back to the server? =

Yes. In the Media Library, you can download any offloaded file back to your server with a single click. For bulk restoration of thousands of files at once, Clockwork Offloader Pro includes a 1-click bulk download tool.

= Does this work with WordPress Multisite? =

Yes! The plugin fully supports multisite networks. You can configure settings globally on the network main site or allow individual subsites to manage their own buckets.

= Is this secure? =

Yes. All credentials can be stored securely in `wp-config.php` outside the database, and all inputs and queries are strictly validated and prepared.

== Screenshots ==

1. Storage vs Delivery settings cards with live URL preview
2. Media Library with Cloud Status indicator column
3. Individual attachment cloud controls and direct Cloud URL copy
4. Performance and advanced queue settings

== Changelog ==

= 1.1.2 =
* Modernized 2-column Storage vs Delivery dashboard.
* Added native Beaver Builder and Elementor compiled CSS URL rewriting.
* Added support for Cloudflare R2, Wasabi, Backblaze B2, and custom S3 endpoints.
* Added Dashicons integration for 100% WordPress.org compliant native UI.
* Optimized database indexing for offload tracking table.
* Multisite network settings inheritance enhancements.

= 1.0.1 =
* Bug-fix release for responsive thumbnail URL construction.
* Prevent duplicate database tracker records on media updates.

= 1.0.0 =
* Initial public release.
* Support for AWS S3 and DigitalOcean Spaces.
* Automatic offload and URL rewriting.
* Multisite compatibility.

== Upgrade Notice ==

= 1.1.2 =
Performance optimizations, Cloudflare R2 support, page builder CSS compatibility, and WordPress 6.9 compatibility.
