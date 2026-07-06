# Clockwork Offloader

A WordPress plugin for offloading media files to Amazon S3 with optional URL rewriting, delete-after-upload, bulk migration tools, and restore functionality.

## Features

- **S3 Integration**: Upload media files to Amazon S3 using AWS SDK v3
- **Auto-Offload**: Automatically offload new media uploads (optional)
- **URL Rewriting**: Optionally rewrite media URLs to point to S3
- **Delete After Upload**: Option to delete files from server after upload
- **Bulk Offload**: Offload existing media files in bulk
- **Restore Functionality**: Restore files from S3 back to local server
- **Database Tracking**: Track offload status for all files
- **CDN Support**: Optional CDN domain configuration

## Installation

1. Upload the plugin to `/wp-content/plugins/clockwork-offloader/`
2. Install dependencies by running `composer install` in the plugin directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure your S3 credentials in Settings > Clockwork Offloader > Settings

## Requirements

- WordPress 5.0 or higher
- PHP 8.2 or higher
- AWS SDK for PHP v3 (installed via Composer)
- Symfony Filesystem ^7.4.0 (installed via Composer)

## Configuration

1. Go to **Clockwork Offloader > Settings**
2. Enter your AWS credentials:
   - AWS Access Key ID
   - AWS Secret Access Key
   - S3 Bucket Name
   - S3 Region
3. Configure optional settings:
   - Base Path (optional prefix for S3 uploads)
   - CDN Domain (optional CDN domain)
4. Enable desired options:
   - Auto-Offload: Automatically offload new uploads
   - Delete After Upload: Remove files from server after upload
   - Rewrite URLs: Replace media URLs with S3 URLs

## Usage

### Dashboard

View statistics and system status on the main dashboard page.

### Bulk Offload

1. Go to **Clockwork Offloader > Bulk Offload**
2. Filter attachments by status (All, Offloaded, Not Offloaded)
3. Click "Offload" on individual attachments or use bulk actions

### Restore Files

1. Go to **Clockwork Offloader > Bulk Offload**
2. Find offloaded attachments
3. Click "Restore" to download files back to the local server

## Database

The plugin creates a custom table `wp_cloudbound_offloads` to track:
- Attachment ID
- S3 bucket and key
- Original file path
- File size
- Offload date
- Status (offloaded, restored, deleted)

## Future Enhancements

- DigitalOcean Spaces integration
- CloudFront integration
- Image optimization before upload
- Scheduled offloads
- Export/import settings

## Resources

This plugin follows WordPress coding standards and uses patterns from WordPress core:

- **WordPress Database API**: Uses `$wpdb->prepare()` with proper placeholders for secure database queries
  - Reference: [WordPress wpdb::prepare() documentation](https://developer.wordpress.org/reference/classes/wpdb/prepare/)
  - Uses `%i` identifier placeholder (WordPress 6.2+) for table names with fallback for older versions
  - Reference: [WordPress wpdb identifier placeholders](https://developer.wordpress.org/reference/classes/wpdb/prepare/#identifier-placeholders)

- **WordPress Coding Standards**: Code follows WordPress PHP Coding Standards
  - Reference: [WordPress Coding Standards Handbook](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)

## License

GPL v2 or later

