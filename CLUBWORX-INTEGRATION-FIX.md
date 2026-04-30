# ClubWorx Integration Fix

## Problem Identified

The plugin was **not fetching live data from ClubWorx**. Instead, it was using a hardcoded schedule in the PHP code. This meant that any changes to your ClubWorx schedule would not be reflected in the booking form.

## What Was Fixed

### 1. Dynamic Schedule Fetching

**File:** `includes/class-rest-api.php`

**Changes:**

- Updated `get_clubworx_schedule()` method to actually fetch schedule data from ClubWorx API
- Added API call to ClubWorx's `/schedule` endpoint
- Implemented proper error handling and fallback to hardcoded schedule if ClubWorx is unavailable
- Added 1-hour caching to reduce API calls and improve performance

**Before:**

```php
private function get_clubworx_schedule() {
    // Return hardcoded schedule
    return array(...);
}
```

**After:**

```php
private function get_clubworx_schedule() {
    // Check cache first
    // If not cached, fetch from ClubWorx API
    // If API fails, fall back to hardcoded schedule
    // Cache result for 1 hour
}
```

### 2. Cache Management

**Added new features:**

- `clear_schedule_cache()` - REST API endpoint to clear cached schedule
- `format_clubworx_schedule()` - Transforms ClubWorx data format to plugin format
- `get_fallback_schedule()` - Provides hardcoded schedule as backup

### 3. Admin Dashboard Improvements

**File:** `admin/admin-page.php`

**Added:**

- **"Refresh Schedule Cache"** button - Clears cached schedule to force fresh fetch from ClubWorx
- Improved **"Test ClubWorx API"** button with better error reporting
- Better visual feedback during API operations

## How to Configure ClubWorx Integration

### Step 1: Get Your ClubWorx API Credentials

1. Log into your ClubWorx account
2. Navigate to **Settings → API Settings**
3. Copy your:
   - **API URL** (e.g., `https://api.clubworx.com/v1`)
   - **API Key** (a long authentication token)

### Step 2: Configure the Plugin

1. In WordPress, go to **Trial Booking → Settings**
2. Scroll to **ClubWorx API Settings** section
3. Enter your:
   - **ClubWorx API URL**
   - **ClubWorx API Key**
4. Click **Save Changes**

### Step 3: Test the Connection

1. Go to **Trial Booking → Dashboard**
2. Click the **"Test ClubWorx API"** button
3. You should see a success message showing:
   - Kids programs found
   - Adults programs found
   - Women programs found

### Step 4: Clear Cache After Changes

Whenever you update your schedule in ClubWorx:

1. Go to **Trial Booking → Dashboard**
2. Click **"Refresh Schedule Cache"**
3. This forces the plugin to fetch fresh data from ClubWorx

## ClubWorx API Requirements

### Expected Schedule Format

The plugin expects ClubWorx to return schedule data in this format:

```json
{
  "kids": {
    "under6": {
      "monday": ["Little Ninjas 4-6 years - 4:00pm"],
      "wednesday": ["Little Ninjas 4-6 years - 4:00pm"],
      "friday": ["Little Ninjas 4-6 years - 4:00pm"],
      "saturday": ["Little Ninjas 4-6 years - 9:00am"]
    },
    "over6": {
      "monday": ["Kids BJJ 6-12 years - 5:00pm"],
      "wednesday": ["Kids BJJ 6-12 years - 5:00pm"],
      "friday": ["Kids BJJ 6-12 years - 5:00pm"],
      "saturday": ["Kids BJJ 6-12 years - 10:00am"]
    }
  },
  "adults": {
    "general": {
      "monday": [
        "Adults BJJ All Levels - 6:30pm",
        "Adults BJJ All Levels - 7:30pm"
      ],
      "tuesday": ["Adults BJJ All Levels - 6:30pm"],
      "wednesday": [
        "Adults BJJ All Levels - 6:30pm",
        "Adults BJJ All Levels - 7:30pm"
      ],
      "thursday": ["Adults BJJ All Levels - 6:30pm"],
      "friday": ["Adults BJJ All Levels - 6:30pm"],
      "saturday": ["Adults BJJ All Levels - 11:00am"]
    },
    "foundations": {
      "monday": ["Foundations - 6:00pm"],
      "wednesday": ["Foundations - 6:00pm"],
      "friday": ["Foundations - 6:00pm"]
    }
  },
  "women": {
    "tuesday": ["Women Only BJJ - 7:30pm"],
    "thursday": ["Women Only BJJ - 7:30pm"]
  }
}
```

