<?php
/**
 * Plugin Name: Clubworx Integration
 * Plugin URI: https://wordpress.org/plugins/clubworx-integration
 * Description: Trial class booking with ClubWorx API, optional GA4 or GTM analytics, and attribution tracking.
 * Version: 2.0.0
 * Author: Andy Jones
 * Author URI: https://onlyjonesy.com.au
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: clubworx-integration
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CLUBWORX_INTEGRATION_VERSION', '2.0.0');
define('CLUBWORX_INTEGRATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CLUBWORX_INTEGRATION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CLUBWORX_INTEGRATION_PLUGIN_FILE', __FILE__);

// GitHub repository - UPDATE THESE VALUES after creating your GitHub repository
// Format: https://github.com/username/repository
define('CLUBWORX_INTEGRATION_GITHUB_USERNAME', '');
define('CLUBWORX_INTEGRATION_GITHUB_REPO', '');

/**
 * Main Plugin Class
 */
class Clubworx_Integration {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Ensures GTM noscript iframe is output once (body_open or footer fallback).
     *
     * @var bool
     */
    private static $gtm_noscript_printed = false;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->load_dependencies();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add shortcode
        add_shortcode('clubworx_trial_booking', array($this, 'render_booking_form'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Handle CSV export early (before any output)
        add_action('admin_init', array($this, 'handle_csv_export'), 1);
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Output dynamic CSS for form customization
        add_action('wp_head', array($this, 'output_form_custom_css'), 100);
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Load GitHub updater (needed for REST API update checks)
        require_once CLUBWORX_INTEGRATION_PLUGIN_DIR . 'includes/class-github-updater.php';
        Clubworx_GitHub_Updater::get_instance();
        
        // Load REST API endpoints
        require_once CLUBWORX_INTEGRATION_PLUGIN_DIR . 'includes/class-rest-api.php';
        
        // Load admin settings
        require_once CLUBWORX_INTEGRATION_PLUGIN_DIR . 'includes/class-admin-settings.php';
        
        // Initialize components
        Clubworx_REST_API::get_instance();
        Clubworx_Admin_Settings::get_instance();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $tz = function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC';
        if (empty($tz)) {
            $tz = 'UTC';
        }
        $host = parse_url(home_url(), PHP_URL_HOST);
        $default_options = array(
            'analytics_mode' => 'none',
            'ga4_measurement_id' => '',
            'gtm_container_id' => '',
            'ga4_debug_mode' => false,
            'ga4_currency' => 'USD',
            'club_display_name' => get_bloginfo('name'),
            'club_website_url' => home_url('/'),
            'post_booking_redirect_url' => '',
            'trial_event_description_intro' => 'Trial class',
            'ics_uid_domain' => $host ? $host : 'localhost',
            'fallback_schedule_json' => '',
            'ga4_api_secret' => '',
            'clubworx_api_key' => '',
            'clubworx_api_url' => '',
            'email_notifications' => true,
            'admin_email' => get_option('admin_email'),
            'timetable_timezone' => $tz,
            'timetable_default_duration_minutes' => 60,
            'timetable_primary_color' => '#1914a6',
            'timetable_accent_color' => '#ffbe00',
            'timetable_text_color' => '#333333',
            'timetable_surface_color' => '#ffffff',
        );

        add_option('clubworx_integration_settings', $default_options);

        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Enqueue plugin scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on pages with the shortcode
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'clubworx_trial_booking')) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'clubworx-booking-styles',
            CLUBWORX_INTEGRATION_PLUGIN_URL . 'assets/css/styles.css',
            array(),
            CLUBWORX_INTEGRATION_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'clubworx-attribution-tracker',
            CLUBWORX_INTEGRATION_PLUGIN_URL . 'assets/js/attribution-tracker.js',
            array(),
            CLUBWORX_INTEGRATION_VERSION,
            true
        );
        
        wp_enqueue_script(
            'clubworx-booking-script',
            CLUBWORX_INTEGRATION_PLUGIN_URL . 'assets/js/script.js',
            array('clubworx-attribution-tracker'),
            CLUBWORX_INTEGRATION_VERSION,
            true
        );
        
        wp_localize_script('clubworx-attribution-tracker', 'clubworxBookingSettings', $this->get_public_script_settings());

        $this->add_analytics_output();
    }

    /**
     * Settings exposed to front-end scripts (attribution + booking).
     *
     * @return array<string,mixed>
     */
    private function get_public_script_settings() {
        $settings = get_option('clubworx_integration_settings', array());
        $mode = isset($settings['analytics_mode']) ? $settings['analytics_mode'] : 'none';
        $host = parse_url(home_url(), PHP_URL_HOST);

        return array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('clubworx/v1/'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'analyticsMode' => in_array($mode, array('none', 'ga4', 'gtm'), true) ? $mode : 'none',
            'ga4MeasurementId' => isset($settings['ga4_measurement_id']) ? $settings['ga4_measurement_id'] : '',
            'gtmContainerId' => isset($settings['gtm_container_id']) ? $settings['gtm_container_id'] : '',
            'ga4DebugMode' => !empty($settings['ga4_debug_mode']),
            'ga4Currency' => isset($settings['ga4_currency']) ? $settings['ga4_currency'] : 'USD',
            'clubDisplayName' => isset($settings['club_display_name']) ? $settings['club_display_name'] : get_bloginfo('name'),
            'clubWebsiteUrl' => isset($settings['club_website_url']) ? $settings['club_website_url'] : home_url('/'),
            'postBookingRedirectUrl' => isset($settings['post_booking_redirect_url']) ? $settings['post_booking_redirect_url'] : '',
            'trialEventIntro' => isset($settings['trial_event_description_intro']) ? $settings['trial_event_description_intro'] : __('Trial class', 'clubworx-integration'),
            'icsUidDomain' => isset($settings['ics_uid_domain']) ? $settings['ics_uid_domain'] : ($host ? $host : 'localhost'),
        );
    }

    /**
     * Load either direct GA4 (gtag) or GTM — never both (avoids duplicate GA4 hits).
     */
    private function add_analytics_output() {
        $settings = get_option('clubworx_integration_settings', array());
        $mode = isset($settings['analytics_mode']) ? $settings['analytics_mode'] : 'none';

        if ($mode === 'ga4') {
            $measurement_id = isset($settings['ga4_measurement_id']) ? trim($settings['ga4_measurement_id']) : '';
            if ($measurement_id === '') {
                return;
            }
            $debug_mode = !empty($settings['ga4_debug_mode']);

            add_action(
                'wp_head',
                function () use ($measurement_id, $debug_mode) {
                    ?>
            <!-- Clubworx Integration — GA4 (direct) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($measurement_id); ?>"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '<?php echo esc_js($measurement_id); ?>', {
                    debug_mode: <?php echo $debug_mode ? 'true' : 'false'; ?>,
                    send_page_view: true,
                    allow_google_signals: true,
                    allow_ad_personalization_signals: true,
                    custom_map: {
                        'custom_lead_source': 'lead_source',
                        'custom_utm_source': 'utm_source',
                        'custom_utm_medium': 'utm_medium',
                        'custom_utm_campaign': 'utm_campaign',
                        'custom_program_interest': 'program_interest',
                        'custom_contact_key': 'contact_key'
                    }
                });
            </script>
                    <?php
                },
                5
            );
            return;
        }

        if ($mode === 'gtm') {
            $gtm = isset($settings['gtm_container_id']) ? trim($settings['gtm_container_id']) : '';
            if (!preg_match('/^GTM-[A-Z0-9]+$/', $gtm)) {
                return;
            }

            add_action(
                'wp_head',
                function () use ($gtm) {
                    ?>
            <!-- Clubworx Integration — Google Tag Manager -->
            <script>
                (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','<?php echo esc_js($gtm); ?>');
            </script>
                    <?php
                },
                1
            );

            add_action('wp_body_open', array($this, 'print_gtm_noscript'), 1);
            add_action('wp_footer', array($this, 'print_gtm_noscript_footer'), 1);
        }
    }

    /**
     * GTM noscript fallback immediately after opening body (preferred).
     */
    public function print_gtm_noscript() {
        if (self::$gtm_noscript_printed) {
            return;
        }
        $settings = get_option('clubworx_integration_settings', array());
        if (!isset($settings['analytics_mode']) || $settings['analytics_mode'] !== 'gtm') {
            return;
        }
        $gtm = isset($settings['gtm_container_id']) ? trim($settings['gtm_container_id']) : '';
        if (!preg_match('/^GTM-[A-Z0-9]+$/', $gtm)) {
            return;
        }
        self::$gtm_noscript_printed = true;
        echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr($gtm) . '" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>' . "\n";
    }

    /**
     * If the theme does not call wp_body_open(), output GTM noscript once in the footer.
     */
    public function print_gtm_noscript_footer() {
        if (self::$gtm_noscript_printed) {
            return;
        }
        $this->print_gtm_noscript();
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Routes are registered in Clubworx_REST_API class
    }
    
    /**
     * Render booking form shortcode
     */
    public function render_booking_form($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts(array(
            'show_header' => 'false',
        ), $atts, 'clubworx_trial_booking');
        
        // Start output buffering
        ob_start();
        
        // Include the booking form template
        include CLUBWORX_INTEGRATION_PLUGIN_DIR . 'templates/booking-form.php';
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Clubworx', 'clubworx-integration'),
            __('Clubworx', 'clubworx-integration'),
            'manage_options',
            'clubworx-integration',
            array($this, 'render_admin_page'),
            'dashicons-calendar-alt',
            30
        );
        
        add_submenu_page(
            'clubworx-integration',
            __('Settings', 'clubworx-integration'),
            __('Settings', 'clubworx-integration'),
            'manage_options',
            'clubworx-integration-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        include CLUBWORX_INTEGRATION_PLUGIN_DIR . 'admin/admin-page.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        include CLUBWORX_INTEGRATION_PLUGIN_DIR . 'admin/settings-page.php';
    }
    
    /**
     * Add settings link on plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=clubworx-integration-settings') . '">' . __('Settings', 'clubworx-integration') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Handle CSV export - must run before any output
     */
    public function handle_csv_export() {
        // Check if this is a CSV export request
        if (!isset($_GET['export']) || $_GET['export'] !== 'csv') {
            return;
        }
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'clubworx-integration'));
        }
        
        // Check if we're on the correct admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'clubworx-integration') {
            return;
        }
        
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'clubworx_bookings';
        
        // Check if table exists
        $bookings_exist = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table;
        
        if (!$bookings_exist) {
            wp_die(__('No bookings table found.', 'clubworx-integration'));
        }
        
        // Get all bookings
        $bookings = $wpdb->get_results("SELECT * FROM $bookings_table ORDER BY created_at DESC", ARRAY_A);
        
        // Clear all output buffers to prevent "headers already sent" errors
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Disable any output buffering
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        @ini_set('zlib.output_compression', 0);
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=clubworx-bookings-' . date('Y-m-d') . '.csv');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        
        // Prevent WordPress from sending any additional headers
        remove_all_actions('admin_head');
        remove_all_actions('admin_footer');
        remove_all_actions('wp_head');
        remove_all_actions('wp_footer');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV headers
        $headers = array('ID', 'Type', 'Date', 'First Name', 'Last Name', 'Email', 'Phone', 'Source', 'Medium', 'Class Details', 'Contact Key', 'Event ID');
        fputcsv($output, $headers);
        
        // Add booking data
        foreach ($bookings as $booking) {
            $request_data = json_decode($booking['request_data'], true);
            
            $row = array(
                $booking['id'],
                $booking['type'],
                $booking['created_at'],
                isset($request_data['first_name']) ? $request_data['first_name'] : '',
                isset($request_data['last_name']) ? $request_data['last_name'] : '',
                isset($request_data['email']) ? $request_data['email'] : '',
                isset($request_data['phone']) ? $request_data['phone'] : '',
                isset($booking['source']) ? $booking['source'] : '',
                isset($booking['medium']) ? $booking['medium'] : '',
                isset($request_data['selectedClass']) ? $request_data['selectedClass'] : (isset($request_data['class']) ? $request_data['class'] : ''),
                isset($request_data['contact_key']) ? $request_data['contact_key'] : '',
                isset($request_data['event_id']) ? $request_data['event_id'] : '',
            );
            
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Output dynamic CSS for form customization
     */
    public function output_form_custom_css() {
        // Only output on pages with the booking form
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'clubworx_trial_booking')) {
            return;
        }
        
        $settings = get_option('clubworx_integration_settings', array());
        $theme_integration = isset($settings['form_theme_integration']) && $settings['form_theme_integration'];
        
        // Get theme colors if theme integration is enabled
        $theme_colors = array();
        if ($theme_integration) {
            $theme_colors = $this->get_theme_colors();
        }
        
        // Build CSS variables
        $css_vars = array();
        
        // Primary button colors
        $css_vars['--clubworx-primary-button-bg'] = !empty($settings['form_primary_button_bg']) 
            ? $settings['form_primary_button_bg'] 
            : ($theme_integration && isset($theme_colors['primary']) ? $theme_colors['primary'] : '#32373c');
        
        $css_vars['--clubworx-primary-button-hover'] = !empty($settings['form_primary_button_hover']) 
            ? $settings['form_primary_button_hover'] 
            : ($theme_integration && isset($theme_colors['primary_hover']) ? $theme_colors['primary_hover'] : '#1e2328');
        
        $css_vars['--clubworx-primary-button-text'] = !empty($settings['form_primary_button_text']) 
            ? $settings['form_primary_button_text'] 
            : ($theme_integration && isset($theme_colors['button_text']) ? $theme_colors['button_text'] : '#ffffff');
        
        // Secondary button colors
        $css_vars['--clubworx-secondary-button-bg'] = !empty($settings['form_secondary_button_bg']) 
            ? $settings['form_secondary_button_bg'] 
            : ($theme_integration && isset($theme_colors['secondary']) ? $theme_colors['secondary'] : '#f8f9fa');
        
        $css_vars['--clubworx-secondary-button-hover'] = !empty($settings['form_secondary_button_hover']) 
            ? $settings['form_secondary_button_hover'] 
            : ($theme_integration && isset($theme_colors['secondary_hover']) ? $theme_colors['secondary_hover'] : '#abb8c3');
        
        // Field colors
        $css_vars['--clubworx-field-border-color'] = !empty($settings['form_field_border_color']) 
            ? $settings['form_field_border_color'] 
            : ($theme_integration && isset($theme_colors['border']) ? $theme_colors['border'] : '#abb8c3');
        
        $css_vars['--clubworx-field-focus-color'] = !empty($settings['form_field_focus_color']) 
            ? $settings['form_field_focus_color'] 
            : ($theme_integration && isset($theme_colors['accent']) ? $theme_colors['accent'] : '#0693e3');
        
        $css_vars['--clubworx-field-error-color'] = !empty($settings['form_field_error_color']) 
            ? $settings['form_field_error_color'] 
            : '#cf2e2e';
        
        $css_vars['--clubworx-field-bg-color'] = !empty($settings['form_field_bg_color']) 
            ? $settings['form_field_bg_color'] 
            : ($theme_integration && isset($theme_colors['background']) ? $theme_colors['background'] : '#ffffff');
        
        $css_vars['--clubworx-field-text-color'] = !empty($settings['form_field_text_color']) 
            ? $settings['form_field_text_color'] 
            : ($theme_integration && isset($theme_colors['text']) ? $theme_colors['text'] : '#000000');
        
        // Section colors
        $css_vars['--clubworx-section-bg-color'] = !empty($settings['form_section_bg_color']) 
            ? $settings['form_section_bg_color'] 
            : ($theme_integration && isset($theme_colors['section_bg']) ? $theme_colors['section_bg'] : '#f8f9fa');
        
        $css_vars['--clubworx-section-heading-color'] = !empty($settings['form_section_heading_color']) 
            ? $settings['form_section_heading_color'] 
            : ($theme_integration && isset($theme_colors['heading']) ? $theme_colors['heading'] : '#000000');
        
        $css_vars['--clubworx-label-text-color'] = !empty($settings['form_label_text_color']) 
            ? $settings['form_label_text_color'] 
            : ($theme_integration && isset($theme_colors['text']) ? $theme_colors['text'] : '#000000');
        
        // Border radius
        $css_vars['--clubworx-border-radius'] = !empty($settings['form_border_radius']) 
            ? $settings['form_border_radius'] 
            : '8px';
        
        // Build CSS
        $css = '<style id="clubworx-booking-custom-css">' . "\n";
        $css .= '.clubworx-booking-wrapper {' . "\n";
        
        foreach ($css_vars as $var => $value) {
            $css .= '    ' . $var . ': ' . esc_html($value) . ';' . "\n";
        }
        
        // Add theme integration classes
        if ($theme_integration) {
            $css .= '    --clubworx-theme-integration: 1;' . "\n";
        }
        
        $css .= '}' . "\n";
        
        // Apply CSS variables to form elements
        $css .= '.clubworx-booking-wrapper .submit-btn {' . "\n";
        $css .= '    background: var(--clubworx-primary-button-bg) !important;' . "\n";
        $css .= '    color: var(--clubworx-primary-button-text) !important;' . "\n";
        $css .= '    border-radius: var(--clubworx-border-radius) !important;' . "\n";
        $css .= '}' . "\n";
        
        $css .= '.clubworx-booking-wrapper .submit-btn:hover {' . "\n";
        $css .= '    background: var(--clubworx-primary-button-hover) !important;' . "\n";
        $css .= '}' . "\n";
        
        $css .= '.clubworx-booking-wrapper .btn-secondary {' . "\n";
        $css .= '    background: var(--clubworx-secondary-button-bg) !important;' . "\n";
        $css .= '    border-radius: var(--clubworx-border-radius) !important;' . "\n";
        $css .= '}' . "\n";
        
        $css .= '.clubworx-booking-wrapper .btn-secondary:hover {' . "\n";
        $css .= '    background: var(--clubworx-secondary-button-hover) !important;' . "\n";
        $css .= '}' . "\n";
        
        $css .= '.clubworx-booking-wrapper .form-group input,' . "\n";
        $css .= '.clubworx-booking-wrapper .form-group select,' . "\n";
        $css .= '.clubworx-booking-wrapper .form-group textarea {' . "\n";
        $css .= '    border-color: var(--clubworx-field-border-color) !important;' . "\n";
        $css .= '    background-color: var(--clubworx-field-bg-color) !important;' . "\n";
        $css .= '    color: var(--clubworx-field-text-color) !important;' . "\n";
        $css .= '    border-radius: var(--clubworx-border-radius) !important;' . "\n";
        $css .= '}' . "\n";
        
        $focus_color = $css_vars['--clubworx-field-focus-color'];
        $css .= '.clubworx-booking-wrapper .form-group input:focus,' . "\n";
        $css .= '.clubworx-booking-wrapper .form-group select:focus,' . "\n";
        $css .= '.clubworx-booking-wrapper .form-group textarea:focus {' . "\n";
        $css .= '    border-color: var(--clubworx-field-focus-color) !important;' . "\n";
        $css .= '    box-shadow: 0 0 0 3px rgba(' . $this->hex_to_rgb($focus_color) . ', 0.1) !important;' . "\n";
        $css .= '}' . "\n";
        
        $css .= '.clubworx-booking-wrapper .form-group input.error,' . "\n";
        $css .= '.clubworx-booking-wrapper .form-group select.error,' . "\n";
        $css .= '.clubworx-booking-wrapper .form-group textarea.error {' . "\n";
        $css .= '    border-color: var(--clubworx-field-error-color) !important;' . "\n";
        $css .= '}' . "\n";
        
        $css .= '.clubworx-booking-wrapper .form-section {' . "\n";
        $css .= '    background-color: var(--clubworx-section-bg-color) !important;' . "\n";
        $css .= '}' . "\n";
        
        $css .= '.clubworx-booking-wrapper .form-section h3 {' . "\n";
        $css .= '    color: var(--clubworx-section-heading-color) !important;' . "\n";
        $css .= '    border-bottom-color: var(--clubworx-field-focus-color) !important;' . "\n";
        $css .= '}' . "\n";
        
        $css .= '.clubworx-booking-wrapper .form-group label {' . "\n";
        $css .= '    color: var(--clubworx-label-text-color) !important;' . "\n";
        $css .= '}' . "\n";
        
        // Theme integration styles
        if ($theme_integration) {
            $css .= $this->get_theme_integration_css();
        }
        
        // Custom CSS
        if (!empty($settings['form_custom_css'])) {
            $css .= "\n" . '/* Custom CSS */' . "\n";
            $css .= wp_strip_all_tags($settings['form_custom_css']) . "\n";
        }
        
        $css .= '</style>' . "\n";
        
        echo $css;
    }
    
    /**
     * Get theme colors from active theme
     */
    private function get_theme_colors() {
        $colors = array();
        
        // Try to get colors from theme mods (common in customizer themes)
        $colors['primary'] = get_theme_mod('primary_color', '');
        $colors['secondary'] = get_theme_mod('secondary_color', '');
        $colors['accent'] = get_theme_mod('accent_color', '');
        $colors['text'] = get_theme_mod('text_color', '');
        $colors['background'] = get_theme_mod('background_color', '');
        
        // Try alternative theme mod names
        if (empty($colors['primary'])) {
            $colors['primary'] = get_theme_mod('color_primary', '');
        }
        if (empty($colors['accent'])) {
            $colors['accent'] = get_theme_mod('color_accent', '');
        }
        
        // Get from CSS custom properties if available (modern themes)
        // This would require parsing CSS, so we'll use a simpler approach
        
        // Check for popular theme support
        $theme = wp_get_theme();
        $theme_name = $theme->get('Name');
        
        // Common theme color locations
        if (function_exists('get_theme_mod')) {
            // Try common WordPress theme color options
            $colors['link_color'] = get_theme_mod('link_color', '');
        }
        
        return $colors;
    }
    
    /**
     * Get theme integration CSS
     */
    private function get_theme_integration_css() {
        $css = "\n" . '/* Theme Integration */' . "\n";
        
        // Inherit theme font
        $css .= '.clubworx-booking-wrapper {' . "\n";
        $css .= '    font-family: inherit;' . "\n";
        $css .= '}' . "\n";
        
        // Remove card border/background for seamless integration
        $css .= '.clubworx-booking-wrapper .booking-card {' . "\n";
        $css .= '    border: none;' . "\n";
        $css .= '    background: transparent;' . "\n";
        $css .= '    box-shadow: none;' . "\n";
        $css .= '    padding: 0;' . "\n";
        $css .= '}' . "\n";
        
        // Use theme's container width
        $css .= '.clubworx-booking-wrapper .main-content {' . "\n";
        $css .= '    max-width: inherit;' . "\n";
        $css .= '}' . "\n";
        
        // Match theme spacing
        $css .= '.clubworx-booking-wrapper .form-section {' . "\n";
        $css .= '    margin-bottom: 1.5em;' . "\n";
        $css .= '}' . "\n";
        
        return $css;
    }
    
    /**
     * Convert hex color to RGB for rgba() usage
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return $r . ', ' . $g . ', ' . $b;
    }
}

/**
 * Initialize the plugin
 */
function clubworx_integration_init() {
    return Clubworx_Integration::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'clubworx_integration_init');

