<?php
/**
 * Multi-location ClubWorx settings and resolution.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Clubworx_Locations {

    const OPTION_KEY = 'clubworx_integration_settings';
    const META_KEY = '_clubworx_location';

    /**
     * One-time migration from flat option keys to locations.primary.
     */
    public static function migrate_legacy_if_needed() {
        $opt = get_option(self::OPTION_KEY, array());
        if (!empty($opt['locations']) && is_array($opt['locations'])) {
            return;
        }

        $tz = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
        if (empty($tz)) {
            $tz = 'UTC';
        }
        $host = parse_url(home_url(), PHP_URL_HOST);

        $primary = self::default_location_data($tz, __('Primary location', 'clubworx-integration'));

        if (!empty($opt['clubworx_api_url'])) {
            $primary['api_url'] = $opt['clubworx_api_url'];
        }
        if (!empty($opt['clubworx_api_key'])) {
            $primary['api_key'] = $opt['clubworx_api_key'];
        }

        if (isset($opt['analytics_mode'])) {
            $mode = sanitize_text_field($opt['analytics_mode']);
            $primary['analytics']['mode'] = in_array($mode, array('none', 'ga4', 'gtm'), true) ? $mode : 'none';
        }
        if (isset($opt['ga4_measurement_id'])) {
            $primary['analytics']['ga4_measurement_id'] = sanitize_text_field($opt['ga4_measurement_id']);
        }
        if (isset($opt['gtm_container_id'])) {
            $primary['analytics']['gtm_container_id'] = strtoupper(sanitize_text_field($opt['gtm_container_id']));
        }
        if (isset($opt['ga4_api_secret'])) {
            $primary['analytics']['ga4_api_secret'] = sanitize_text_field($opt['ga4_api_secret']);
        }
        if (isset($opt['ga4_currency'])) {
            $cur = strtoupper(sanitize_text_field($opt['ga4_currency']));
            $primary['analytics']['ga4_currency'] = (strlen($cur) === 3) ? $cur : 'USD';
        }
        $primary['analytics']['ga4_debug_mode'] = !empty($opt['ga4_debug_mode']);

        if (isset($opt['club_display_name'])) {
            $primary['branding']['club_display_name'] = sanitize_text_field($opt['club_display_name']);
        }
        if (isset($opt['club_website_url'])) {
            $primary['branding']['club_website_url'] = esc_url_raw($opt['club_website_url']);
        }
        if (isset($opt['post_booking_redirect_url'])) {
            $primary['branding']['post_booking_redirect_url'] = esc_url_raw($opt['post_booking_redirect_url']);
        }
        if (isset($opt['trial_event_description_intro'])) {
            $primary['branding']['trial_event_description_intro'] = sanitize_text_field($opt['trial_event_description_intro']);
        }
        if (isset($opt['ics_uid_domain'])) {
            $primary['branding']['ics_uid_domain'] = preg_replace('/[^a-zA-Z0-9.-]/', '', sanitize_text_field($opt['ics_uid_domain']));
        }

        if (isset($opt['fallback_schedule_json'])) {
            $primary['fallback_schedule_json'] = $opt['fallback_schedule_json'];
        }

        $primary['email']['enabled'] = isset($opt['email_notifications']) ? (bool) $opt['email_notifications'] : true;
        if (isset($opt['admin_email'])) {
            $primary['email']['admin_email'] = sanitize_email($opt['admin_email']);
        }

        $primary['smtp']['enabled'] = isset($opt['smtp_enabled']) ? (bool) $opt['smtp_enabled'] : false;
        foreach (array('host', 'username', 'encryption') as $k) {
            $sk = 'smtp_' . $k;
            if (isset($opt[$sk])) {
                $primary['smtp'][$k] = sanitize_text_field($opt[$sk]);
            }
        }
        if (isset($opt['smtp_port'])) {
            $primary['smtp']['port'] = absint($opt['smtp_port']);
        }
        if (isset($opt['smtp_password'])) {
            $primary['smtp']['password'] = $opt['smtp_password'];
        }
        if (isset($opt['smtp_from_email'])) {
            $primary['smtp']['from_email'] = sanitize_email($opt['smtp_from_email']);
        }
        if (isset($opt['smtp_from_name'])) {
            $primary['smtp']['from_name'] = sanitize_text_field($opt['smtp_from_name']);
        }

        $form_map = array(
            'form_theme_integration' => 'theme_integration',
            'form_primary_button_bg' => 'primary_button_bg',
            'form_primary_button_hover' => 'primary_button_hover',
            'form_primary_button_text' => 'primary_button_text',
            'form_secondary_button_bg' => 'secondary_button_bg',
            'form_secondary_button_hover' => 'secondary_button_hover',
            'form_field_border_color' => 'field_border_color',
            'form_field_focus_color' => 'field_focus_color',
            'form_field_error_color' => 'field_error_color',
            'form_field_bg_color' => 'field_bg_color',
            'form_field_text_color' => 'field_text_color',
            'form_section_bg_color' => 'section_bg_color',
            'form_section_heading_color' => 'section_heading_color',
            'form_label_text_color' => 'label_text_color',
            'form_border_radius' => 'border_radius',
            'form_submit_button_text' => 'submit_button_text',
            'form_secondary_button_text' => 'secondary_button_text',
            'form_custom_css' => 'custom_css',
        );
        foreach ($form_map as $old => $new) {
            if (isset($opt[$old])) {
                $primary['form'][$new] = $opt[$old];
            }
        }

        if (isset($opt['timetable_timezone'])) {
            $primary['timetable']['timezone'] = $opt['timetable_timezone'];
        }
        if (isset($opt['timetable_default_duration_minutes'])) {
            $primary['timetable']['default_duration_minutes'] = absint($opt['timetable_default_duration_minutes']);
        }
        foreach (array('primary_color', 'accent_color', 'text_color', 'surface_color') as $c) {
            $tk = 'timetable_' . $c;
            if (isset($opt[$tk])) {
                $primary['timetable'][$c] = $opt[$tk];
            }
        }

        $opt['locations'] = array('primary' => self::normalize_location($primary));
        $opt['default_location'] = 'primary';

        if (!isset($opt['github']) || !is_array($opt['github'])) {
            $opt['github'] = array(
                'token' => isset($opt['github_token']) ? $opt['github_token'] : '',
                'username' => isset($opt['github_username']) ? $opt['github_username'] : '',
                'repo' => isset($opt['github_repo']) ? $opt['github_repo'] : '',
            );
        }

        update_option(self::OPTION_KEY, $opt);
    }

    /**
     * Default nested shape for one location.
     *
     * @param string $tz Default timetable timezone.
     * @param string $label Human-readable tab title.
     * @return array<string,mixed>
     */
    public static function default_location_data($tz, $label = '') {
        $host = parse_url(home_url(), PHP_URL_HOST);
        return array(
            'label' => $label !== '' ? $label : __('Location', 'clubworx-integration'),
            'api_url' => '',
            'api_key' => '',
            'analytics' => array(
                'mode' => 'none',
                'ga4_measurement_id' => '',
                'gtm_container_id' => '',
                'ga4_api_secret' => '',
                'ga4_currency' => 'USD',
                'ga4_debug_mode' => false,
            ),
            'branding' => array(
                'club_display_name' => get_bloginfo('name'),
                'club_website_url' => home_url('/'),
                'post_booking_redirect_url' => '',
                'trial_event_description_intro' => __('Trial class', 'clubworx-integration'),
                'ics_uid_domain' => $host ? $host : 'localhost',
            ),
            'fallback_schedule_json' => '',
            'email' => array(
                'enabled' => true,
                'admin_email' => get_option('admin_email'),
            ),
            'smtp' => array(
                'enabled' => false,
                'host' => '',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'from_email' => '',
                'from_name' => get_bloginfo('name'),
            ),
            'form' => array(
                'theme_integration' => false,
                'primary_button_bg' => '',
                'primary_button_hover' => '',
                'primary_button_text' => '',
                'secondary_button_bg' => '',
                'secondary_button_hover' => '',
                'field_border_color' => '',
                'field_focus_color' => '',
                'field_error_color' => '',
                'field_bg_color' => '',
                'field_text_color' => '',
                'section_bg_color' => '',
                'section_heading_color' => '',
                'label_text_color' => '',
                'border_radius' => '8px',
                'submit_button_text' => '',
                'secondary_button_text' => '',
                'custom_css' => '',
            ),
            'timetable' => array(
                'timezone' => $tz,
                'default_duration_minutes' => 60,
                'primary_color' => '#1914a6',
                'accent_color' => '#ffbe00',
                'text_color' => '#333333',
                'surface_color' => '#ffffff',
            ),
        );
    }

    /**
     * Merge partial location with defaults and fix types.
     *
     * @param array<string,mixed> $loc
     * @return array<string,mixed>
     */
    public static function normalize_location($loc) {
        $tz = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
        if (empty($tz)) {
            $tz = 'UTC';
        }
        $defaults = self::default_location_data($tz);
        $merged = array_replace_recursive($defaults, is_array($loc) ? $loc : array());

        if (!isset($merged['label']) || $merged['label'] === '') {
            $merged['label'] = __('Location', 'clubworx-integration');
        }

        return $merged;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function all() {
        self::migrate_legacy_if_needed();
        $opt = get_option(self::OPTION_KEY, array());
        $locs = isset($opt['locations']) && is_array($opt['locations']) ? $opt['locations'] : array();
        $out = array();
        foreach ($locs as $slug => $data) {
            $slug = sanitize_key($slug);
            if ($slug === '') {
                continue;
            }
            $out[$slug] = self::normalize_location($data);
        }
        return $out;
    }

    public static function get_default_slug() {
        $opt = get_option(self::OPTION_KEY, array());
        $def = isset($opt['default_location']) ? sanitize_key($opt['default_location']) : 'primary';
        $all = self::all();
        if ($def !== '' && isset($all[$def])) {
            return $def;
        }
        $keys = array_keys($all);
        return !empty($keys) ? $keys[0] : 'primary';
    }

    /**
     * @param string|null $slug Location slug or null for default.
     * @return array<string,mixed>|null
     */
    public static function get($slug = null) {
        $all = self::all();
        if ($slug === null || $slug === '') {
            $slug = self::get_default_slug();
        } else {
            $slug = sanitize_key($slug);
        }
        return isset($all[$slug]) ? $all[$slug] : null;
    }

    /**
     * Read account/locations slug from REST request (query, then JSON body).
     *
     * @return string|null Slug or null if not specified (caller uses default).
     */
    public static function get_account_param_from_request(WP_REST_Request $request) {
        $account = $request->get_param('account');
        if ($account !== null && $account !== '') {
            return sanitize_key($account);
        }
        $json = $request->get_json_params();
        if (is_array($json) && !empty($json['account'])) {
            return sanitize_key($json['account']);
        }
        return null;
    }

    /**
     * Resolve a single location for API handlers; uses default when account omitted.
     *
     * @return array<string,mixed>|WP_Error
     */
    public static function resolve_from_request(WP_REST_Request $request) {
        $slug = self::get_account_param_from_request($request);
        if ($slug === null) {
            $slug = self::get_default_slug();
        }
        $loc = self::get($slug);
        if ($loc === null) {
            return new WP_Error(
                'clubworx_no_location',
                __('Unknown or missing Clubworx location.', 'clubworx-integration'),
                array('status' => 400)
            );
        }
        if (empty($loc['api_url']) || empty($loc['api_key'])) {
            return new WP_Error(
                'clubworx_not_configured',
                __('ClubWorx API is not configured for this location.', 'clubworx-integration'),
                array('status' => 500)
            );
        }
        return array_merge($loc, array('_slug' => $slug));
    }

    /**
     * Post meta or default (for front-end shortcodes / analytics).
     *
     * @param int|null $post_id
     * @return string Slug
     */
    public static function resolve_slug_for_post($post_id = null) {
        $slug = '';
        if ($post_id) {
            $meta = get_post_meta($post_id, self::META_KEY, true);
            if (is_string($meta) && $meta !== '' && $meta !== '__default__') {
                $slug = sanitize_key($meta);
            }
        }
        if ($slug === '' || self::get($slug) === null) {
            return self::get_default_slug();
        }
        return $slug;
    }

    /**
     * Shortcode attr (may be empty, "all", comma list, or single slug).
     *
     * @param string       $account_attr account attribute from shortcode.
     * @param int|null     $post_id      Current post ID.
     * @return string Single slug for booking form.
     */
    public static function resolve_single_account_from_shortcode($account_attr, $post_id = null) {
        $attr = trim((string) $account_attr);
        if ($attr === '') {
            return self::resolve_slug_for_post($post_id);
        }
        if (strtolower($attr) === 'all') {
            return self::get_default_slug();
        }
        $parts = array_map('trim', explode(',', $attr));
        $first = sanitize_key($parts[0]);
        if ($first !== '' && self::get($first) !== null) {
            return $first;
        }
        return self::resolve_slug_for_post($post_id);
    }

    /**
     * Expand account shortcode value to list of location slugs.
     *
     * @param string $account_attr '', single slug, comma list, or 'all'.
     * @param int|null $post_id
     * @return array<int,string>
     */
    public static function expand_account_slugs($account_attr, $post_id = null) {
        $attr = trim((string) $account_attr);
        $all = self::all();
        $keys = array_keys($all);

        if ($attr === '') {
            return array(self::resolve_slug_for_post($post_id));
        }
        if (strtolower($attr) === 'all') {
            return $keys;
        }
        $out = array();
        foreach (array_map('trim', explode(',', $attr)) as $part) {
            $s = sanitize_key($part);
            if ($s !== '' && isset($all[$s])) {
                $out[] = $s;
            }
        }
        return !empty($out) ? $out : array(self::resolve_slug_for_post($post_id));
    }

    /**
     * Slug suitable for new locations from a label.
     */
    public static function slugify($label) {
        $s = sanitize_title($label);
        if ($s === '') {
            $s = 'location-' . wp_generate_password(4, false, false);
        }
        return $s;
    }
}
