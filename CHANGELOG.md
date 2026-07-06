# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-12-15

### Added
- Initial release of Clockwork Offloader
- Support for AWS S3 and DigitalOcean Spaces storage providers
- Setup wizard for initial configuration (3-step process)
- Credential storage in wp-config.php (recommended) or database
- Automatic offloading of new media uploads (optional)
- Manual bulk offloading of existing media
- URL rewriting to serve media from S3/CDN
- Delete-after-upload option to free local storage
- Queue system for batch processing large operations
- Dashboard with statistics and system status
- Development Mode with diagnostic tools
- Multisite support (network-wide and per-site configuration)
- Database tracking of all offloaded files
- Support for all WordPress image sizes (thumbnails tracked separately)
- S3 connection testing and verification
- Region mismatch detection and error handling
- Custom logo SVG for admin interface
- URL preview feature showing constructed media URLs
- Automatic database table creation if missing
- Security features: nonce verification, capability checks, input sanitization, output escaping, SQL injection prevention

### Security
- All AJAX handlers verify nonces using `check_ajax_referer()`
- All admin actions check user capabilities (`manage_options` or `manage_network_options`)
- All user input sanitized using WordPress sanitization functions
- All output escaped using WordPress escaping functions
- All SQL queries use `$wpdb->prepare()` and `esc_sql()` for table names
- File path validation to prevent directory traversal attacks

### Technical
- Uses AWS SDK v3 for S3 operations
- Database tables: `wp_cloudbound_offloads` and `wp_cloudbound_offload_queue`
- Settings priority: wp-config constants > network settings > site settings > defaults
- Statistics caching (5 minutes) for performance
- Image size tracking with separate database records per size
- S3 key path structure includes `wp-content/uploads/` prefix
- Automatic migration from legacy Clockwork Offloader plugin

