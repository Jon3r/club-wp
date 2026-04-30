# GitHub Repository Setup Instructions

## Step 1: Create GitHub Repository

1. Go to [GitHub.com](https://github.com) and sign in
2. Click the "+" icon in the top right → "New repository"
3. Repository name: `pjja-booking` (or your preferred name)
4. Description: "WordPress plugin for trial class booking with ClubWorx integration"
5. Choose Public or Private
6. **DO NOT** initialize with README, .gitignore, or license (we already have these)
7. Click "Create repository"

## Step 2: Update Plugin with Your GitHub Info

✅ **Already Completed!** The plugin is configured with:

- GitHub Username: `Jon3r`
- Repository: `pjja-booking`

The configuration is in `pjja-trial-booking.php` (lines 30-31):

```php
define('PJJA_BOOKING_GITHUB_USERNAME', 'Jon3r');
define('PJJA_BOOKING_GITHUB_REPO', 'pjja-booking');
```

## Step 3: Connect Local Repository to GitHub

✅ **Already Completed!** Your repository is connected and pushed to:

- Repository: https://github.com/Jon3r/pjja-booking
- Remote: `origin` → `https://github.com/Jon3r/pjja-booking.git`

If you need to reconnect in the future, use:

```bash
cd /Applications/MAMP/htdocs/parra-form/wp-content/plugins/pjja-booking
git remote add origin https://github.com/Jon3r/pjja-booking.git
git branch -M main
git push -u origin main
```

## Step 4: Create Initial Release

✅ **Tag Created!** The v1.0.0 tag has been created and pushed to GitHub.

To create a formal release on GitHub:

1. Go to https://github.com/Jon3r/pjja-booking/releases
2. Click "Draft a new release"
3. Select tag: `v1.0.0` (already exists)
4. Release title: `Version 1.0.0`
5. Description:

   ```
   Initial release of PJJA Trial Booking Plugin

   Features:
   - GitHub automatic update integration
   - Email testing and logging functionality
   - CSV export for bookings
   - ClubWorx API integration
   - GA4 tracking and attribution
   ```

6. Click "Publish release"

## Step 5: Verify Updates Work

1. In WordPress admin, go to **Plugins** page
2. You should see update notifications when new releases are available
3. Updates will appear automatically when you create new releases on GitHub

## How Updates Work

- The plugin checks GitHub Releases API for new versions
- When you create a new release tag (e.g., `v1.0.1`), WordPress will detect it
- Updates appear in **Dashboard → Updates** and **Plugins** page
- Version numbers are extracted from release tags (remove 'v' prefix if present)

## Creating New Releases

1. Make your code changes
2. Commit and push to GitHub:
   ```bash
   git add .
   git commit -m "Description of changes"
   git push
   ```
3. Create a new release on GitHub:
   - Tag: `v1.0.1` (increment version)
   - WordPress will automatically detect the update

## Notes

- The plugin caches release info for 1 hour to reduce API calls
- Updates only work if GitHub username/repo are configured correctly
- Private repositories may require authentication (not implemented in current version)
