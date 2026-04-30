# Manual Plugin Update Guide - Quick Reference

This is a quick guide for manually updating the plugin on a live WordPress site.

## 🚀 Quick Steps (Recommended Method)

### 1. Backup First! ⚠️

**Via FTP/SFTP:**

- Download the entire `pjja-booking` folder from `wp-content/plugins/`
- Save it as a backup

**Via SSH:**

```bash
cd /path/to/wp-content/plugins
cp -r pjja-booking pjja-booking-backup-$(date +%Y%m%d)
```

### 2. Get Updated Files

**Option A: Download from GitHub**

1. Go to https://github.com/Jon3r/pjja-booking/releases
2. Download the latest release ZIP (e.g., `v1.2.1`)
3. Extract the files

**Option B: Use Git (if you have access)**

```bash
git clone https://github.com/Jon3r/pjja-booking.git
cd pjja-booking
git checkout v1.2.1  # or latest tag
```

### 3. Upload Files to Server

**Files to Update for v1.2.1:**

- `pjja-trial-booking.php` (version updated)
- `assets/js/script.js` (booking data fix)
- `includes/class-rest-api.php` (email formatting + update detection)
- `includes/class-github-updater.php` (update detection improvements)

**Via FTP/SFTP:**

1. Connect to your server
2. Navigate to `wp-content/plugins/pjja-booking/`
3. Upload and overwrite the files listed above

**Via SSH/SCP:**

```bash
# From your local machine
cd /path/to/pjja-booking

# Upload files
scp pjja-trial-booking.php user@server:/path/to/wp-content/plugins/pjja-booking/
scp assets/js/script.js user@server:/path/to/wp-content/plugins/pjja-booking/assets/js/
scp includes/class-rest-api.php user@server:/path/to/wp-content/plugins/pjja-booking/includes/
scp includes/class-github-updater.php user@server:/path/to/wp-content/plugins/pjja-booking/includes/
```

### 4. Set File Permissions

```bash
# On your server
chmod 644 /path/to/wp-content/plugins/pjja-booking/*.php
chmod 644 /path/to/wp-content/plugins/pjja-booking/includes/*.php
chmod 644 /path/to/wp-content/plugins/pjja-booking/assets/js/*.js
chmod 755 /path/to/wp-content/plugins/pjja-booking/
```

### 5. Clear Caches

1. **WordPress Cache**: Clear any caching plugins (WP Super Cache, W3 Total Cache, etc.)
2. **Browser Cache**: Hard refresh (Ctrl+F5 or Cmd+Shift+R)
3. **Object Cache**: If using Redis/Memcached, flush it

### 6. Verify Update

1. Go to **WordPress Admin → Plugins**
2. Check that "PJJA Clubworx integration" shows **version 1.2.1**
3. Go to **Trial Booking → Dashboard**
4. Click **"Check for Updates"** to verify update detection works
5. Test a booking to verify email includes program details

---

## 📋 Alternative: Complete Plugin Replacement

If you prefer to replace the entire plugin:

### Step 1: Deactivate Plugin

- Go to **Plugins → Installed Plugins**
- Deactivate "PJJA Clubworx integration"

### Step 2: Backup & Delete

```bash
# Backup
cp -r /path/to/wp-content/plugins/pjja-booking /path/to/backup/

# Delete old version
rm -rf /path/to/wp-content/plugins/pjja-booking
```

### Step 3: Upload New Version

- Upload the entire plugin folder from the latest GitHub release
- OR use Git: `git clone https://github.com/Jon3r/pjja-booking.git`

### Step 4: Activate Plugin

- Go to **Plugins → Installed Plugins**
- Activate "PJJA Clubworx integration"

**Note:** Settings are stored in the database, so they should be preserved.

---

## 🔍 Verification Checklist

After updating, verify:

- [ ] Plugin version shows **1.2.1** in WordPress admin
- [ ] "Check for Updates" button works
- [ ] Booking emails include program details (not "N/A")
- [ ] No PHP errors in WordPress debug log
- [ ] All features work as expected

---

## 🆘 Troubleshooting

### Version Not Updating

- Clear WordPress cache
- Check file permissions (644 for files, 755 for directories)
- Verify files were uploaded correctly
- Check `wp-content/debug.log` for errors

### Files Not Uploading

- Check FTP/SFTP connection
- Verify file permissions on server
- Ensure you have write access to the plugin directory

### Plugin Breaks After Update

- Restore from backup
- Check WordPress debug log
- Verify PHP version compatibility (requires PHP 7.4+)

### Can't Access Server

- Use WordPress admin file manager plugin
- Contact your hosting provider
- Use cPanel File Manager if available

---

## 📞 Need Help?

1. Check WordPress debug log: `wp-content/debug.log`
2. Check server error logs
3. Verify file permissions
4. Test on staging site first if possible

---

## 🎯 For Future Updates

Once you've manually updated to v1.2.1, **future updates can be automatic**:

1. Ensure GitHub token is configured in **Settings → GitHub Update Settings**
2. Create a GitHub release
3. WordPress will detect the update automatically
4. Click "Update Now" in WordPress admin

No more manual file transfers needed! 🎉
