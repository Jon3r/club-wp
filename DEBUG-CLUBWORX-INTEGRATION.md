# Debug ClubWorx Integration - Missing Classes Fix

## 🎯 Problem: Form Not Showing Live ClubWorx Data

Your form is showing **incorrect or missing classes** because the ClubWorx API integration needs debugging. Let's fix this step by step.

---

## 🔍 **Step 1: Check Current Status**

### A. Check if ClubWorx is Configured

1. Go to **WordPress Admin → Trial Booking → Dashboard**
2. Look for the status banner at the top:

**If you see YELLOW banner:**

```
⚠️ Action Required: ClubWorx API Not Configured
Your booking form is currently showing static/hardcoded schedule data.
```

→ **ClubWorx is NOT configured** → Go to Step 2

**If you see GREEN banner:**

```
✅ ClubWorx API Active
Fetching live schedule data from: https://api.clubworx.com/v1
```

→ **ClubWorx IS configured** → Go to Step 3

### B. Run Diagnostics

1. Click **"Show Diagnostics"** button
2. Look for this line: `ClubWorx Configured: YES/NO`

---

## 🚀 **Step 2: Configure ClubWorx API (If Not Done)**

### A. Get ClubWorx Credentials

Contact ClubWorx support and ask for:

1. **API Endpoint URL** (e.g., `https://api.clubworx.com/v1`)
2. **API Authentication Key** (long string of letters/numbers)

### B. Enter Credentials

1. Go to **Trial Booking → Settings**
2. Scroll to **"ClubWorx API Settings"**
3. Enter:
   - **ClubWorx API URL**: Your API endpoint
   - **ClubWorx API Key**: Your authentication key
4. Click **"Save Changes"**

---

## 🔧 **Step 3: Debug ClubWorx API Response**

### A. Test Raw ClubWorx Response

1. Go to **Trial Booking → Dashboard**
2. Click **"Test ClubWorx Raw"** button
3. This will show you **exactly** what ClubWorx is returning

### B. Analyze the Response

The popup will show:

- **Status Code**: Should be `200` for success
- **Raw Response**: The exact JSON ClubWorx returns
- **Parsed Response**: Formatted JSON data

**Common ClubWorx Response Formats:**

#### Format 1: Classes Array

```json
{
  "classes": [
    {
      "name": "General Gi Class",
      "day": "monday",
      "time": "6:00pm"
    },
    {
      "name": "Foundations Gi",
      "day": "monday",
      "time": "7:00pm"
    }
  ]
}
```

#### Format 2: Schedule Array

```json
{
  "schedule": [
    {
      "class_name": "General Gi Class",
      "day": "monday",
      "start_time": "6:00pm"
    }
  ]
}
```

#### Format 3: Events Array

```json
{
  "events": [
    {
      "name": "General Gi Class",
      "day": "monday",
      "time": "6:00pm"
    }
  ]
}
```

### C. Check What's Missing

Compare the raw ClubWorx response with your actual schedule:

**Your ClubWorx Schedule (from screenshot):**

- 4:30pm - 5:00pm: Little Kids Jiu Jitsu
- 5:00pm - 5:45pm: Big Kids Jiu Jitsu (7-12 years)
- 6:00pm - 7:00pm: General Gi Class
- 7:00pm - 8:00pm: Foundations Gi
- 7:00pm - 8:00pm: General No Gi Class

**Check if these classes appear in the raw response.**

---

## 🛠️ **Step 4: Fix Data Mapping (If Needed)**

### A. If Classes Are Missing from Raw Response

**Problem:** ClubWorx API doesn't return all classes
**Solution:**

1. Contact ClubWorx support
2. Ask: "Why are some classes missing from the API response?"
3. They might need to:
   - Enable the classes in API settings
   - Grant additional permissions
   - Use a different API endpoint

### B. If Classes Are Present But Form Shows Wrong Data

**Problem:** Plugin can't parse ClubWorx response format
**Solution:** Customize the data mapping

1. **Identify the format** from raw response
2. **Edit the mapping code** in `format_clubworx_schedule()` method
3. **Test with "Refresh Schedule Cache"**

### C. Common Mapping Issues

#### Issue 1: Different Field Names

ClubWorx uses `class_name` instead of `name`:

