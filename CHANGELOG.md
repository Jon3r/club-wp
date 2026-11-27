# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.5.5] - 2026-11-27

### Changed

- Version bump (no functional changes from 3.5.4).

## [3.5.4] - 2026-11-27

### Fixed

- Release packaging: 3.5.3 was tagged on a commit still labelled 3.5.1, so no installable zip was published and WordPress could not find the update. This release re-publishes the 3.5.3 changes under a new version.

## [3.5.3] - 2026-11-27

### Fixed

- Prospect "status is invalid": when ClubWorx rejects the status, the plugin now reads the account's own statuses (`/prospect_statuses`) and retries with the first one (cached 24 hours per location), falling back to no status if the list cannot be read.

## [3.5.2] - 2026-11-27

### Fixed

- **"Error creating new prospect: status is invalid."**: prospect statuses are customised per ClubWorx account, so "Initial Contact" can be rejected. The prospects endpoint now retries without a status (it is optional) and remembers that for the location for 24 hours.
- Prospects that ClubWorx rejects are no longer stored locally or emailed as if they had been created.

## [3.5.1] - 2026-11-27

### Fixed

- **Misleading "ClubWorx integration error"**: the booking form now shows the real reason (no matching class, timetable unreachable, ClubWorx rejected the booking, or contact details not saved) instead of one generic message. Full detail is logged to the browser console.
- If the prospect cannot be created in ClubWorx, the booking stops with that error instead of continuing with a made-up contact key that ClubWorx always rejects.

## [3.5.0] - 2026-11-27

### Added

- **Facebook (Meta) Pixel tracking**: new per-location "Facebook Pixel ID" field under Settings → Analytics.
  - Works with any tagging mode (none, GA4, GTM) and for the booking, master and contact forms.
  - Sends `Lead` on every form submission and `Schedule` when a trial class is booked (with an `eventID` for future server-side deduplication).
  - Loads the pixel library only if it isn't already on the page; `PageView` is sent only when the plugin loaded the library itself, to avoid double counting with GTM or other plugins.

## [3.4.0] - 2026-11-26

### Added

- **Contact form shortcode** `[clubworx_contact_form]`: collects first name, last name, email and phone and creates a ClubWorx prospect via `clubworx/v1/prospects`.
  - Location dropdown like the master booking form; optional `account="slug-one,slug-two"` (or `all`). With a single location the dropdown is hidden and the location preselected.
  - Inline thank-you message after submit (no redirect); attribution tracking and analytics event on success.
  - Honeypot spam protection on the client and in the `prospects` REST endpoint.

### Changed

- Booking assets are split into shared styles/tracker/settings and the booking-only `script.js`, so the contact form loads without the booking script.

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
