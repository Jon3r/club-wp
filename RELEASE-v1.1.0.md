# Version 1.1.0 - Form Design Customization & Theme Integration

## 🎨 New Features

### Form Design Customization
Complete control over your booking form's appearance directly from the WordPress admin settings:

- **Color Customization**
  - Primary button colors (background, hover, text)
  - Secondary button colors (background, hover)
  - Form field colors (border, focus, error, background, text)
  - Section colors (background, heading text)
  - Label text color
  - Border radius for buttons and fields

- **Button Text Customization**
  - Customize submit button text
  - Customize secondary button text
  - Falls back to default translations if left empty

- **Theme Integration**
  - Automatically inherit your theme's colors, fonts, spacing, and container width
  - Seamless design integration - removes card borders and backgrounds
  - Custom colors override theme colors when specified
  - Perfect for matching your site's existing design

- **Advanced Customization**
  - Custom CSS field for additional styling
  - All settings are sanitized and validated
  - WordPress color picker integration for easy color selection

## 🔧 Technical Improvements

- Dynamic CSS generation from settings
- Theme color detection via `get_theme_mod()`
- CSS variable system for flexible theming
- Settings sanitization for all form design inputs
- Improved form template with customizable text support

## 📝 Files Changed

- `includes/class-admin-settings.php` - Added form design settings section
- `admin/settings-page.php` - Added color picker integration
- `templates/booking-form.php` - Updated to use customizable button text
- `pjja-trial-booking.php` - Added dynamic CSS generation and theme integration

## 🚀 How to Use

1. Go to **Trial Booking → Settings** in WordPress admin
2. Scroll to the **Form Design & Customization** section
3. Enable **Theme Integration** to automatically match your theme
4. Customize colors using the color pickers
5. Customize button text as needed
6. Add custom CSS for advanced styling (optional)
7. Save settings and view your customized form!

## 💡 Tips

- Enable theme integration first to see how the form looks with your theme
- Then customize specific colors to fine-tune the appearance
- Use the custom CSS field for advanced styling needs
- All color values are validated (hex format required)

---

**Full Changelog**: https://github.com/Jon3r/pjja-booking/compare/v1.0.2...v1.1.0

