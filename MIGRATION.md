# Migrating from PJJA Trial Booking to Clubworx Integration

This release is a **breaking** rebrand and refactor. Use the following checklist when upgrading an existing site (for example Parramatta Jiu Jitsu).

## 1. Plugin entry file and folder

- The WordPress plugin bootstrap file is now **`clubworx-integration.php`** (not `pjja-trial-booking.php`).
- Install or rename the plugin directory so the active plugin points at this file. If you deploy by replacing the plugin folder, ensure only one copy of the plugin is active to avoid duplicate shortcodes and REST routes.

## 2. Shortcodes (update page content)

| Old | New |
|-----|-----|
| `[pjja_trial_booking]` | `[clubworx_trial_booking]` |
| `[pjja_timetable]` | `[clubworx_timetable]` |
| `[pjja_pricing]` | `[clubworx_pricing]` |

Search the database or use the block editor to replace these in all posts and pages.

## 3. Options key

- Settings are stored under the option name **`clubworx_integration_settings`**.
- If you need to copy values manually from the old `pjja_booking_settings` row, export JSON from the database and map fields into the new option, or re-enter values under **Clubworx → Settings**.

## 4. Database tables (bookings and attribution)

If you need to keep existing rows, rename the tables in MySQL (prefix may differ; default `wp_`):

```sql
RENAME TABLE wp_pjja_bookings TO wp_clubworx_bookings;
RENAME TABLE wp_pjja_attribution TO wp_clubworx_attribution;
```

If the new tables are empty and the old names still exist, the plugin will create `wp_clubworx_*` on first use; you would then merge data manually if required.

## 5. REST API

- The REST namespace is now **`clubworx/v1`** (was `pjja-booking/v1`).
- Update any external clients or hardcoded URLs that called the old namespace.

## 6. WordPress filter renames

| Old | New |
|-----|-----|
| `pjja_pricing_fallback_plans` | `clubworx_pricing_fallback_plans` |
| `pjja_smtp_debug` | `clubworx_smtp_debug` |

## 7. Settings to review after upgrade

- **Tagging mode:** None, **GA4 direct**, or **GTM only** — do not duplicate the same GA4 property or GTM container you already load in the theme.
- **GA4 Measurement ID** (when mode is GA4 direct), **GTM Container ID** (when mode is GTM), **reporting currency**, **GA4 API secret** (for Measurement Protocol, optional).
- **Site & booking display:** club name, website URL, optional post-booking redirect, trial intro line, ICS UID domain.
- **Fallback schedule JSON** (optional) if the ClubWorx API is down.
- **GitHub updates:** set username, repository, and token if you use private GitHub releases; plugin constants default to empty.

## 8. Caching and transients

- Schedule cache transient key is now `clubworx_schedule` (was `pjja_clubworx_schedule`). Old transients can be left to expire or deleted manually.

---

After migration, test the booking form, timetable and pricing shortcodes, CSV export from the dashboard, and analytics in GA4 / GTM debug tools to confirm a single tag path and no duplicate hits.
