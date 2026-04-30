# GA4 Setup Requirements

This document outlines the setup requirements for Google Analytics 4 (GA4) tracking on the PJJA Trial Booking system.

## 1. Vercel Environment Variables

### Required: GA4_API_SECRET
The server-side GA4 Measurement Protocol requires an API secret to be configured in Vercel.

**How to set it up:**
1. Go to your GA4 Property → Admin → Data Streams
2. Click on your Web data stream (G-44L97V68WN)
3. Scroll down to "Measurement Protocol API secrets"
4. Click "Create" to generate a new API secret
5. Copy the secret value
6. Go to Vercel Dashboard → Your Project → Settings → Environment Variables
7. Add new variable:
   - **Name:** `GA4_API_SECRET`
   - **Value:** [paste the API secret from GA4]
   - **Environment:** Production, Preview, Development (select all)
8. Redeploy your application

**Current Status:**
- The code checks for this variable at `api/ga4-measurement.js:5`
- If not set, server-side events will fail but client-side tracking continues
- Check: `api/ga4-measurement.js:77-84` for the warning message

---

## 2. Custom Dimensions Configuration

The following custom dimensions must be registered in your GA4 property to capture attribution data properly.

**How to register custom dimensions:**
1. Go to GA4 → Admin → Custom Definitions → Custom Dimensions
2. Click "Create custom dimension" for each of the following:

### Required Custom Dimensions

| Dimension Name | Parameter Name | Scope | Description |
|---------------|----------------|-------|-------------|
| Lead Source | `lead_source` | Event | Primary lead source classification (direct, social, paid, etc.) |
| UTM Source | `utm_source` | Event | UTM source parameter from URL |
| UTM Medium | `utm_medium` | Event | UTM medium parameter from URL |
| UTM Campaign | `utm_campaign` | Event | UTM campaign parameter from URL |
| Program Interest | `program_interest` | Event | Which program the user is interested in (kids, teens, adults, women) |
| Contact Key | `contact_key` | Event | ClubWorx contact key for linking GA4 to CRM |

**Important:**
- The parameter names MUST match exactly (case-sensitive)
- These match the `custom_map` configuration in `index.html:36-43`
- Custom dimensions can take 24-48 hours to start collecting data after creation

---

## 3. Debug Mode Configuration

Debug mode is now **ENABLED** to help verify events are reaching GA4.

**Current Setting:** `index.html:30`
```javascript
debug_mode: true  // Enable debug mode to verify events in GA4 DebugView
```

**How to test:**
1. Open your trial booking form in a browser
2. Go to GA4 → Admin → DebugView
3. Fill out and submit the form
4. Watch for events appearing in DebugView in real-time:
   - `page_view` - Initial page load
   - `form_start` - User starts filling the form
   - `trial_booking_complete` - User completes booking
   - `conversion` - Conversion event

**After Testing:**
- You can leave debug mode ON for ongoing monitoring
- Or set `debug_mode: false` in production if you prefer
- Debug mode does NOT affect normal GA4 reporting

---

## 4. Event Tracking Configuration

### Events Being Tracked

| Event Name | Trigger | Location | Parameters |
|------------|---------|----------|------------|
| `form_start` | User starts filling form | `script.js:1585-1588` | event_category, event_label |
| `form_submit` | Info-only submission | `script.js:459-463` | event_category, event_label, value |
| `trial_booking_complete` | Booking completed | `script.js:1597-1602` | event_category, event_label, value, currency |
| `conversion` | Conversion event | `attribution-tracker.js:207-214` | send_to, value, currency, custom params |

### Transport Type Fixes
- **REMOVED** `transport_type: 'beacon'` parameter (not officially supported)
- GA4 now automatically uses beacon transport when appropriate
- Changed in: `script.js:464, 1591, 1607`

---

## 5. Event Parameter Limits

GA4 has a **25 parameter limit per event**. The attribution tracker sends many custom parameters.

**Current parameter count:**
- `attribution-tracker.js:163-193` sends ~20+ parameters
- This may exceed the limit and cause data truncation

**Recommendation:**
- Review and prioritize the most critical parameters
- Consider sending detailed attribution data to a separate database
- Use GA4 for high-level metrics only

**Files to review:**
- `attribution-tracker.js:163-193` - Main attribution event parameters

---

## 6. Testing Checklist

Use this checklist to verify GA4 is working correctly:

### Initial Setup
- [ ] `GA4_API_SECRET` environment variable set in Vercel
- [ ] All 6 custom dimensions created in GA4
- [ ] Application redeployed after environment variable changes

### Debug Mode Testing
- [ ] Open form in browser
- [ ] Open GA4 DebugView (Admin → DebugView)
- [ ] Fill out form and watch for events:
  - [ ] `page_view` appears on page load
  - [ ] `form_start` appears on first input
  - [ ] `trial_booking_complete` or `form_submit` appears on submission
  - [ ] Custom parameters visible in event details

### Verification
- [ ] Events appear in DebugView within seconds
- [ ] Custom dimensions show values (not "(not set)")
- [ ] Server-side events logging success (check Vercel logs)
- [ ] No console errors related to GA4

### Production Monitoring (24-48 hours later)
- [ ] Events appear in GA4 Reports → Realtime
- [ ] Custom dimensions populate in GA4 Reports
- [ ] Conversion events tracked correctly
- [ ] Attribution data visible in reports

---

## 7. Troubleshooting

### Events not appearing in DebugView
1. Check console for errors
2. Verify `debug_mode: true` in `index.html:30`
3. Make sure you're looking at the correct GA4 property (G-44L97V68WN)
4. Hard refresh the page (Ctrl+F5)

### Custom dimensions showing "(not set)"
1. Verify dimension parameter names match exactly in GA4
2. Check `custom_map` in `index.html:36-43` matches dimension parameter names
3. Wait 24-48 hours for dimensions to start collecting data
4. Verify events are sending the custom parameters (check DebugView event details)

### Server-side events failing
1. Check Vercel logs for errors
2. Verify `GA4_API_SECRET` is set in environment variables
3. Check `api/ga4-measurement.js` logs for warnings
4. Client-side tracking still works even if server-side fails

---

## 8. Files Modified

This setup guide corresponds to the following code changes:

| File | Change | Line Numbers |
|------|--------|--------------|
| `index.html` | Enabled debug mode | 30 |
| `script.js` | Removed transport_type from form_start | 1585-1590 |
| `script.js` | Removed transport_type from form_submit | 459-465 |
| `script.js` | Removed transport_type from conversion | 1597-1604 |
| `api/ga4-measurement.js` | GA4_API_SECRET configuration | 5, 77-84 |
| `attribution-tracker.js` | Custom parameter mapping | 163-193 |

---

## 9. Next Steps

1. **Immediate:**
   - Set `GA4_API_SECRET` in Vercel environment variables
   - Create the 6 custom dimensions in GA4
   - Test using DebugView

2. **Within 24 hours:**
   - Monitor Vercel logs for any GA4 API errors
   - Verify events appearing in GA4 Realtime reports

3. **Within 48 hours:**
   - Check custom dimensions are populating with data
   - Review GA4 reports for attribution data
   - Consider optimizing event parameters if needed

4. **Ongoing:**
   - Monitor GA4 DebugView periodically
   - Review conversion tracking accuracy
   - Optimize custom parameters based on reporting needs
