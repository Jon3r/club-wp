# PJJA Trial Booking - WordPress Plugin

A complete trial class booking system for martial arts academies with GA4 tracking, ClubWorx integration, and attribution tracking.

## Features

- 📝 **Complete Booking Form** - Multi-step form with cascading dropdowns
- 📊 **GA4 Analytics** - Front-end and server-side event tracking
- 🔗 **ClubWorx Integration** - Direct integration with ClubWorx CRM
- 📈 **Attribution Tracking** - UTM parameters and referrer tracking
- 📧 **Email Notifications** - Automated booking alerts
- 💾 **Database Storage** - Backup of all bookings in WordPress database
- 📱 **Responsive Design** - Works on all devices
- 🎨 **Customizable** - Easy to style and modify

## Installation

### Option 1: Standard Installation

1. Download the plugin folder
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the `pjja-trial-booking` folder as a ZIP file
4. Click "Install Now" then "Activate"

### Option 2: Manual Installation

1. Upload the `pjja-trial-booking` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress

## Quick Start

### 1. Add the Booking Form

Use the shortcode on any page or post:

```
[pjja_trial_booking]
```

Or use the Gutenberg block "PJJA Trial Booking" in the block editor.

### 2. Configure Settings

Go to **WordPress Admin → Trial Booking → Settings**

#### GA4 Settings

1. **GA4 Measurement ID**: Enter your GA4 Measurement ID (e.g., G-XXXXXXXXXX)
2. **GA4 API Secret**: Create in GA4 Admin → Data Streams → Measurement Protocol API secrets
3. **Debug Mode**: Enable to see events in GA4 DebugView

#### ClubWorx Settings

1. **API URL**: Your ClubWorx API base URL
2. **API Key**: Your ClubWorx API authentication key

#### Email Settings

1. **Enable Notifications**: Check to receive booking alerts
2. **Admin Email**: Email address for notifications

### 3. Test the Form

1. Add the shortcode to a test page
2. Fill out the form with test data
3. Check the Dashboard for the booking
4. Verify GA4 events in DebugView

## Directory Structure

```
pjja-trial-booking/
├── pjja-trial-booking.php       # Main plugin file
├── includes/
│   ├── class-rest-api.php       # REST API endpoints
│   └── class-admin-settings.php # Settings handler
├── admin/
│   ├── admin-page.php           # Dashboard page
│   └── settings-page.php        # Settings page
├── templates/
│   └── booking-form.php         # Form template
├── assets/
│   ├── css/
│   │   └── styles.css           # Plugin styles
│   ├── js/
│   │   ├── script.js            # Main JavaScript
│   │   └── attribution-tracker.js # Attribution tracking
│   └── images/
│       └── logo.png             # Plugin logo
└── README-WORDPRESS.md          # This file
```

## REST API Endpoints

The plugin creates the following REST API endpoints:

- `GET /wp-json/pjja-booking/v1/schedule-simple` - Get class schedule
- `POST /wp-json/pjja-booking/v1/prospects` - Create prospect in ClubWorx
- `POST /wp-json/pjja-booking/v1/events-simple` - Find available classes
- `POST /wp-json/pjja-booking/v1/bookings` - Create booking in ClubWorx
- `POST /wp-json/pjja-booking/v1/attribution` - Track attribution data
- `POST /wp-json/pjja-booking/v1/ga4-measurement` - Send GA4 events

## Database Tables

The plugin creates two database tables:

### wp_pjja_bookings

Stores all booking submissions:

- `id` - Auto-increment ID
- `type` - 'booking' or 'prospect'
- `request_data` - JSON of submitted data
- `response_data` - JSON of API response
- `created_at` - Timestamp

### wp_pjja_attribution

Stores attribution data:

- `id` - Auto-increment ID
- `contact_key` - ClubWorx contact key
- `utm_source` - UTM source parameter
- `utm_medium` - UTM medium parameter
- `utm_campaign` - UTM campaign parameter
- `referrer` - Referrer URL
- `landing_page` - Landing page URL
- `program_interest` - Selected program
- `data` - Full attribution JSON
- `created_at` - Timestamp

## Admin Dashboard

Access via **WordPress Admin → Trial Booking**

### Dashboard Features

- **Stats Cards**: Total bookings, monthly bookings, info requests, conversion rate
- **Recent Bookings**: Last 10 bookings with details
- **Attribution Stats**: Last 30 days of traffic sources
- **Quick Actions**:
  - Configure Settings
  - Create Booking Page
  - Test ClubWorx API
  - Export Bookings

