<?php
/**
 * Admin Settings Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class Clubworx_Admin_Settings {
    
    private static $instance = null;

    /**
     * Current settings tab slug (location slug, or "global", or "locations").
     *
     * @var string
     */
    private $active_settings_slug = 'primary';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('phpmailer_init', array($this, 'configure_smtp'));
        add_action('admin_post_clubworx_add_location', array($this, 'handle_add_location'));
        add_action('admin_post_clubworx_delete_location', array($this, 'handle_delete_location'));
        add_action('admin_post_clubworx_set_default_location', array($this, 'handle_set_default_location'));
    }
    
    /**
     * HTML name attribute for a nested location field.
     *
     * @param array<int,string> $parts Keys after locations/{slug}/...
     * @return string
     */
    private function loc_name($parts) {
        $slug = $this->active_settings_slug;
        $n = 'clubworx_integration_settings[locations][' . $slug . ']';
        foreach ($parts as $p) {
            $n .= '[' . $p . ']';
        }
        return $n;
    }

    /**
     * Active location row for field callbacks (location tabs only).
     *
     * @return array<string,mixed>
     */
    private function current_loc() {
        $loc = Clubworx_Locations::get($this->active_settings_slug);
        if ($loc === null) {
            $tz = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
            return Clubworx_Locations::default_location_data($tz);
        }
        return $loc;
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        $tab = '';
        if (isset($_REQUEST['_clubworx_settings_tab'])) {
            $tab = sanitize_key(wp_unslash($_REQUEST['_clubworx_settings_tab']));
        } elseif (isset($_GET['tab'])) {
            $tab = sanitize_key(wp_unslash($_GET['tab']));
        }
        $all = Clubworx_Locations::all();
        if ($tab === '' || ($tab !== 'global' && $tab !== 'locations' && !isset($all[$tab]))) {
            $tab = Clubworx_Locations::get_default_slug();
        }
        $this->active_settings_slug = $tab;

        register_setting('clubworx_integration_settings_group', 'clubworx_integration_settings', array($this, 'sanitize_settings'));

        if ($tab === 'global') {
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
            return;
        }

        if ($tab === 'locations') {
            return;
        }

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
        
        add_settings_field(
            'timetable_title_color',
            __('Title color', 'clubworx-integration'),
            array($this, 'timetable_title_color_callback'),
            'clubworx-integration-settings',
            'clubworx_timetable_settings'
        );
        
        add_settings_field(
            'timetable_border_color',
            __('Border color', 'clubworx-integration'),
            array($this, 'timetable_border_color_callback'),
            'clubworx-integration-settings',
            'clubworx_timetable_settings'
        );
        
        add_settings_field(
            'timetable_class_card_bg_color',
            __('Class card background', 'clubworx-integration'),
            array($this, 'timetable_class_card_bg_color_callback'),
            'clubworx-integration-settings',
            'clubworx_timetable_settings'
        );
        
        add_settings_field(
            'timetable_class_card_text_color',
            __('Class card text color', 'clubworx-integration'),
            array($this, 'timetable_class_card_text_color_callback'),
            'clubworx-integration-settings',
            'clubworx_timetable_settings'
        );
    }
    
    /**
     * Sanitize one location subtree (merged into existing).
     *
     * @param array<string,mixed> $input
     * @param array<string,mixed> $existing
     * @return array<string,mixed>
     */
    private function sanitize_location_blob($input, $existing) {
        $out = Clubworx_Locations::normalize_location($existing);
        if (!is_array($input)) {
            return $out;
        }

        if (isset($input['label'])) {
            $out['label'] = sanitize_text_field($input['label']);
        }
        if (isset($input['api_url'])) {
            $out['api_url'] = esc_url_raw($input['api_url']);
        }
        if (isset($input['api_key'])) {
            $out['api_key'] = sanitize_text_field($input['api_key']);
        }

        if (isset($input['analytics']) && is_array($input['analytics'])) {
            $a = $input['analytics'];
            if (isset($a['mode'])) {
                $mode = sanitize_text_field($a['mode']);
                $out['analytics']['mode'] = in_array($mode, array('none', 'ga4', 'gtm'), true) ? $mode : 'none';
            }
            if (isset($a['ga4_measurement_id'])) {
                $out['analytics']['ga4_measurement_id'] = sanitize_text_field($a['ga4_measurement_id']);
            }
            if (isset($a['gtm_container_id'])) {
                $out['analytics']['gtm_container_id'] = strtoupper(sanitize_text_field($a['gtm_container_id']));
            }
            if (isset($a['ga4_api_secret'])) {
                $out['analytics']['ga4_api_secret'] = sanitize_text_field($a['ga4_api_secret']);
            }
            if (isset($a['ga4_currency'])) {
                $cur = strtoupper(sanitize_text_field($a['ga4_currency']));
                $out['analytics']['ga4_currency'] = (strlen($cur) === 3) ? $cur : 'USD';
            }
            $out['analytics']['ga4_debug_mode'] = !empty($a['ga4_debug_mode']);
        }

        if (isset($input['branding']) && is_array($input['branding'])) {
            $b = $input['branding'];
            if (isset($b['club_display_name'])) {
                $out['branding']['club_display_name'] = sanitize_text_field($b['club_display_name']);
            }
            if (isset($b['club_website_url'])) {
                $out['branding']['club_website_url'] = esc_url_raw($b['club_website_url']);
            }
            if (isset($b['post_booking_redirect_url'])) {
                $out['branding']['post_booking_redirect_url'] = esc_url_raw($b['post_booking_redirect_url']);
            }
            if (isset($b['trial_event_description_intro'])) {
                $out['branding']['trial_event_description_intro'] = sanitize_text_field($b['trial_event_description_intro']);
            }
            if (isset($b['ics_uid_domain'])) {
                $out['branding']['ics_uid_domain'] = preg_replace('/[^a-zA-Z0-9.-]/', '', sanitize_text_field($b['ics_uid_domain']));
            }
        }

        if (isset($input['fallback_schedule_json'])) {
            $raw = trim(wp_unslash($input['fallback_schedule_json']));
            if ($raw === '') {
                $out['fallback_schedule_json'] = '';
            } else {
                $decoded = json_decode($raw, true);
                $out['fallback_schedule_json'] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                    ? wp_json_encode($decoded)
                    : '';
            }
        }

        if (isset($input['email']) && is_array($input['email'])) {
            $e = $input['email'];
            $out['email']['enabled'] = !empty($e['enabled']);
            if (isset($e['admin_email'])) {
                $out['email']['admin_email'] = sanitize_email($e['admin_email']);
            }
        }

        if (isset($input['smtp']) && is_array($input['smtp'])) {
            $s = $input['smtp'];
            $out['smtp']['enabled'] = !empty($s['enabled']);
            foreach (array('host', 'username', 'encryption') as $k) {
                if (isset($s[$k])) {
                    $out['smtp'][$k] = sanitize_text_field($s[$k]);
                }
            }
            if (isset($s['port'])) {
                $out['smtp']['port'] = absint($s['port']);
            }
            if (isset($s['password']) && $s['password'] !== '') {
                $out['smtp']['password'] = $s['password'];
            }
            if (isset($s['from_email'])) {
                $out['smtp']['from_email'] = sanitize_email($s['from_email']);
            }
            if (isset($s['from_name'])) {
                $out['smtp']['from_name'] = sanitize_text_field($s['from_name']);
            }
        }

        if (isset($input['form']) && is_array($input['form'])) {
            $f = $input['form'];
            $out['form']['theme_integration'] = !empty($f['theme_integration']);
            $form_colors = array(
                'primary_button_bg', 'primary_button_hover', 'primary_button_text',
                'secondary_button_bg', 'secondary_button_hover',
                'field_border_color', 'field_focus_color', 'field_error_color',
                'field_bg_color', 'field_text_color',
                'section_bg_color', 'section_heading_color', 'label_text_color',
            );
            foreach ($form_colors as $fc) {
                if (isset($f[$fc])) {
                    $color = sanitize_text_field($f[$fc]);
                    if ($color === '' || preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
                        $out['form'][$fc] = $color;
                    }
                }
            }
            if (isset($f['border_radius'])) {
                $radius = sanitize_text_field($f['border_radius']);
                if ($radius === '' || preg_match('/^(\d+(\.\d+)?)(px|em|rem|%)?$/', $radius)) {
                    $out['form']['border_radius'] = $radius;
                }
            }
            if (isset($f['submit_button_text'])) {
                $out['form']['submit_button_text'] = sanitize_text_field($f['submit_button_text']);
            }
            if (isset($f['secondary_button_text'])) {
                $out['form']['secondary_button_text'] = sanitize_text_field($f['secondary_button_text']);
            }
            if (isset($f['custom_css'])) {
                $css = wp_strip_all_tags($f['custom_css']);
                $dangerous = array('expression', 'javascript:', 'import', '@import', 'behavior', 'binding');
                foreach ($dangerous as $danger) {
                    $css = str_ireplace($danger, '', $css);
                }
                $out['form']['custom_css'] = $css;
            }
        }

        if (isset($input['timetable']) && is_array($input['timetable'])) {
            $t = $input['timetable'];
            if (isset($t['timezone'])) {
                $tz = sanitize_text_field($t['timezone']);
                $identifiers = timezone_identifiers_list();
                $fallback_tz = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
                if (empty($fallback_tz)) {
                    $fallback_tz = 'UTC';
                }
                $out['timetable']['timezone'] = in_array($tz, $identifiers, true) ? $tz : $fallback_tz;
            }
            if (isset($t['default_duration_minutes'])) {
                $dur = absint($t['default_duration_minutes']);
                $out['timetable']['default_duration_minutes'] = max(15, min(240, $dur > 0 ? $dur : 60));
            }
            foreach (array('primary_color', 'accent_color', 'text_color', 'surface_color', 'title_color', 'border_color', 'class_card_bg_color', 'class_card_text_color') as $tc) {
                if (isset($t[$tc])) {
                    $color = sanitize_text_field($t[$tc]);
                    if ($color === '' || preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
                        $out['timetable'][$tc] = $color;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $existing
     * @return array<string,mixed>
     */
    private function sanitize_github_blob($input, $existing) {
        $out = is_array($existing) ? $existing : array();
        if (!is_array($input)) {
            return $out;
        }
        if (isset($input['username'])) {
            $out['username'] = sanitize_text_field($input['username']);
        }
        if (isset($input['repo'])) {
            $out['repo'] = sanitize_text_field($input['repo']);
        }
        if (!empty($input['token'])) {
            $out['token'] = sanitize_text_field($input['token']);
        }
        return $out;
    }

    /**
     * Sanitize settings
     *
     * @param array<string,mixed>|null $input
     * @return array<string,mixed>
     */
    public function sanitize_settings($input) {
        $existing = get_option('clubworx_integration_settings', array());
        if (!is_array($existing)) {
            $existing = array();
        }

        if (!is_array($input)) {
            return $existing;
        }

        if (isset($input['locations']) && is_array($input['locations'])) {
            if (!isset($existing['locations']) || !is_array($existing['locations'])) {
                $existing['locations'] = array();
            }
            foreach ($input['locations'] as $slug => $blob) {
                $slug = sanitize_key($slug);
                if ($slug === '') {
                    continue;
                }
                $prev = isset($existing['locations'][$slug])
                    ? $existing['locations'][$slug]
                    : Clubworx_Locations::default_location_data(
                        function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC'
                    );
                $existing['locations'][$slug] = $this->sanitize_location_blob(
                    is_array($blob) ? $blob : array(),
                    is_array($prev) ? $prev : array()
                );
            }
        }

        if (isset($input['github']) && is_array($input['github'])) {
            $existing['github'] = $this->sanitize_github_blob(
                $input['github'],
                isset($existing['github']) && is_array($existing['github']) ? $existing['github'] : array()
            );
        }

        if (!isset($existing['default_location']) || $existing['default_location'] === '') {
            $existing['default_location'] = 'primary';
        }

        return $existing;
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
        $gh = isset($settings['github']) && is_array($settings['github']) ? $settings['github'] : array();
        $github_username = !empty($gh['username'])
            ? $gh['username']
            : (!empty($settings['github_username'])
                ? $settings['github_username']
                : (defined('CLUBWORX_INTEGRATION_GITHUB_USERNAME') ? CLUBWORX_INTEGRATION_GITHUB_USERNAME : ''));
        $github_repo = !empty($gh['repo'])
            ? $gh['repo']
            : (!empty($settings['github_repo'])
                ? $settings['github_repo']
                : (defined('CLUBWORX_INTEGRATION_GITHUB_REPO') ? CLUBWORX_INTEGRATION_GITHUB_REPO : ''));
        $has_token = !empty($gh['token']) || !empty($settings['github_token']);
        
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
        $gh = isset($settings['github']) && is_array($settings['github']) ? $settings['github'] : array();
        $value = isset($gh['token']) ? $gh['token'] : (isset($settings['github_token']) ? $settings['github_token'] : '');
        // Show masked value if token exists
        $display_value = !empty($value) ? str_repeat('•', min(strlen($value), 40)) : '';
        echo '<input type="password" name="clubworx_integration_settings[github][token]" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . (!empty($value) ? $display_value : '') . '" />';
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
        $loc = $this->current_loc();
        $smtp_enabled = !empty($loc['smtp']['enabled']);
        
        if ($smtp_enabled) {
            echo '<div class="notice notice-success inline" style="padding: 10px; margin: 10px 0;"><p><strong>✅ SMTP is enabled.</strong> Emails will be sent using the configured SMTP server.</p></div>';
        } else {
            echo '<div class="notice notice-info inline" style="padding: 10px; margin: 10px 0;"><p>' . __('Configure SMTP settings to send emails through an SMTP server instead of PHP mail(). This is recommended for better deliverability.', 'clubworx-integration') . '</p></div>';
        }
        echo '<p>' . __('Configure SMTP server settings for sending emails. Leave password field empty to keep existing password.', 'clubworx-integration') . '</p>';
    }
    
    public function form_design_section_callback() {
        $loc = $this->current_loc();
        $theme_integration = !empty($loc['form']['theme_integration']);
        
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
        $loc = $this->current_loc();
        $tz_default = function_exists('wp_timezone_string') && wp_timezone_string() ? wp_timezone_string() : 'UTC';
        $value = isset($loc['timetable']['timezone']) ? $loc['timetable']['timezone'] : $tz_default;
        $identifiers = timezone_identifiers_list();
        echo '<select name="' . esc_attr($this->loc_name(array('timetable', 'timezone'))) . '" class="regular-text">';
        foreach ($identifiers as $id) {
            echo '<option value="' . esc_attr($id) . '"' . selected($value, $id, false) . '>' . esc_html($id) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('IANA timezone for the academy (not the visitor’s browser).', 'clubworx-integration') . '</p>';
    }
    
    public function timetable_default_duration_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['timetable']['default_duration_minutes']) ? absint($loc['timetable']['default_duration_minutes']) : 60;
        if ($value < 15) {
            $value = 60;
        }
        echo '<input type="number" name="' . esc_attr($this->loc_name(array('timetable', 'default_duration_minutes'))) . '" value="' . esc_attr($value) . '" min="15" max="240" step="5" class="small-text" />';
        echo '<p class="description">' . __('Used to decide when a class is “on now” when ClubWorx does not provide an end time.', 'clubworx-integration') . '</p>';
    }

    public function timetable_primary_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['timetable']['primary_color']) ? $loc['timetable']['primary_color'] : '#1914a6';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('timetable', 'primary_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#1914a6" />';
        echo '<p class="description">' . __('Primary timetable brand color (banner and active filter states).', 'clubworx-integration') . '</p>';
    }

    public function timetable_accent_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['timetable']['accent_color']) ? $loc['timetable']['accent_color'] : '#ffbe00';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('timetable', 'accent_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#ffbe00" />';
        echo '<p class="description">' . __('Accent color for highlights and key details.', 'clubworx-integration') . '</p>';
    }

    public function timetable_text_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['timetable']['text_color']) ? $loc['timetable']['text_color'] : '#333333';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('timetable', 'text_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#333333" />';
        echo '<p class="description">' . __('Base text color used across timetable cards.', 'clubworx-integration') . '</p>';
    }

    public function timetable_surface_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['timetable']['surface_color']) ? $loc['timetable']['surface_color'] : '#ffffff';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('timetable', 'surface_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#ffffff" />';
        echo '<p class="description">' . __('Background color for timetable cards and wrappers.', 'clubworx-integration') . '</p>';
    }
    
    public function timetable_title_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['timetable']['title_color']) ? $loc['timetable']['title_color'] : '#2c3e50';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('timetable', 'title_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#2c3e50" />';
        echo '<p class="description">' . __('Color for timetable heading and day titles.', 'clubworx-integration') . '</p>';
    }
    
    public function timetable_border_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['timetable']['border_color']) ? $loc['timetable']['border_color'] : '#e1e8ed';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('timetable', 'border_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#e1e8ed" />';
        echo '<p class="description">' . __('Border color for timetable day cards and title dividers.', 'clubworx-integration') . '</p>';
    }
    
    public function timetable_class_card_bg_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['timetable']['class_card_bg_color']) ? $loc['timetable']['class_card_bg_color'] : '#f8f9fa';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('timetable', 'class_card_bg_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#f8f9fa" />';
        echo '<p class="description">' . __('Base background color for class cards.', 'clubworx-integration') . '</p>';
    }
    
    public function timetable_class_card_text_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['timetable']['class_card_text_color']) ? $loc['timetable']['class_card_text_color'] : '#34495e';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('timetable', 'class_card_text_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#34495e" />';
        echo '<p class="description">' . __('Text color for class names and card content.', 'clubworx-integration') . '</p>';
    }
    
    // Field callbacks
    public function analytics_mode_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['analytics']['mode']) ? $loc['analytics']['mode'] : 'none';
        $nm = esc_attr($this->loc_name(array('analytics', 'mode')));
        $modes = array(
            'none' => __('None (no tag from this plugin)', 'clubworx-integration'),
            'ga4' => __('GA4 direct (gtag.js)', 'clubworx-integration'),
            'gtm' => __('Google Tag Manager only', 'clubworx-integration'),
        );
        foreach ($modes as $key => $label) {
            echo '<label style="display:block;margin-bottom:6px;"><input type="radio" name="' . $nm . '" value="' . esc_attr($key) . '" ' . checked($value, $key, false) . ' /> ' . esc_html($label) . '</label>';
        }
        echo '<p class="description">' . __('Use GTM mode when GA4 is configured inside your container. Do not enable GA4 direct and GTM together.', 'clubworx-integration') . '</p>';
    }

    public function gtm_container_id_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['analytics']['gtm_container_id']) ? $loc['analytics']['gtm_container_id'] : '';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('analytics', 'gtm_container_id'))) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="GTM-XXXXXXX" />';
        echo '<p class="description">' . __('Required when tagging mode is GTM. Not used when mode is GA4 direct.', 'clubworx-integration') . '</p>';
    }

    public function ga4_currency_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['analytics']['ga4_currency']) ? $loc['analytics']['ga4_currency'] : 'USD';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('analytics', 'ga4_currency'))) . '" value="' . esc_attr($value) . '" maxlength="3" class="small-text" />';
        echo '<p class="description">' . __('ISO 4217 code for ecommerce/event currency (e.g. USD, AUD, EUR).', 'clubworx-integration') . '</p>';
    }

    public function club_display_name_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['branding']['club_display_name']) ? $loc['branding']['club_display_name'] : get_bloginfo('name');
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('branding', 'club_display_name'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function club_website_url_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['branding']['club_website_url']) ? $loc['branding']['club_website_url'] : home_url('/');
        echo '<input type="url" name="' . esc_attr($this->loc_name(array('branding', 'club_website_url'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function post_booking_redirect_url_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['branding']['post_booking_redirect_url']) ? $loc['branding']['post_booking_redirect_url'] : '';
        echo '<input type="url" name="' . esc_attr($this->loc_name(array('branding', 'post_booking_redirect_url'))) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="" />';
        echo '<p class="description">' . __('Leave empty to stay on the page after a successful booking.', 'clubworx-integration') . '</p>';
    }

    public function trial_event_description_intro_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['branding']['trial_event_description_intro']) ? $loc['branding']['trial_event_description_intro'] : __('Trial class', 'clubworx-integration');
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('branding', 'trial_event_description_intro'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function ics_uid_domain_callback() {
        $loc = $this->current_loc();
        $host = parse_url(home_url(), PHP_URL_HOST);
        $value = isset($loc['branding']['ics_uid_domain']) ? $loc['branding']['ics_uid_domain'] : ($host ? $host : 'localhost');
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('branding', 'ics_uid_domain'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Domain used in ICS UID (defaults to this site’s hostname).', 'clubworx-integration') . '</p>';
    }

    public function fallback_schedule_json_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['fallback_schedule_json']) ? $loc['fallback_schedule_json'] : '';
        echo '<textarea name="' . esc_attr($this->loc_name(array('fallback_schedule_json'))) . '" rows="8" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Valid JSON schedule structure when the ClubWorx API is unavailable. Leave empty for an empty timetable fallback.', 'clubworx-integration') . '</p>';
    }

    public function github_username_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $gh = isset($settings['github']) && is_array($settings['github']) ? $settings['github'] : array();
        $value = isset($gh['username']) ? $gh['username'] : '';
        echo '<input type="text" name="clubworx_integration_settings[github][username]" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
    }

    public function github_repo_callback() {
        $settings = get_option('clubworx_integration_settings', array());
        $gh = isset($settings['github']) && is_array($settings['github']) ? $settings['github'] : array();
        $value = isset($gh['repo']) ? $gh['repo'] : '';
        echo '<input type="text" name="clubworx_integration_settings[github][repo]" value="' . esc_attr($value) . '" class="regular-text" autocomplete="off" />';
    }

    public function ga4_measurement_id_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['analytics']['ga4_measurement_id']) ? $loc['analytics']['ga4_measurement_id'] : '';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('analytics', 'ga4_measurement_id'))) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="G-XXXXXXXXXX" />';
        echo '<p class="description">' . __('Used when tagging mode is GA4 direct. Configure GA4 inside GTM when using GTM mode.', 'clubworx-integration') . '</p>';
    }
    
    public function ga4_api_secret_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['analytics']['ga4_api_secret']) ? $loc['analytics']['ga4_api_secret'] : '';
        echo '<input type="password" name="' . esc_attr($this->loc_name(array('analytics', 'ga4_api_secret'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('GA4 API Secret for Measurement Protocol (create in GA4 Admin → Data Streams → Measurement Protocol API secrets)', 'clubworx-integration') . '</p>';
    }
    
    public function ga4_debug_mode_callback() {
        $loc = $this->current_loc();
        $checked = !empty($loc['analytics']['ga4_debug_mode']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($this->loc_name(array('analytics', 'ga4_debug_mode'))) . '" value="1" ' . $checked . ' /> ';
        echo __('Enable Debug Mode (events will appear in GA4 DebugView)', 'clubworx-integration') . '</label>';
    }
    
    public function clubworx_api_url_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['api_url']) ? $loc['api_url'] : '';
        echo '<input type="url" name="' . esc_attr($this->loc_name(array('api_url'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your ClubWorx API base URL (e.g., https://app.clubworx.com/api/v2)', 'clubworx-integration') . '</p>';
    }
    
    public function clubworx_api_key_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['api_key']) ? $loc['api_key'] : '';
        echo '<input type="password" name="' . esc_attr($this->loc_name(array('api_key'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your ClubWorx account key (used as account_key query parameter)', 'clubworx-integration') . '</p>';
    }
    
    public function email_notifications_callback() {
        $loc = $this->current_loc();
        $checked = !empty($loc['email']['enabled']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($this->loc_name(array('email', 'enabled'))) . '" value="1" ' . $checked . ' /> ';
        echo __('Send email notifications for new bookings', 'clubworx-integration') . '</label>';
    }
    
    public function admin_email_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['email']['admin_email']) ? $loc['email']['admin_email'] : get_option('admin_email');
        echo '<input type="email" name="' . esc_attr($this->loc_name(array('email', 'admin_email'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Email address to receive booking notifications', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_enabled_callback() {
        $loc = $this->current_loc();
        $checked = !empty($loc['smtp']['enabled']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($this->loc_name(array('smtp', 'enabled'))) . '" value="1" ' . $checked . ' /> ';
        echo __('Enable SMTP for sending emails', 'clubworx-integration') . '</label>';
        echo '<p class="description">' . __('When enabled, emails will be sent through the configured SMTP server instead of PHP mail().', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_host_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['smtp']['host']) ? $loc['smtp']['host'] : '';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('smtp', 'host'))) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="smtp.gmail.com" />';
        echo '<p class="description">' . __('SMTP server hostname (e.g., smtp.gmail.com, smtp.outlook.com)', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_port_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['smtp']['port']) ? $loc['smtp']['port'] : '587';
        echo '<input type="number" name="' . esc_attr($this->loc_name(array('smtp', 'port'))) . '" value="' . esc_attr($value) . '" class="small-text" min="1" max="65535" />';
        echo '<p class="description">' . __('SMTP port (common: 587 for TLS, 465 for SSL, 25 for unencrypted)', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_encryption_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['smtp']['encryption']) ? $loc['smtp']['encryption'] : 'tls';
        echo '<select name="' . esc_attr($this->loc_name(array('smtp', 'encryption'))) . '">';
        echo '<option value="none" ' . selected($value, 'none', false) . '>' . __('None', 'clubworx-integration') . '</option>';
        echo '<option value="ssl" ' . selected($value, 'ssl', false) . '>SSL</option>';
        echo '<option value="tls" ' . selected($value, 'tls', false) . '>TLS</option>';
        echo '</select>';
        echo '<p class="description">' . __('Encryption method (TLS is recommended for most servers)', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_username_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['smtp']['username']) ? $loc['smtp']['username'] : '';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('smtp', 'username'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('SMTP username (usually your email address)', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_password_callback() {
        $loc = $this->current_loc();
        $has_password = isset($loc['smtp']['password']) && $loc['smtp']['password'] !== '';
        echo '<input type="password" name="' . esc_attr($this->loc_name(array('smtp', 'password'))) . '" value="" class="regular-text" autocomplete="new-password" />';
        if ($has_password) {
            echo '<p class="description">' . __('Leave blank to keep existing password. Enter new password to change it.', 'clubworx-integration') . '</p>';
        } else {
            echo '<p class="description">' . __('SMTP password or app-specific password (for Gmail, use an App Password)', 'clubworx-integration') . '</p>';
        }
    }
    
    public function smtp_from_email_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['smtp']['from_email']) ? $loc['smtp']['from_email'] : get_option('admin_email');
        echo '<input type="email" name="' . esc_attr($this->loc_name(array('smtp', 'from_email'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Email address to send emails from (should match your SMTP username)', 'clubworx-integration') . '</p>';
    }
    
    public function smtp_from_name_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['smtp']['from_name']) ? $loc['smtp']['from_name'] : get_bloginfo('name');
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('smtp', 'from_name'))) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Name to display as the sender', 'clubworx-integration') . '</p>';
    }
    
    /**
     * Configure PHPMailer to use SMTP settings (per-location or active context).
     */
    public function configure_smtp($phpmailer) {
        $smtp = null;
        if (class_exists('Clubworx_SMTP_Context') && Clubworx_SMTP_Context::is_active()) {
            $smtp = Clubworx_SMTP_Context::get_smtp();
        }
        if (!is_array($smtp) || empty($smtp)) {
            $slug = Clubworx_Locations::get_default_slug();
            $loc = Clubworx_Locations::get($slug);
            if ($loc === null || empty($loc['smtp']) || !is_array($loc['smtp'])) {
                return;
            }
            $smtp = $loc['smtp'];
        }

        if (empty($smtp['enabled'])) {
            return;
        }

        if (empty($smtp['host']) || empty($smtp['port'])) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $smtp['host'];
        $phpmailer->Port = isset($smtp['port']) ? intval($smtp['port']) : 587;
        $phpmailer->SMTPAuth = !empty($smtp['username']) && !empty($smtp['password']);

        $encryption = isset($smtp['encryption']) ? $smtp['encryption'] : 'tls';
        if ($encryption === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($encryption === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = false;
        }

        if ($phpmailer->SMTPAuth) {
            $phpmailer->Username = $smtp['username'];
            $phpmailer->Password = isset($smtp['password']) ? $smtp['password'] : '';
        }

        if (!empty($smtp['from_email'])) {
            $phpmailer->From = $smtp['from_email'];
        }

        if (!empty($smtp['from_name'])) {
            $phpmailer->FromName = $smtp['from_name'];
        }

        $phpmailer->SMTPDebug = apply_filters('clubworx_smtp_debug', 0);
        $phpmailer->CharSet = 'UTF-8';
    }

    /**
     * Add a new location (slug + label).
     */
    public function handle_add_location() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'clubworx-integration'));
        }
        check_admin_referer('clubworx_add_location');

        $label = isset($_POST['new_location_label']) ? sanitize_text_field(wp_unslash($_POST['new_location_label'])) : '';
        $slug_in = isset($_POST['new_location_slug']) ? sanitize_key(wp_unslash($_POST['new_location_slug'])) : '';

        if ($label === '') {
            wp_safe_redirect(admin_url('admin.php?page=clubworx-integration-settings&tab=locations&error=empty'));
            exit;
        }

        $slug = $slug_in !== '' ? $slug_in : Clubworx_Locations::slugify($label);
        $opt = get_option('clubworx_integration_settings', array());
        if (!isset($opt['locations']) || !is_array($opt['locations'])) {
            $opt['locations'] = array();
        }
        if (isset($opt['locations'][$slug])) {
            wp_safe_redirect(admin_url('admin.php?page=clubworx-integration-settings&tab=locations&error=exists'));
            exit;
        }

        $tz = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
        if (empty($tz)) {
            $tz = 'UTC';
        }
        $opt['locations'][$slug] = Clubworx_Locations::normalize_location(
            array_merge(
                Clubworx_Locations::default_location_data($tz, $label),
                array('label' => $label)
            )
        );
        update_option('clubworx_integration_settings', $opt);

        wp_safe_redirect(admin_url('admin.php?page=clubworx-integration-settings&tab=' . rawurlencode($slug) . '&added=1'));
        exit;
    }

    /**
     * Delete a location (not default).
     */
    public function handle_delete_location() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'clubworx-integration'));
        }
        check_admin_referer('clubworx_delete_location');

        $slug = isset($_POST['delete_location_slug']) ? sanitize_key(wp_unslash($_POST['delete_location_slug'])) : '';
        $def = Clubworx_Locations::get_default_slug();
        if ($slug === '' || $slug === $def) {
            wp_safe_redirect(admin_url('admin.php?page=clubworx-integration-settings&tab=locations&error=delete'));
            exit;
        }

        $opt = get_option('clubworx_integration_settings', array());
        if (isset($opt['locations'][$slug])) {
            unset($opt['locations'][$slug]);
            update_option('clubworx_integration_settings', $opt);
        }

        wp_safe_redirect(admin_url('admin.php?page=clubworx-integration-settings&tab=' . rawurlencode($def) . '&deleted=1'));
        exit;
    }

    /**
     * Set default location slug.
     */
    public function handle_set_default_location() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'clubworx-integration'));
        }
        check_admin_referer('clubworx_set_default_location');

        $slug = isset($_POST['default_location_slug']) ? sanitize_key(wp_unslash($_POST['default_location_slug'])) : '';
        $all = Clubworx_Locations::all();
        if ($slug === '' || !isset($all[$slug])) {
            wp_safe_redirect(admin_url('admin.php?page=clubworx-integration-settings&tab=locations&error=default'));
            exit;
        }

        $opt = get_option('clubworx_integration_settings', array());
        $opt['default_location'] = $slug;
        update_option('clubworx_integration_settings', $opt);

        wp_safe_redirect(admin_url('admin.php?page=clubworx-integration-settings&tab=locations&defaulted=1'));
        exit;
    }
    
    // Form design field callbacks
    public function form_theme_integration_callback() {
        $loc = $this->current_loc();
        $checked = !empty($loc['form']['theme_integration']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($this->loc_name(array('form', 'theme_integration'))) . '" value="1" ' . $checked . ' /> ';
        echo __('Enable theme integration (inherit colors, fonts, spacing, container width)', 'clubworx-integration') . '</label>';
        echo '<p class="description">' . __('When enabled, the form will automatically match your theme\'s design. Custom colors below will override theme colors.', 'clubworx-integration') . '</p>';
    }
    
    public function form_primary_button_bg_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['primary_button_bg']) ? $loc['form']['primary_button_bg'] : '#32373c';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'primary_button_bg'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#32373c" />';
        echo '<p class="description">' . __('Background color for the primary submit button', 'clubworx-integration') . '</p>';
    }
    
    public function form_primary_button_hover_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['primary_button_hover']) ? $loc['form']['primary_button_hover'] : '#1e2328';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'primary_button_hover'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#1e2328" />';
        echo '<p class="description">' . __('Hover state color for the primary button', 'clubworx-integration') . '</p>';
    }
    
    public function form_primary_button_text_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['primary_button_text']) ? $loc['form']['primary_button_text'] : '#ffffff';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'primary_button_text'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#ffffff" />';
        echo '<p class="description">' . __('Text color for the primary button', 'clubworx-integration') . '</p>';
    }
    
    public function form_secondary_button_bg_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['secondary_button_bg']) ? $loc['form']['secondary_button_bg'] : '#f8f9fa';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'secondary_button_bg'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#f8f9fa" />';
        echo '<p class="description">' . __('Background color for secondary buttons', 'clubworx-integration') . '</p>';
    }
    
    public function form_secondary_button_hover_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['secondary_button_hover']) ? $loc['form']['secondary_button_hover'] : '#abb8c3';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'secondary_button_hover'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#abb8c3" />';
        echo '<p class="description">' . __('Hover state color for secondary buttons', 'clubworx-integration') . '</p>';
    }
    
    public function form_field_border_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['field_border_color']) ? $loc['form']['field_border_color'] : '#abb8c3';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'field_border_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#abb8c3" />';
        echo '<p class="description">' . __('Border color for form fields (input, select, textarea)', 'clubworx-integration') . '</p>';
    }
    
    public function form_field_focus_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['field_focus_color']) ? $loc['form']['field_focus_color'] : '#0693e3';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'field_focus_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#0693e3" />';
        echo '<p class="description">' . __('Border color when form fields are focused', 'clubworx-integration') . '</p>';
    }
    
    public function form_field_error_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['field_error_color']) ? $loc['form']['field_error_color'] : '#cf2e2e';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'field_error_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#cf2e2e" />';
        echo '<p class="description">' . __('Border color for form fields with errors', 'clubworx-integration') . '</p>';
    }
    
    public function form_field_bg_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['field_bg_color']) ? $loc['form']['field_bg_color'] : '#ffffff';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'field_bg_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#ffffff" />';
        echo '<p class="description">' . __('Background color for form fields', 'clubworx-integration') . '</p>';
    }
    
    public function form_field_text_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['field_text_color']) ? $loc['form']['field_text_color'] : '#000000';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'field_text_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#000000" />';
        echo '<p class="description">' . __('Text color for form field input', 'clubworx-integration') . '</p>';
    }
    
    public function form_section_bg_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['section_bg_color']) ? $loc['form']['section_bg_color'] : '#f8f9fa';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'section_bg_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#f8f9fa" />';
        echo '<p class="description">' . __('Background color for form sections', 'clubworx-integration') . '</p>';
    }
    
    public function form_section_heading_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['section_heading_color']) ? $loc['form']['section_heading_color'] : '#000000';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'section_heading_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#000000" />';
        echo '<p class="description">' . __('Text color for section headings (h3)', 'clubworx-integration') . '</p>';
    }
    
    public function form_label_text_color_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['label_text_color']) ? $loc['form']['label_text_color'] : '#000000';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'label_text_color'))) . '" value="' . esc_attr($value) . '" class="clubworx-color-picker" data-default-color="#000000" />';
        echo '<p class="description">' . __('Text color for form field labels', 'clubworx-integration') . '</p>';
    }
    
    public function form_border_radius_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['border_radius']) ? $loc['form']['border_radius'] : '8px';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'border_radius'))) . '" value="' . esc_attr($value) . '" class="small-text" placeholder="8px" />';
        echo '<p class="description">' . __('Border radius for buttons and fields (e.g., 8px, 0.5rem, 50% for rounded)', 'clubworx-integration') . '</p>';
    }
    
    public function form_submit_button_text_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['submit_button_text']) ? $loc['form']['submit_button_text'] : '';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'submit_button_text'))) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr__('Book My Trial Class', 'clubworx-integration') . '" />';
        echo '<p class="description">' . __('Custom text for the main submit button. Leave empty to use default translation.', 'clubworx-integration') . '</p>';
    }
    
    public function form_secondary_button_text_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['secondary_button_text']) ? $loc['form']['secondary_button_text'] : '';
        echo '<input type="text" name="' . esc_attr($this->loc_name(array('form', 'secondary_button_text'))) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr__('Submit Information Only', 'clubworx-integration') . '" />';
        echo '<p class="description">' . __('Custom text for secondary buttons. Leave empty to use default translation.', 'clubworx-integration') . '</p>';
    }
    
    public function form_custom_css_callback() {
        $loc = $this->current_loc();
        $value = isset($loc['form']['custom_css']) ? $loc['form']['custom_css'] : '';
        echo '<textarea name="' . esc_attr($this->loc_name(array('form', 'custom_css'))) . '" rows="10" class="large-text code">' . esc_textarea($value) . '</textarea>';
        echo '<p class="description">' . __('Add custom CSS to further customize the form. Use selectors like <code>.clubworx-booking-wrapper</code>, <code>.booking-form</code>, <code>.submit-btn</code>, etc.', 'clubworx-integration') . '</p>';
    }
}

