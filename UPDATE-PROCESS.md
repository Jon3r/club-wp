# Plugin Update Process

## How Plugin Updates Work

The plugin uses GitHub Releases to provide automatic updates. Here's how it works:

1. **Version in Plugin File**: The plugin version is defined in two places:
   - Plugin header: `Version: 1.0.1` (line 6 in `pjja-trial-booking.php`)
   - Plugin constant: `define('PJJA_BOOKING_VERSION', '1.0.1');` (line 23)

2. **GitHub Release**: WordPress checks GitHub for the latest release tag (e.g., `v1.0.1`)

3. **Version Comparison**: If the GitHub release version is higher than the installed version, WordPress shows an update notification

4. **Update Installation**: Users can update the plugin from the WordPress admin (Plugins page or Dashboard → Updates)

## Steps to Release a New Version

### 1. Update Plugin Version

Update the version in **both** places in `pjja-trial-booking.php`:

```php
/**
 * Plugin Name: PJJA Clubworx integration
 * ...
 * Version: 1.0.1  // ← Update this
 * ...
 */

// Plugin constants
define('PJJA_BOOKING_VERSION', '1.0.1'); // ← Update this
```

### 2. Commit and Push Changes

```bash
git add pjja-trial-booking.php
git commit -m "Bump version to 1.0.1"
git push origin main
```

### 3. Create GitHub Release

1. Go to your GitHub repository: https://github.com/Jon3r/pjja-booking
2. Click **"Releases"** → **"Create a new release"**
3. **Tag version**: Enter `v1.0.1` (must match plugin version, with `v` prefix)
4. **Release title**: Enter `Version 1.0.1` or a descriptive title
5. **Description**: Add release notes describing the changes
6. Click **"Publish release"**

### 4. Test Update Detection

1. Go to WordPress admin → **PJJA Trial Booking** page
2. Click **"Check for Updates"** button
3. You should see the update notification if the GitHub release version is higher than the installed version

### 5. Update Existing Installations

After creating the GitHub release, existing installations will:
- Automatically check for updates (WordPress checks every 12 hours)
- Show update notification in **Dashboard → Updates** and **Plugins** page
- Allow users to update with one click

## Manual Update Check

Users can manually check for updates:
1. Click **"Check for Updates"** button on the plugin admin page
2. This clears the cache and immediately checks GitHub for the latest release

## Troubleshooting

### Update Not Showing Up

1. **Check GitHub Release**: Ensure a release exists with tag `v1.0.1` (or higher)
2. **Check Version Numbers**: The GitHub release tag must be higher than the installed version
3. **Clear Cache**: Click "Check for Updates" button to clear cache
4. **Wait for Automatic Check**: WordPress checks every 12 hours automatically
5. **Check GitHub Configuration**: Verify `PJJA_BOOKING_GITHUB_USERNAME` and `PJJA_BOOKING_GITHUB_REPO` constants are correct

### Version Mismatch

If the plugin header version and constant don't match, WordPress may not detect updates correctly. Always update both:
- Plugin header `Version: X.X.X`
- Constant `define('PJJA_BOOKING_VERSION', 'X.X.X');`

### GitHub Release Not Found

If the updater can't find the release:
1. Check that the release tag matches the version (with `v` prefix): `v1.0.1`
2. Verify the GitHub username and repository name are correct in the plugin constants
3. Check GitHub API rate limits (60 requests/hour for unauthenticated requests)
4. Check server can access GitHub API (some servers block external API calls)

## Important Notes

- **Version Format**: Use semantic versioning (e.g., `1.0.1`, `1.1.0`, `2.0.0`)
- **GitHub Tag Format**: Always prefix with `v` (e.g., `v1.0.1`)
- **Cache**: Update checks are cached for 1 hour. Use "Check for Updates" button to force refresh
- **WordPress Cache**: WordPress caches update checks for ~12 hours. Clearing plugin cache also clears WordPress update cache

## Example Release Workflow

```bash
# 1. Update version in plugin file
# Edit pjja-trial-booking.php: Version: 1.0.1 and define('PJJA_BOOKING_VERSION', '1.0.1');

# 2. Commit changes
git add pjja-trial-booking.php
git commit -m "Bump version to 1.0.1 - Add update check functionality"

# 3. Push to GitHub
git push origin main

# 4. Create GitHub release via web interface:
# - Tag: v1.0.1
# - Title: Version 1.0.1
# - Description: Added manual update check button and improved update detection

# 5. Test update detection
# - Go to WordPress admin → PJJA Trial Booking
# - Click "Check for Updates"
# - Verify update is detected (if version on GitHub is higher)
```

## Current Version

- **Plugin Version**: 1.0.1
- **GitHub Repository**: https://github.com/Jon3r/pjja-booking
- **Latest Release**: Check GitHub Releases page

