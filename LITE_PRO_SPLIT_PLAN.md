---
name: Split Clockwork Offloader into Lite and Pro
overview: "Split Clockwork Offloader into two plugins: Clockwork Offloader Lite (free, WordPress.org) with core features (auto-offload, settings, dashboard), and Clockwork Offloader Pro (paid addon) with advanced features (bulk operations, queue system, development mode, migration tools)."
todos:
  - id: analyze_current_features
    content: Analyze current plugin structure and identify all features to split between Lite and Pro
    status: pending
  - id: create_lite_structure
    content: Create Lite plugin structure by removing Pro-only features and adding feature gating
    status: pending
    dependencies:
      - analyze_current_features
  - id: implement_auto_offload_restriction
    content: Implement installation date tracking to restrict auto-offload to new files only in Lite
    status: pending
    dependencies:
      - create_lite_structure
  - id: create_pro_addon
    content: Create Pro addon plugin structure that requires Lite and extends functionality
    status: pending
    dependencies:
      - create_lite_structure
  - id: move_pro_features
    content: Move bulk operations, queue system, and development mode to Pro plugin
    status: pending
    dependencies:
      - create_pro_addon
  - id: integrate_migrator
    content: Fully integrate Clockwork Offloader Migrator functionality into Pro plugin (move all migrator classes, views, and assets to Pro)
    status: pending
    dependencies:
      - create_pro_addon
  - id: implement_license_system
    content: Implement EDD Software Licensing integration for Pro plugin (license activation, validation, deactivation, and automatic updates)
    status: pending
    dependencies:
      - create_pro_addon
  - id: add_upgrade_prompts
    content: Add upgrade prompts and nag notices in Lite version for Pro features
    status: pending
    dependencies:
      - create_lite_structure
  - id: update_admin_menus
    content: Update admin menus to show Lite vs Pro features appropriately
    status: pending
    dependencies:
      - create_lite_structure
      - create_pro_addon
  - id: create_migration_script
    content: Create migration script for existing users to transition to Lite/Pro model
    status: pending
    dependencies:
      - create_lite_structure
      - create_pro_addon
  - id: todo-1765858892090-0g6cv4qck
    content: ""
    status: pending
---

# Split Clockwork Offloader into Lite and Pro Versions

## Overview

Transform Clockwork Offloader into a freemium model with:

- **Clockwork Offloader Lite** (free, WordPress.org): Core features for new uploads only
- **Clockwork Offloader Pro** (paid addon): Advanced features including bulk operations, migration, and development tools

## Feature Distribution

### Lite Version (Free)

**Core Features:**

- ✅ Auto-offload new media uploads (after plugin installation)
- ✅ Settings page (configure credentials, bucket, region, provider)
- ✅ Dashboard (view statistics, system status, URL preview)
- ✅ URL rewriting (serve files from S3/CDN)
- ✅ Delete after upload option
- ✅ Multisite support
- ✅ Database tracking
- ✅ wp-config.php credential storage

**Restrictions:**

- ❌ Cannot bulk offload existing media
- ❌ Cannot bulk restore/download files
- ❌ No queue system for large operations
- ❌ No development mode
- ❌ No migration tools (cannot migrate from other plugins)

### Pro Version (Paid Addon)

**Additional Features:**

- ✅ Bulk Upload (offload existing media files)
- ✅ Bulk Download/Restore (restore files from S3)
- ✅ Bulk Tools (all bulk operations)
- ✅ Queue System (background processing for large operations)
- ✅ Development Mode (diagnostic tools, test files)
- ✅ Migration Tools (migrate from other offload plugins - fully integrated from migrator plugin)
- ✅ Advanced statistics and reporting

## Implementation Strategy

### 1. Codebase Structure

**Option: Pro as Addon Plugin**

- Lite remains the base plugin (`clockwork-offloader/`)
- Pro is a separate addon plugin (`clockwork-offloader-pro/`)
- Pro checks if Lite is active before loading
- Pro extends Lite's functionality using hooks and filters

**Benefits:**

- Clean separation of code
- Lite can be submitted to WordPress.org independently
- Pro can be distributed separately (own site, marketplace, etc.)
- Users can upgrade by installing Pro addon

### 2. File Structure Changes