```php
// Current code looks for:
$className = $class['name'];

// Fix for ClubWorx format:
$className = isset($class['name']) ? $class['name'] : $class['class_name'];
```

#### Issue 2: Different Day Format

ClubWorx returns `"Monday"` instead of `"monday"`:

```php
// Current code:
$day = strtolower($class['day']);

// Already handles this correctly
```

#### Issue 3: Time Format Issues

ClubWorx returns `"18:00"` instead of `"6:00pm"`:

```php
// Add time conversion:
$time = $this->convert_time_format($class['time']);
```

---

## 📊 **Step 5: Test the Fix**

### A. Clear Cache and Test

1. Click **"Refresh Schedule Cache"** button
2. Go to your booking form page
3. **Hard refresh** (Ctrl+F5 or Cmd+Shift+R)
4. Check if classes now appear correctly

### B. Verify Class Mapping

The form should now show:

**Monday Adults Classes:**

- General Gi Class - 6:00pm
- General No Gi Class - 7:00pm
- Foundations Gi - 7:00pm

**Monday Kids Classes:**

- Little Kids Jiu Jitsu - 4:30pm
- Big Kids Jiu Jitsu (7-12 years) - 5:00pm

---

## 🔍 **Step 6: Enable Debug Logging**

### A. Enable WordPress Debug Mode

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### B. Check Debug Logs

1. Go to your WordPress root directory
2. Open `wp-content/debug.log`
3. Look for lines starting with "PJJA Booking:"
4. This shows:
   - API requests being made
   - Raw responses received
   - Data transformation steps
   - Any errors

### C. Example Debug Output

```
PJJA Booking: Fetching schedule from ClubWorx API: https://api.clubworx.com/v1/schedule
PJJA Booking: Raw ClubWorx data received: {"classes":[{"name":"General Gi Class","day":"monday","time":"6:00pm"}]}
PJJA Booking: Transforming ClubWorx data format
PJJA Booking: Formatted schedule: {"adults":{"general":{"monday":["General Gi Class - 6:00pm"]}}}
PJJA Booking: Successfully fetched and cached schedule from ClubWorx
```

---

## 🎯 **Step 7: Common Solutions**

### Solution 1: Wrong API Endpoint

**Problem:** Using `/schedule` but ClubWorx uses `/classes`
**Fix:** Update API URL in settings:

```
https://api.clubworx.com/v1/classes
```

### Solution 2: Missing Authentication

**Problem:** API returns 401 Unauthorized
**Fix:**

1. Regenerate API key in ClubWorx
2. Copy key exactly (no extra spaces)
3. Save settings again

### Solution 3: API Returns Different Format

**Problem:** Raw response doesn't match expected format
**Fix:** Customize the `add_class_to_schedule()` method

### Solution 4: Classes Not Enabled in ClubWorx

**Problem:** Some classes missing from API response
**Fix:** Contact ClubWorx support to enable all classes in API

---

## 📞 **Step 8: Contact ClubWorx Support**

If you need to contact ClubWorx support, provide them with:

1. **Your API endpoint URL**
2. **Screenshot of "Test ClubWorx Raw" response**
3. **List of missing classes**
4. **What you're trying to achieve**

**Sample message:**

> Hi, I'm integrating ClubWorx with my WordPress website to display class schedules.
>
> I'm using the API endpoint: [YOUR_API_URL]
>
> The API response is missing some classes that appear in my ClubWorx dashboard:
>
> - General No Gi Class (7:00pm Monday)
> - Foundations Gi (7:00pm Monday)
>
> Can you help me get the complete schedule data via API?

---

## ✅ **Verification Checklist**

After fixing, verify:

- [ ] ClubWorx API is configured (green banner)
- [ ] "Test ClubWorx Raw" shows all your classes
- [ ] Debug logs show successful API calls
- [ ] Form displays correct class times
- [ ] All classes from ClubWorx appear in form
- [ ] Classes update when you change ClubWorx schedule

---

## 🚨 **Emergency Fallback**

If ClubWorx API continues to have issues:

1. **Use the corrected static schedule** (already implemented)
2. **Manually update the static schedule** when classes change
3. **Contact ClubWorx support** for API issues
4. **Consider alternative integration methods**

---

**Next Action:** Run the "Test ClubWorx Raw" button and share the results so we can see exactly what ClubWorx is returning! 🔍
