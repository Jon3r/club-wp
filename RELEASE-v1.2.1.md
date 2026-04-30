# Version 1.2.1 - Fix Missing Program Details in Booking Emails

## 🐛 Bug Fixes

### Fixed Missing Program Details in Booking Emails

- **Issue**: Booking confirmation emails were showing "N/A" for all program details (Program, Age Group, Day, Class)
- **Root Cause**: Booking API request only included `contact_key` and `event_id`, missing the full form data with program details
- **Solution**: Updated booking request to include complete form data (program, preferences, personal info)

### Improved GitHub Update Detection

- **Issue**: Auto-updates from GitHub releases were not being detected reliably
- **Root Cause**: Update information wasn't being properly injected into WordPress update system when manually checking
- **Solution**: Enhanced `force_check_updates()` method to properly inject updates into WordPress transient system
  - Updates are now immediately available after clicking "Check for Updates"
  - Better cache clearing and update injection
  - Improved logging for debugging update issues

## 🔧 Technical Changes

### JavaScript (`assets/js/script.js`)

- **Enhanced Booking Request**: Now includes full form data when creating bookings
  - Added `personal` object (name, email, phone)
  - Added `programInfo` object (interested in, contact preference)
  - Added `program` object (group, ageGroup, day, selectedClass)
  - Added `preferences` object (experience, goals)
  - Added `status`, `bookingId`, `submittedAt` metadata

### PHP Backend (`includes/class-rest-api.php`)

- **Improved Data Merging**: Enhanced `get_enhanced_booking_data()` method

  - Now properly preserves booking program details when merging with prospect data
  - Ensures nested arrays (program, programInfo, preferences, personal) are preserved from booking data
  - Booking data takes precedence over prospect data to maintain accuracy

- **Enhanced Email Formatting**: Improved `format_booking_email_html()` method
  - Better handling of empty strings and null values
  - More robust fallback logic for program details
  - Properly displays "N/A" only when data is truly missing

## 📊 What's Fixed

### Before (v1.2.0)

Booking emails showed:

- **Program**: N/A
- **Age Group**: N/A
- **Day**: N/A
- **Class**: N/A

### After (v1.2.1)

Booking emails now show:

- **Program**: Actual program type (e.g., "BJJ", "Muay Thai")
- **Age Group**: Selected age group (e.g., "Adults", "Kids")
- **Day**: Selected day (e.g., "Monday", "Wednesday")
- **Class**: Selected class name (e.g., "General Gi Class - 6:00 PM")

## 📝 Files Changed

- `assets/js/script.js` - Enhanced booking request to include full form data
- `includes/class-rest-api.php` - Improved data merging and email formatting
  - `get_enhanced_booking_data()` - Better preservation of booking program details
  - `format_booking_email_html()` - Enhanced program detail extraction with proper fallbacks
  - `check_for_updates()` - Improved debugging and response information
- `includes/class-github-updater.php` - Enhanced update detection system
  - `force_check_updates()` - Now properly injects updates into WordPress update system
  - `check_for_updates()` - Added logging for better debugging
  - Improved cache management and update injection

## 🚀 Impact

- **User Experience**: Booking confirmation emails now contain complete booking information
- **Data Integrity**: All form data is now properly stored and displayed
- **Update Detection**: GitHub releases are now reliably detected and available for update immediately
- **Backward Compatibility**: Changes are fully backward compatible

## ✅ Testing

To verify the fix:

1. Submit a trial class booking through the form
2. Check the booking confirmation email
3. Verify that Program Details section shows:
   - Actual program name (not "N/A")
   - Actual age group (not "N/A")
   - Actual day (not "N/A")
   - Actual class name (not "N/A")

---

**Full Changelog**: https://github.com/Jon3r/pjja-booking/compare/v1.2.0...v1.2.1
