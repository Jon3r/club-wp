# PJJA Trial Booking - Quick Start Guide

## 🚀 Installation (3 steps)

### Step 1: Package the Plugin

Run the installation script:

```bash
cd /Users/andyjones/Desktop/Web/pjja-booking-plugin/pjja-trial-booking-main
./install-instructions.sh
```

Or manually create ZIP:

```bash
cd /Users/andyjones/Desktop/Web/pjja-booking-plugin
zip -r pjja-trial-booking.zip pjja-trial-booking-main
```

### Step 2: Install in WordPress

1. WordPress Admin → **Plugins** → **Add New** → **Upload Plugin**
2. Choose `pjja-trial-booking.zip`
3. Click **Install Now** → **Activate**

### Step 3: Configure & Use

1. Go to **Trial Booking** → **Settings**
2. Enter your API credentials
3. Add shortcode to any page: `[pjja_trial_booking]`
4. Done! 🎉

---

## ⚙️ Essential Settings

### GA4 Settings

- **Measurement ID**: `G-44L97V68WN` (or your ID)
- **API Secret**: Create in GA4 Admin → Data Streams → Measurement Protocol
- **Debug Mode**: ☑️ Enable (for testing)

### ClubWorx Settings

- **API URL**: Your ClubWorx base URL
- **API Key**: Your ClubWorx authentication key

### Email Settings

- **Enable Notifications**: ☑️ Check to receive booking alerts
- **Admin Email**: Email for notifications

---

## 📝 Usage

### Add to Page/Post

**Shortcode:**

```
[pjja_trial_booking]
```

**PHP Template:**

```php
<?php echo do_shortcode('[pjja_trial_booking]'); ?>
```

**Gutenberg Block:**
Search for "PJJA Trial Booking" in block inserter

---

## 🧪 Testing

### 1. Test Form Display

- Create test page
- Add shortcode
- Verify form appears

### 2. Test GA4 Tracking

- Open page with form
- Open browser console (F12)
- Look for: `✅ GA4 loaded successfully`
- Go to GA4 Admin → DebugView
- You should see your session

### 3. Test ClubWorx API

- Go to **Trial Booking** → **Dashboard**
- Click **Test ClubWorx API** button
- Should show success message

### 4. Test Booking

- Fill out form with test data
- Submit booking
- Check **Dashboard** for new booking
- Check email for notification

---

## 📊 Dashboard Features

Access: **WordPress Admin** → **Trial Booking**

### Stats Cards

- Total bookings (all time)
- This month (last 30 days)
- Info requests
- Conversion rate

### Recent Bookings

- Last 10 bookings
- View details
- Export to CSV

### Attribution Stats

- Traffic sources (last 30 days)
- UTM tracking
- Conversion analytics

---

## 🔧 Troubleshooting

### Form not showing

```bash
# Check shortcode spelling
[pjja_trial_booking]

# Clear cache
WordPress Admin → Settings → Permalinks → Save Changes
```

### REST API not working

```bash
# Test endpoint
Visit: yoursite.com/wp-json/pjja-booking/v1/schedule-simple

# Should return JSON data
```

### GA4 events not appearing

1. Disable ad blockers
2. Check browser console for errors
3. Server-side events don't appear in DebugView (this is normal)
4. Check GA4 Realtime reports instead

### ClubWorx errors

1. Verify API URL is correct
2. Check API key permissions
3. Use "Test ClubWorx API" button
4. Check WordPress debug log

---

## 📚 Documentation Files

- **README-WORDPRESS.md** - Complete documentation
- **CONVERSION-SUMMARY.md** - What was converted
- **GA4-SETUP.md** - GA4 configuration details
- **QUICK-START.md** - This file

---

## 🎯 Key Differences from Vercel Version

| Feature       | Vercel            | WordPress                   |
| ------------- | ----------------- | --------------------------- |
| Deployment    | Vercel serverless | WordPress plugin            |
| Configuration | .env files        | WordPress settings page     |
| API endpoints | /api/\*           | /wp-json/pjja-booking/v1/\* |
| Database      | None (API only)   | WordPress tables (backup)   |
| Admin UI      | None              | Full dashboard              |
| Installation  | Git push          | Plugin upload               |

---

## 🔐 Security

The plugin implements WordPress security best practices:

- ✅ Nonce verification on all API calls
- ✅ Data sanitization and validation
- ✅ SQL prepared statements
- ✅ Capability checks for admin functions
- ✅ XSS prevention with output escaping
- ✅ CSRF protection

---

## 📞 Support Checklist

If you need help:

1. ✅ Plugin activated?
2. ✅ Settings configured?
3. ✅ Shortcode added to page?
4. ✅ Browser console checked for errors?
5. ✅ WordPress debug log checked?
6. ✅ REST API endpoint tested?
7. ✅ ClubWorx API connection tested?

---

## 🎉 You're Ready!

Your trial booking system is now a fully-functional WordPress plugin with:

- ✅ All original functionality preserved
- ✅ Enhanced WordPress integration
- ✅ Admin dashboard and analytics
- ✅ Database backup of all bookings
- ✅ Easy configuration via settings page
- ✅ No code editing required

**Next:** Configure your settings and start accepting trial bookings!
