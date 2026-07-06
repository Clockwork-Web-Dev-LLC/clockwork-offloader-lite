# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-07-06

Bug-fix pass following an adversarial audit — no user-facing feature changes.

### Fixed
- `ajax_restore()` could fatal-error on a Lite-only site (called a Pro-only class unconditionally) — now guarded, returns a clean error instead
- `activate_new_site()` could fatal-error when a new site was created on a Lite-only multisite network (required a Pro-only file unconditionally)
- DigitalOcean Spaces thumbnail URLs could 404 when a size-specific S3 record was missing — the fallback URL builder ignored the configured provider and always built an AWS-style URL
- Setup wizard: the DigitalOcean radio button could never render as checked after navigating back to Step 1
- Dashboard's example S3 URL showed a path structure (`wp-content/uploads/...`) that doesn't match what the plugin actually generates
- Concurrent offload attempts for the same attachment/size could create duplicate database rows, inflating statistics — offload recording is now an atomic upsert keyed on a new unique constraint (existing installs are migrated automatically, deduping any pre-existing duplicates first)
- wp-config.php credential fallback parsing never actually ran for the current `CLOCKWORK_OFFLOADER_SETTINGS` constant name (only the legacy name)
- A wp-config.php provider override (AWS vs DigitalOcean) was unreachable dead code
- `upload_file()` gained an optional explicit-key parameter so callers uploading a file that isn't under the WordPress uploads directory can specify its S3 key directly
- `file_exists()` now logs non-404 S3 errors (network/throttling) distinctly instead of silently treating them the same as a genuinely missing object

### Removed
- Two unused duplicate files at the plugin root (`class-admin.php`, `file-list.php`) — stale drafts, never loaded

### Changed
- `composer.json` package name updated to reflect the plugin's current name; removed an unused autoload mapping to a namespace nothing in the codebase uses

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

