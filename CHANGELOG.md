# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.1] - 2024-12-XX

### Fixed

- **Missing Program Details in Booking Emails**: Fixed issue where booking confirmation emails showed "N/A" for all program details (Program, Age Group, Day, Class)

  - Booking API request now includes full form data (program, preferences, personal info)
  - Enhanced data merging to preserve booking program details
  - Improved email formatting with better handling of empty/null values

- **GitHub Update Detection**: Improved reliability of auto-update detection from GitHub releases
  - Enhanced `force_check_updates()` to properly inject updates into WordPress update system
  - Updates are now immediately available after clicking "Check for Updates"
  - Better cache clearing and update injection
  - Improved logging for debugging update issues

### Technical Details

- Updated `assets/js/script.js` - Enhanced booking request to include complete form data
- Updated `includes/class-rest-api.php`:
  - Improved `get_enhanced_booking_data()` to preserve booking program details
  - Enhanced `format_booking_email_html()` with better fallback logic
  - Improved `check_for_updates()` REST endpoint with better debugging
- Updated `includes/class-github-updater.php`:
  - Enhanced `force_check_updates()` to inject updates into WordPress transient system
  - Added logging to `check_for_updates()` for better debugging

[1.2.1]: https://github.com/Jon3r/pjja-booking/releases/tag/v1.2.1

## [1.2.0] - 2024-12-XX

### Added

- **Professional HTML Email Notifications**:
  - Beautiful, mobile-responsive HTML email templates for booking notifications
  - New email notifications for prospect/contact submissions
  - Organized sections: Contact Information, Program Details, Preferences, Booking Metadata
  - Table-based layout for maximum email client compatibility
  - Inline CSS styling (no external dependencies)

### Changed

- **Email Format**: Replaced plain text emails with `print_r()` output with professional HTML formatting
- **Booking Emails**: Now include all available fields in organized, readable format
- **Prospect Emails**: Now send email notifications (previously only bookings sent emails)

### Technical Details

- Created `format_booking_email_html()` method for booking email templates
- Created `format_prospect_email_html()` method for prospect email templates
- Added `get_enhanced_booking_data()` helper to merge prospect and booking data
- Updated `send_booking_notification()` to use HTML format
- Created `send_prospect_notification()` method
- Enhanced `create_prospect()` to send email notifications

[1.2.0]: https://github.com/Jon3r/pjja-booking/releases/tag/v1.2.0

## [1.1.0] - 2024-12-XX

### Added

- **Form Design Customization**: Complete control over booking form appearance
  - Color customization (buttons, fields, sections)
  - Button text customization
  - Theme integration (automatic color/font inheritance)
  - Custom CSS field for advanced styling

### Technical Details

- Dynamic CSS generation from settings
- Theme color detection via `get_theme_mod()`
- CSS variable system for flexible theming

[1.1.0]: https://github.com/Jon3r/pjja-booking/releases/tag/v1.1.0

## [1.0.0] - 2024-12-19

### Added

- **GitHub Update Integration**: Automatic plugin updates from GitHub releases
  - Checks GitHub Releases API for new versions
  - Displays updates in WordPress admin
  - Configurable via constants in main plugin file
- **Email Testing & Logging**:
  - REST API endpoint for sending test emails (`/test-email`)
  - Email logging system (stores last 50 attempts)
  - Test email buttons in Dashboard and Settings pages
  - Email log display showing success/failure status and error messages
- **CSV Export Functionality**:
  - Export all bookings to CSV format
  - Includes all booking data: contact info, source, medium, class details
  - Excel-compatible format with BOM
  - Accessible from Dashboard Quick Actions

### Changed

- Updated plugin structure to support GitHub updates
- Enhanced email notification system with logging
- Improved admin dashboard with email testing tools

### Technical Details

- Created `includes/class-github-updater.php` for update management
- Added email logging to `includes/class-rest-api.php`
- Implemented CSV export handler in `admin/admin-page.php`
- Added test email UI in both admin pages

[1.0.0]: https://github.com/Jon3r/pjja-booking/releases/tag/v1.0.0
