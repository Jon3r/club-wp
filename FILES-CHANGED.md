# Files Changed - Version 1.0.2

This document lists all files that have been modified or added in version 1.0.2.

## Modified Files

### Core Plugin File
- **`pjja-trial-booking.php`**
  - Updated version to 1.0.2
  - Added CSV export handler method
  - Integrated GitHub updater

### Admin Files
- **`admin/admin-page.php`**
  - Added "Check for Updates" button
  - Enhanced email error display with modals
  - Added JavaScript for update checking
  - Enhanced email diagnostics display

- **`admin/settings-page.php`**
  - Enhanced email diagnostics in test email results
  - Added plugin SMTP status display

### Include Files
- **`includes/class-admin-settings.php`**
  - Added SMTP configuration section
  - Added SMTP settings fields (host, port, encryption, username, password, from email, from name)
  - Added PHPMailer configuration method
  - Enhanced settings sanitization

- **`includes/class-rest-api.php`**
  - Added `/check-updates` REST API endpoint
  - Enhanced email diagnostics to show plugin SMTP status
  - Improved troubleshooting suggestions
  - Enhanced test email functionality

- **`includes/class-github-updater.php`**
  - Added cache clearing method
  - Added force update check method
  - Enhanced update detection

## New Files

- **`UPDATE-PROCESS.md`** - Documentation for update process
- **`MIGRATION-GUIDE.md`** - Complete migration guide

## Files That Have NOT Changed

These files remain the same and do not need to be updated:
- Database structure (no migrations needed)
- Asset files (CSS, JS) - no changes
- Other include files not listed above
- Language files

## Quick File List for Manual Transfer

If you need to manually transfer files, these are the files that changed:

```
pjja-trial-booking.php
admin/admin-page.php
admin/settings-page.php
includes/class-admin-settings.php
includes/class-rest-api.php
includes/class-github-updater.php
UPDATE-PROCESS.md (optional)
MIGRATION-GUIDE.md (optional)
```

## Database Changes

**No database changes required.** All settings are stored in WordPress options and will persist through updates.

## Settings Preserved

The following settings will be preserved during update:
- GA4 settings
- ClubWorx API settings
- Email notification settings
- SMTP settings (if configured)
- All plugin options

