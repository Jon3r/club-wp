## PJJA Booking v2.0.0

### Highlights

- Fixed timetable runtime stability so the class filters and "Happening now / Up next" banner initialize reliably.
- Added resilient fallback logic for timetable rendering when inline config JSON is missing or invalid.
- Added new timetable design controls in plugin settings for admin-managed styling.
- Introduced brand-color defaults for timetable UI:
  - Primary: `#1914a6`
  - Accent: `#ffbe00`

### Timetable Improvements

- Filter chips now apply state on initial load and use safer click handling.
- Banner update logic now fails safely instead of stopping all timetable behavior.
- Timetable config now supports DOM-based fallback data extraction for classes by day.
- Frontend timetable color system now uses CSS variables for easier customization.

### New Admin Settings

In **PJJA Trial Booking > Settings > Timetable shortcode**:

- Primary color
- Accent color
- Text color
- Card surface color

These fields are sanitized and applied to timetable output as CSS variables.

### Technical Notes

- Updated plugin version to `2.0.0`.
- Improved script dependency behavior for timetable initialization.
- Maintained compatibility with existing timetable shortcode output.

### Upgrade Notes

- Clear any page/cache plugin/CDN cache after update.
- Re-save plugin settings once to ensure new defaults are stored.
