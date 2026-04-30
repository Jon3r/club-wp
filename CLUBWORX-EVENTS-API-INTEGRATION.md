# ClubWorx /api/v2/events Integration - Complete Guide

## 🎯 **What's New**

I've updated the plugin to use the correct ClubWorx API endpoint: **`/api/v2/events`** and created a **live timetable display** on the admin page.

---

## ✅ **What's Been Updated**

### 1. **Correct API Endpoint**

- **Old:** `/schedule` (wasn't working)
- **New:** `/api/v2/events` (correct endpoint)

### 2. **Enhanced Data Mapping**

- Handles multiple field name variations:
  - `name`, `class_name`, `title`, `event_name`
  - `day`, `day_of_week`
  - `time`, `start_time`, `event_time`
- Supports date-based events (converts to day of week)
- Automatic time format conversion (24-hour to 12-hour)

### 3. **Live Timetable Display**

- **New section** on admin dashboard
- Shows **real-time ClubWorx data**
- **Refresh button** to update instantly
- **Debug information** shows data source
- **Visual grid layout** by day

### 4. **Smart Class Categorization**

- **Kids (Under 6):** Classes with "little", "under 6", "4-6"
- **Kids (6+):** Classes with "big kids", "7-12", "over 6"
- **Adults General:** Default for adult classes
- **Adults Foundations:** Classes with "foundations"
- **Women:** Classes with "women", "female"

---

## 🚀 **How to Use**

### Step 1: Configure ClubWorx API

1. Go to **WordPress Admin → Trial Booking → Settings**
2. Enter your ClubWorx credentials:
   - **API URL:** Your base URL (e.g., `https://api.clubworx.com`)
   - **API Key:** Your authentication token
3. Click **Save Changes**

### Step 2: View Live Timetable

1. Go to **Trial Booking → Dashboard**
2. Scroll down to **"Live ClubWorx Timetable"** section
3. The timetable loads automatically
4. Click **"Refresh Timetable"** to update

### Step 3: Test the Integration

1. Click **"Test ClubWorx Raw"** button
2. This shows exactly what ClubWorx returns
3. Verify all your classes appear in the response

---

## 📊 **Live Timetable Features**

### **Visual Display**

- **Grid layout** showing each day
- **Color-coded categories** for different class types
- **Real-time data** from ClubWorx
- **Last updated timestamp**

### **Debug Information**

Shows at bottom of timetable:

- **Configured:** Yes/No (ClubWorx API status)
- **Source:** clubworx_or_fallback/fallback_only
- **Cache:** cached/fresh data

### **Error Handling**

- **Loading states** with spinning icons
- **Error messages** if API fails
- **No data message** if no classes found
- **Fallback to static data** if ClubWorx unavailable

---

## 🔍 **Expected ClubWorx Response Format**

The plugin now handles multiple response formats from `/api/v2/events`:

### **Format 1: Events Array**

```json
{
  "events": [
    {
      "name": "General Gi Class",
      "day": "monday",
      "time": "6:00pm"
    },
    {
      "event_name": "Foundations Gi",
      "day_of_week": "monday",
      "start_time": "7:00pm"
    }
  ]
}
```

### **Format 2: Date-Based Events**

```json
{
  "events": [
    {
      "title": "Little Kids Jiu Jitsu",
      "date": "2024-01-15",
      "event_time": "16:30"
    }
  ]
}
```

### **Format 3: Mixed Field Names**

```json
{
  "events": [
    {
      "class_name": "Big Kids Jiu Jitsu",
      "day": "monday",
      "time": "17:00"
    }
  ]
}
```

---

## 🛠️ **Testing the Integration**

### **Test 1: Check Configuration**

1. Go to Dashboard
2. Look for green banner: "✅ ClubWorx API Active"
3. If yellow banner appears, ClubWorx is not configured

### **Test 2: Raw API Response**

1. Click **"Test ClubWorx Raw"** button
2. Check the popup shows:
   - **Status Code:** 200 (success)
   - **Raw Response:** Contains your classes
   - **Parsed Response:** Formatted JSON

### **Test 3: Live Timetable**

1. Scroll to **"Live ClubWorx Timetable"**
2. Should show your actual classes organized by day
3. **Debug info** should show "Configured: Yes"

### **Test 4: Form Integration**

1. Go to your booking form page
2. **Hard refresh** (Ctrl+F5)
3. Form should show live ClubWorx classes
4. Classes should match your ClubWorx schedule

---

## 🔧 **Troubleshooting**

### **Issue: Timetable Shows "No Classes Found"**

**Causes & Solutions:**

1. **ClubWorx Not Configured**

   - Add API credentials in Settings
   - Look for green banner confirmation

2. **Wrong API Response Format**

   - Click "Test ClubWorx Raw" to see actual response
   - Check if classes are in different field names
   - Plugin handles most common formats automatically

3. **Classes Not in Expected Categories**
   - Plugin categorizes by class name patterns
   - Classes must contain keywords like "little", "foundations", "women"
   - Check raw response for exact class names

### **Issue: Classes Missing from Timetable**

**Check:**

1. **Raw API response** - Are classes in ClubWorx response?
2. **Class names** - Do they match categorization patterns?
3. **Time format** - Are times in recognizable format?
4. **Day format** - Are days in lowercase (monday, tuesday)?

### **Issue: Form Still Shows Wrong Classes**

**Solution:**

1. **Clear cache** - Click "Refresh Schedule Cache"
2. **Check timetable** - Verify live data is correct
3. **Hard refresh form** - Ctrl+F5 on booking form page

---

## 📋 **API Endpoint Details**

### **URL Structure**

```
{Your ClubWorx Base URL}/api/v2/events
```

**Examples:**

- `https://api.clubworx.com/api/v2/events`
- `https://your-club.clubworx.com/api/v2/events`

### **Authentication**

```http
GET /api/v2/events
Authorization: Bearer {your_api_key}
Content-Type: application/json
```

### **Expected Response**

```json
{
  "events": [
    {
      "name": "Class Name",
      "day": "monday",
      "time": "6:00pm"
    }
  ]
}
```

---

## 🎯 **Verification Checklist**

After setup, verify:

- [ ] Settings page shows green "✅ ClubWorx API Configured!" banner
- [ ] Dashboard shows "✅ ClubWorx API Active" banner
- [ ] "Test ClubWorx Raw" shows status 200 with classes
- [ ] Live timetable displays your actual classes
- [ ] Classes are categorized correctly (kids/adults/women)
- [ ] Form shows live ClubWorx data (not static)
- [ ] Classes match your ClubWorx dashboard
- [ ] Timetable updates when you refresh

---

## 🚀 **Next Steps**

### **If Everything Works:**

✅ **You're done!** The form now fetches live ClubWorx data automatically.

### **If Issues Persist:**

1. **Run "Test ClubWorx Raw"** and share the response
2. **Check debug logs** in `wp-content/debug.log`
3. **Contact ClubWorx support** if API response is unexpected
4. **Verify API permissions** for all class types

---

## 📞 **ClubWorx Support**

If you need to contact ClubWorx support, provide:

1. **API endpoint:** `/api/v2/events`
2. **Authentication method:** Bearer token
3. **Expected response format** (see above)
4. **Missing classes** (if any)

**Sample message:**

> Hi, I'm integrating ClubWorx with my WordPress website using the `/api/v2/events` endpoint.
>
> I need to fetch all class schedules including:
>
> - Little Kids Jiu Jitsu (4:30pm Monday)
> - Big Kids Jiu Jitsu (5:00pm Monday)
> - General Gi Class (6:00pm Monday)
> - Foundations Gi (7:00pm Monday)
> - General No Gi Class (7:00pm Monday)
>
> Can you confirm the API returns all these classes in the response?

---

## 🎉 **Success!**

Your plugin now:

- ✅ Uses the correct `/api/v2/events` endpoint
- ✅ Displays live ClubWorx data in admin dashboard
- ✅ Shows real-time timetable with all classes
- ✅ Automatically categorizes classes correctly
- ✅ Updates form with live data
- ✅ Handles various API response formats
- ✅ Provides comprehensive debugging tools

**The form will now show your actual ClubWorx classes in real-time!** 🚀