### API Endpoints Used

1. **Schedule Fetching:**

   - Endpoint: `GET {API_URL}/schedule`
   - Headers: `Authorization: Bearer {API_KEY}`

2. **Create Prospect:**

   - Endpoint: `POST {API_URL}/prospects`
   - Headers: `Authorization: Bearer {API_KEY}`

3. **Find Events:**

   - Endpoint: `POST {API_URL}/events`
   - Headers: `Authorization: Bearer {API_KEY}`

4. **Create Booking:**
   - Endpoint: `POST {API_URL}/bookings`
   - Headers: `Authorization: Bearer {API_KEY}`

## Troubleshooting

### Schedule Not Updating

**Problem:** Changes in ClubWorx don't appear in the booking form

**Solution:**

1. Go to **Trial Booking → Dashboard**
2. Click **"Refresh Schedule Cache"**
3. Wait a few seconds and test the form

### ClubWorx API Test Fails

**Problem:** "ClubWorx API connection failed" message

**Causes & Solutions:**

1. **Incorrect API URL**

   - Check that URL is correct (no trailing slash)
   - Example: `https://api.clubworx.com/v1`

2. **Invalid API Key**

   - Regenerate API key in ClubWorx
   - Copy and paste carefully (no extra spaces)

3. **API Endpoint Mismatch**

   - Contact ClubWorx support to confirm the `/schedule` endpoint exists
   - They may use a different endpoint name

4. **Firewall Issues**
   - Check WordPress site can make outbound HTTPS requests
   - Contact hosting provider if needed

### Viewing Detailed Logs

Enable WordPress debug logging to see detailed API interactions:

1. Edit `wp-config.php`
2. Add these lines:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   ```
3. Check logs in `wp-content/debug.log`
4. Look for entries starting with "PJJA Booking:"

## Performance Notes

### Caching Strategy

- Schedule data is cached for **1 hour** (3600 seconds)
- This reduces API calls to ClubWorx
- Cache is stored in WordPress transients
- Cache automatically expires and refreshes

### When Cache is Used

- First request: Fetches from ClubWorx, stores in cache
- Subsequent requests (within 1 hour): Uses cached data
- After 1 hour: Automatically fetches fresh data
- Manual refresh: Use "Refresh Schedule Cache" button

### Benefits

- ✅ Faster page loads (cached data)
- ✅ Reduced ClubWorx API calls
- ✅ Continues working if ClubWorx is temporarily unavailable
- ✅ Fallback to hardcoded schedule if API fails

## Custom Schedule Format

If your ClubWorx returns a different data format, you can customize the transformation:

**File:** `includes/class-rest-api.php`

**Method:** `format_clubworx_schedule()`

```php
private function format_clubworx_schedule($clubworx_data) {
    // Add your custom transformation logic here
    // Example: Map ClubWorx field names to plugin format

    return array(
        'kids' => $this->transform_kids_schedule($clubworx_data),
        'adults' => $this->transform_adults_schedule($clubworx_data),
        'women' => $this->transform_women_schedule($clubworx_data),
    );
}
```

## Summary

✅ **Fixed:** Plugin now fetches live data from ClubWorx
✅ **Added:** 1-hour caching for performance
✅ **Added:** Manual cache refresh button
✅ **Added:** Better error handling and logging
✅ **Added:** Fallback to hardcoded schedule if API fails
✅ **Improved:** Admin dashboard with better testing tools

The plugin will now dynamically fetch and display your actual ClubWorx schedule, updating automatically every hour or when you manually refresh the cache.
