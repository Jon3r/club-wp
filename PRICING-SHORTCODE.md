# PJJA Pricing Cards Shortcode

## Overview

The `[pjja_pricing]` shortcode displays membership plans from ClubWorx in modern, responsive pricing cards. It automatically fetches pricing data from the ClubWorx API, caches it for performance, and provides extensive filtering options.

## Features

- ✅ **ClubWorx Integration**: Fetches real-time pricing from ClubWorx API
- ✅ **Smart Caching**: 12-hour transient cache for optimal performance
- ✅ **Graceful Fallback**: Shows default plans when API is unavailable
- ✅ **Advanced Filtering**: Filter by category, keywords, and more
- ✅ **Responsive Design**: Modern cards that work on all devices
- ✅ **SEO Friendly**: Server-side rendering for search engines
- ✅ **Customizable**: Extensive shortcode attributes for customization

## Basic Usage

```
[pjja_pricing]
```

## Advanced Usage Examples

### Filter by Category

```
[pjja_pricing category="adults"]
[pjja_pricing category="kids"]
[pjja_pricing category="women"]
```

### Highlight Popular Plans

```
[pjja_pricing highlight="Foundations,Adults General" button_text="Book Trial" button_link="#trial"]
```

### Keyword Filtering

```
[pjja_pricing includes="gi,foundations" excludes="kids"]
```

### Custom Styling

```
[pjja_pricing title="Choose Your Plan" button_text="Get Started" button_link="/signup"]
```

## Shortcode Attributes

| Attribute     | Default            | Description                                                  |
| ------------- | ------------------ | ------------------------------------------------------------ |
| `category`    | `all`              | Filter by age group: `adults`, `kids`, `women`, `all`        |
| `includes`    | `""`               | Comma-separated keywords to include in plan names            |
| `excludes`    | `""`               | Comma-separated keywords to exclude from plan names          |
| `highlight`   | `""`               | Comma-separated plan names to highlight with "Popular" badge |
| `button_text` | `Sign Up`          | Text for the CTA button                                      |
| `button_link` | `#booking-form`    | URL for the CTA button                                       |
| `title`       | `Membership Plans` | Section title                                                |
| `show_title`  | `true`             | Show/hide the section title                                  |
| `layout`      | `cards`            | Layout type (currently only `cards` supported)               |

## API Integration

### ClubWorx API Endpoint

- **URL**: `GET /api/v2/membership_plans?account_key=YOUR_KEY`
- **Cache**: 12 hours (configurable)
- **Fallback**: Default plans when API fails

### Data Structure

The shortcode expects plans with the following structure:

```json
{
  "id": "plan_id",
  "name": "Plan Name",
  "price": 120.0,
  "currency": "AUD",
  "billing_cycle": "monthly",
  "category": "adults",
  "description": "Plan description",
  "features": []
}
```

## Styling

The shortcode automatically enqueues `assets/css/pricing.css` with modern, responsive styling:

- **Grid Layout**: Responsive cards that adapt to screen size
- **Hover Effects**: Smooth animations and transitions
- **Highlighted Cards**: Special styling for popular plans
- **Mobile Optimized**: Touch-friendly on all devices

### CSS Classes

- `.pjja-pricing` - Main container
- `.pjja-pricing-card` - Individual pricing card
- `.pjja-pricing-card-highlighted` - Highlighted/popular card
- `.pjja-pricing-badge` - "Popular" badge
- `.pjja-pricing-button` - CTA button

## Error Handling

The shortcode gracefully handles various error scenarios:

1. **API Unavailable**: Shows fallback plans
2. **No Plans Found**: Displays "No plans match criteria" message
3. **Invalid Configuration**: Shows "Pricing not available" message
4. **Network Issues**: Falls back to cached data or defaults

## Caching

- **Cache Key**: `pjja_membership_plans`
- **Duration**: 12 hours
- **Bypass**: Add `?refresh=1` to any page with the shortcode
- **Admin**: Cache can be cleared via WordPress admin

## Hooks and Filters

### Filters

```php
// Provide custom fallback plans
add_filter('pjja_pricing_fallback_plans', function($plans) {
    return [
        [
            'id' => 'custom-1',
            'name' => 'Custom Plan',
            'price' => 150.00,
            'currency' => 'AUD',
            'billing_cycle' => 'monthly',
            'category' => 'adults',
            'description' => 'Custom plan description',
            'features' => []
        ]
    ];
});
```

## Troubleshooting

### Common Issues

1. **No Plans Showing**

   - Check ClubWorx API configuration
   - Verify API key and URL settings
   - Check error logs for API issues

2. **Styling Issues**

   - Ensure `pricing.css` is loading
   - Check for theme CSS conflicts
   - Verify shortcode is properly registered

3. **Filtering Not Working**
   - Check attribute spelling and values
   - Verify plan names match filter criteria
   - Test with `category="all"` first

### Debug Mode

Enable WordPress debug logging to see detailed API calls and responses:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Performance

- **Server-Side Rendering**: SEO-friendly, no JavaScript required
- **Caching**: Reduces API calls and improves load times
- **Minimal CSS**: Lightweight stylesheet (~8KB)
- **Responsive Images**: Optimized for all screen sizes

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Future Enhancements

- Table layout option
- More filtering options
- Plan comparison features
- Integration with WooCommerce
- Multi-currency support
- Plan feature lists
- Pricing calculators

## Support

For issues or questions:

1. Check the error logs
2. Verify ClubWorx API configuration
3. Test with basic shortcode first
4. Contact plugin support

---

**Version**: 1.0.0  
**Last Updated**: January 2025  
**Compatibility**: WordPress 5.8+, PHP 7.4+
