# Quick Fix Guide - ClubWorx Integration

## 🔧 What Was Wrong?

Your plugin was showing **hardcoded schedule data** instead of fetching live data from ClubWorx.

## ✅ What's Fixed Now?

The plugin now:

1. ✅ Fetches live schedule data from ClubWorx API
2. ✅ Caches data for 1 hour to improve performance
3. ✅ Falls back to hardcoded schedule if ClubWorx is unavailable
4. ✅ Includes admin tools to test and refresh the connection

## 🚀 Quick Setup (3 Steps)

### Step 1: Enter ClubWorx Credentials

1. Go to: **WordPress Admin → Trial Booking → Settings**
2. Find: **ClubWorx API Settings** section
3. Enter:
   - ClubWorx API URL (e.g., `https://api.clubworx.com/v1`)
   - ClubWorx API Key (from your ClubWorx account)
4. Click: **Save Changes**

### Step 2: Test Connection

1. Go to: **WordPress Admin → Trial Booking → Dashboard**
2. Click: **"Test ClubWorx API"** button
3. Should see: ✅ "ClubWorx API connection successful!"

### Step 3: Refresh Cache

1. On the same Dashboard page
2. Click: **"Refresh Schedule Cache"** button
3. This forces fresh data from ClubWorx

## 🎯 Daily Usage

### When You Update ClubWorx Schedule:

1. Update schedule in ClubWorx
2. Go to WordPress: **Trial Booking → Dashboard**
3. Click: **"Refresh Schedule Cache"**
4. Done! New schedule is live.

### Automatic Refresh:

- Cache expires every **1 hour**
- Plugin automatically fetches fresh data
- No manual action needed

## 📁 Files Changed

1. **includes/class-rest-api.php**

   - Added live ClubWorx API integration
   - Added caching system
   - Added error handling

2. **admin/admin-page.php**
   - Added "Test ClubWorx API" button
   - Added "Refresh Schedule Cache" button
   - Improved error messages

## ⚠️ Troubleshooting

### "ClubWorx API connection failed"

**Check:**

- ✓ API URL is correct (no trailing slash)
- ✓ API Key is correct (no extra spaces)
- ✓ ClubWorx API is online and accessible
- ✓ WordPress can make outbound HTTPS requests

**Get More Info:**

1. Enable WordPress debugging (add to `wp-config.php`):
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
2. Check: `wp-content/debug.log`
3. Look for: "PJJA Booking:" entries

### Schedule Not Showing New Classes

**Solution:**

1. Click "Refresh Schedule Cache" button
2. Wait 5 seconds
3. Reload booking form page
4. New classes should appear

### Plugin Still Shows Old Schedule

**Check:**

1. Is ClubWorx API URL entered in settings?
2. Is ClubWorx API Key entered in settings?
3. If both are empty, plugin uses fallback hardcoded schedule

## 🔍 How It Works

```
User visits booking page
         ↓
Plugin checks cache
         ↓
Cache exists? → YES → Show cached schedule
         ↓
         NO
         ↓
Call ClubWorx API
         ↓
API success? → YES → Cache for 1 hour → Show schedule
         ↓
         NO
         ↓
Show fallback hardcoded schedule
```

## 📞 Need Help?

**Detailed Guide:** See `CLUBWORX-INTEGRATION-FIX.md`

**ClubWorx Support:**

- Confirm your API URL format
- Confirm `/schedule` endpoint is available
- Regenerate API key if needed

**WordPress Logs:**

- Location: `wp-content/debug.log`
- Filter for: "PJJA Booking:"
- Shows all API calls and responses

---

**Status:** ✅ Plugin is now configured to fetch live ClubWorx data!

**Next Step:** Enter your ClubWorx API credentials in Settings → ClubWorx API Settings
