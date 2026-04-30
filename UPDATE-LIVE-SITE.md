# How to Update Live Site with New Changes

This guide covers updating your live WordPress site with the new email improvements and GitHub token support.

## Current Situation

- **Live site**: Has old version (likely 1.1.0 or earlier)
- **New changes**: 
  - HTML email notifications (v1.2.0)
  - GitHub token support for private repos
- **Repository**: Private (requires token to access)

## Option 1: Manual File Upload (Recommended for First Update)

Since your live site doesn't have GitHub token support yet, manually upload the files first.

### Step 1: Backup Your Live Site

**Via FTP/SFTP:**
1. Download the entire `pjja-booking` plugin folder from your server
2. Save it as a backup (e.g., `pjja-booking-backup-2024-12-15`)

**Via SSH:**
```bash
# On your server
cd /path/to/wp-content/plugins
cp -r pjja-booking pjja-booking-backup-$(date +%Y%m%d)
```

### Step 2: Prepare Files to Upload

**Files that need to be updated:**

1. **Core Plugin File:**
   - `pjja-trial-booking.php` (version updated to 1.2.0)

2. **Include Files:**
   - `includes/class-rest-api.php` (HTML email templates + prospect notifications)
   - `includes/class-github-updater.php` (GitHub token authentication)
   - `includes/class-admin-settings.php` (GitHub token settings field)

3. **Optional (but recommended):**
   - `RELEASE-v1.2.0.md` (release notes)
   - `CHANGELOG.md` (updated changelog)

### Step 3: Upload Files to Server

**Via FTP/SFTP:**
1. Connect to your server
2. Navigate to `wp-content/plugins/pjja-booking/`
3. Upload and overwrite these files:
   - `pjja-trial-booking.php`
   - `includes/class-rest-api.php`
   - `includes/class-github-updater.php`
   - `includes/class-admin-settings.php`

**Via SSH/SCP:**
```bash
# From your local machine (where you have the updated files)
cd /Users/andyjones/Desktop/Web/pjja-booking

# Upload files to server
scp pjja-trial-booking.php user@yourserver.com:/path/to/wp-content/plugins/pjja-booking/
scp includes/class-rest-api.php user@yourserver.com:/path/to/wp-content/plugins/pjja-booking/includes/
scp includes/class-github-updater.php user@yourserver.com:/path/to/wp-content/plugins/pjja-booking/includes/
scp includes/class-admin-settings.php user@yourserver.com:/path/to/wp-content/plugins/pjja-booking/includes/
```

### Step 4: Verify File Permissions

```bash
# On your server
chmod 644 /path/to/wp-content/plugins/pjja-booking/*.php
chmod 644 /path/to/wp-content/plugins/pjja-booking/includes/*.php
chmod 755 /path/to/wp-content/plugins/pjja-booking/
```

### Step 5: Clear Caches

1. **WordPress Cache**: Clear any caching plugins
2. **Browser Cache**: Hard refresh (Ctrl+F5 or Cmd+Shift+R)
3. **Object Cache**: If using Redis/Memcached, flush it

### Step 6: Verify Installation

1. Go to **WordPress Admin → Plugins**
2. Check that "PJJA Clubworx integration" shows **version 1.2.0**
3. Go to **Trial Booking → Settings**
4. Scroll down - you should see **"GitHub Update Settings"** section
5. Test email notifications by submitting a booking

### Step 7: Configure GitHub Token (Important!)

Now that the plugin has token support:

1. Go to **Trial Booking → Settings**
2. Scroll to **"GitHub Update Settings"** section
3. Create a GitHub Personal Access Token:
   - Go to: https://github.com/settings/tokens/new
   - Name: "PJJA Booking Plugin Updates"
   - Scope: Check `repo` (Full control of private repositories)
   - Click "Generate token"
   - Copy the token
4. Paste the token in the **"GitHub Personal Access Token"** field
5. Click **"Save Changes"**

### Step 8: Test Update Detection

1. Go to **Trial Booking → Dashboard**
2. Click **"Check for Updates"** button
3. It should now be able to access your private repository
4. Future updates can be done automatically via WordPress!

---

## Option 2: Use Git (If Live Site is a Git Repository)

If your live site's plugin directory is already a Git repository:

```bash
# On your server
cd /path/to/wp-content/plugins/pjja-booking

# Pull latest changes
git pull origin main

# Verify version
grep "Version:" pjja-trial-booking.php
grep "PJJA_BOOKING_VERSION" pjja-trial-booking.php
```

Then follow **Step 7** and **Step 8** above to configure the GitHub token.

---

## Option 3: Temporary Public Repo (Not Recommended)

If you want to use automatic updates immediately:

1. **Temporarily make repo public:**
   - Go to GitHub repo settings
   - Change visibility to Public
   
2. **Create GitHub release v1.2.0** (if not already done)

3. **Update via WordPress:**
   - Go to Plugins → Update Now
   - Or use "Check for Updates" button

4. **Add GitHub token** (Step 7 above)

5. **Make repo private again:**
   - Go back to repo settings
   - Change visibility back to Private

**Note:** This exposes your code temporarily. Only do this if you're comfortable with that.

---

## After First Manual Update

Once you've manually updated and added the GitHub token, **future updates will be automatic**:

1. Commit changes to GitHub
2. Create a GitHub release
3. WordPress will detect the update automatically
4. Click "Update Now" in WordPress admin

---

## Troubleshooting

### Version Not Updating

- Clear WordPress cache
- Check file permissions
- Verify files were uploaded correctly
- Check WordPress debug log for errors

### GitHub Token Not Working

- Verify token has `repo` scope
- Check token hasn't expired
- Try regenerating the token
- Check WordPress debug log for API errors

### Email Notifications Not Working

- Go to Settings → Email Notifications
- Ensure "Enable Email Notifications" is checked
- Test with "Test Email" button
- Check email log in Dashboard

### Can't Access Private Repo

- Verify GitHub token is saved in settings
- Check token has correct permissions
- Try "Check for Updates" button to test

---

## Quick Checklist

- [ ] Backed up live site
- [ ] Uploaded updated files
- [ ] Verified version shows 1.2.0
- [ ] Created GitHub Personal Access Token
- [ ] Added token to WordPress settings
- [ ] Tested "Check for Updates" button
- [ ] Tested email notifications
- [ ] Cleared all caches

---

## Need Help?

If you encounter issues:
1. Check WordPress debug log: `wp-content/debug.log`
2. Check server error logs
3. Verify file permissions
4. Test with a staging site first if possible

