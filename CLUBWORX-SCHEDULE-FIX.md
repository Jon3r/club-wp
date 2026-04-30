# ClubWorx Schedule Fix - Your Specific Issue

## 🎯 Problem Identified

Your form shows **incorrect class times** because it's using **hardcoded static data** instead of fetching from ClubWorx.

### What You're Seeing vs Reality

**Form Shows (WRONG):**

- Adults BJJ All Levels - 6:30pm
- Adults BJJ All Levels - 7:30pm

**ClubWorx Actually Has (CORRECT):**

- General Gi Class - 6:00pm
- Foundations Gi - 7:00pm
- General No Gi Class - 7:00pm

---

## ✅ What I've Fixed

### 1. Updated Static Schedule

I've updated the fallback schedule to match your **actual ClubWorx classes**:

**Kids Classes:**

- Little Kids Jiu Jitsu - 4:30pm
- Big Kids Jiu Jitsu (7-12 years) - 5:00pm

**Adults Classes:**

- General Gi Class - 6:00pm
- General No Gi Class - 7:00pm
- Foundations Gi - 7:00pm

### 2. Added Diagnostic Tools

- **"Show Diagnostics"** button - Shows exactly what's happening
- **Better error messages** - Clear indication if ClubWorx is configured
- **Visual status banners** - Yellow warning if not configured

### 3. Enhanced API Integration

- Proper ClubWorx API calls when configured
- 1-hour caching for performance
- Fallback to corrected static schedule if ClubWorx unavailable

---

## 🚀 Immediate Solution (2 Steps)

### Step 1: Check Current Status

1. Go to **WordPress Admin → Trial Booking → Dashboard**
2. Click **"Show Diagnostics"** button
3. Look for this line: `ClubWorx Configured: NO`

**If you see "NO":** ClubWorx is not configured → Continue to Step 2
**If you see "YES":** ClubWorx IS configured → Skip to "Advanced Troubleshooting"

### Step 2: Configure ClubWorx API

1. Go to **Trial Booking → Settings**
2. You'll see a **yellow warning banner**
3. Scroll to **"ClubWorx API Settings"**
4. Enter your credentials:

   **ClubWorx API URL:**

   - Get this from ClubWorx support
   - Usually: `https://api.clubworx.com/v1` or `https://your-club.clubworx.com/api`

   **ClubWorx API Key:**

   - Get from ClubWorx Settings → API/Developer settings
   - Long string starting with letters/numbers

5. Click **"Save Changes"**
6. Banner should turn **green**: "✅ ClubWorx API Configured!"

### Step 3: Test & Verify

1. Go back to **Dashboard**
2. Click **"Test ClubWorx API"** button
3. Should show: "Status: ✓ ClubWorx Configured"
4. Go to your booking form page
5. **Hard refresh** (Ctrl+F5 or Cmd+Shift+R)
6. Form should now show **correct class times**!

---

## 🔍 If You Don't Have ClubWorx API Access

### Temporary Fix (Until You Get API Access)

The form will now show the **corrected static schedule** that matches your ClubWorx:

**Monday Adults Classes:**

- General Gi Class - 6:00pm
- General No Gi Class - 7:00pm

**Monday Kids Classes:**

- Little Kids Jiu Jitsu - 4:30pm
- Big Kids Jiu Jitsu (7-12 years) - 5:00pm

This is **much better** than the incorrect times you had before.

### Getting ClubWorx API Access

Contact ClubWorx support and ask for:

1. **API endpoint URL** for your club
2. **API authentication key/token**
3. **Documentation** on how to fetch schedule data

Tell them: _"I need API access to fetch class schedule data for my WordPress website integration."_

---

## 🔧 Advanced Troubleshooting

### Issue: ClubWorx IS Configured But Still Shows Wrong Data

**Diagnostic Steps:**

1. **Click "Show Diagnostics"** button
2. Look for:

   ```
   ClubWorx Configured: YES
   Sample Monday Classes:
   - [Check if these match your ClubWorx]
   ```

3. **If classes are wrong:**

   - ClubWorx API might return different format
   - Need to customize the `format_clubworx_schedule()` method
   - Contact ClubWorx support for API response format

4. **If classes are correct but form shows wrong:**
   - Clear browser cache (Ctrl+F5)
   - Clear WordPress cache
   - Click "Refresh Schedule Cache"

### Issue: API Test Fails

**Common Causes:**

1. **Wrong API URL**

   - Must be exact URL from ClubWorx
   - Usually ends with `/api` or `/v1`
   - Must use HTTPS

2. **Wrong API Key**

   - Copy directly from ClubWorx (don't type)
   - No extra spaces
   - Must be API key, not password

3. **ClubWorx API Different Format**
   - Your ClubWorx might use different endpoint
   - Try: `/schedule` or `/classes` or `/events`
   - Contact ClubWorx support

### Issue: Classes Still Don't Match

**If you have ClubWorx API but classes are wrong:**

1. **Check ClubWorx API Response:**

   - Enable WordPress debug logging
   - Look in `wp-content/debug.log`
   - Find "PJJA Booking:" entries
   - See what ClubWorx actually returns

2. **Customize Data Mapping:**
   - Edit `format_clubworx_schedule()` method
   - Map ClubWorx response to plugin format
   - Test with "Refresh Schedule Cache"

---

## 📊 Expected ClubWorx API Format

The plugin expects ClubWorx to return data like this:

```json
{
  "adults": {
    "general": {
      "monday": ["General Gi Class - 6:00pm", "General No Gi Class - 7:00pm"]
    },
    "foundations": {
      "monday": ["Foundations Gi - 7:00pm"]
    }
  },
  "kids": {
    "under6": {
      "monday": ["Little Kids Jiu Jitsu - 4:30pm"]
    },
    "over6": {
      "monday": ["Big Kids Jiu Jitsu (7-12 years) - 5:00pm"]
    }
  }
}
```

**If ClubWorx returns different format:**

- Need to customize the mapping in `format_clubworx_schedule()`
- Contact ClubWorx support for exact format

---

## 🎯 Verification Checklist

After configuration, verify:

- [ ] Settings page shows green "✅ ClubWorx API Configured!" banner
- [ ] Diagnostics shows "ClubWorx Configured: YES"
- [ ] Test API button shows success
- [ ] Sample classes in diagnostics match your ClubWorx
- [ ] Booking form shows correct class times
- [ ] Classes update when you change ClubWorx schedule

---

## 📞 Next Steps

### If You Have ClubWorx API Access:

1. Configure the API credentials in Settings
2. Test the connection
3. Verify classes match your ClubWorx
4. Form will fetch live data automatically

### If You Don't Have ClubWorx API Access:

1. Contact ClubWorx support for API access
2. Form will use corrected static schedule in meantime
3. Much better than the wrong times you had before

### If ClubWorx API Format is Different:

1. Get API documentation from ClubWorx
2. Customize the data mapping code
3. Test with diagnostics button

---

## 🔑 Key Points

1. **Form was showing wrong times** because of hardcoded data
2. **I've fixed the static data** to match your actual ClubWorx schedule
3. **To get live data**, you need ClubWorx API credentials
4. **Diagnostics button** shows exactly what's happening
5. **Form will work** with corrected static data until you get API access

---

**Immediate Result:** Your form now shows the correct class times (6:00pm and 7:00pm) instead of the wrong ones (6:30pm and 7:30pm).

**Next Step:** Get ClubWorx API credentials to fetch live data automatically! 🚀
