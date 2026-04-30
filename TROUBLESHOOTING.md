# Troubleshooting: Form Still Showing Static Data

## 🔍 Diagnosis

If your form is still showing static/hardcoded schedule data, the most likely reason is:

**ClubWorx API credentials are NOT configured yet.**

---

## ✅ Quick Fix (5 minutes)

### Step 1: Check Current Status

1. Go to **WordPress Admin → Trial Booking → Dashboard**
2. Click **"Refresh Schedule Cache"** button
3. Read the popup message carefully

**If you see:** "ClubWorx API is NOT configured!"

- ✅ This is expected! Continue to Step 2.

**If you see:** "ClubWorx is configured!"

- Jump to "Advanced Troubleshooting" section below.

---

### Step 2: Configure ClubWorx API

1. Go to **WordPress Admin → Trial Booking → Settings** tab
2. You should see a **yellow warning banner** that says:

   - "⚠️ ClubWorx API Not Configured!"
   - "Your booking form is currently using static/hardcoded schedule data"

3. Scroll down to **"ClubWorx API Settings"** section

4. You'll see two empty fields:

   - **ClubWorx API URL** (currently empty)
   - **ClubWorx API Key** (currently empty)

5. Fill in your ClubWorx credentials:

   ```
   ClubWorx API URL: https://api.clubworx.com/v1
   ClubWorx API Key: [Your API key from ClubWorx]
   ```

6. Click **"Save Changes"**

7. You should now see a **green success banner** that says:
   - "✅ ClubWorx API Configured!"

---

### Step 3: Test the Connection

1. Go back to **Trial Booking → Dashboard** tab
2. Click **"Test ClubWorx API"** button
3. You should see:

   ```
   Schedule Data:

   Status: ✓ ClubWorx Configured
   Cache: Fresh data
   Source: clubworx_or_fallback

   ✓ Kids programs found
   ✓ Adults programs found
   ✓ Women programs found
   ```

4. If test is successful, go to your booking form page
5. Refresh the page (Ctrl+F5 or Cmd+Shift+R)
6. The form should now show live ClubWorx data!

---

## 🔧 Advanced Troubleshooting

### Issue: "Refresh Cache" Button Failed

**Possible Causes:**

#### 1. REST API Permissions Issue

**Symptoms:** Button doesn't do anything, or shows error
**Solution:**

1. Open browser Developer Tools (F12)
2. Click Console tab
3. Click "Refresh Schedule Cache" again
4. Look for error messages
5. If you see "403 Forbidden" or "401 Unauthorized":
   - Log out of WordPress and log back in
   - Clear browser cookies
   - Try again

#### 2. WordPress REST API Disabled

**Symptoms:** All buttons fail, console shows "Failed to fetch"
**Solution:**

1. Check if REST API is working: Visit `https://yoursite.com/wp-json/`
2. You should see JSON data, not an error
3. If you see an error, your REST API might be disabled by:
   - Security plugin (Wordfence, iThemes Security, etc.)
   - Custom code in `functions.php`
   - `.htaccess` rules

**Fix:**

- Temporarily disable security plugins
- Check `.htaccess` for REST API blocks
- Check theme's `functions.php` for REST API filters

#### 3. JavaScript Error

**Symptoms:** Button doesn't respond at all
**Solution:**

1. Open browser Console (F12)
2. Look for red error messages
3. Common issues:
   - jQuery not loaded
   - Script conflict with another plugin
4. Try:
   - Disable other plugins temporarily
   - Switch to default WordPress theme temporarily
   - Test if issue persists

---

### Issue: ClubWorx API Test Fails

**Symptoms:** Test shows error like "Error connecting to ClubWorx API"

**Diagnostic Steps:**

#### 1. Check API URL Format

Your ClubWorx API URL should look like:

- ✅ CORRECT: `https://api.clubworx.com/v1`
- ✅ CORRECT: `https://clubworx.example.com/api`
- ❌ WRONG: `https://api.clubworx.com/v1/` (trailing slash)
- ❌ WRONG: `http://api.clubworx.com/v1` (not HTTPS)

#### 2. Check API Key

