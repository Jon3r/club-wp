# PJJA Trial Booking - WordPress Plugin

WordPress plugin for trial class booking with ClubWorx integration, GA4 tracking, and attribution tracking.

## 📦 What's Included

This directory contains **only** the WordPress plugin files:

```
pjja-booking/
├── pjja-trial-booking.php     # Main plugin file
├── includes/                   # Core functionality
│   ├── class-rest-api.php
│   ├── class-admin-settings.php
│   └── class-github-updater.php
├── admin/                      # Admin pages
│   ├── admin-page.php
│   └── settings-page.php
├── templates/                  # Front-end templates
│   └── booking-form.php
├── assets/                     # CSS, JS, images
│   ├── css/styles.css
│   ├── js/script.js
│   ├── js/attribution-tracker.js
│   └── images/logo.png
└── Documentation files
```

## 🚀 Quick Installation

### Option 1: Upload ZIP (Recommended)

1. **Create ZIP file:**

   ```bash
   cd /Users/andyjones/Desktop/Web/pjja-booking-plugin
   zip -r pjja-booking.zip pjja-booking
   ```

2. **Upload to WordPress:**
   - Go to: **WordPress Admin → Plugins → Add New → Upload Plugin**
   - Choose `pjja-booking.zip`
   - Click **Install Now** → **Activate**

### Option 2: Use Install Script

```bash
cd /Users/andyjones/Desktop/Web/pjja-booking-plugin/pjja-booking
./install-instructions.sh
```

### Option 3: Manual FTP

1. Upload the entire `pjja-booking` folder to: `/wp-content/plugins/`
2. Activate in **WordPress Admin → Plugins**

## ⚙️ Configuration

After activation:

1. Go to **WordPress Admin → Trial Booking → Settings**
2. Configure:
   - GA4 Measurement ID & API Secret
   - ClubWorx API URL & Key
   - Email notifications
3. Add shortcode to any page: `[pjja_trial_booking]`

## 📚 Documentation

- **README-WORDPRESS.md** - Complete plugin documentation
- **QUICK-START.md** - Quick setup guide
- **CONVERSION-SUMMARY.md** - Technical details
- **GA4-SETUP.md** - GA4 configuration
- **GITHUB-SETUP.md** - GitHub repository and update setup

## ✅ Ready to Install

This is a clean, production-ready WordPress plugin with:

- ✅ No Vercel files
- ✅ No node_modules
- ✅ No unnecessary files
- ✅ All WordPress plugin files included
- ✅ Proper directory structure
- ✅ GitHub update integration
- ✅ Email testing and logging
- ✅ CSV export functionality

**Next step:** Run `./install-instructions.sh` or create a ZIP file for upload!