### Settings Features

- GA4 configuration
- ClubWorx API settings
- Email notification settings
- Help documentation

## GA4 Setup

### 1. Create Custom Dimensions

In GA4 Admin → Custom Definitions, create:

| Dimension Name   | Parameter Name     | Scope |
| ---------------- | ------------------ | ----- |
| Lead Source      | `lead_source`      | Event |
| UTM Source       | `utm_source`       | Event |
| UTM Medium       | `utm_medium`       | Event |
| UTM Campaign     | `utm_campaign`     | Event |
| Program Interest | `program_interest` | Event |
| Contact Key      | `contact_key`      | Event |

### 2. Create API Secret

1. Go to GA4 Admin → Data Streams
2. Click your web data stream
3. Scroll to "Measurement Protocol API secrets"
4. Click "Create" and copy the secret
5. Enter it in plugin settings

### 3. Enable Debug Mode

Enable in plugin settings to see events in GA4 DebugView (Admin → DebugView).

## Events Tracked

| Event Name               | Trigger              | Type                |
| ------------------------ | -------------------- | ------------------- |
| `page_view`              | Page loads           | Automatic           |
| `form_start`             | User starts typing   | Client-side         |
| `form_submit`            | Info-only submission | Client-side         |
| `trial_booking_complete` | Booking completed    | Both                |
| `conversion`             | Conversion event     | Both                |
| `ga4_initialized`        | GA4 loads            | Client-side (debug) |

**Note**: Server-side events (sent via Measurement Protocol) appear in GA4 Reports but NOT in DebugView.

## ClubWorx Integration

### API Endpoints Used

1. **Prospects API**: Creates new contacts
2. **Events API**: Finds available classes
3. **Bookings API**: Creates trial bookings

### Data Flow

```
User submits form
    ↓
WordPress REST API
    ↓
ClubWorx API (via wp_remote_post)
    ↓
Store in WordPress database (backup)
    ↓
Send email notification
    ↓
Track in GA4
```

## Customization

### Styling

Override styles in your theme's CSS:

```css
.pjja-booking-wrapper {
  /* Your styles */
}
.booking-card {
  /* Your styles */
}
.submit-btn {
  /* Your styles */
}
```

### Template Override

Copy `templates/booking-form.php` to your theme:

```
your-theme/pjja-booking/booking-form.php
```

### Filter Hooks

```php
// Modify booking data before submission
add_filter('pjja_booking_data', function($data) {
    // Your modifications
    return $data;
});

// Modify email notification content
add_filter('pjja_booking_email', function($message, $booking_data) {
    // Your modifications
    return $message;
}, 10, 2);
```

### Action Hooks

```php
// After successful booking
add_action('pjja_booking_success', function($booking_data) {
    // Your code
});

// After failed booking
add_action('pjja_booking_failed', function($error, $booking_data) {
    // Your code
}, 10, 2);
```

## Troubleshooting

### Events Not Appearing in GA4 DebugView

1. **Ad blocker**: Disable ad blockers
2. **Server-side events**: Only client-side events appear in DebugView
3. **Wrong property**: Verify GA4 Measurement ID
4. **Debug mode**: Ensure it's enabled in settings

### ClubWorx API Errors

1. **API URL**: Verify the URL is correct and includes protocol (https://)
2. **API Key**: Check the key is valid and has correct permissions
3. **Test Connection**: Use "Test ClubWorx API" button on Dashboard
4. **Logs**: Check WordPress debug log for API errors

### Form Not Submitting

1. **JavaScript errors**: Check browser console for errors
2. **REST API**: Verify REST API is accessible at `/wp-json/pjja-booking/v1/`
3. **Permissions**: Check file permissions in plugin directory
4. **Caching**: Clear WordPress and browser cache

### Database Tables Not Created

1. **Permissions**: Check database user has CREATE TABLE permissions
2. **Manual creation**: Tables auto-create on first use
3. **Plugin reactivation**: Deactivate and reactivate plugin

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- Active internet connection (for GA4 and ClubWorx API)

## Support

For issues or questions:

1. Check the troubleshooting section above
2. Review the admin Dashboard for system status
3. Enable WP_DEBUG to see detailed error messages

## License

GPL v2 or later

## Credits

Developed for Parramatta Jiu Jitsu Academy