- Copy API key directly from ClubWorx (don't type it)
- Check for extra spaces at beginning or end
- Make sure it's the API key, not a password

#### 3. Enable Debug Logging

Add to `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Then:

1. Click "Test ClubWorx API" again
2. Check `wp-content/debug.log`
3. Look for lines starting with "PJJA Booking:"
4. You'll see the actual API request and response

**Common error messages in debug.log:**

```
PJJA Booking: ClubWorx API error: cURL error 28: Connection timed out
```

**Fix:** Your server can't reach ClubWorx. Contact your hosting provider.

```
PJJA Booking: ClubWorx API returned status 401
```

**Fix:** Invalid API key. Check your ClubWorx account settings.

```
PJJA Booking: ClubWorx API returned status 404
```

**Fix:** Wrong API URL. Verify the URL with ClubWorx support.

#### 4. Test Outbound Connections

Your WordPress server needs to make HTTPS requests to ClubWorx.

**Test:**

1. Install "Server Status" or "Site Health" plugin
2. Check if outbound HTTPS requests are allowed
3. Some hosting providers block outbound connections by default

**Contact hosting if:**

- Outbound connections are blocked
- Firewall is blocking ClubWorx domain
- SSL certificate errors appear

---

### Issue: Form Shows Static Data Even After Configuration

**If ClubWorx IS configured but form still shows static data:**

#### 1. Clear All Caches

Clear these caches in order:

**A. WordPress Cache:**

1. Go to Dashboard → Refresh Schedule Cache

**B. WordPress Object Cache:**

```php
// Add to wp-config.php temporarily
define('WP_CACHE', false);
```

**C. Browser Cache:**

- Chrome: Ctrl+Shift+Delete → Clear browsing data
- Firefox: Ctrl+Shift+Delete → Clear recent history
- Safari: Cmd+Option+E

**D. CDN/Caching Plugin:**
If you use:

- WP Rocket → Clear cache
- W3 Total Cache → Clear all caches
- WP Super Cache → Clear cache
- Cloudflare → Purge everything

#### 2. Check Browser Console

On the booking form page:

1. Open Console (F12)
2. Refresh the page
3. Look for this log message:

   ```
   📅 Loading schedule data from API...
   📊 Schedule data received: {object}
   ```

4. Expand the object to see `debug` property
5. Check `debug.clubworx_configured` value

**If false:**

- ClubWorx settings didn't save properly
- Go back to Settings and save again

**If true:**

- Check `debug.data_source`
- Should say "clubworx_or_fallback"

#### 3. Force Refresh Without Cache

In the browser console (on booking form page), run:

```javascript
// Clear the loaded schedule
bookingManager.schedule = null;
bookingManager.scheduleLoaded = false;

// Force reload
bookingManager.loadScheduleData();
```

Watch the console output for errors.

---

## 📋 Verification Checklist

After configuration, verify everything:

- [ ] Settings page shows green "✅ ClubWorx API Configured!" banner
- [ ] Dashboard "Test ClubWorx API" button shows success
- [ ] Test shows "Status: ✓ ClubWorx Configured"
- [ ] `debug.log` shows "Successfully fetched and cached schedule from ClubWorx"
- [ ] Browser console shows "Schedule loaded successfully"
- [ ] Booking form displays your actual ClubWorx classes
- [ ] Classes change when you update ClubWorx schedule

---

## 🆘 Still Not Working?

### Collect Diagnostic Info

1. **WordPress Info:**

   - WordPress version: (Dashboard → Updates)
   - PHP version: (Tools → Site Health → Info → Server)
   - Active plugins: (Plugins page)

2. **Plugin Settings:**

   - Go to Settings page
   - Take screenshot of ClubWorx API Settings section (hide API key)

3. **Console Output:**

   - Go to booking form page
   - Open Console (F12)
   - Refresh page
   - Copy all messages (especially errors in red)

4. **Debug Log:**

   - Check `wp-content/debug.log`
   - Copy lines starting with "PJJA Booking:"

5. **Test Results:**
   - Click "Test ClubWorx API"
   - Copy the entire popup message
   - Click "Refresh Schedule Cache"
   - Copy that popup message too

### Contact Support

Provide the diagnostic info above when contacting:

- ClubWorx support (for API issues)
- Your hosting provider (for connection issues)
- Plugin developer (for plugin issues)

---

## 🎯 Expected Behavior

**When properly configured:**

1. **First page load:**

   - Plugin checks cache (empty)
   - Makes API call to ClubWorx
   - Receives schedule data
   - Caches for 1 hour
   - Displays on form

2. **Subsequent loads (within 1 hour):**

   - Plugin checks cache (found)
   - Uses cached data
   - No API call needed
   - Fast page load

3. **After 1 hour:**

   - Cache expires automatically
   - Next page load fetches fresh data
   - New cache created

4. **Manual refresh:**

   - Click "Refresh Schedule Cache"
   - Cache cleared immediately
   - Next page load fetches fresh data

5. **If ClubWorx is down:**
   - API call fails (timeout or error)
   - Plugin falls back to hardcoded schedule
   - Form still works
   - Retry on next cache expiry

---

## 🔑 Key Points

1. **Static data is the DEFAULT** until you configure ClubWorx
2. **Configuration takes 5 minutes** - just add API URL and Key
3. **Test before relying on it** - use the Test button
4. **Cache lasts 1 hour** - refresh manually if needed
5. **Fallback protects you** - form works even if ClubWorx is down

---

**Next Step:** Go configure your ClubWorx API credentials in Settings! 🚀
