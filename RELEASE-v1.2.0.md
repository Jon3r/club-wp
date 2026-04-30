# Version 1.2.0 - Professional Email Notifications

## 📧 New Features

### HTML Email Notifications
Completely redesigned email notification system with professional HTML formatting:

- **Professional HTML Emails**
  - Beautiful, mobile-responsive HTML email templates
  - Table-based layout for maximum email client compatibility
  - Inline CSS styling (no external dependencies)
  - Clean, organized sections with clear visual hierarchy
  - Dark header with light content sections

- **Enhanced Booking Notifications**
  - All booking details displayed in organized sections:
    - Contact Information (name, email, phone)
    - Program Details (program, age group, day, selected class)
    - Preferences (experience level, goals, contact preference)
    - Booking Information (booking ID, contact key, event ID, status, submission time)
  - Automatically enhances booking data with prospect information from database
  - Professional formatting replaces previous plain text with array dump

- **New Prospect/Contact Notifications**
  - Email notifications now sent for contact/prospect submissions (previously only bookings)
  - Displays all contact information and submission details
  - Same professional HTML formatting as booking emails
  - Respects the same email notification settings

## 🔧 Technical Improvements

- Created `format_booking_email_html()` method for booking email templates
- Created `format_prospect_email_html()` method for prospect email templates
- Added `get_enhanced_booking_data()` helper to merge prospect and booking data
- Updated `send_booking_notification()` to use HTML format with proper headers
- Created `send_prospect_notification()` method for prospect email notifications
- Enhanced `create_prospect()` to send email notifications when enabled
- All email content properly escaped for security
- Plain text fallback prepared (for future multipart support)

## 📝 Files Changed

- `includes/class-rest-api.php` - Added HTML email templates and prospect notifications
  - `format_booking_email_html()` - HTML template for booking emails
  - `format_prospect_email_html()` - HTML template for prospect emails
  - `get_enhanced_booking_data()` - Helper to merge prospect data with bookings
  - Updated `send_booking_notification()` - Now uses HTML format
  - New `send_prospect_notification()` - Sends HTML emails for prospects
  - Updated `create_prospect()` - Now sends email notifications

- `pjja-trial-booking.php` - Version bumped to 1.2.0

## 🚀 How to Use

Email notifications work automatically when enabled:

1. Go to **Trial Booking → Settings** in WordPress admin
2. Ensure **Enable Email Notifications** is checked
3. Set your **Admin Email** address
4. Both booking and prospect submissions will now send professional HTML emails

## 💡 What's Different

### Before (v1.1.0)
- Plain text emails with `print_r()` array dump
- Only booking notifications (no prospect notifications)
- Unprofessional appearance
- Difficult to read on mobile devices

### After (v1.2.0)
- Professional HTML emails with organized sections
- Both booking AND prospect notifications
- Clean, readable format
- Mobile-responsive design
- All fields clearly labeled and organized

## 📊 Email Content

### Booking Emails Include:
- Contact Information (name, email, phone)
- Program Details (program type, age group, day, class)
- Preferences (experience, goals, contact preference)
- Booking Metadata (booking ID, contact key, event ID, status, timestamp)

### Prospect Emails Include:
- Contact Information (name, email, phone)
- Submission Details (status, program interest, contact preference, contact key, timestamp)

## 🔒 Security

- All user input properly escaped using `esc_html()` and `esc_attr()`
- Email addresses validated before sending
- No XSS vulnerabilities in email content

---

**Full Changelog**: https://github.com/Jon3r/pjja-booking/compare/v1.1.0...v1.2.0

