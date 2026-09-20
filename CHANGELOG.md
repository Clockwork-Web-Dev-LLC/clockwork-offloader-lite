# Changelog

All notable changes to this project will be documented in this file.

## 1.1.1 (unreleased)

### Fixed
- Offloaded files in AWS buckets whose name contains a dot (e.g. `example.org`) were served from `https://example.org.s3.{region}.amazonaws.com/…`, which fails TLS (`ERR_CERT_COMMON_NAME_INVALID`) because Amazon's wildcard certificate covers one label only. Such buckets now use path-style URLs, `https://s3.{region}.amazonaws.com/example.org/…`. URL construction was duplicated in five places (S3 service, tracker ×2, URL rewriter ×2, settings preview, verification pattern); all now go through `Clockwork_Offloader_S3_Service::build_public_url()`.
- Setup wizard: bucket-scoped IAM keys (no `s3:ListAllMyBuckets`) no longer fail steps 1 and 2. `ListBuckets` returning `AccessDenied` is now treated as "authenticated, not authorised to list" and the bucket-level check runs instead.
- Setup wizard: error notices stay visible until the next action and scroll into view instead of fading after five seconds.
- Setup wizard: every Continue button now shows an inline status line (checking… / done / the exact error, including timeouts and HTTP errors) so a slow or failing request never looks like a frozen page.

### Added
- Setup wizard step 3 now probes public readability: it uploads a marker object, fetches its public URL anonymously, deletes it, and warns when the answer is 403. For AWS it explains the "Bucket owner enforced" / no-bucket-policy cause and prints a ready-to-paste `s3:GetObject` bucket policy. Uploads succeeding while every image 403s was previously invisible until a page was viewed.
- Dashboard "Get started" panel: shown once credentials exist but nothing is being pushed (Auto Upload off, or zero files offloaded). It says plainly that connecting a bucket moves nothing by itself, and walks through Enable Auto Upload → push existing media (Bulk Offload in Pro, upgrade link in Lite) → check the Files tab. Disappears on its own once Auto Upload is on and files have landed.

### Changed
- Setup wizard step 2 hides "Browse existing buckets" when the key cannot list buckets, and explains that the bucket name (not the IAM user name) must be typed in.
- Renamed user-facing "CDN" actions and status labels to "Cloud" (e.g. "Remove from Cloud", "Upload to Cloud", "Cloud Status") across admin views, notices, and media library controls to clearly separate cloud storage management from CDN edge caching.
- Standardized admin tab layout, spacing, and card architecture to ensure consistent top margins, typography, and container styling across all tabs.
- Redesigned AJAX action notifications: replaced intrusive top banners (which prepended above the header banner and pushed the layout down) with a sleek, non-intrusive floating toast notification system anchored at the bottom-right of the viewport with smooth stacking, auto-dismiss, and pause-on-hover.



The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-09-19

Multisite hardening pass. Reviewed against a production 11-site network with a 28 GB library.

### Fixed
- Fatal on activation / re-activation when Pro was already active: `activate()` and `activate_new_site()` required `includes/class-queue.php`, which only Pro ships. The same path fired inside `wp_insert_site()`, so creating a new subsite with both plugins active died mid-insert
- Pro wiring (queue cron interval + callback, every Pro AJAX handler) was decided at file-include time with `class_exists( 'Clockwork_Offloader_Pro' )`, so it silently never registered unless Lite's directory happened to sort after Pro's. Now wired on `plugins_loaded` (priority 20), after every plugin file is loaded
- `Clockwork_Offloader_Settings_Helper::update_settings()` was called from three places but never existed (fatal on "Force S3 URLs" / "Switch back", and in the provider migration)
- **Object keys collided across subsites.** Keys were built relative to the current site's uploads dir, which for a subsite already includes `sites/N`, so `www` and `sub` uploading `2024/01/photo.jpg` shared one object (and deleting on one site deleted the other's). Subsite keys are now `sites/{blog_id}/…`, mirroring the uploads directory. Single sites are unaffected
- Auto-offload with delete-after-upload unlinked the original on `add_attachment` — before WordPress generated thumbnails and the `-scaled` copy. Deletion now happens only in `wp_generate_attachment_metadata`, after every size is confirmed offloaded
- The full-resolution original behind a `-scaled` image (`metadata['original_image']`) was never offloaded, tracked, restored or deleted. It is now tracked under the size name `original_image`
- Hard-coded upload URLs inside post content were never rewritten (only attachment-function URLs were), so they 404'd once local files were deleted. `the_content`, `the_excerpt`, widget text and widget blocks are now rewritten at render time, one tracker query per distinct URL set
- `get_settings()` re-read options and regexed `wp-config.php` from disk on every call — and it is called from every attachment-URL filter. Both are now cached per request (and invalidated on every settings write)
- Bulk "add all to queue" called `wp_cache_flush()` every 500 items, emptying a shared Redis/Memcached cache for the whole network
- `wpmu_new_blog` (deprecated since 5.1) replaced with `wp_initialize_site`
- Network activation now creates tables on every existing site, instead of only the main site

### Added
- Network settings page now carries the feature toggles (auto-offload, delete-after-upload, rewrite URLs, batch size, throttle) plus base path and CDN domain. Previously network mode could only hold credentials, so every behaviour fell back to defaults with no way to change it
- `Clockwork_Offloader_Settings_Helper::current_user_can_manage()`: under network mode, destructive and credential-writing operations (delete from server, delete from bucket, remove all from bucket, uninstall data, create table, setup wizard, URL toggles, settings save) require `manage_network_options`. A subsite administrator can no longer wipe shared bucket objects or drop tables
- `clockwork_offloader_s3_key` filter on generated object keys
- `clockwork_offloader_settings` filter on the effective settings
- `clockwork_offloader_loaded` action once Lite/Pro hooks are wired
- `Clockwork_Offloader::get_attachment_files()` / `delete_local_file()` helpers shared with Pro and WP-CLI

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

