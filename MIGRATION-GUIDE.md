# Migration Guide - Updating Existing Plugin Installation

This guide covers how to migrate all file changes from the development version to an existing plugin installation.

## Option 1: Automated Update via GitHub (Recommended)

If your existing installation has version 1.0.0 or 1.0.1, and you create a GitHub release for v1.0.2, WordPress will automatically detect the update.

### Steps:

1. **Create GitHub Release** (if not already done):
   - Go to https://github.com/Jon3r/pjja-booking
   - Click "Releases" → "Create a new release"
   - Tag: `v1.0.2`
   - Title: `Version 1.0.2`
   - Add release notes
   - Click "Publish release"

2. **Update via WordPress Admin**:
   - Go to WordPress Admin → Plugins
   - Look for "PJJA Clubworx integration" plugin
   - Click "Update Now" if update is available
   - OR go to Dashboard → Updates and update from there

3. **Manual Update Check**:
   - Go to WordPress Admin → PJJA Trial Booking
   - Click "Check for Updates" button
   - If update is available, follow the prompts to update

### Advantages:
- ✅ No manual file transfer needed
- ✅ WordPress handles the update process
- ✅ Automatic backup (WordPress creates backups before updates)
- ✅ Minimal downtime

---

## Option 2: Manual File Transfer

If you prefer to manually transfer files or if automated update is not working.

### Files Changed in Recent Updates:

**Core Files:**
- `pjja-trial-booking.php` - Updated version to 1.0.2, added CSV export handler

**Admin Files:**
- `admin/admin-page.php` - Added update check button, enhanced email error display
- `admin/settings-page.php` - Enhanced email diagnostics display

**Include Files:**
- `includes/class-admin-settings.php` - Added SMTP configuration settings
- `includes/class-rest-api.php` - Enhanced email diagnostics, added update check endpoint
- `includes/class-github-updater.php` - Added cache clearing and force update check

**New Files:**
- `UPDATE-PROCESS.md` - Documentation (optional)

### Manual Migration Steps:

#### Step 1: Backup Existing Installation

```bash
# On the server, backup the plugin directory
cp -r /path/to/wp-content/plugins/pjja-booking /path/to/backup/pjja-booking-backup-$(date +%Y%m%d)
```

Or via FTP:
- Download the entire `pjja-booking` plugin folder
- Save it as a backup

#### Step 2: Download Latest Files from GitHub

```bash
# Option A: Clone the repository
git clone https://github.com/Jon3r/pjja-booking.git pjja-booking-new

# Option B: Download ZIP from GitHub
# Go to https://github.com/Jon3r/pjja-booking
# Click "Code" → "Download ZIP"
# Extract the files
```

#### Step 3: Transfer Files to Server

**Via FTP/SFTP:**
1. Connect to your server via FTP/SFTP client
2. Navigate to `wp-content/plugins/pjja-booking/`
3. Upload the following files (overwrite existing):
   - `pjja-trial-booking.php`
   - `admin/admin-page.php`
   - `admin/settings-page.php`
   - `includes/class-admin-settings.php`
   - `includes/class-rest-api.php`
   - `includes/class-github-updater.php`
4. Upload new file:
   - `UPDATE-PROCESS.md` (optional)

**Via SSH:**
```bash
# On your local machine
cd /path/to/pjja-booking

# Transfer files via SCP
scp pjja-trial-booking.php user@server:/path/to/wp-content/plugins/pjja-booking/
scp admin/admin-page.php user@server:/path/to/wp-content/plugins/pjja-booking/admin/
scp admin/settings-page.php user@server:/path/to/wp-content/plugins/pjja-booking/admin/
scp includes/class-admin-settings.php user@server:/path/to/wp-content/plugins/pjja-booking/includes/
scp includes/class-rest-api.php user@server:/path/to/wp-content/plugins/pjja-booking/includes/
scp includes/class-github-updater.php user@server:/path/to/wp-content/plugins/pjja-booking/includes/
```

#### Step 4: Verify File Permissions

```bash
# On the server, ensure proper file permissions
chmod 644 /path/to/wp-content/plugins/pjja-booking/*.php
chmod 644 /path/to/wp-content/plugins/pjja-booking/admin/*.php
chmod 644 /path/to/wp-content/plugins/pjja-booking/includes/*.php
chmod 755 /path/to/wp-content/plugins/pjja-booking/
```