```
clockwork-offloader/ (Lite - WordPress.org)
├── clockwork-offloader.php (main file)
├── includes/
│   ├── class-admin.php (Lite admin - no bulk operations)
│   ├── class-s3-service.php (shared)
│   ├── class-settings-helper.php (shared)
│   ├── class-offload-tracker.php (shared)
│   ├── class-url-rewriter.php (shared)
│   └── class-lite-restrictions.php (NEW - feature gating)
├── admin/
│   ├── views/
│   │   ├── dashboard.php (Lite version)
│   │   ├── settings.php (Lite version)
│   │   └── setup-wizard.php (Lite version)
│   └── ...
└── readme.txt

clockwork-offloader-pro/ (Pro Addon)
├── clockwork-offloader-pro.php (main file - checks for Lite)
├── includes/
│   ├── class-pro-admin.php (Pro admin features)
│   ├── class-bulk-offloader.php (moved from Lite)
│   ├── class-queue.php (moved from Lite)
│   ├── class-development-helper.php (moved from Lite)
│   ├── class-pro-license.php (NEW - license management)
│   ├── class-migrator.php (from migrator plugin)
│   ├── class-migration-processor.php (from migrator plugin)
│   ├── class-wp-offload-detector.php (from migrator plugin)
│   ├── class-acowebs-detector.php (from migrator plugin)
│   └── class-s3-verifier.php (from migrator plugin)
├── admin/
│   ├── views/
│   │   ├── bulk-offload.php (Pro only)
│   │   ├── bulk-download.php (Pro only)
│   │   ├── bulk-tools.php (Pro only)
│   │   ├── development.php (Pro only)
│   │   └── migration-wizard.php (Pro only - from migrator plugin)
│   ├── css/
│   │   └── admin.css (merged from migrator)
│   └── js/
│       └── admin.js (merged from migrator)
└── readme.txt
```

### 3. Feature Gating Implementation

**In Lite Plugin:**

- Create `class-lite-restrictions.php` to check feature availability
- Add capability checks before showing bulk operation UI
- Hide bulk operation menu items in Lite
- Show upgrade prompts/nag notices for Pro features

**Example Implementation:**

```php
// In class-lite-restrictions.php
class Clockwork_Offloader_Lite_Restrictions {
    public static function is_pro_active() {
        return class_exists( 'Clockwork_Offloader_Pro' );
    }
    
    public static function can_bulk_offload() {
        return self::is_pro_active();
    }
    
    public static function show_upgrade_notice() {
        if ( ! self::is_pro_active() ) {
            // Show upgrade notice
        }
    }
}
```

### 4. Migration from Current Plugin

**Steps:**

1. Create new `clockwork-offloader-lite` directory (or rename current)
2. Remove Pro-only features from Lite:

   - Remove bulk operation classes
   - Remove queue system (or make it Pro-only)
   - Remove development mode
   - Remove bulk operation admin views

3. Add feature gating checks
4. Add upgrade notices/prompts
5. Create Pro addon plugin structure
6. Move Pro features to Pro plugin
7. Integrate migrator functionality into Pro

### 5. Auto-Offload Restriction (Lite)

**Implementation:**

- Track plugin installation date
- Only auto-offload files uploaded AFTER installation
- Store installation timestamp: `cloudbound_offloader_installed_date`
- Check file upload date vs installation date in `handle_new_attachment()`

**Code Changes:**

```php
// In clockwork-offloader.php activation
register_activation_hook( __FILE__, function() {
    if ( ! get_option( 'cloudbound_offloader_installed_date' ) ) {
        add_option( 'cloudbound_offloader_installed_date', time() );
    }
});

// In handle_new_attachment()
$installed_date = get_option( 'cloudbound_offloader_installed_date' );
$attachment_date = get_post_time( 'U', false, $attachment_id );
if ( $attachment_date < $installed_date ) {
    return; // Don't auto-offload old files in Lite
}
```

### 6. Admin Menu Changes

**Lite Menu:**

- Clockwork Offloader
  - Dashboard
  - Settings
  - (Upgrade to Pro) ← Link to Pro purchase

**Pro Menu (when active):**

- Clockwork Offloader
  - Dashboard
  - Settings
  - Bulk Offload ← Pro
  - Bulk Tools ← Pro
  - Development Mode ← Pro
  - Migration ← Pro (integrated from migrator plugin)

### 7. Migrator Plugin Integration

**Current:** `clockwork-offloader-migrator` (separate plugin)

**New:** Fully integrated into Pro plugin - Migration is a Pro-only feature

**Integration Strategy:**

1. **Move all migrator functionality to Pro plugin:**

   - Move all migrator classes to `clockwork-offloader-pro/includes/`
   - Move migrator admin views to `clockwork-offloader-pro/admin/views/`
   - Move migrator CSS/JS to Pro plugin
   - Add migration wizard to Pro admin menu

2. **Migration becomes Pro-only:**

   - Migration tools require Pro license
   - Lite users cannot migrate from other plugins
   - Pro users get migration as part of Pro features

3. **Backward Compatibility:**

   - Existing migrator plugin installations can remain active
   - Migrator plugin can check for Pro and redirect users
   - Or migrator plugin becomes a "Pro required" notice plugin
   - Eventually deprecate standalone migrator plugin

**Files to Move from Migrator to Pro:**

