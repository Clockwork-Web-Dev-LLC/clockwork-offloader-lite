# Clockwork Offloader

A fast, lightweight WordPress plugin for offloading WordPress media to cloud object storage (Amazon S3, Cloudflare R2, DigitalOcean Spaces, Backblaze B2, Wasabi, or any S3-compatible service) with automatic URL rewriting, local disk reclamation, and CDN delivery.

## Key Features

- **Multi-Provider Cloud Storage**: Connect seamlessly to Amazon S3, Cloudflare R2 ($0 egress fees), DigitalOcean Spaces, Wasabi, Backblaze B2, MinIO, or custom S3-compatible endpoints.
- **Automatic Offload**: Automatically copies newly uploaded media and all generated responsive image sub-sizes (including WebP) to your cloud bucket.
- **Automatic URL Rewriting**: Transparently rewrites media URLs in posts, pages, excerpts, text widgets, and responsive `srcset` attributes to serve directly from S3 or your CDN.
- **Page Builder Compatibility**: Native compatibility with Beaver Builder and Elementor to ensure background images in layout CSS are rewritten properly.
- **Local Storage Reclamation**: Optional "Delete After Upload" deletes local files from your server once confirmed in the cloud — freeing up valuable hosting storage.
- **Media Library Controls**: Visual cloud status column (On Cloud / On Server) and single-click manual offload, download, and delete controls directly in your WordPress Media Library.
- **Custom CDN / CNAME Delivery**: Route your media URLs through a custom domain or CDN (e.g., `cdn.example.com` or Amazon CloudFront).
- **Secure Credentials**: Store credentials in the database or lock them down securely in `wp-config.php` constants.
- **Multisite Support**: Supports network-wide global inheritance or independent per-subsite configuration across WordPress Multisite networks.

## Clockwork Offloader Pro

For advanced features and enterprise workloads, [Clockwork Offloader Pro](https://clockworkplugins.com/plugins/clockwork-offloader) adds:
- **Bulk Offload & Download**: Offload your entire existing Media Library in batches or pull all cloud files back to your server.
- **Background Queue System**: Asynchronous cron processor with throttle protection and automatic retries.
- **Assets Pull CDN**: Automatically deliver theme/plugin CSS, JS, and web fonts through CloudFront without manual uploads.
- **Private Media & Signed URLs**: Expiring pre-signed download links for WooCommerce and Easy Digital Downloads digital products.
- **1-Click Migrator**: Migrate existing offloaded databases seamlessly without re-uploading files.

## Requirements

- WordPress 5.0 or higher (tested up to 7.1)
- PHP 8.2 or higher
- AWS SDK for PHP v3 (included in release bundle)
- Symfony Filesystem ^7.4.0 (included in release bundle)

## Installation

1. Upload the plugin folder to `/wp-content/plugins/clockwork-offloader/` (or install the `.zip` via **Plugins > Add New > Upload Plugin**).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Follow the guided 3-step setup wizard or configure your credentials in **Settings > Clockwork Offloader**.

## Database

The plugin creates a custom table `{prefix}clockwork_offloads` to track:
- Attachment ID
- Image size name (empty for original file)
- S3 bucket and object key
- Original file path and file size
- Offload timestamp and status

## Contributing & Development

```bash
# Clone the repository
git clone https://github.com/Clockwork-Web-Dev-LLC/clockwork-offloader-lite.git
cd clockwork-offloader-lite

# Install development dependencies
composer install

# Run unit tests
./vendor/bin/phpunit
```

## License

GPL v2 or later