#### Step 5: Clear WordPress Cache

- If using a caching plugin, clear the cache
- Clear browser cache
- Refresh the WordPress admin page

#### Step 6: Verify Installation

1. Go to WordPress Admin → Plugins
2. Check that "PJJA Clubworx integration" shows version 1.0.2
3. Go to PJJA Trial Booking → Settings
4. Verify SMTP settings section is visible
5. Test the "Check for Updates" button
6. Test email sending with SMTP configuration

---

## Option 3: Git Pull (If Existing Installation is a Git Repository)

If your existing installation is already a Git repository:

```bash
# On the server, navigate to plugin directory
cd /path/to/wp-content/plugins/pjja-booking

# Pull latest changes
git pull origin main

# Verify version
grep "Version:" pjja-trial-booking.php
grep "PJJA_BOOKING_VERSION" pjja-trial-booking.php
```

**Note:** Make sure you're on the `main` branch and there are no local changes that would conflict.

---

## Option 4: Complete Plugin Replacement

If you want to replace the entire plugin:

### Steps:

1. **Backup Existing Installation**:
   ```bash
   cp -r /path/to/wp-content/plugins/pjja-booking /path/to/backup/
   ```

2. **Deactivate Plugin** (via WordPress Admin):
   - Go to Plugins → Installed Plugins
   - Deactivate "PJJA Clubworx integration"

3. **Delete Old Plugin**:
   ```bash
   rm -rf /path/to/wp-content/plugins/pjja-booking
   ```

4. **Install New Version**:
   - Download latest from GitHub
   - Upload to `wp-content/plugins/pjja-booking`
   - OR use Git: `git clone https://github.com/Jon3r/pjja-booking.git`

5. **Activate Plugin**:
   - Go to Plugins → Installed Plugins
   - Activate "PJJA Clubworx integration"

6. **Verify Settings**:
   - Check that all settings are preserved (they should be in the database)
   - Re-configure SMTP settings if needed

---

## Post-Migration Checklist

After migrating, verify the following:

- [ ] Plugin version shows 1.0.2 in WordPress admin
- [ ] SMTP settings section is visible in Settings
- [ ] "Check for Updates" button works
- [ ] Email sending works (test with test email button)
- [ ] CSV export works
- [ ] All existing bookings are still visible
- [ ] ClubWorx integration still works
- [ ] GA4 tracking still works

---

## Troubleshooting

### Plugin shows wrong version
- Clear WordPress cache
- Check file permissions
- Verify files were uploaded correctly

### SMTP settings not visible
- Clear browser cache
- Check that `includes/class-admin-settings.php` was updated
- Verify file permissions

### Update check not working
- Verify GitHub repository constants are correct in `pjja-trial-booking.php`
- Check that GitHub release exists for v1.0.2
- Clear WordPress transients: `delete_transient('pjja_github_latest_release')`

### Database settings lost
- Settings are stored in WordPress options table
- They should persist through updates
- If lost, re-configure in Settings page

---

## Rollback Procedure

If something goes wrong, you can rollback:

1. **Deactivate Plugin**:
   - Go to Plugins → Deactivate

2. **Restore Backup**:
   ```bash
   rm -rf /path/to/wp-content/plugins/pjja-booking
   cp -r /path/to/backup/pjja-booking-backup-YYYYMMDD /path/to/wp-content/plugins/pjja-booking
   ```

3. **Activate Plugin**:
   - Go to Plugins → Activate

---

## Recommended Approach

**For Production Sites:**
1. Use Option 1 (Automated Update via GitHub) - safest and easiest
2. Test on staging site first if possible
3. Backup before updating

**For Development Sites:**
1. Use Option 3 (Git Pull) if using Git
2. Or Option 2 (Manual File Transfer) for quick updates

**For Multiple Sites:**
1. Set up the GitHub updater on all sites
2. Create releases on GitHub
3. All sites will automatically detect and offer updates

---

## Need Help?

If you encounter issues during migration:
1. Check the error logs (WordPress debug log, server error log)
2. Verify file permissions
3. Check that all files were transferred correctly
4. Verify database settings are intact
5. Test on a staging site first

