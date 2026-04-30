# How to Create a GitHub Release

The plugin updater requires a **GitHub Release** (not just a tag) to detect updates. Follow these steps:

## Quick Steps

1. **Go to your GitHub repository:**
   - https://github.com/Jon3r/pjja-booking/releases

2. **Click "Draft a new release"** (or "Create a new release" if you see it)

3. **Fill in the release form:**
   - **Tag version:** Select `v1.1.0` from the dropdown (or type it if it doesn't appear)
   - **Release title:** `Version 1.1.0 - Form Design Customization & Theme Integration`
   - **Description:** Copy the content from `RELEASE-v1.1.0.md` file in this repository
   - **Target:** Should be `main` branch

4. **Click "Publish release"**

## Alternative: Using GitHub CLI

If you have GitHub CLI installed:

```bash
gh release create v1.1.0 \
  --title "Version 1.1.0 - Form Design Customization & Theme Integration" \
  --notes-file RELEASE-v1.1.0.md \
  --target main
```

## After Creating the Release

Once the release is published:

1. The plugin updater will detect it within 1 hour (or immediately if you use "Check for Updates" button)
2. WordPress will show an update notification in the Plugins page
3. Users can update directly from WordPress admin

## Verify the Release

After creating the release, verify it exists by visiting:
- https://api.github.com/repos/Jon3r/pjja-booking/releases/latest

You should see JSON data with the release information, not a 404 error.

## Troubleshooting

- **If the tag doesn't appear:** Make sure you've pushed the tag: `git push origin v1.1.0`
- **If updates still don't show:** Clear the cache using the "Check for Updates" button in the plugin dashboard
- **If you get a 404:** The release hasn't been created yet - follow the steps above