- `includes/class-migrator.php` → `clockwork-offloader-pro/includes/`
- `includes/class-migration-processor.php` → `clockwork-offloader-pro/includes/`
- `includes/class-wp-offload-detector.php` → `clockwork-offloader-pro/includes/`
- `includes/class-acowebs-detector.php` → `clockwork-offloader-pro/includes/`
- `includes/class-s3-verifier.php` → `clockwork-offloader-pro/includes/`
- `admin/views/migration-wizard.php` → `clockwork-offloader-pro/admin/views/`
- `admin/css/admin.css` → Merge into Pro CSS
- `admin/js/admin.js` → Merge into Pro JS

### 8. License Management (Pro) - EDD Software Licensing Integration

**Implementation Using Easy Digital Downloads (EDD) Software Licensing:**

- Integrate with EDD Software Licensing extension (installed on your site)
- Use EDD API for license validation, activation, and deactivation
- Store license key in WordPress database: `cloudbound_offloader_pro_license`
- Manual license activation via Pro settings page
- Automatic plugin updates through EDD Software Licensing update system
- Show license status in Pro admin (active, inactive, expired, invalid)

**EDD Integration Components:**

1. **License Activation:**

   - User enters license key in Pro settings page
   - Pro plugin calls EDD API: `edd_sl_activate_license()`
   - Store license key and status in database
   - Show success/error messages

2. **License Validation:**

   - Validate license on each admin page load (cached for performance)
   - Check license status: `edd_sl_check_license()`
   - Disable Pro features if license invalid/expired
   - Show license renewal notices if expired

3. **License Deactivation:**

   - Allow users to deactivate license (for site transfers)
   - Call EDD API: `edd_sl_deactivate_license()`
   - Remove license from database

4. **Automatic Updates:**

   - Integrate EDD Software Licensing update system
   - Pro plugin checks for updates via EDD API
   - Automatic update notifications in WordPress admin
   - Secure update delivery through EDD

**Files to Create:**

- `includes/class-pro-license.php` - EDD license management
- `includes/class-edd-updater.php` - EDD update system integration
- `admin/views/license-settings.php` - License activation UI

**EDD API Endpoints Required:**

- License activation endpoint
- License validation endpoint
- License deactivation endpoint
- Update check endpoint
- Download URL endpoint (for updates)

**Database Storage:**

- `cloudbound_offloader_pro_license` - License key
- `cloudbound_offloader_pro_license_status` - License status (active, inactive, expired, etc.)
- `cloudbound_offloader_pro_license_expires` - License expiration date
- `cloudbound_offloader_pro_license_site_count` - Number of sites using license

**Security Considerations:**

- All EDD API calls use HTTPS
- License keys stored securely in database
- Nonce verification for license activation/deactivation
- Capability checks for license management
- Rate limiting for API calls to prevent abuse

**EDD Setup Requirements (On Your Site):**

1. **Install EDD + Software Licensing Extension:**

   - Easy Digital Downloads plugin
   - Software Licensing extension (paid addon)
   - Configure store settings

2. **Create Pro Plugin Product:**

   - Add "Clockwork Offloader Pro" as downloadable product
   - Set up pricing/license tiers
   - Configure license settings (lifetime, annual, etc.)

3. **Configure API Settings:**

   - Set up API endpoint URL (e.g., `https://yourstore.com/edd-sl/`)
   - Configure API authentication
   - Set up update server URL

4. **Configure Update System:**

   - Set up automatic update delivery
   - Configure version checking
   - Set up secure download URLs

**Pro Plugin Configuration:**

- Store EDD store URL in Pro plugin: `CLOUDBOUND_OFFLOADER_PRO_STORE_URL`
- Store item name/ID for license validation
- Configure update check frequency
- Set up license activation endpoint

### 9. Upgrade Flow

**User Experience:**

1. User installs Lite from WordPress.org
2. Uses auto-offload for new files
3. Sees upgrade prompts when trying to access Pro features
4. Purchases Pro license from your EDD-powered store
5. Receives license key via email (from EDD)
6. Installs Pro addon plugin
7. Enters license key in Pro settings page
8. License activates via EDD API
9. Pro features unlock
10. Automatic updates delivered through EDD Software Licensing

**Upgrade Prompts:**

- Nag notices in admin
- Disabled buttons with "Upgrade to Pro" tooltips
- Upgrade links in menu
- Dashboard upgrade CTA

### 10. Backward Compatibility

**For Existing Users:**

- Current plugin becomes "Pro" version
- Create migration script to:
  - Detect existing installations
  - Offer to "downgrade" to Lite (remove Pro features)
  - Or keep as Pro (if they have license)
- Preserve all settings and database records

## Files to Modify/Create

### Lite Plugin

