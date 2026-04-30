<?php
/**
 * Admin Settings Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class Clubworx_Admin_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('phpmailer_init', array($this, 'configure_smtp'));
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('clubworx_integration_settings_group', 'clubworx_integration_settings', array($this, 'sanitize_settings'));
        
        // Analytics (GA4 direct or GTM — mutually exclusive on the front end)
        add_settings_section(
            'clubworx_ga4_settings',
            __('Analytics (GA4 / GTM)', 'clubworx-integration'),
            array($this, 'ga4_section_callback'),
            'clubworx-integration-settings'
        );

        add_settings_field(
            'analytics_mode',
            __('Tagging mode', 'clubworx-integration'),
            array($this, 'analytics_mode_callback'),
            'clubworx-integration-settings',
            'clubworx_ga4_settings'
        );

        add_settings_field(
            'ga4_measurement_id',
            __('GA4 Measurement ID', 'clubworx-integration'),
            array($this, 'ga4_measurement_id_callback'),
            'clubworx-integration-settings',
            'clubworx_ga4_settings'
        );

        add_settings_field(
            'gtm_container_id',
            __('GTM Container ID', 'clubworx-integration'),
            array($this, 'gtm_container_id_callback'),
            'clubworx-integration-settings',
            'clubworx_ga4_settings'
        );

        add_settings_field(
            'ga4_api_secret',
            __('GA4 API Secret', 'clubworx-integration'),
            array($this, 'ga4_api_secret_callback'),
            'clubworx-integration-settings',
            'clubworx_ga4_settings'
        );

        add_settings_field(
            'ga4_currency',
            __('Reporting currency code', 'clubworx-integration'),
            array($this, 'ga4_currency_callback'),
            'clubworx-integration-settings',
            'clubworx_ga4_settings'
        );

        add_settings_field(
            'ga4_debug_mode',
            __('GA4 Debug Mode', 'clubworx-integration'),
            array($this, 'ga4_debug_mode_callback'),
            'clubworx-integration-settings',
            'clubworx_ga4_settings'
        );

        add_settings_section(
            'clubworx_site_branding',
            __('Site & booking display', 'clubworx-integration'),
            array($this, 'site_branding_section_callback'),
            'clubworx-integration-settings'
        );

        add_settings_field(
            'club_display_name',
            __('Club / venue display name', 'clubworx-integration'),
            array($this, 'club_display_name_callback'),
            'clubworx-integration-settings',
            'clubworx_site_branding'
        );

        add_settings_field(
            'club_website_url',
            __('Club website URL', 'clubworx-integration'),
            array($this, 'club_website_url_callback'),
            'clubworx-integration-settings',
            'clubworx_site_branding'
        );

        add_settings_field(
            'post_booking_redirect_url',
            __('After successful booking, redirect to (optional)', 'clubworx-integration'),
            array($this, 'post_booking_redirect_url_callback'),
            'clubworx-integration-settings',
            'clubworx_site_branding'
        );

        add_settings_field(
            'trial_event_description_intro',
            __('Trial event intro line (calendar descriptions)', 'clubworx-integration'),
            array($this, 'trial_event_description_intro_callback'),
            'clubworx-integration-settings',
            'clubworx_site_branding'
        );

        add_settings_field(
            'ics_uid_domain',
            __('ICS calendar UID domain', 'clubworx-integration'),
            array($this, 'ics_uid_domain_callback'),
            'clubworx-integration-settings',
            'clubworx_site_branding'
        );

        // ClubWorx Settings Section
        add_settings_section(
            'clubworx_api_settings',
            __('ClubWorx API Settings', 'clubworx-integration'),
            array($this, 'clubworx_section_callback'),
            'clubworx-integration-settings'
        );
        
        add_settings_field(
            'clubworx_api_url',
            __('ClubWorx API URL', 'clubworx-integration'),
            array($this, 'clubworx_api_url_callback'),
            'clubworx-integration-settings',
            'clubworx_api_settings'
        );
        
        add_settings_field(
            'clubworx_api_key',
            __('ClubWorx API Key', 'clubworx-integration'),
            array($this, 'clubworx_api_key_callback'),
            'clubworx-integration-settings',
            'clubworx_api_settings'
        );

        add_settings_field(
            'fallback_schedule_json',
            __('Fallback schedule JSON (optional)', 'clubworx-integration'),
            array($this, 'fallback_schedule_json_callback'),
            'clubworx-integration-settings',
            'clubworx_api_settings'
        );

        // GitHub Settings Section (for private repository updates)
        add_settings_section(
            'clubworx_github_settings',
            __('GitHub Update Settings', 'clubworx-integration'),
            array($this, 'github_section_callback'),
            'clubworx-integration-settings'
        );
        
        add_settings_field(
            'github_token',
            __('GitHub Personal Access Token', 'clubworx-integration'),
            array($this, 'github_token_callback'),
            'clubworx-integration-settings',
            'clubworx_github_settings'
        );

        add_settings_field(
            'github_username',
            __('GitHub organization or username', 'clubworx-integration'),
            array($this, 'github_username_callback'),
            'clubworx-integration-settings',
            'clubworx_github_settings'
        );

        add_settings_field(
            'github_repo',
            __('GitHub repository name', 'clubworx-integration'),
            array($this, 'github_repo_callback'),
            'clubworx-integration-settings',
            'clubworx_github_settings'
        );

        // Email Settings Section
        add_settings_section(
            'clubworx_email_settings',
            __('Email Notification Settings', 'clubworx-integration'),
            array($this, 'email_section_callback'),
            'clubworx-integration-settings'
        );
        
        add_settings_field(
            'email_notifications',
            __('Enable Email Notifications', 'clubworx-integration'),
            array($this, 'email_notifications_callback'),
            'clubworx-integration-settings',
            'clubworx_email_settings'
        );
        
        add_settings_field(
            'admin_email',
            __('Admin Email', 'clubworx-integration'),
            array($this, 'admin_email_callback'),
            'clubworx-integration-settings',
            'clubworx_email_settings'
        );
        
        // SMTP Settings Section
        add_settings_section(
            'clubworx_smtp_settings',
            __('SMTP Configuration', 'clubworx-integration'),
            array($this, 'smtp_section_callback'),
            'clubworx-integration-settings'
        );
        
        add_settings_field(
            'smtp_enabled',
            __('Enable SMTP', 'clubworx-integration'),
            array($this, 'smtp_enabled_callback'),
            'clubworx-integration-settings',
            'clubworx_smtp_settings'
        );
        
        add_settings_field(
            'smtp_host',
            __('SMTP Host', 'clubworx-integration'),
            array($this, 'smtp_host_callback'),
            'clubworx-integration-settings',
            'clubworx_smtp_settings'
        );
        
        add_settings_field(
            'smtp_port',
            __('SMTP Port', 'clubworx-integration'),
            array($this, 'smtp_port_callback'),
            'clubworx-integration-settings',
            'clubworx_smtp_settings'
        );
        
        add_settings_field(
            'smtp_encryption',
            __('Encryption', 'clubworx-integration'),
            array($this, 'smtp_encryption_callback'),
            'clubworx-integration-settings',
            'clubworx_smtp_settings'
        );
        
        add_settings_field(
            'smtp_username',
            __('SMTP Username', 'clubworx-integration'),
            array($this, 'smtp_username_callback'),
            'clubworx-integration-settings',
            'clubworx_smtp_settings'
        );
        
        add_settings_field(
            'smtp_password',
            __('SMTP Password', 'clubworx-integration'),
            array($this, 'smtp_password_callback'),
            'clubworx-integration-settings',
            'clubworx_smtp_settings'
        );
        
        add_settings_field(
            'smtp_from_email',
            __('From Email', 'clubworx-integration'),
            array($this, 'smtp_from_email_callback'),
            'clubworx-integration-settings',
            'clubworx_smtp_settings'
        );
        
        add_settings_field(
            'smtp_from_name',
            __('From Name', 'clubworx-integration'),
            array($this, 'smtp_from_name_callback'),
            'clubworx-integration-settings',
            'clubworx_smtp_settings'
        );
        
        // Form Design Settings Section
        add_settings_section(
            'clubworx_form_design_settings',
            __('Form Design & Customization', 'clubworx-integration'),
            array($this, 'form_design_section_callback'),
            'clubworx-integration-settings'
        );
        
        add_settings_field(
            'form_theme_integration',
            __('Theme Integration', 'clubworx-integration'),
            array($this, 'form_theme_integration_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_primary_button_bg',
            __('Primary Button Background', 'clubworx-integration'),
            array($this, 'form_primary_button_bg_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_primary_button_hover',
            __('Primary Button Hover', 'clubworx-integration'),
            array($this, 'form_primary_button_hover_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_primary_button_text',
            __('Primary Button Text Color', 'clubworx-integration'),
            array($this, 'form_primary_button_text_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_secondary_button_bg',
            __('Secondary Button Background', 'clubworx-integration'),
            array($this, 'form_secondary_button_bg_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_secondary_button_hover',
            __('Secondary Button Hover', 'clubworx-integration'),
            array($this, 'form_secondary_button_hover_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_field_border_color',
            __('Field Border Color', 'clubworx-integration'),
            array($this, 'form_field_border_color_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_field_focus_color',
            __('Field Focus Color', 'clubworx-integration'),
            array($this, 'form_field_focus_color_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_field_error_color',
            __('Field Error Color', 'clubworx-integration'),
            array($this, 'form_field_error_color_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_field_bg_color',
            __('Field Background Color', 'clubworx-integration'),
            array($this, 'form_field_bg_color_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_field_text_color',
            __('Field Text Color', 'clubworx-integration'),
            array($this, 'form_field_text_color_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_section_bg_color',
            __('Section Background Color', 'clubworx-integration'),
            array($this, 'form_section_bg_color_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_section_heading_color',
            __('Section Heading Color', 'clubworx-integration'),
            array($this, 'form_section_heading_color_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_label_text_color',
            __('Label Text Color', 'clubworx-integration'),
            array($this, 'form_label_text_color_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_border_radius',
            __('Border Radius', 'clubworx-integration'),
            array($this, 'form_border_radius_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_submit_button_text',
            __('Submit Button Text', 'clubworx-integration'),
            array($this, 'form_submit_button_text_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_secondary_button_text',
            __('Secondary Button Text', 'clubworx-integration'),
            array($this, 'form_secondary_button_text_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        add_settings_field(
            'form_custom_css',
            __('Custom CSS', 'clubworx-integration'),
            array($this, 'form_custom_css_callback'),
            'clubworx-integration-settings',
            'clubworx_form_design_settings'
        );
        
        // Timetable (shortcode) settings
        add_settings_section(
            'clubworx_timetable_settings',
            __('Timetable shortcode', 'clubworx-integration'),
            array($this, 'timetable_section_callback'),
            'clubworx-integration-settings'
        );
        
        add_settings_field(
            'timetable_timezone',
            __('Academy timezone', 'clubworx-integration'),
            array($this, 'timetable_timezone_callback'),
            'clubworx-integration-settings',
            'clubworx_timetable_settings'
        );
        
        add_settings_field(
            'timetable_default_duration_minutes',
            __('Default class length (minutes)', 'clubworx-integration'),
            array($this, 'timetable_default_duration_callback'),
            'clubworx-integration-settings',
            'clubworx_timetable_settings'
        );

        add_settings_field(
            'timetable_primary_color',
            __('Primary color', 'clubworx-integration'),
            array($this, 'timetable_primary_color_callback'),
            'clubworx-integration-settings',
            'clubworx_timetable_settings'
        );

        add_settings_field(
            'timetable_accent_color',
            __('Accent color', 'clubworx-integration'),
            array($this, 'timetable_accent_color_callback'),
            'clubworx-integration-settings',
            'clubworx_timetable_settings'
        );

        add_settings_field(
            'timetable_text_color',
            __('Text color', 'clubworx-integration'),
            array($this, 'timetable_text_color_callback'),
            'clubworx-integration-settings',
            'clubworx_timetable_settings'
        );

        add_settings_field(
            'timetable_surface_color',
            __('Card surface color', 'clubworx-integration'),
            array($this, 'timetable_surface_color_callback'),
            'clubworx-integration-settings',
            'clubworx_timetable_settings'
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        if (isset($input['analytics_mode'])) {
            $mode = sanitize_text_field($input['analytics_mode']);
            $sanitized['analytics_mode'] = in_array($mode, array('none', 'ga4', 'gtm'), true) ? $mode : 'none';
        }

        if (isset($input['gtm_container_id'])) {
            $sanitized['gtm_container_id'] = strtoupper(sanitize_text_field($input['gtm_container_id']));
        }

        if (isset($input['ga4_measurement_id'])) {
            $sanitized['ga4_measurement_id'] = sanitize_text_field($input['ga4_measurement_id']);
        }

        if (isset($input['ga4_currency'])) {
            $cur = strtoupper(sanitize_text_field($input['ga4_currency']));
            $sanitized['ga4_currency'] = (strlen($cur) === 3) ? $cur : 'USD';
        }

        if (isset($input['club_display_name'])) {
            $sanitized['club_display_name'] = sanitize_text_field($input['club_display_name']);
        }

        if (isset($input['club_website_url'])) {
            $sanitized['club_website_url'] = esc_url_raw($input['club_website_url']);
        }

        if (isset($input['post_booking_redirect_url'])) {
            $sanitized['post_booking_redirect_url'] = esc_url_raw($input['post_booking_redirect_url']);
        }

        if (isset($input['trial_event_description_intro'])) {
            $sanitized['trial_event_description_intro'] = sanitize_text_field($input['trial_event_description_intro']);
        }

        if (isset($input['ics_uid_domain'])) {
            $sanitized['ics_uid_domain'] = preg_replace('/[^a-zA-Z0-9.-]/', '', sanitize_text_field($input['ics_uid_domain']));
        }

        if (isset($input['fallback_schedule_json'])) {
            $raw = trim(wp_unslash($input['fallback_schedule_json']));
            if ($raw === '') {
                $sanitized['fallback_schedule_json'] = '';
            } else {
                $decoded = json_decode($raw, true);
                $sanitized['fallback_schedule_json'] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                    ? wp_json_encode($decoded)
                    : '';
            }
        }

        if (isset($input['github_username'])) {
            $sanitized['github_username'] = sanitize_text_field($input['github_username']);
        }

        if (isset($input['github_repo'])) {
            $sanitized['github_repo'] = sanitize_text_field($input['github_repo']);
        }
        
        if (isset($input['ga4_api_secret'])) {
            $sanitized['ga4_api_secret'] = sanitize_text_field($input['ga4_api_secret']);
        }
        
        $sanitized['ga4_debug_mode'] = isset($input['ga4_debug_mode']) ? (bool)$input['ga4_debug_mode'] : false;
        
        if (isset($input['clubworx_api_url'])) {
            $sanitized['clubworx_api_url'] = esc_url_raw($input['clubworx_api_url']);
        }
        
        if (isset($input['clubworx_api_key'])) {
            $sanitized['clubworx_api_key'] = sanitize_text_field($input['clubworx_api_key']);
        }
        
        // GitHub token (required for private repositories)
        if (isset($input['github_token']) && !empty($input['github_token'])) {
            $sanitized['github_token'] = sanitize_text_field($input['github_token']);
        } else {
            // Keep existing token if field is empty (don't overwrite)
            $existing_settings = get_option('clubworx_integration_settings', array());
            if (isset($existing_settings['github_token'])) {
                $sanitized['github_token'] = $existing_settings['github_token'];
            }
        }
        
        $sanitized['email_notifications'] = isset($input['email_notifications']) ? (bool)$input['email_notifications'] : false;
        
        if (isset($input['admin_email'])) {
            $sanitized['admin_email'] = sanitize_email($input['admin_email']);
        }
        
        // SMTP settings
        $sanitized['smtp_enabled'] = isset($input['smtp_enabled']) ? (bool)$input['smtp_enabled'] : false;
        
        if (isset($input['smtp_host'])) {
            $sanitized['smtp_host'] = sanitize_text_field($input['smtp_host']);
        }
        
        if (isset($input['smtp_port'])) {
            $sanitized['smtp_port'] = absint($input['smtp_port']);
        }
        
        if (isset($input['smtp_encryption'])) {
            $sanitized['smtp_encryption'] = sanitize_text_field($input['smtp_encryption']);
        }
        
        if (isset($input['smtp_username'])) {
            $sanitized['smtp_username'] = sanitize_text_field($input['smtp_username']);
        }
        
        // Password handling: keep existing password if field is empty
        $existing_settings = get_option('clubworx_integration_settings', array());
        if (isset($input['smtp_password']) && !empty($input['smtp_password'])) {
            // Only update password if a new one is provided
            $sanitized['smtp_password'] = $input['smtp_password'];
        } elseif (isset($existing_settings['smtp_password'])) {
            // Keep existing password if field is empty or not provided
            $sanitized['smtp_password'] = $existing_settings['smtp_password'];
        }
        
        if (isset($input['smtp_from_email'])) {
            $sanitized['smtp_from_email'] = sanitize_email($input['smtp_from_email']);
        }
        
        if (isset($input['smtp_from_name'])) {
            $sanitized['smtp_from_name'] = sanitize_text_field($input['smtp_from_name']);
        }
        
        // Form design settings
        $sanitized['form_theme_integration'] = isset($input['form_theme_integration']) ? (bool)$input['form_theme_integration'] : false;
        
        // Color fields - validate hex colors
        $color_fields = array(
            'form_primary_button_bg',
            'form_primary_button_hover',
            'form_primary_button_text',
            'form_secondary_button_bg',
            'form_secondary_button_hover',
            'form_field_border_color',
            'form_field_focus_color',
            'form_field_error_color',
            'form_field_bg_color',
            'form_field_text_color',
            'form_section_bg_color',
            'form_section_heading_color',
            'form_label_text_color',
            'timetable_primary_color',
            'timetable_accent_color',
            'timetable_text_color',
            'timetable_surface_color'
        );
        
        foreach ($color_fields as $field) {
            if (isset($input[$field])) {
                $color = sanitize_text_field($input[$field]);
                // Validate hex color format
                if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
                    $sanitized[$field] = $color;
                } elseif (empty($color)) {
                    $sanitized[$field] = '';
                }
            }
        }
        
        // Border radius
        if (isset($input['form_border_radius'])) {
            $radius = sanitize_text_field($input['form_border_radius']);
            // Allow px, em, rem, or numeric values
            if (preg_match('/^(\d+(\.\d+)?)(px|em|rem|%)?$/', $radius) || empty($radius)) {
                $sanitized['form_border_radius'] = $radius;
            }
        }
        
        // Button text
        if (isset($input['form_submit_button_text'])) {
            $sanitized['form_submit_button_text'] = sanitize_text_field($input['form_submit_button_text']);
        }
        
        if (isset($input['form_secondary_button_text'])) {
            $sanitized['form_secondary_button_text'] = sanitize_text_field($input['form_secondary_button_text']);
        }
        
        // Custom CSS - sanitize but allow safe CSS
        if (isset($input['form_custom_css'])) {
            $css = wp_strip_all_tags($input['form_custom_css']);
            // Remove dangerous CSS properties
            $dangerous = array('expression', 'javascript:', 'import', '@import', 'behavior', 'binding');
            foreach ($dangerous as $danger) {
                $css = str_ireplace($danger, '', $css);
            }
            $sanitized['form_custom_css'] = $css;
        }
        
        // Timetable
        if (isset($input['timetable_timezone'])) {
            $tz = sanitize_text_field($input['timetable_timezone']);
            $identifiers = timezone_identifiers_list();
            $fallback_tz = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
            if (empty($fallback_tz)) {
                $fallback_tz = 'UTC';
            }
            $sanitized['timetable_timezone'] = in_array($tz, $identifiers, true) ? $tz : $fallback_tz;
        }
        
        if (isset($input['timetable_default_duration_minutes'])) {
            $dur = absint($input['timetable_default_duration_minutes']);
            $sanitized['timetable_default_duration_minutes'] = max(15, min(240, $dur > 0 ? $dur : 60));
        }
        
        if (!isset($sanitized['timetable_timezone']) && isset($existing_settings['timetable_timezone'])) {
            $sanitized['timetable_timezone'] = $existing_settings['timetable_timezone'];
        }
        
        if (!isset($sanitized['timetable_default_duration_minutes']) && isset($existing_settings['timetable_default_duration_minutes'])) {
            $sanitized['timetable_default_duration_minutes'] = $existing_settings['timetable_default_duration_minutes'];
        }
        
        return $sanitized;
    }
    
    // Section callbacks
    public function ga4_section_callback() {
        echo '<p>' . __('Choose one tagging mode. Do not load GA4 here if your theme already injects the same GTM container or GA4 property — use None and keep theme tags.', 'clubworx-integration') . '</p>';
    }

    public function site_branding_section_callback() {
        echo '<p>' . __('Used in calendar downloads, ICS files, and post-booking redirects.', 'clubworx-integration') . '</p>';
    }
    
    public function clubworx_section_callback() {
        echo '<p>' . __('Configure ClubWorx API integration for booking management.', 'clubworx-integration') . '</p>';
    }
    
    public function github_section_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $github_username = !empty($settings['github_username'])
            ? $settings['github_username']
            : (defined('CLUBWORX_INTEGRATION_GITHUB_USERNAME') ? CLUBWORX_INTEGRATION_GITHUB_USERNAME : '');
        $github_repo = !empty($settings['github_repo'])
            ? $settings['github_repo']
            : (defined('CLUBWORX_INTEGRATION_GITHUB_REPO') ? CLUBWORX_INTEGRATION_GITHUB_REPO : '');
        $has_token = !empty($settings['github_token']);
        
        echo '<p>' . __('Configure GitHub authentication for automatic plugin updates from private repositories.', 'clubworx-integration') . '</p>';
        if ($github_username !== '' && $github_repo !== '') {
            echo '<p><strong>Repository:</strong> <code>' . esc_html($github_username) . '/' . esc_html($github_repo) . '</code></p>';
        } else {
            echo '<p>' . __('Set GitHub username and repository below (or define constants in wp-config). Updates are disabled until both are set.', 'clubworx-integration') . '</p>';
        }
        
        if ($has_token) {
            echo '<div class="notice notice-success inline" style="padding: 10px; margin: 10px 0;"><p><strong>✅ GitHub token configured.</strong> The plugin can now check for updates from your private repository.</p></div>';
        } else {
            echo '<div class="notice notice-warning inline" style="padding: 10px; margin: 10px 0;"><p><strong>⚠️ GitHub token required.</strong> Since your repository is private, you need to provide a GitHub Personal Access Token to enable automatic updates.</p></div>';
        }
    }
    
    public function github_token_callback() {
        $settings = get_option('clubworx_integration_settings');
        $value = isset($settings['github_token']) ? $settings['github_token'] : '';
        // Show masked value if token exists
        $display_value = !empty($value) ? str_repeat('•', min(strlen($value), 40)) : '';
        echo '<input type="password" name="clubworx_integration_settings[github_token]" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . (!empty($value) ? $display_value : '') . '" />';
        echo '<p class="description">';
        echo __('Required for private repositories. ', 'clubworx-integration');
        echo '<a href="https://github.com/settings/tokens/new?scopes=repo&description=Clubworx%20Integration%20Updates" target="_blank">';
        echo __('Create a GitHub Personal Access Token', 'clubworx-integration');
        echo '</a>';
        echo __(' with <code>repo</code> scope. Leave empty to keep existing token.', 'clubworx-integration');
        echo '</p>';
    }
    
    public function email_section_callback() {
        echo '<p>' . __('Configure email notification settings.', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_section_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $smtp_enabled = isset($settings['smtp_enabled']) && $settings['smtp_enabled'];
        
        if ($smtp_enabled) {
            echo '<div class="notice notice-success inline" style="padding: 10px; margin: 10px 0;"><p><strong>✅ SMTP is enabled.</strong> Emails will be sent using the configured SMTP server.</p></div>';
        } else {
            echo '<div class="notice notice-info inline" style="padding: 10px; margin: 10px 0;"><p>' . __('Configure SMTP settings to send emails through an SMTP server instead of PHP mail(). This is recommended for better deliverability.', 'clubworx-integration') . '</p></div>';
        }
        echo '<p>' . __('Configure SMTP server settings for sending emails. Leave password field empty to keep existing password.', 'clubworx-integration') . '</p>';
    }
    
    public function form_design_section_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $theme_integration = isset($settings['form_theme_integration']) && $settings['form_theme_integration'];
        
        echo '<p>' . __('Customize the appearance of the booking form to match your site design.', 'clubworx-integration') . '</p>';
        
        if ($theme_integration) {
            echo '<div class="notice notice-success inline" style="padding: 10px; margin: 10px 0;"><p><strong>✅ Theme Integration Enabled.</strong> The form will inherit colors, fonts, spacing, and container width from your active theme.</p></div>';
        } else {
            echo '<div class="notice notice-info inline" style="padding: 10px; margin: 10px 0;"><p>' . __('Enable theme integration to automatically inherit your theme\'s colors, fonts, and styling. You can still override specific colors below.', 'clubworx-integration') . '</p></div>';
        }
    }
    
    public function timetable_section_callback() {
        echo '<p>' . __('Used by the <code>[clubworx_timetable]</code> shortcode for “Happening now / Up next” (venue clock), default class length when end times are not available, and timetable color styling.', 'clubworx-integration') . '</p>';
    }
    
    public function timetable_timezone_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $tz_default = function_exists('wp_timezone_string') && wp_timezone_string() ? wp_timezone_string() : 'UTC';
        $value = isset($settings['timetable_timezone']) ? $settings['timetable_timezone'] : $tz_default;
        $identifiers = timezone_identifiers_list();
        echo '<select name="clubworx_integration_settings[timetable_timezone]" class="regular-text">';
        foreach ($identifiers as $id) {
            echo '<option value="' . esc_attr($id) . '"' . selected($value, $id, false) . '>' . esc_html($id) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('IANA timezone for the academy (not the visitor’s browser).', 'clubworx-integration') . '</p>';
    }
    
    public function timetable_default_duration_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['timetable_default_duration_minutes']) ? absint($settings['timetable_default_duration_minutes']) : 60;
        if ($value < 15) {
            $value = 60;
        }
        echo '<input type="number" name="clubworx_integration_settings[timetable_default_duration_minutes]" value="' . esc_attr($value) . '" min="15" max="240" step="5" class="small-text" />';
        echo '<p class="description">' . __('Used to decide when a class is “on now” when ClubWorx does not provide an end time.', 'clubworx-integration') . '</p>';
    }

    public function timetable_primary_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['timetable_primary_color']) ? $settings['timetable_primary_color'] : '#1914a6';
        echo '<input type="text" name="clubworx_integration_settings[timetable_primary_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#1914a6" />';
        echo '<p class="description">' . __('Primary timetable brand color (banner and active filter states).', 'clubworx-integration') . '</p>';
    }

    public function timetable_accent_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['timetable_accent_color']) ? $settings['timetable_accent_color'] : '#ffbe00';
        echo '<input type="text" name="clubworx_integration_settings[timetable_accent_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#ffbe00" />';
        echo '<p class="description">' . __('Accent color for highlights and key details.', 'clubworx-integration') . '</p>';
    }

    public function timetable_text_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['timetable_text_color']) ? $settings['timetable_text_color'] : '#333333';
        echo '<input type="text" name="clubworx_integration_settings[timetable_text_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#333333" />';
        echo '<p class="description">' . __('Base text color used across timetable cards.', 'clubworx-integration') . '</p>';
    }

    public function timetable_surface_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['timetable_surface_color']) ? $settings['timetable_surface_color'] : '#ffffff';
        echo '<input type="text" name="clubworx_integration_settings[timetable_surface_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#ffffff" />';
        echo '<p class="description">' . __('Background color for timetable cards and wrappers.', 'clubworx-integration') . '</p>';
    }
    
    // Field callbacks
    public function analytics_mode_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['analytics_mode']) ? $settings['analytics_mode'] : 'none';
        $modes = array(
            'none' => __('None (no tag from this plugin)', 'clubworx-integration'),
            'ga4' => __('GA4 direct (gtag.js)', 'clubworx-integration'),
            'gtm' => __('Google Tag Manager only', 'clubworx-integration'),
        );
        foreach ($modes as $key => $label) {
            echo '<label style="display:block;margin-bottom:6px;"><input type="radio" name="clubworx_integration_settings[analytics_mode]" value="' . esc_attr($key) . '" ' . checked($value, $key, false) . ' /> ' . esc_html($label) . '</label>';
        }
        echo '<p class="description">' . __('Use GTM mode when GA4 is configured inside your container. Do not enable GA4 direct and GTM together.', 'clubworx-integration') . '</p>';
    }

    public function gtm_container_id_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['gtm_container_id']) ? $settings['gtm_container_id'] : '';
        echo '<input type="text" name="clubworx_integration_settings[gtm_container_id]" value="' . esc_attr($value) . '" class="regular-text" placeholder="GTM-XXXXXXX" />';
        echo '<p class="description">' . __('Required when tagging mode is GTM. Not used when mode is GA4 direct.', 'clubworx-integration') . '</p>';
    }

    public function ga4_currency_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['ga4_currency']) ? $settings['ga4_currency'] : 'USD';
        echo '<input type="text" name="clubworx_integration_settings[ga4_currency]" value="' . esc_attr($value) . '" maxlength="3" class="small-text" />';
        echo '<p class="description">' . __('ISO 4217 code for ecommerce/event currency (e.g. USD, AUD, EUR).', 'clubworx-integration') . '</p>';
    }

    public function club_display_name_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['club_display_name']) ? $settings['club_display_name'] : get_bloginfo('name');
        echo '<input type="text" name="clubworx_integration_settings[club_display_name]" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function club_website_url_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['club_website_url']) ? $settings['club_website_url'] : home_url('/');
        echo '<input type="url" name="clubworx_integration_settings[club_website_url]" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function post_booking_redirect_url_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['post_booking_redirect_url']) ? $settings['post_booking_redirect_url'] : '';
        echo '<input type="url" name="clubworx_integration_settings[post_booking_redirect_url]" value="' . esc_attr($value) . '" class="regular-text" placeholder="" />';
        echo '<p class="description">' . __('Leave empty to stay on the page after a successful booking.', 'clubworx-integration') . '</p>';
    }

    public function trial_event_description_intro_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['trial_event_description_intro']) ? $settings['trial_event_description_intro'] : __('Trial class', 'clubworx-integration');
        echo '<input type="text" name="clubworx_integration_settings[trial_event_description_intro]" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function ics_uid_domain_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $host = parse_url(home_url(), PHP_URL_HOST);
        $value = isset($settings['ics_uid_domain']) ? $settings['ics_uid_domain'] : ($host ? $host : 'localhost');
        echo '<input type="text" name="clubworx_integration_settings[ics_uid_domain]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Domain used in ICS UID (defaults to this site’s hostname).', 'clubworx-integration') . '</p>';
    }

    public function fallback_schedule_json_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['fallback_schedule_json']) ? $settings['fallback_schedule_json'] : '';
        echo '<textarea name="clubworx_integration_settings[fallback_schedule_json]" rows="8" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Valid JSON schedule structure when the ClubWorx API is unavailable. Leave empty for an empty timetable fallback.', 'clubworx-integration') . '</p>';
    }

    public function github_username_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['github_username']) ? $settings['github_username'] : '';
        echo '<input type="text" name="clubworx_integration_settings[github_username]" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
    }

    public function github_repo_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['github_repo']) ? $settings['github_repo'] : '';
        echo '<input type="text" name="clubworx_integration_settings[github_repo]" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
    }

    public function ga4_measurement_id_callback() {
        $settings = get_option('clubworx_integration_settings');
        $value = isset($settings['ga4_measurement_id']) ? $settings['ga4_measurement_id'] : '';
        echo '<input type="text" name="clubworx_integration_settings[ga4_measurement_id]" value="' . esc_attr($value) . '" class="regular-text" placeholder="G-XXXXXXXXXX" />';
        echo '<p class="description">' . __('Used when tagging mode is GA4 direct. Configure GA4 inside GTM when using GTM mode.', 'clubworx-integration') . '</p>';
    }
    
    public function ga4_api_secret_callback() {
        $settings = get_option('clubworx_integration_settings');
        $value = isset($settings['ga4_api_secret']) ? $settings['ga4_api_secret'] : '';
        echo '<input type="password" name="clubworx_integration_settings[ga4_api_secret]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('GA4 API Secret for Measurement Protocol (create in GA4 Admin → Data Streams → Measurement Protocol API secrets)', 'clubworx-integration') . '</p>';
    }
    
    public function ga4_debug_mode_callback() {
        $settings = get_option('clubworx_integration_settings');
        $checked = isset($settings['ga4_debug_mode']) && $settings['ga4_debug_mode'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="clubworx_integration_settings[ga4_debug_mode]" value="1" ' . $checked . ' /> ';
        echo __('Enable Debug Mode (events will appear in GA4 DebugView)', 'clubworx-integration') . '</label>';
    }
    
    public function clubworx_api_url_callback() {
        $settings = get_option('clubworx_integration_settings');
        $value = isset($settings['clubworx_api_url']) ? $settings['clubworx_api_url'] : '';
        echo '<input type="url" name="clubworx_integration_settings[clubworx_api_url]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your ClubWorx API base URL (e.g., https://app.clubworx.com/api/v2)', 'clubworx-integration') . '</p>';
    }
    
    public function clubworx_api_key_callback() {
        $settings = get_option('clubworx_integration_settings');
        $value = isset($settings['clubworx_api_key']) ? $settings['clubworx_api_key'] : '';
        echo '<input type="password" name="clubworx_integration_settings[clubworx_api_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your ClubWorx account key (used as account_key query parameter)', 'clubworx-integration') . '</p>';
    }
    
    public function email_notifications_callback() {
        $settings = get_option('clubworx_integration_settings');
        $checked = isset($settings['email_notifications']) && $settings['email_notifications'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="clubworx_integration_settings[email_notifications]" value="1" ' . $checked . ' /> ';
        echo __('Send email notifications for new bookings', 'clubworx-integration') . '</label>';
    }
    
    public function admin_email_callback() {
        $settings = get_option('clubworx_integration_settings');
        $value = isset($settings['admin_email']) ? $settings['admin_email'] : get_option('admin_email');
        echo '<input type="email" name="clubworx_integration_settings[admin_email]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Email address to receive booking notifications', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_enabled_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $checked = isset($settings['smtp_enabled']) && $settings['smtp_enabled'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="clubworx_integration_settings[smtp_enabled]" value="1" ' . $checked . ' /> ';
        echo __('Enable SMTP for sending emails', 'clubworx-integration') . '</label>';
        echo '<p class="description">' . __('When enabled, emails will be sent through the configured SMTP server instead of PHP mail().', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_host_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['smtp_host']) ? $settings['smtp_host'] : '';
        echo '<input type="text" name="clubworx_integration_settings[smtp_host]" value="' . esc_attr($value) . '" class="regular-text" placeholder="smtp.gmail.com" />';
        echo '<p class="description">' . __('SMTP server hostname (e.g., smtp.gmail.com, smtp.outlook.com)', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_port_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['smtp_port']) ? $settings['smtp_port'] : '587';
        echo '<input type="number" name="clubworx_integration_settings[smtp_port]" value="' . esc_attr($value) . '" class="small-text" min="1" max="65535" />';
        echo '<p class="description">' . __('SMTP port (common: 587 for TLS, 465 for SSL, 25 for unencrypted)', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_encryption_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls';
        echo '<select name="clubworx_integration_settings[smtp_encryption]">';
        echo '<option value="none" ' . selected($value, 'none', false) . '>' . __('None', 'clubworx-integration') . '</option>';
        echo '<option value="ssl" ' . selected($value, 'ssl', false) . '>SSL</option>';
        echo '<option value="tls" ' . selected($value, 'tls', false) . '>TLS</option>';
        echo '</select>';
        echo '<p class="description">' . __('Encryption method (TLS is recommended for most servers)', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_username_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['smtp_username']) ? $settings['smtp_username'] : '';
        echo '<input type="text" name="clubworx_integration_settings[smtp_username]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('SMTP username (usually your email address)', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_password_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $has_password = isset($settings['smtp_password']) && !empty($settings['smtp_password']);
        echo '<input type="password" name="clubworx_integration_settings[smtp_password]" value="" class="regular-text" autocomplete="new-password" />';
        if ($has_password) {
            echo '<p class="description">' . __('Leave blank to keep existing password. Enter new password to change it.', 'clubworx-integration') . '</p>';
        } else {
            echo '<p class="description">' . __('SMTP password or app-specific password (for Gmail, use an App Password)', 'clubworx-integration') . '</p>';
        }
    }
    
    public function smtp_from_email_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['smtp_from_email']) ? $settings['smtp_from_email'] : get_option('admin_email');
        echo '<input type="email" name="clubworx_integration_settings[smtp_from_email]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Email address to send emails from (should match your SMTP username)', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_from_name_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['smtp_from_name']) ? $settings['smtp_from_name'] : get_bloginfo('name');
        echo '<input type="text" name="clubworx_integration_settings[smtp_from_name]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Name to display as the sender', 'clubworx-integration') . '</p>';
    }
    
    /**
     * Configure PHPMailer to use SMTP settings
     */
    public function configure_smtp($phpmailer) {
        $settings = get_option('clubworx_integration_settings', array());
        
        // Only configure if SMTP is enabled
        if (!isset($settings['smtp_enabled']) || !$settings['smtp_enabled']) {
            return;
        }
        
        // Check if required SMTP settings are configured
        if (empty($settings['smtp_host']) || empty($settings['smtp_port'])) {
            return;
        }
        
        // Configure PHPMailer for SMTP
        $phpmailer->isSMTP();
        $phpmailer->Host = $settings['smtp_host'];
        $phpmailer->Port = isset($settings['smtp_port']) ? intval($settings['smtp_port']) : 587;
        $phpmailer->SMTPAuth = !empty($settings['smtp_username']) && !empty($settings['smtp_password']);
        
        // Set encryption
        $encryption = isset($settings['smtp_encryption']) ? $settings['smtp_encryption'] : 'tls';
        if ($encryption === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($encryption === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = false;
        }
        
        // Set authentication if credentials are provided
        if ($phpmailer->SMTPAuth) {
            $phpmailer->Username = $settings['smtp_username'];
            $phpmailer->Password = isset($settings['smtp_password']) ? $settings['smtp_password'] : '';
        }
        
        // Set From email and name
        if (!empty($settings['smtp_from_email'])) {
            $phpmailer->From = $settings['smtp_from_email'];
        }
        
        if (!empty($settings['smtp_from_name'])) {
            $phpmailer->FromName = $settings['smtp_from_name'];
        }
        
        // Enable debugging (optional, can be enabled via filter)
        $phpmailer->SMTPDebug = apply_filters('clubworx_smtp_debug', 0);
        
        // Set charset
        $phpmailer->CharSet = 'UTF-8';
    }
    
    // Form design field callbacks
    public function form_theme_integration_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $checked = isset($settings['form_theme_integration']) && $settings['form_theme_integration'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="clubworx_integration_settings[form_theme_integration]" value="1" ' . $checked . ' /> ';
        echo __('Enable theme integration (inherit colors, fonts, spacing, container width)', 'clubworx-integration') . '</label>';
        echo '<p class="description">' . __('When enabled, the form will automatically match your theme\'s design. Custom colors below will override theme colors.', 'clubworx-integration') . '</p>';
    }
    
    public function form_primary_button_bg_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_primary_button_bg']) ? $settings['form_primary_button_bg'] : '#32373c';
        echo '<input type="text" name="clubworx_integration_settings[form_primary_button_bg]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#32373c" />';
        echo '<p class="description">' . __('Background color for the primary submit button', 'clubworx-integration') . '</p>';
    }
    
    public function form_primary_button_hover_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_primary_button_hover']) ? $settings['form_primary_button_hover'] : '#1e2328';
        echo '<input type="text" name="clubworx_integration_settings[form_primary_button_hover]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#1e2328" />';
        echo '<p class="description">' . __('Hover state color for the primary button', 'clubworx-integration') . '</p>';
    }
    
    public function form_primary_button_text_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_primary_button_text']) ? $settings['form_primary_button_text'] : '#ffffff';
        echo '<input type="text" name="clubworx_integration_settings[form_primary_button_text]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#ffffff" />';
        echo '<p class="description">' . __('Text color for the primary button', 'clubworx-integration') . '</p>';
    }
    
    public function form_secondary_button_bg_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_secondary_button_bg']) ? $settings['form_secondary_button_bg'] : '#f8f9fa';
        echo '<input type="text" name="clubworx_integration_settings[form_secondary_button_bg]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#f8f9fa" />';
        echo '<p class="description">' . __('Background color for secondary buttons', 'clubworx-integration') . '</p>';
    }
    
    public function form_secondary_button_hover_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_secondary_button_hover']) ? $settings['form_secondary_button_hover'] : '#abb8c3';
        echo '<input type="text" name="clubworx_integration_settings[form_secondary_button_hover]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#abb8c3" />';
        echo '<p class="description">' . __('Hover state color for secondary buttons', 'clubworx-integration') . '</p>';
    }
    
    public function form_field_border_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_field_border_color']) ? $settings['form_field_border_color'] : '#abb8c3';
        echo '<input type="text" name="clubworx_integration_settings[form_field_border_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#abb8c3" />';
        echo '<p class="description">' . __('Border color for form fields (input, select, textarea)', 'clubworx-integration') . '</p>';
    }
    
    public function form_field_focus_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_field_focus_color']) ? $settings['form_field_focus_color'] : '#0693e3';
        echo '<input type="text" name="clubworx_integration_settings[form_field_focus_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#0693e3" />';
        echo '<p class="description">' . __('Border color when form fields are focused', 'clubworx-integration') . '</p>';
    }
    
    public function form_field_error_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_field_error_color']) ? $settings['form_field_error_color'] : '#cf2e2e';
        echo '<input type="text" name="clubworx_integration_settings[form_field_error_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#cf2e2e" />';
        echo '<p class="description">' . __('Border color for form fields with errors', 'clubworx-integration') . '</p>';
    }
    
    public function form_field_bg_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_field_bg_color']) ? $settings['form_field_bg_color'] : '#ffffff';
        echo '<input type="text" name="clubworx_integration_settings[form_field_bg_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#ffffff" />';
        echo '<p class="description">' . __('Background color for form fields', 'clubworx-integration') . '</p>';
    }
    
    public function form_field_text_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_field_text_color']) ? $settings['form_field_text_color'] : '#000000';
        echo '<input type="text" name="clubworx_integration_settings[form_field_text_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#000000" />';
        echo '<p class="description">' . __('Text color for form field input', 'clubworx-integration') . '</p>';
    }
    
    public function form_section_bg_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_section_bg_color']) ? $settings['form_section_bg_color'] : '#f8f9fa';
        echo '<input type="text" name="clubworx_integration_settings[form_section_bg_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#f8f9fa" />';
        echo '<p class="description">' . __('Background color for form sections', 'clubworx-integration') . '</p>';
    }
    
    public function form_section_heading_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_section_heading_color']) ? $settings['form_section_heading_color'] : '#000000';
        echo '<input type="text" name="clubworx_integration_settings[form_section_heading_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#000000" />';
        echo '<p class="description">' . __('Text color for section headings (h3)', 'clubworx-integration') . '</p>';
    }
    
    public function form_label_text_color_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_label_text_color']) ? $settings['form_label_text_color'] : '#000000';
        echo '<input type="text" name="clubworx_integration_settings[form_label_text_color]" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#000000" />';
        echo '<p class="description">' . __('Text color for form field labels', 'clubworx-integration') . '</p>';
    }
    
    public function form_border_radius_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_border_radius']) ? $settings['form_border_radius'] : '8px';
        echo '<input type="text" name="clubworx_integration_settings[form_border_radius]" value="' . esc_attr($value) . '" class="small-text" placeholder="8px" />';
        echo '<p class="description">' . __('Border radius for buttons and fields (e.g., 8px, 0.5rem, 50% for rounded)', 'clubworx-integration') . '</p>';
    }
    
    public function form_submit_button_text_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_submit_button_text']) ? $settings['form_submit_button_text'] : '';
        echo '<input type="text" name="clubworx_integration_settings[form_submit_button_text]" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr__('Book My Trial Class', 'clubworx-integration') . '" />';
        echo '<p class="description">' . __('Custom text for the main submit button. Leave empty to use default translation.', 'clubworx-integration') . '</p>';
    }
    
    public function form_secondary_button_text_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_secondary_button_text']) ? $settings['form_secondary_button_text'] : '';
        echo '<input type="text" name="clubworx_integration_settings[form_secondary_button_text]" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr__('Submit Information Only', 'clubworx-integration') . '" />';
        echo '<p class="description">' . __('Custom text for secondary buttons. Leave empty to use default translation.', 'clubworx-integration') . '</p>';
    }
    
    public function form_custom_css_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $value = isset($settings['form_custom_css']) ? $settings['form_custom_css'] : '';
        echo '<textarea name="clubworx_integration_settings[form_custom_css]" rows="10" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Add custom CSS to further customize the form. Use selectors like <code>.clubworx-booking-wrapper</code>, <code>.booking-form</code>, <code>.submit-btn</code>, etc.', 'clubworx-integration') . '</p>';
    }
}