- `clockwork-offloader.php` - Add installation date tracking
- `includes/class-admin.php` - Remove bulk operation handlers, add feature checks
- `includes/class-lite-restrictions.php` - NEW - Feature gating
- `admin/views/dashboard.php` - Add upgrade prompts
- `admin/views/settings.php` - Remove Pro-only settings
- Remove: `class-bulk-offloader.php`, `class-queue.php`, `class-development-helper.php`
- Remove: Bulk operation admin views

### Pro Plugin (New)

- `clockwork-offloader-pro.php` - Main plugin file, checks for Lite
- `includes/class-pro-admin.php` - Pro admin features
- `includes/class-pro-license.php` - EDD Software Licensing integration
- `includes/class-edd-updater.php` - EDD automatic update system
- Move bulk operation classes from Lite
- Move development helper from Lite
- Integrate migrator functionality
- Create Pro admin views
- `admin/views/license-settings.php` - License activation/deactivation UI

## Testing Checklist

- [ ] Lite auto-offloads only new files (after installation)
- [ ] Lite blocks bulk operations (shows upgrade prompt)
- [ ] Pro unlocks all features when active
- [ ] EDD license activation works (manual via settings)
- [ ] EDD license validation works (cached, checks on admin load)
- [ ] EDD license deactivation works
- [ ] EDD automatic updates work (Pro plugin updates via EDD)
- [ ] License status displays correctly in admin
- [ ] Pro features disabled when license invalid/expired
- [ ] Upgrade flow is smooth
- [ ] Backward compatibility for existing users
- [ ] Multisite support works in both versions
- [ ] Settings migration works
- [ ] Database records preserved

## Versioning

- **Lite**: Start at 1.0.0 (WordPress.org)
- **Pro**: Start at 1.0.0 (separate versioning)
- Both use semantic versioning independently

## Safety Measures & Revert Plan

### ✅ Pre-Split Checkpoint Created

**Git Commits:**

- `clockwork-offloader`: Committed all changes with message "Pre-split checkpoint: Working version before Lite/Pro split - v1.0.0"
- `clockwork-offloader-migrator`: Committed all changes with message "Pre-split checkpoint: Working version before integration into Pro - v1.0.0"

**Git Tags Created:**

- `clockwork-offloader`: Tagged as `v1.0.0-pre-split` with message "Working version before Lite/Pro split - Safe to revert to this point"
- `clockwork-offloader-migrator`: Tagged as `v1.0.0-pre-split` with message "Working version before integration into Pro - Safe to revert to this point"

### How to Revert if Something Goes Wrong

**Option 1: Reset to Pre-Split Tag (Recommended)**

```bash
# For main plugin
cd "/Users/aaronr/Local Sites/cdn/app/public/wp-content/plugins/clockwork-offloader"
git reset --hard v1.0.0-pre-split

# For migrator plugin
cd "/Users/aaronr/Local Sites/cdn/app/public/wp-content/plugins/clockwork-offloader-migrator"
git reset --hard v1.0.0-pre-split
```

**Option 2: Reset to Pre-Split Commit**

```bash
# For main plugin
cd "/Users/aaronr/Local Sites/cdn/app/public/wp-content/plugins/clockwork-offloader"
git reset --hard eda439f  # Pre-split commit hash

# For migrator plugin
cd "/Users/aaronr/Local Sites/cdn/app/public/wp-content/plugins/clockwork-offloader-migrator"
git reset --hard 6fd4397  # Pre-split commit hash
```

**Option 3: Create New Branch for Split Work**

```bash
# Create a new branch for the split work
cd "/Users/aaronr/Local Sites/cdn/app/public/wp-content/plugins/clockwork-offloader"
git checkout -b feature/lite-pro-split

# Work on the split in this branch
# If it fails, just switch back to main:
git checkout main
```

### Additional Safety Measures

1. **Create Backup Zip Files**

   - Before starting, create zip files of current working versions
   - Store them in a safe location outside the plugin directories

2. **Test in Staging First**

   - Consider testing the split in a staging environment first
   - Or create a copy of the plugins to test the split

3. **Incremental Approach**

   - We can do this in small, testable steps
   - Each step can be committed separately
   - If a step fails, we can revert just that step

4. **Keep Original Plugins Intact**

   - We can work in a separate directory structure initially
   - Only merge back when everything is tested and working

### Recommended Approach

**Phase 1: Create Lite Version (Non-Destructive)**

1. Create a copy of current plugin as `clockwork-offloader-lite`
2. Remove Pro features from the copy
3. Test Lite version thoroughly
4. Only proceed if Lite works perfectly

**Phase 2: Create Pro Addon (Non-Destructive)**

1. Create new `clockwork-offloader-pro` directory
2. Move Pro features from original to Pro addon
3. Test Pro addon with Lite
4. Only proceed if Pro works perfectly

**Phase 3: Integration & Cleanup**

1. Once both are tested, integrate into main codebase
2. Update documentation
3. Create migration scripts

This way, the original working plugins remain untouched until we're confident everything works.