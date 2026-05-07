<?php
/**
 * Admin Dashboard Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Enqueue timetable CSS for shortcode preview
wp_enqueue_style('clubworx-timetable-style', plugin_dir_url(__FILE__) . '../assets/css/timetable.css', array(), '1.0.0');

// Enqueue pricing CSS for shortcode preview
wp_enqueue_style('clubworx-pricing-style', plugin_dir_url(__FILE__) . '../assets/css/pricing.css', array(), '1.0.0');

// Note: CSV export is now handled in the main plugin class via admin_init hook
// This ensures it runs before any output is sent, preventing "headers already sent" errors

// Get recent bookings
global $wpdb;
$bookings_table = $wpdb->prefix . 'clubworx_bookings';
$attribution_table = $wpdb->prefix . 'clubworx_attribution';

// Check if tables exist
$bookings_exist = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table;
$attribution_exist = $wpdb->get_var("SHOW TABLES LIKE '$attribution_table'") === $attribution_table;

// Ensure tables are up to date with latest structure
if ($bookings_exist) {
    // Check if source column exists, if not, add it
    $source_exists = $wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'source'");
    if (empty($source_exists)) {
        $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN source varchar(255) DEFAULT NULL");
        $wpdb->query("ALTER TABLE $bookings_table ADD KEY source (source)");
    }
    
    // Check if medium column exists, if not, add it
    $medium_exists = $wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'medium'");
    if (empty($medium_exists)) {
        $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN medium varchar(255) DEFAULT NULL");
        $wpdb->query("ALTER TABLE $bookings_table ADD KEY medium (medium)");
    }
}

// Get attribution stats
$attribution_stats = array();
if ($attribution_exist) {
    $attribution_stats = $wpdb->get_results("
        SELECT utm_source, utm_medium, COUNT(*) as count 
        FROM $attribution_table 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY utm_source, utm_medium
        ORDER BY count DESC
        LIMIT 10
    ");
}

$settings = get_option('clubworx_integration_settings', array());

$all_locs = class_exists('Clubworx_Locations') ? Clubworx_Locations::all() : array();
$bookings_tab = isset($_GET['location']) ? sanitize_key(wp_unslash($_GET['location'])) : 'all';
if ($bookings_tab !== 'all' && $bookings_tab !== '' && !isset($all_locs[$bookings_tab])) {
    $bookings_tab = 'all';
}

$any_api_configured = false;
foreach ($all_locs as $loc_row) {
    if (!empty($loc_row['api_url']) && !empty($loc_row['api_key'])) {
        $any_api_configured = true;
        break;
    }
}

// Get recent bookings (optional filter by location tab)
$recent_bookings = array();
if ($bookings_exist) {
    if ($bookings_tab !== 'all' && $bookings_tab !== '' && isset($all_locs[$bookings_tab])) {
        $recent_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $bookings_table WHERE account = %s ORDER BY created_at DESC LIMIT 10",
            $bookings_tab
        ));
    } else {
        $recent_bookings = $wpdb->get_results("SELECT * FROM $bookings_table ORDER BY created_at DESC LIMIT 10");
    }
}

$rest_schedule_default_url = add_query_arg(
    'account',
    class_exists('Clubworx_Locations') ? Clubworx_Locations::get_default_slug() : 'primary',
    rest_url('clubworx/v1/schedule-simple')
);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (!$any_api_configured) : ?>
    <div class="notice notice-warning">
        <p><strong><?php esc_html_e('Action required: ClubWorx API not configured', 'clubworx-integration'); ?></strong></p>
        <p><?php esc_html_e('Configure API URL and key for each location under Settings.', 'clubworx-integration'); ?></p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=clubworx-integration-settings')); ?>" class="button button-primary">
                <?php esc_html_e('Open settings', 'clubworx-integration'); ?>
            </a>
        </p>
    </div>
    <?php else : ?>
    <div class="notice notice-success">
        <p><strong><?php esc_html_e('ClubWorx API active', 'clubworx-integration'); ?></strong></p>
        <p><?php esc_html_e('Schedule data is cached per location. Use Refresh Schedule Cache to force a refresh.', 'clubworx-integration'); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="clubworx-admin-tabs">
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url(admin_url('admin.php?page=clubworx-integration')); ?>" class="nav-tab nav-tab-active"><?php _e('Dashboard', 'clubworx-integration'); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=clubworx-integration-settings')); ?>" class="nav-tab"><?php _e('Settings', 'clubworx-integration'); ?></a>
        </h2>
    </div>

    <?php if (!empty($all_locs)) : ?>
    <h2 class="nav-tab-wrapper" style="margin-bottom: 16px;">
        <a href="<?php echo esc_url(add_query_arg(array('page' => 'clubworx-integration', 'location' => 'all'), admin_url('admin.php'))); ?>" class="nav-tab<?php echo $bookings_tab === 'all' ? ' nav-tab-active' : ''; ?>"><?php esc_html_e('All locations', 'clubworx-integration'); ?></a>
        <?php foreach ($all_locs as $slug => $loc_row) : ?>
            <?php
            $lbl = isset($loc_row['label']) ? $loc_row['label'] : $slug;
            $url = add_query_arg(array('page' => 'clubworx-integration', 'location' => $slug), admin_url('admin.php'));
            ?>
            <a href="<?php echo esc_url($url); ?>" class="nav-tab<?php echo $bookings_tab === $slug ? ' nav-tab-active' : ''; ?>"><?php echo esc_html($lbl); ?></a>
        <?php endforeach; ?>
    </h2>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="clubworx-stats-grid">
        <div class="clubworx-stat-card">
            <h3><?php _e('Total Bookings', 'clubworx-integration'); ?></h3>
            <div class="stat-value"><?php echo $bookings_exist ? $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE type='booking'") : 0; ?></div>
            <p class="stat-label"><?php _e('All time', 'clubworx-integration'); ?></p>
        </div>
        
        <div class="clubworx-stat-card">
            <h3><?php _e('This Month', 'clubworx-integration'); ?></h3>
            <div class="stat-value"><?php echo $bookings_exist ? $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE type='booking' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)") : 0; ?></div>
            <p class="stat-label"><?php _e('Last 30 days', 'clubworx-integration'); ?></p>
        </div>
        
        <div class="clubworx-stat-card">
            <h3><?php _e('Info Requests', 'clubworx-integration'); ?></h3>
            <div class="stat-value"><?php echo $bookings_exist ? $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE type='prospect'") : 0; ?></div>
            <p class="stat-label"><?php _e('All time', 'clubworx-integration'); ?></p>
        </div>
        
        <div class="clubworx-stat-card">
            <h3><?php _e('Conversion Rate', 'clubworx-integration'); ?></h3>
            <div class="stat-value">
                <?php 
                if ($bookings_exist) {
                    $total = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
                    $bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE type='booking'");
                    $rate = $total > 0 ? round(($bookings / $total) * 100, 1) : 0;
                    echo $rate . '%';
                } else {
                    echo '0%';
                }
                ?>
            </div>
            <p class="stat-label"><?php _e('Bookings vs total submissions', 'clubworx-integration'); ?></p>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="clubworx-quick-actions">
        <h2><?php _e('Quick Actions', 'clubworx-integration'); ?></h2>
        <div class="clubworx-actions-grid">
            <a href="<?php echo admin_url('admin.php?page=clubworx-integration-settings'); ?>" class="clubworx-action-button">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Configure Settings', 'clubworx-integration'); ?>
            </a>
            <a href="<?php echo admin_url('post-new.php?post_type=page'); ?>" class="clubworx-action-button">
                <span class="dashicons dashicons-welcome-add-page"></span>
                <?php _e('Create Booking Page', 'clubworx-integration'); ?>
            </a>
            <button type="button" id="test-clubworx-api" class="clubworx-action-button">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php _e('Test ClubWorx API', 'clubworx-integration'); ?>
            </button>
            <button type="button" id="clear-schedule-cache" class="clubworx-action-button">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Refresh Schedule Cache', 'clubworx-integration'); ?>
            </button>
            <button type="button" id="show-diagnostics" class="clubworx-action-button">
                <span class="dashicons dashicons-info"></span>
                <?php _e('Show Diagnostics', 'clubworx-integration'); ?>
            </button>
            <button type="button" id="test-clubworx-raw" class="clubworx-action-button">
                <span class="dashicons dashicons-media-code"></span>
                <?php _e('Test ClubWorx Raw', 'clubworx-integration'); ?>
            </button>
            <button type="button" id="debug-class-processing" class="clubworx-action-button">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Debug Class Processing', 'clubworx-integration'); ?>
            </button>
            <button type="button" id="test-auth-methods" class="clubworx-action-button">
                <span class="dashicons dashicons-lock"></span>
                <?php _e('Test Auth Methods', 'clubworx-integration'); ?>
            </button>
            <?php
            $export_csv_url = admin_url('admin.php?page=clubworx-integration&export=csv');
            if ($bookings_tab !== 'all' && $bookings_tab !== '') {
                $export_csv_url = add_query_arg('location', $bookings_tab, $export_csv_url);
            }
            ?>
            <a href="<?php echo esc_url($export_csv_url); ?>" class="clubworx-action-button">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Export Bookings', 'clubworx-integration'); ?>
            </a>
            <button type="button" id="test-email" class="clubworx-action-button">
                <span class="dashicons dashicons-email-alt"></span>
                <?php _e('Test Email', 'clubworx-integration'); ?>
            </button>
            <button type="button" id="check-updates" class="clubworx-action-button">
                <span class="dashicons dashicons-update"></span>
                <?php _e('Check for Updates', 'clubworx-integration'); ?>
            </button>
        </div>
    </div>
    
    <!-- Recent Bookings -->
    <div class="clubworx-recent-bookings">
        <h2><?php _e('Recent Bookings', 'clubworx-integration'); ?></h2>
        <?php if (!empty($recent_bookings)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Type', 'clubworx-integration'); ?></th>
                        <th><?php _e('Account', 'clubworx-integration'); ?></th>
                        <th><?php _e('Date', 'clubworx-integration'); ?></th>
                        <th><?php _e('Details', 'clubworx-integration'); ?></th>
                        <th><?php _e('Source', 'clubworx-integration'); ?></th>
                        <th><?php _e('Medium', 'clubworx-integration'); ?></th>
                        <th><?php _e('Actions', 'clubworx-integration'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bookings as $booking): ?>
                        <tr>
                            <td>
                                <span class="clubworx-badge clubworx-badge-<?php echo esc_attr($booking->type); ?>">
                                    <?php echo esc_html(ucfirst($booking->type)); ?>
                                </span>
                            </td>
                            <td><code><?php echo esc_html(isset($booking->account) ? $booking->account : 'primary'); ?></code></td>
                            <td><?php echo esc_html(mysql2date('M j, Y g:i a', $booking->created_at)); ?></td>
                            <td>
                                <?php 
                                $request_data = json_decode($booking->request_data, true);
                                $details = '';
                                
                                // Try to get name from various possible locations
                                if (isset($request_data['first_name']) && isset($request_data['last_name'])) {
                                    $details = $request_data['first_name'] . ' ' . $request_data['last_name'];
                                } elseif (isset($request_data['name'])) {
                                    $details = $request_data['name'];
                                } elseif (isset($request_data['email'])) {
                                    $details = $request_data['email'];
                                } elseif (isset($request_data['contact_key'])) {
                                    $details = 'Contact: ' . substr($request_data['contact_key'], 0, 8) . '...';
                                } elseif (isset($request_data['event_id'])) {
                                    $details = 'Event: ' . $request_data['event_id'];
                                } else {
                                    $details = 'No details available';
                                }
                                
                                echo esc_html($details);
                                ?>
                            </td>
                            <td>
                                <span class="clubworx-source-badge">
                                    <?php echo esc_html(isset($booking->source) ? $booking->source : 'Direct'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="clubworx-medium-badge">
                                    <?php echo esc_html(isset($booking->medium) ? $booking->medium : 'Unknown'); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button button-small view-booking-details" data-id="<?php echo esc_attr($booking->id); ?>">
                                    <?php _e('View Details', 'clubworx-integration'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="clubworx-empty-state">
                <p><?php _e('No bookings yet. Share your booking page to start receiving trial class requests!', 'clubworx-integration'); ?></p>
                <p><strong><?php _e('Shortcode:', 'clubworx-integration'); ?></strong> <code>[clubworx_trial_booking]</code></p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Live ClubWorx Timetable -->
    <div class="clubworx-live-timetable">
        <h2><?php _e('Live ClubWorx Timetable', 'clubworx-integration'); ?></h2>
        <div class="timetable-controls">
            <button type="button" id="refresh-live-timetable" class="button button-secondary">
                <span class="dashicons dashicons-update"></span> <?php _e('Refresh Timetable', 'clubworx-integration'); ?>
            </button>
            <span class="timetable-status" id="timetable-status"><?php _e('Loading...', 'clubworx-integration'); ?></span>
        </div>
        
        <div id="live-timetable-content" class="timetable-content">
            <div class="timetable-loading">
                <span class="dashicons dashicons-update dashicons-update-spin"></span>
                <?php _e('Loading live timetable from ClubWorx...', 'clubworx-integration'); ?>
            </div>
        </div>
    </div>

    <!-- Shortcode Preview -->
    <div class="clubworx-shortcode-preview">
        <h2><?php _e('Shortcode Preview', 'clubworx-integration'); ?></h2>
        <p><?php _e('Here\'s how your timetable will look using the shortcode on your website:', 'clubworx-integration'); ?></p>
        
        <div class="shortcode-examples">
            <h3><?php _e('Shortcode Usage Examples:', 'clubworx-integration'); ?></h3>
            <div class="shortcode-example">
                <strong><?php _e('Basic Usage:', 'clubworx-integration'); ?></strong>
                <code>[clubworx_timetable]</code>
            </div>
            <div class="shortcode-example">
                <strong><?php _e('With Custom Title:', 'clubworx-integration'); ?></strong>
                <code>[clubworx_timetable title="Our Class Schedule"]</code>
            </div>
            <div class="shortcode-example">
                <strong><?php _e('Adults Only - Weekdays:', 'clubworx-integration'); ?></strong>
                <code>[clubworx_timetable title="Adult Classes" days="weekdays" categories="adults-general,adults-foundations"]</code>
            </div>
            <div class="shortcode-example">
                <strong><?php _e('Kids Classes - Compact Layout:', 'clubworx-integration'); ?></strong>
                <code>[clubworx_timetable title="Kids Classes" layout="compact" categories="kids-under6,kids-over6"]</code>
            </div>
            <div class="shortcode-example">
                <strong><?php _e('Desktop Layout - All Days Side by Side:', 'clubworx-integration'); ?></strong>
                <code>[clubworx_timetable title="Weekly Schedule" layout="desktop"]</code>
            </div>
            <div class="shortcode-example">
                <strong><?php _e('Specific Location (secondary timetable):', 'clubworx-integration'); ?></strong>
                <code>[clubworx_timetable account="your-location-slug"]</code>
            </div>
        </div>

        <div class="clubworx-debug-links">
            <h3><?php _e('Timetable Debug Helper', 'clubworx-integration'); ?></h3>
            <p><?php _e('Use these links to test each location endpoint and copy the exact slug into your shortcode account attribute.', 'clubworx-integration'); ?></p>
            <ul>
                <?php if (!empty($all_locs)) : ?>
                    <?php foreach ($all_locs as $slug => $loc_row) : ?>
                        <?php
                        $loc_label = isset($loc_row['label']) && $loc_row['label'] !== '' ? $loc_row['label'] : $slug;
                        $debug_url = add_query_arg('account', $slug, rest_url('clubworx/v1/timetable'));
                        ?>
                        <li>
                            <strong><?php echo esc_html($loc_label); ?></strong>
                            <code><?php echo esc_html($slug); ?></code>
                            <a href="<?php echo esc_url($debug_url); ?>" target="_blank" rel="noopener noreferrer"><?php _e('Open endpoint', 'clubworx-integration'); ?></a>
                        </li>
                    <?php endforeach; ?>
                <?php else : ?>
                    <li><?php _e('No locations configured yet.', 'clubworx-integration'); ?></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <div class="shortcode-preview-controls">
            <button type="button" id="refresh-shortcode-preview" class="button button-secondary">
                <span class="dashicons dashicons-update"></span> <?php _e('Refresh Preview', 'clubworx-integration'); ?>
            </button>
            <span class="preview-status" id="preview-status"><?php _e('Loading...', 'clubworx-integration'); ?></span>
        </div>
        
        <div id="shortcode-preview-content" class="shortcode-preview-content">
            <div class="preview-loading">
                <span class="dashicons dashicons-update dashicons-update-spin"></span>
                <?php _e('Loading shortcode preview...', 'clubworx-integration'); ?>
            </div>
        </div>
    </div>

    <!-- Pricing Shortcode Preview -->
    <div class="clubworx-pricing-shortcode-preview">
        <h2><?php _e('Pricing Shortcode Preview', 'clubworx-integration'); ?></h2>
        <p><?php _e('Display membership plans from ClubWorx in modern pricing cards:', 'clubworx-integration'); ?></p>
        
        <div class="shortcode-examples">
            <h3><?php _e('Pricing Shortcode Usage Examples:', 'clubworx-integration'); ?></h3>
            <div class="shortcode-example">
                <strong><?php _e('Basic Usage:', 'clubworx-integration'); ?></strong>
                <code>[clubworx_pricing]</code>
            </div>
            <div class="shortcode-example">
                <strong><?php _e('Adults Only:', 'clubworx-integration'); ?></strong>
                <code>[clubworx_pricing category="adults"]</code>
            </div>
            <div class="shortcode-example">
                <strong><?php _e('Highlight Popular Plans:', 'clubworx-integration'); ?></strong>
                <code>[clubworx_pricing highlight="Foundations,Adults General" button_text="Book Trial" button_link="#trial"]</code>
            </div>
            <div class="shortcode-example">
                <strong><?php _e('Keyword Filtering:', 'clubworx-integration'); ?></strong>
                <code>[clubworx_pricing includes="gi,foundations" excludes="kids"]</code>
            </div>
            <div class="shortcode-example">
                <strong><?php _e('Custom Styling:', 'clubworx-integration'); ?></strong>
                <code>[clubworx_pricing title="Choose Your Plan" button_text="Get Started" button_link="/signup"]</code>
            </div>
        </div>
        
        <div class="shortcode-preview-controls">
            <button type="button" id="refresh-pricing-preview" class="button button-secondary">
                <span class="dashicons dashicons-update"></span> <?php _e('Refresh Pricing Preview', 'clubworx-integration'); ?>
            </button>
            <span class="preview-status" id="pricing-preview-status"><?php _e('Loading...', 'clubworx-integration'); ?></span>
        </div>
        
        <div id="pricing-preview-content" class="shortcode-preview-content">
            <div class="preview-loading">
                <span class="dashicons dashicons-update dashicons-update-spin"></span>
                <?php _e('Loading pricing preview...', 'clubworx-integration'); ?>
            </div>
        </div>
    </div>

    <!-- Email Log -->
    <?php 
    $email_log = get_option('clubworx_email_log', array());
    $email_log = array_reverse($email_log); // Show most recent first
    ?>
    <div class="clubworx-email-log">
        <h2><?php _e('Email Log', 'clubworx-integration'); ?></h2>
        <p class="description"><?php _e('Recent email send attempts. Last 50 entries are kept.', 'clubworx-integration'); ?></p>
        <?php if (!empty($email_log)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date/Time', 'clubworx-integration'); ?></th>
                        <th><?php _e('To', 'clubworx-integration'); ?></th>
                        <th><?php _e('Subject', 'clubworx-integration'); ?></th>
                        <th><?php _e('Type', 'clubworx-integration'); ?></th>
                        <th><?php _e('Status', 'clubworx-integration'); ?></th>
                        <th><?php _e('Error', 'clubworx-integration'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($email_log as $log_entry): ?>
                        <tr>
                            <td><?php echo esc_html($log_entry['timestamp']); ?></td>
                            <td><?php echo esc_html($log_entry['to']); ?></td>
                            <td><?php echo esc_html($log_entry['subject']); ?></td>
                            <td><?php echo esc_html($log_entry['type']); ?></td>
                            <td>
                                <?php if ($log_entry['success']): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php _e('Success', 'clubworx-integration'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span>
                                    <?php _e('Failed', 'clubworx-integration'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$log_entry['success'] && isset($log_entry['error'])): ?>
                                    <span style="color: #dc3232;" title="<?php echo esc_attr($log_entry['error']); ?>">
                                        <?php echo esc_html(wp_trim_words($log_entry['error'], 20, '...')); ?>
                                    </span>
                                    <?php if (strlen($log_entry['error']) > 50): ?>
                                        <button type="button" class="button button-small view-email-error" style="margin-left: 5px;" data-error="<?php echo esc_attr($log_entry['error']); ?>">
                                            <?php _e('View Full Error', 'clubworx-integration'); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="clubworx-empty-state">
                <p><?php _e('No email log entries yet. Emails will be logged here when sent.', 'clubworx-integration'); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Attribution Stats -->
    <?php if (!empty($attribution_stats)): ?>
    <div class="clubworx-attribution-stats">
        <h2><?php _e('Attribution Stats (Last 30 Days)', 'clubworx-integration'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Source', 'clubworx-integration'); ?></th>
                    <th><?php _e('Medium', 'clubworx-integration'); ?></th>
                    <th><?php _e('Count', 'clubworx-integration'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attribution_stats as $stat): ?>
                    <tr>
                        <td><?php echo esc_html($stat->utm_source ?: '(not set)'); ?></td>
                        <td><?php echo esc_html($stat->utm_medium ?: '(not set)'); ?></td>
                        <td><strong><?php echo esc_html($stat->count); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
.clubworx-admin-tabs {
    margin: 20px 0;
}

.clubworx-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.clubworx-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    border-radius: 4px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.clubworx-stat-card h3 {
    margin: 0 0 15px 0;
    font-size: 14px;
    color: #646970;
    text-transform: uppercase;
    font-weight: 600;
}

.clubworx-stat-card .stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #1d2327;
    margin-bottom: 5px;
}

.clubworx-stat-card .stat-label {
    font-size: 13px;
    color: #646970;
    margin: 0;
}

.clubworx-quick-actions {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.clubworx-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.clubworx-action-button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 15px 20px;
    background: #f0f0f1;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    text-decoration: none;
    color: #1d2327;
    font-weight: 500;
    transition: all 0.2s;
}

.clubworx-action-button:hover {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

.clubworx-action-button .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.clubworx-recent-bookings,
.clubworx-attribution-stats,
.clubworx-email-log {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.clubworx-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.clubworx-badge-booking {
    background: #d4edda;
    color: #155724;
}

.clubworx-badge-prospect {
    background: #cce5ff;
    color: #004085;
}

.clubworx-source-badge {
    display: inline-block;
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
    background: #f8f9fa;
    color: #495057;
    border: 1px solid #dee2e6;
}

.clubworx-medium-badge {
    display: inline-block;
    padding: 3px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
    background: #e9ecef;
    color: #6c757d;
    border: 1px solid #ced4da;
}

.booking-details-modal h3 {
    margin-top: 0;
    color: #23282d;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.booking-details-content {
    margin: 20px 0;
}

.booking-section {
    margin-bottom: 25px;
}

.booking-section h4 {
    margin: 0 0 10px 0;
    color: #0073aa;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.booking-details-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
}

.booking-details-table td {
    padding: 8px 12px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: top;
}

.booking-details-table td:first-child {
    width: 140px;
    font-weight: 600;
    color: #555;
}

.booking-details-table td:last-child {
    color: #333;
}

.response-data {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 3px;
    padding: 12px;
    font-family: monospace;
    font-size: 12px;
    line-height: 1.4;
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}

.clubworx-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
}

.clubworx-empty-state code {
    background: #f0f0f1;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 14px;
}

/* Live Timetable Styles */
.clubworx-live-timetable {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.timetable-controls {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.timetable-status {
    font-size: 14px;
    color: #646970;
}

.timetable-content {
    min-height: 200px;
}

.timetable-loading {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 40px;
    text-align: center;
    color: #646970;
}

.timetable-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

/* Shortcode Preview Styles */
.clubworx-shortcode-preview {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.shortcode-examples {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.shortcode-example {
    margin: 10px 0;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.shortcode-example:last-child {
    border-bottom: none;
}

.shortcode-example code {
    background: #f1f1f1;
    padding: 4px 8px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
}

.clubworx-debug-links {
    margin: 16px 0;
    padding: 14px 16px;
    background: #f0f6fc;
    border: 1px solid #cfe3f5;
    border-radius: 4px;
}

.clubworx-debug-links ul {
    margin: 8px 0 0 18px;
}

.clubworx-debug-links li {
    margin: 6px 0;
}

.clubworx-debug-links a {
    margin-left: 8px;
}

/* Pricing Shortcode Preview Styles */
.clubworx-pricing-shortcode-preview {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.clubworx-pricing-shortcode-preview .shortcode-examples {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.clubworx-pricing-shortcode-preview .shortcode-example {
    margin: 10px 0;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.clubworx-pricing-shortcode-preview .shortcode-example:last-child {
    border-bottom: none;
}

.clubworx-pricing-shortcode-preview .shortcode-example code {
    background: #f1f1f1;
    padding: 4px 8px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
}

/* Admin Pricing Preview Styles */
.pricing-admin-preview {
    margin: 15px 0;
}

.pricing-admin-preview h4 {
    margin: 15px 0 10px 0;
    color: #1d2327;
}

.pricing-admin-preview table {
    margin: 10px 0;
}

.pricing-admin-preview .category-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pricing-admin-preview .category-adults {
    background: #e3f2fd;
    color: #1976d2;
}

.pricing-admin-preview .category-kids {
    background: #f3e5f5;
    color: #7b1fa2;
}

.pricing-admin-preview .category-women {
    background: #fce4ec;
    color: #c2185b;
}

.pricing-admin-preview .category-general {
    background: #f1f8e9;
    color: #388e3c;
}

.pricing-shortcode-examples {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.shortcode-example-admin {
    margin: 8px 0;
    padding: 5px 0;
}

.shortcode-example-admin code {
    background: #f1f1f1;
    padding: 3px 6px;
    border-radius: 3px;
    font-family: Consolas, Monaco, monospace;
    font-size: 12px;
    color: #d63384;
}

.shortcode-preview-controls {
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.preview-status {
    color: #666;
    font-style: italic;
}

.shortcode-preview-content {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    background: #fff;
    margin-top: 15px;
    min-height: 200px;
}

.preview-loading {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #666;
    font-style: italic;
}

.dashicons-update-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.timetable-day {
    background: #f8f9fa;
    border: 1px solid #e1e5e9;
    border-radius: 4px;
    padding: 15px;
}

.timetable-day h4 {
    margin: 0 0 15px 0;
    color: #1d2327;
    font-size: 16px;
    border-bottom: 2px solid #2271b1;
    padding-bottom: 5px;
}

.timetable-category {
    margin-bottom: 15px;
}

.timetable-category h5 {
    margin: 0 0 8px 0;
    color: #646970;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
}

.timetable-classes {
    list-style: none;
    padding: 0;
    margin: 0;
}

.timetable-classes li {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 8px 12px;
    margin-bottom: 5px;
    font-size: 13px;
}

.timetable-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 15px;
    border-radius: 4px;
    margin: 20px 0;
}

.timetable-no-data {
    text-align: center;
    padding: 40px;
    color: #646970;
}

.dashicons-update-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Load live timetable on page load
    loadLiveTimetable();
    
    // Refresh live timetable button
    $('#refresh-live-timetable').on('click', function() {
        loadLiveTimetable();
    });
    
    // Load shortcode preview on page load
    loadShortcodePreview();
    
    // Refresh shortcode preview button
    $('#refresh-shortcode-preview').on('click', function() {
        loadShortcodePreview();
    });
    
    // Load pricing preview on page load
    loadPricingPreview();
    
    // Refresh pricing preview button
    $('#refresh-pricing-preview').on('click', function() {
        loadPricingPreview();
    });
    
    // Function to load and display live timetable
    function loadLiveTimetable() {
        $('#timetable-status').text('Loading...');
        $('#live-timetable-content').html('<div class="timetable-loading"><span class="dashicons dashicons-update dashicons-update-spin"></span> Loading live timetable from ClubWorx...</div>');
        
        $.ajax({
            url: '<?php echo esc_url($rest_schedule_default_url); ?>',
            method: 'GET',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            }
        }).done(function(response) {
            console.log('Live Timetable Response:', response);
            
            if (response.success && response.schedule) {
                displayTimetable(response.schedule, response.debug);
                $('#timetable-status').text('Last updated: ' + new Date().toLocaleTimeString());
            } else {
                showTimetableError('Failed to load timetable data');
            }
        }).fail(function(xhr) {
            console.error('Live Timetable Error:', xhr);
            showTimetableError('Error loading timetable: ' + (xhr.responseJSON?.message || 'Unknown error'));
        });
    }
    
    // Helper function to extract time from class string
    function extractTimeFromClass(classStr) {
        // Extract time from strings like "General Gi Class - 6:00 PM"
        var timeMatch = classStr.match(/- (\d{1,2}:\d{2} [AP]M)$/);
        return timeMatch ? timeMatch[1] : '12:00 AM';
    }
    
    // Helper function to compare two time strings
    function compareTimes(time1, time2) {
        function timeToMinutes(time) {
            var parts = time.match(/(\d{1,2}):(\d{2}) ([AP]M)/);
            if (!parts) return 0;
            
            var hours = parseInt(parts[1]);
            var minutes = parseInt(parts[2]);
            var ampm = parts[3];
            
            if (ampm === 'PM' && hours !== 12) hours += 12;
            if (ampm === 'AM' && hours === 12) hours = 0;
            
            return hours * 60 + minutes;
        }
        
        return timeToMinutes(time1) - timeToMinutes(time2);
    }
    
    // Function to display timetable
    function displayTimetable(schedule, debug) {
        var html = '<div class="timetable-grid">';
        
        // Define day order
        var days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        var dayNames = {
            'monday': 'Monday',
            'tuesday': 'Tuesday', 
            'wednesday': 'Wednesday',
            'thursday': 'Thursday',
            'friday': 'Friday',
            'saturday': 'Saturday',
            'sunday': 'Sunday'
        };
        
        var hasClasses = false;
        
        days.forEach(function(day) {
            var dayClasses = [];
            
            // Collect all classes for this day with time info for sorting
            if (schedule.kids) {
                if (schedule.kids.under6 && schedule.kids.under6[day]) {
                    schedule.kids.under6[day].forEach(function(cls) {
                        var time = extractTimeFromClass(cls);
                        dayClasses.push({
                            time: time,
                            html: '<li><strong>Kids (Under 6):</strong> ' + cls + '</li>'
                        });
                    });
                }
                if (schedule.kids.over6 && schedule.kids.over6[day]) {
                    schedule.kids.over6[day].forEach(function(cls) {
                        var time = extractTimeFromClass(cls);
                        dayClasses.push({
                            time: time,
                            html: '<li><strong>Kids (6+):</strong> ' + cls + '</li>'
                        });
                    });
                }
            }
            
            if (schedule.adults) {
                if (schedule.adults.general && schedule.adults.general[day]) {
                    schedule.adults.general[day].forEach(function(cls) {
                        var time = extractTimeFromClass(cls);
                        dayClasses.push({
                            time: time,
                            html: '<li><strong>Adults General:</strong> ' + cls + '</li>'
                        });
                    });
                }
                if (schedule.adults.foundations && schedule.adults.foundations[day]) {
                    schedule.adults.foundations[day].forEach(function(cls) {
                        var time = extractTimeFromClass(cls);
                        dayClasses.push({
                            time: time,
                            html: '<li><strong>Adults Foundations:</strong> ' + cls + '</li>'
                        });
                    });
                }
            }
            
            if (schedule.women && schedule.women[day]) {
                schedule.women[day].forEach(function(cls) {
                    var time = extractTimeFromClass(cls);
                    dayClasses.push({
                        time: time,
                        html: '<li><strong>Women:</strong> ' + cls + '</li>'
                    });
                });
            }
            
            // Sort all classes by time (mix categories together)
            dayClasses.sort(function(a, b) {
                return compareTimes(a.time, b.time);
            });
            
            if (dayClasses.length > 0) {
                hasClasses = true;
                html += '<div class="timetable-day">';
                html += '<h4>' + dayNames[day] + '</h4>';
                html += '<ul class="timetable-classes">';
                dayClasses.forEach(function(cls) {
                    html += cls.html;
                });
                html += '</ul>';
                html += '</div>';
            }
        });
        
        html += '</div>';
        
        if (debug) {
            html += '<div style="margin-top: 20px; padding: 10px; background: #f0f0f1; border-radius: 3px; font-size: 12px;">';
            html += '<strong>Debug Info:</strong> ';
            html += 'Configured: ' + (debug.clubworx_configured ? 'Yes' : 'No') + ' | ';
            html += 'Source: ' + debug.data_source + ' | ';
            html += 'Cache: ' + debug.cache_status;
            html += '</div>';
        }
        
        if (!hasClasses) {
            html = '<div class="timetable-no-data">';
            html += '<span class="dashicons dashicons-info" style="font-size: 48px; color: #ccc;"></span><br><br>';
            html += 'No classes found in the schedule.<br>';
            html += 'Check ClubWorx API configuration or try refreshing.';
            html += '</div>';
        }
        
        $('#live-timetable-content').html(html);
    }
    
    // Function to show timetable error
    function showTimetableError(message) {
        $('#live-timetable-content').html('<div class="timetable-error">' + message + '</div>');
        $('#timetable-status').text('Error loading timetable');
    }
    
    // Test ClubWorx API
    $('#test-clubworx-api').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);
        button.find('.dashicons').removeClass('dashicons-admin-plugins').addClass('dashicons-update');
        button.contents().last()[0].textContent = '<?php _e('Testing...', 'clubworx-integration'); ?>';
        
        $.ajax({
            url: '<?php echo esc_url($rest_schedule_default_url); ?>',
            method: 'GET',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            }
        }).done(function(response) {
            console.log('API Test Response:', response);
            
            if (response.success && response.schedule) {
                var scheduleInfo = 'Schedule Data:\n\n';
                
                // Check debug info
                if (response.debug) {
                    scheduleInfo += 'Status: ';
                    if (response.debug.clubworx_configured) {
                        scheduleInfo += '✓ ClubWorx Configured\n';
                    } else {
                        scheduleInfo += '⚠ ClubWorx NOT Configured (using fallback)\n\n';
                        scheduleInfo += 'ACTION REQUIRED:\n';
                        scheduleInfo += '1. Go to Settings tab\n';
                        scheduleInfo += '2. Add ClubWorx API URL\n';
                        scheduleInfo += '3. Add ClubWorx API Key\n\n';
                    }
                    scheduleInfo += 'Cache: ' + (response.debug.cache_status === 'cached' ? 'Using cached data' : 'Fresh data') + '\n';
                    scheduleInfo += 'Source: ' + response.debug.data_source + '\n\n';
                }
                
                // Show programs found
                if (response.schedule.kids) {
                    scheduleInfo += '✓ Kids programs found\n';
                }
                if (response.schedule.adults) {
                    scheduleInfo += '✓ Adults programs found\n';
                }
                if (response.schedule.women) {
                    scheduleInfo += '✓ Women programs found\n';
                }
                
                alert(scheduleInfo);
            } else {
                alert('<?php _e('ClubWorx API connection failed. Check your settings.', 'clubworx-integration'); ?>');
            }
        }).fail(function(xhr) {
            console.error('API Test Error:', xhr);
            var errorMsg = '<?php _e('Error connecting to ClubWorx API', 'clubworx-integration'); ?>';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg += '\n\n' + xhr.responseJSON.message;
            }
            errorMsg += '\n\nCheck browser console (F12) for details.';
            alert(errorMsg);
        }).always(function() {
            button.prop('disabled', false);
            button.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-admin-plugins');
            button.contents().last()[0].textContent = '<?php _e('Test ClubWorx API', 'clubworx-integration'); ?>';
        });
    });
    
    // Clear schedule cache
    $('#clear-schedule-cache').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);
        button.find('.dashicons').addClass('dashicons-update-spin');
        button.contents().last()[0].textContent = '<?php _e('Clearing...', 'clubworx-integration'); ?>';
        
        $.ajax({
            url: '<?php echo rest_url('clubworx/v1/clear-cache'); ?>',
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            }
        }).done(function(response) {
            console.log('Cache Clear Response:', response);
            
            if (response.success) {
                var message = response.message || '<?php _e('Schedule cache cleared!', 'clubworx-integration'); ?>';
                
                if (response.clubworx_configured === false) {
                    message += '\n\n⚠ WARNING: ClubWorx API is NOT configured!\n\n';
                    message += 'The form is using STATIC/HARDCODED data.\n\n';
                    message += 'To get LIVE ClubWorx data:\n';
                    message += '1. Click Settings tab above\n';
                    message += '2. Scroll to "ClubWorx API Settings"\n';
                    message += '3. Enter your ClubWorx API URL and Key\n';
                    message += '4. Click Save Changes\n';
                    message += '5. Come back and click "Test ClubWorx API"';
                } else {
                    message += '\n\n✓ ClubWorx is configured!\n';
                    message += 'API URL: ' + response.api_url;
                }
                
                alert(message);
            } else {
                alert('<?php _e('Failed to clear cache.', 'clubworx-integration'); ?>');
            }
        }).fail(function(xhr) {
            console.error('Cache Clear Error:', xhr);
            alert('<?php _e('Error clearing cache. Check browser console for details.', 'clubworx-integration'); ?>');
        }).always(function() {
            button.prop('disabled', false);
            button.find('.dashicons').removeClass('dashicons-update-spin');
            button.contents().last()[0].textContent = '<?php _e('Refresh Schedule Cache', 'clubworx-integration'); ?>';
        });
    });
    
    // Show diagnostics
    $('#show-diagnostics').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);
        button.find('.dashicons').addClass('dashicons-update-spin');
        button.contents().last()[0].textContent = '<?php _e('Loading...', 'clubworx-integration'); ?>';
        
        $.ajax({
            url: '<?php echo rest_url('clubworx/v1/diagnostics'); ?>',
            method: 'GET',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            }
        }).done(function(response) {
            console.log('Diagnostics:', response);
            
            var message = 'Clubworx Integration Diagnostics:\n\n';
            message += 'WordPress: ' + response.wordpress_version + '\n';
            message += 'Plugin: ' + response.plugin_version + '\n';
            message += 'Timestamp: ' + response.timestamp + '\n\n';
            
            message += 'ClubWorx Configuration:\n';
            message += 'API URL: ' + (response.clubworx_api_url || 'NOT SET') + '\n';
            message += 'API Key: ' + (response.clubworx_api_key_set ? 'SET' : 'NOT SET') + '\n';
            message += 'Configured: ' + (response.clubworx_configured ? 'YES' : 'NO') + '\n\n';
            
            message += 'Cache Status:\n';
            message += 'Cache exists: ' + (response.cache_exists ? 'YES' : 'NO') + '\n';
            if (response.cache_expires) {
                var expiresDate = new Date(response.cache_expires * 1000);
                message += 'Cache expires: ' + expiresDate.toLocaleString() + '\n';
            }
            message += '\n';
            
            message += 'REST API:\n';
            message += 'URL: ' + response.rest_api_url + '\n';
            message += 'Enabled: ' + (response.wp_rest_enabled ? 'YES' : 'NO') + '\n\n';
            
            if (response.current_schedule) {
                message += 'Current Schedule:\n';
                message += 'Has Kids: ' + (response.current_schedule.has_kids ? 'YES' : 'NO') + '\n';
                message += 'Has Adults: ' + (response.current_schedule.has_adults ? 'YES' : 'NO') + '\n';
                message += 'Has Women: ' + (response.current_schedule.has_women ? 'YES' : 'NO') + '\n';
                
                if (response.current_schedule.sample_classes && response.current_schedule.sample_classes.length > 0) {
                    message += '\nSample Monday Classes:\n';
                    response.current_schedule.sample_classes.forEach(function(cls) {
                        message += '- ' + cls + '\n';
                    });
                }
            }
            
            if (response.schedule_error) {
                message += '\nError: ' + response.schedule_error;
            }
            
            alert(message);
        }).fail(function(xhr) {
            console.error('Diagnostics Error:', xhr);
            alert('Error getting diagnostics. Check browser console for details.');
        }).always(function() {
            button.prop('disabled', false);
            button.find('.dashicons').removeClass('dashicons-update-spin');
            button.contents().last()[0].textContent = '<?php _e('Show Diagnostics', 'clubworx-integration'); ?>';
        });
    });
    
    // Test ClubWorx Raw Response
    $('#test-clubworx-raw').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);
        button.find('.dashicons').addClass('dashicons-update-spin');
        button.contents().last()[0].textContent = '<?php _e('Testing...', 'clubworx-integration'); ?>';
        
        $.ajax({
            url: '<?php echo rest_url('clubworx/v1/test-clubworx-raw'); ?>',
            method: 'GET',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            }
        }).done(function(response) {
            console.log('ClubWorx Raw Response:', response);
            
            if (response.success) {
                var message = 'ClubWorx Raw API Response:\n\n';
                message += 'Status Code: ' + response.status_code + '\n';
                message += 'API URL: ' + response.api_url + '\n';
                message += 'Timestamp: ' + response.timestamp + '\n\n';
                
                message += 'Raw Response:\n';
                message += response.raw_response + '\n\n';
                
                if (response.parsed_response) {
                    message += 'Parsed Response:\n';
                    message += JSON.stringify(response.parsed_response, null, 2);
                }
                
                // Show in a scrollable textarea instead of alert
                var modal = $('<div>').css({
                    'position': 'fixed',
                    'top': '50px',
                    'left': '50px',
                    'right': '50px',
                    'bottom': '50px',
                    'background': 'white',
                    'border': '2px solid #333',
                    'border-radius': '5px',
                    'padding': '20px',
                    'z-index': '10000',
                    'overflow': 'auto'
                });
                
                modal.html('<h3>ClubWorx Raw API Response</h3>' +
                    '<button onclick="jQuery(this).parent().remove()" style="float: right; margin-bottom: 10px;">Close</button>' +
                    '<textarea readonly style="width: 100%; height: 80%; font-family: monospace; font-size: 12px;">' + 
                    message.replace(/\n/g, '\n') + '</textarea>');
                
                $('body').append(modal);
                
            } else {
                alert('ClubWorx API Test Failed:\n\n' + response.message);
            }
        }).fail(function(xhr) {
            console.error('ClubWorx Raw Test Error:', xhr);
            alert('Error testing ClubWorx API. Check browser console for details.');
        }).always(function() {
            button.prop('disabled', false);
            button.find('.dashicons').removeClass('dashicons-update-spin');
            button.contents().last()[0].textContent = '<?php _e('Test ClubWorx Raw', 'clubworx-integration'); ?>';
        });
    });
    
    // View Booking Details
    $('.view-booking-details').on('click', function() {
        var button = $(this);
        var bookingId = button.data('id');
        
        // Make AJAX request to get booking details
        $.ajax({
            url: '<?php echo rest_url('clubworx/v1/booking-details'); ?>',
            method: 'GET',
            data: { id: bookingId },
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            }
        }).done(function(response) {
            if (response.success && response.booking) {
                showBookingDetailsModal(response.booking);
            } else {
                alert('Failed to load booking details: ' + (response.message || 'Unknown error'));
            }
        }).fail(function(xhr) {
            console.error('Booking Details Error:', xhr);
            alert('Error loading booking details. Check browser console for details.');
        });
    });
    
    // Function to show booking details modal
    function showBookingDetailsModal(booking) {
        var requestData = booking.request_data || {};
        var responseData = booking.response_data || {};
        
        var html = '<div class="booking-details-modal">';
        html += '<h3>Booking Details</h3>';
        html += '<div class="booking-details-content">';
        
        // Basic Info
        html += '<div class="booking-section">';
        html += '<h4>Basic Information</h4>';
        html += '<table class="booking-details-table">';
        html += '<tr><td><strong>Type:</strong></td><td>' + (booking.type || 'N/A') + '</td></tr>';
        html += '<tr><td><strong>Date:</strong></td><td>' + (booking.created_at || 'N/A') + '</td></tr>';
        html += '<tr><td><strong>Source:</strong></td><td>' + (booking.source || 'Direct') + '</td></tr>';
        html += '<tr><td><strong>Medium:</strong></td><td>' + (booking.medium || 'Unknown') + '</td></tr>';
        html += '</table>';
        html += '</div>';
        
        // Contact Info
        if (requestData.first_name || requestData.last_name || requestData.email || requestData.phone) {
            html += '<div class="booking-section">';
            html += '<h4>Contact Information</h4>';
            html += '<table class="booking-details-table">';
            if (requestData.first_name) html += '<tr><td><strong>First Name:</strong></td><td>' + requestData.first_name + '</td></tr>';
            if (requestData.last_name) html += '<tr><td><strong>Last Name:</strong></td><td>' + requestData.last_name + '</td></tr>';
            if (requestData.email) html += '<tr><td><strong>Email:</strong></td><td>' + requestData.email + '</td></tr>';
            if (requestData.phone) html += '<tr><td><strong>Phone:</strong></td><td>' + requestData.phone + '</td></tr>';
            html += '</table>';
            html += '</div>';
        }
        
        // Class Information
        if (requestData.group || requestData.ageGroup || requestData.selectedClass) {
            html += '<div class="booking-section">';
            html += '<h4>Class Information</h4>';
            html += '<table class="booking-details-table">';
            if (requestData.group) html += '<tr><td><strong>Group:</strong></td><td>' + requestData.group + '</td></tr>';
            if (requestData.ageGroup) html += '<tr><td><strong>Age Group:</strong></td><td>' + requestData.ageGroup + '</td></tr>';
            if (requestData.selectedClass) html += '<tr><td><strong>Selected Class:</strong></td><td>' + requestData.selectedClass + '</td></tr>';
            if (requestData.day) html += '<tr><td><strong>Day:</strong></td><td>' + requestData.day + '</td></tr>';
            html += '</table>';
            html += '</div>';
        }
        
        // ClubWorx Response
        if (responseData && Object.keys(responseData).length > 0) {
            html += '<div class="booking-section">';
            html += '<h4>ClubWorx Response</h4>';
            html += '<pre class="response-data">' + JSON.stringify(responseData, null, 2) + '</pre>';
            html += '</div>';
        }
        
        html += '</div>';
        html += '<button type="button" class="button button-primary close-booking-modal" style="margin-top: 15px;">Close</button>';
        html += '</div>';
        
        // Create modal
        var modal = $('<div>').css({
            'position': 'fixed',
            'top': '50px',
            'left': '50px',
            'right': '50px',
            'bottom': '50px',
            'background': 'white',
            'border': '2px solid #333',
            'border-radius': '5px',
            'padding': '20px',
            'z-index': '10000',
            'overflow': 'auto',
            'box-shadow': '0 4px 20px rgba(0,0,0,0.3)'
        });
        
        modal.html(html);
        $('body').append(modal);
        
        // Add event handler for close button
        modal.find('.close-booking-modal').on('click', function() {
            modal.remove();
        });
        
        // Also close on Escape key
        $(document).on('keydown.booking-modal', function(e) {
            if (e.keyCode === 27) { // Escape key
                modal.remove();
                $(document).off('keydown.booking-modal');
            }
        });
        
        // Close when clicking outside modal
        modal.on('click', function(e) {
            if (e.target === modal[0]) {
                modal.remove();
                $(document).off('keydown.booking-modal');
            }
        });
    }
    
    // Debug Class Processing
    $('#debug-class-processing').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);
        button.find('.dashicons').addClass('dashicons-update-spin');
        button.contents().last()[0].textContent = '<?php _e('Debugging...', 'clubworx-integration'); ?>';
        
        $.ajax({
            url: '<?php echo rest_url('clubworx/v1/debug-class-processing'); ?>',
            method: 'GET',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            }
        }).done(function(response) {
            console.log('Class Processing Debug:', response);
            
            var message = 'Class Processing Debug Report:\n\n';
            message += 'ClubWorx Configured: ' + (response.configured ? 'Yes' : 'No') + '\n\n';
            
            if (response.processing_stats) {
                message += 'PROCESSING STATISTICS:\n';
                message += 'Raw Classes from ClubWorx: ' + response.processing_stats.total_raw_classes + '\n';
                message += 'Processed Classes: ' + response.processing_stats.total_processed_classes + '\n';
                message += 'Skipped Classes: ' + response.processing_stats.total_skipped_classes + '\n\n';
                
                if (response.processing_stats.class_breakdown) {
                    message += 'CLASS BREAKDOWN BY CATEGORY/DAY:\n';
                    for (var key in response.processing_stats.class_breakdown) {
                        message += key + ': ' + response.processing_stats.class_breakdown[key] + ' classes\n';
                    }
                    message += '\n';
                }
            }
            
            if (response.raw_clubworx_data) {
                message += 'RAW CLUBWORX DATA STRUCTURE:\n';
                message += 'Keys: ' + Object.keys(response.raw_clubworx_data).join(', ') + '\n';
                if (response.raw_clubworx_data.events) {
                    message += 'Events count: ' + response.raw_clubworx_data.events.length + '\n';
                }
                if (response.raw_clubworx_data.classes) {
                    message += 'Classes count: ' + response.raw_clubworx_data.classes.length + '\n';
                }
                message += '\n';
            }
            
            // Show in a scrollable modal
            var modal = $('<div>').css({
                'position': 'fixed',
                'top': '50px',
                'left': '50px',
                'right': '50px',
                'bottom': '50px',
                'background': 'white',
                'border': '2px solid #333',
                'border-radius': '5px',
                'padding': '20px',
                'z-index': '10000',
                'overflow': 'auto'
            });
            
            modal.html('<h3>Class Processing Debug Report</h3>' +
                '<button onclick="jQuery(this).parent().remove()" style="float: right; margin-bottom: 10px;">Close</button>' +
                '<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; white-space: pre-wrap; font-family: monospace; font-size: 12px;">' + 
                message + '</pre>');
            
            $('body').append(modal);
            
        }).fail(function(xhr) {
            console.error('Debug Class Processing Error:', xhr);
            alert('Error debugging class processing. Check browser console for details.');
        }).always(function() {
            button.prop('disabled', false);
            button.find('.dashicons').removeClass('dashicons-update-spin');
            button.contents().last()[0].textContent = '<?php _e('Debug Class Processing', 'clubworx-integration'); ?>';
        });
    });
    
    // Function to load and display shortcode preview
    function loadShortcodePreview() {
        $('#preview-status').text('Loading...');
        $('#shortcode-preview-content').html('<div class="preview-loading"><span class="dashicons dashicons-update dashicons-update-spin"></span> Loading shortcode preview...</div>');
        
        $.ajax({
            url: '<?php echo esc_url($rest_schedule_default_url); ?>',
            method: 'GET',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            }
        }).done(function(response) {
            console.log('Shortcode Preview Response:', response);
            
            if (response.success && response.schedule) {
                displayShortcodePreview(response.schedule);
                $('#preview-status').text('Last updated: ' + new Date().toLocaleTimeString());
            } else {
                showPreviewError('Failed to load timetable data');
            }
        }).fail(function(xhr) {
            console.error('Shortcode Preview Error:', xhr);
            showPreviewError('Error loading timetable: ' + (xhr.responseJSON?.message || 'Unknown error'));
        });
    }
    
    // Function to display shortcode preview
    function displayShortcodePreview(schedule) {
        var html = '<div class="clubworx-timetable clubworx-timetable-desktop">';
        html += '<h3 class="clubworx-timetable-title">Class Schedule</h3>';
        html += '<div class="clubworx-timetable-content">';
        
        // Define day order
        var days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        var dayNames = {
            'monday': 'Monday',
            'tuesday': 'Tuesday', 
            'wednesday': 'Wednesday',
            'thursday': 'Thursday',
            'friday': 'Friday',
            'saturday': 'Saturday',
            'sunday': 'Sunday'
        };
        
        var hasClasses = false;
        
        days.forEach(function(day) {
            var dayClasses = [];
            
            // Collect all classes for this day with time info for sorting
            if (schedule.kids) {
                if (schedule.kids.under6 && schedule.kids.under6[day]) {
                    schedule.kids.under6[day].forEach(function(cls) {
                        var time = extractTimeFromClass(cls);
                        var className = cls.replace(/ - \d{1,2}:\d{2} [AP]M$/, '');
                        dayClasses.push({
                            time: time,
                            html: '<div class="clubworx-timetable-class kids-under6">' +
                                '<span class="clubworx-timetable-time">' + time + '</span>' +
                                '<span class="clubworx-timetable-class-name">' + className + '</span>' +
                                '<span class="clubworx-timetable-category">Kids (Under 6)</span>' +
                                '</div>'
                        });
                    });
                }
                if (schedule.kids.over6 && schedule.kids.over6[day]) {
                    schedule.kids.over6[day].forEach(function(cls) {
                        var time = extractTimeFromClass(cls);
                        var className = cls.replace(/ - \d{1,2}:\d{2} [AP]M$/, '');
                        dayClasses.push({
                            time: time,
                            html: '<div class="clubworx-timetable-class kids-over6">' +
                                '<span class="clubworx-timetable-time">' + time + '</span>' +
                                '<span class="clubworx-timetable-class-name">' + className + '</span>' +
                                '<span class="clubworx-timetable-category">Kids (6+)</span>' +
                                '</div>'
                        });
                    });
                }
            }
            
            if (schedule.adults) {
                if (schedule.adults.general && schedule.adults.general[day]) {
                    schedule.adults.general[day].forEach(function(cls) {
                        var time = extractTimeFromClass(cls);
                        var className = cls.replace(/ - \d{1,2}:\d{2} [AP]M$/, '');
                        dayClasses.push({
                            time: time,
                            html: '<div class="clubworx-timetable-class adults-general">' +
                                '<span class="clubworx-timetable-time">' + time + '</span>' +
                                '<span class="clubworx-timetable-class-name">' + className + '</span>' +
                                '<span class="clubworx-timetable-category">Adults General</span>' +
                                '</div>'
                        });
                    });
                }
                if (schedule.adults.foundations && schedule.adults.foundations[day]) {
                    schedule.adults.foundations[day].forEach(function(cls) {
                        var time = extractTimeFromClass(cls);
                        var className = cls.replace(/ - \d{1,2}:\d{2} [AP]M$/, '');
                        dayClasses.push({
                            time: time,
                            html: '<div class="clubworx-timetable-class adults-foundations">' +
                                '<span class="clubworx-timetable-time">' + time + '</span>' +
                                '<span class="clubworx-timetable-class-name">' + className + '</span>' +
                                '<span class="clubworx-timetable-category">Adults Foundations</span>' +
                                '</div>'
                        });
                    });
                }
            }
            
            if (schedule.women && schedule.women[day]) {
                schedule.women[day].forEach(function(cls) {
                    var time = extractTimeFromClass(cls);
                    var className = cls.replace(/ - \d{1,2}:\d{2} [AP]M$/, '');
                    dayClasses.push({
                        time: time,
                        html: '<div class="clubworx-timetable-class women">' +
                            '<span class="clubworx-timetable-time">' + time + '</span>' +
                            '<span class="clubworx-timetable-class-name">' + className + '</span>' +
                            '<span class="clubworx-timetable-category">Women</span>' +
                            '</div>'
                    });
                });
            }
            
            // Sort all classes by time (mix categories together)
            dayClasses.sort(function(a, b) {
                return compareTimes(a.time, b.time);
            });
            
            if (dayClasses.length > 0) {
                hasClasses = true;
                html += '<div class="clubworx-timetable-day">';
                html += '<h4 class="clubworx-timetable-day-title">' + dayNames[day] + '</h4>';
                html += '<div class="clubworx-timetable-classes">';
                dayClasses.forEach(function(cls) {
                    html += cls.html;
                });
                html += '</div>';
                html += '</div>';
            }
        });
        
        html += '</div>';
        html += '</div>';
        
        if (!hasClasses) {
            html = '<div class="clubworx-timetable-error">No classes scheduled for the current week.</div>';
        }
        
        $('#shortcode-preview-content').html(html);
    }
    
    // Function to show preview error
    function showPreviewError(message) {
        $('#shortcode-preview-content').html('<div class="clubworx-timetable-error">' + message + '</div>');
        $('#preview-status').text('Error loading preview');
    }
    
    // Function to load pricing preview
    function loadPricingPreview() {
        $('#pricing-preview-status').text('Loading...');
        $('#pricing-preview-content').html('<div class="preview-loading"><span class="dashicons dashicons-update dashicons-update-spin"></span> Loading pricing preview...</div>');
        
        $.ajax({
            url: '<?php echo rest_url('clubworx/v1/membership-plans'); ?>',
            method: 'GET',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            }
        }).done(function(response) {
            console.log('Pricing Preview Response:', response);
            
            if (response.success && response.plans) {
                displayPricingPreview(response.plans);
                $('#pricing-preview-status').text('Last updated: ' + new Date().toLocaleTimeString());
            } else {
                showPricingPreviewError('Failed to load pricing data');
            }
        }).fail(function(xhr) {
            console.error('Pricing Preview Error:', xhr);
            showPricingPreviewError('Error loading pricing: ' + (xhr.responseJSON?.message || 'Unknown error'));
        });
    }
    
    // Function to display pricing preview
    function displayPricingPreview(plans) {
        if (!plans || plans.length === 0) {
            $('#pricing-preview-content').html('<div class="clubworx-pricing-error">No pricing plans available. This could be because ClubWorx API is not configured or no plans are available.</div>');
            return;
        }
        
        var html = '<div class="pricing-admin-preview">';
        html += '<h4>Available Membership Plans (' + plans.length + ' total):</h4>';
        html += '<table class="wp-list-table widefat fixed striped">';
        html += '<thead>';
        html += '<tr>';
        html += '<th>Plan Name</th>';
        html += '<th>Price</th>';
        html += '<th>Category</th>';
        html += '<th>Billing Cycle</th>';
        html += '<th>Description</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        plans.forEach(function(plan) {
            html += '<tr>';
            html += '<td><strong>' + plan.name + '</strong></td>';
            html += '<td>' + plan.currency + ' ' + Math.round(plan.price) + '</td>';
            html += '<td><span class="category-badge category-' + plan.category + '">' + plan.category + '</span></td>';
            html += '<td>' + plan.billing_cycle + '</td>';
            html += '<td>' + (plan.description || 'No description') + '</td>';
            html += '</tr>';
        });
        
        html += '</tbody>';
        html += '</table>';
        
        // Add shortcode examples
        html += '<div class="pricing-shortcode-examples">';
        html += '<h4>Shortcode Examples:</h4>';
        html += '<div class="shortcode-example-admin">';
        html += '<code>[clubworx_pricing]</code> - Show all plans';
        html += '</div>';
        html += '<div class="shortcode-example-admin">';
        html += '<code>[clubworx_pricing category="adults"]</code> - Adults only';
        html += '</div>';
        html += '<div class="shortcode-example-admin">';
        html += '<code>[clubworx_pricing highlight="' + plans[0].name + '"]</code> - Highlight first plan';
        html += '</div>';
        html += '</div>';
        
        html += '</div>';
        
        $('#pricing-preview-content').html(html);
    }
    
    // Function to show pricing preview error
    function showPricingPreviewError(message) {
        $('#pricing-preview-content').html('<div class="clubworx-pricing-error">' + message + '</div>');
        $('#pricing-preview-status').text('Error loading preview');
    }
    
    // Test Email button
    $('#test-email').on('click', function() {
        var button = $(this);
        button.prop('disabled', true);
        button.find('.dashicons').removeClass('dashicons-email-alt').addClass('dashicons-update');
        button.contents().last()[0].textContent = '<?php _e('Sending...', 'clubworx-integration'); ?>';
        
        $.ajax({
            url: '<?php echo rest_url('clubworx/v1/test-email'); ?>',
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>',
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                account: '<?php echo esc_js(class_exists('Clubworx_Locations') ? Clubworx_Locations::get_default_slug() : 'primary'); ?>'
            })
        }).done(function(response) {
            console.log('Test Email Response:', response);
            
            if (response.success) {
                alert('✅ ' + response.message + '\n\nCheck your inbox and the Email Log below.');
                // Reload page to show updated email log
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                // Build detailed error message
                var errorMsg = '❌ ' + response.message;
                
                if (response.error) {
                    errorMsg += '\n\nError: ' + response.error;
                }
                
                if (response.error_details && response.error_details.length > 0) {
                    errorMsg += '\n\nDetails:\n' + response.error_details.join('\n');
                }
                
                if (response.diagnostics) {
                    errorMsg += '\n\nDiagnostics:';
                    errorMsg += '\n- Mail function exists: ' + (response.diagnostics.mail_function_exists ? 'Yes' : 'No');
                    errorMsg += '\n- SMTP plugins active: ' + (response.diagnostics.smtp_configured ? 'Yes' : 'No');
                    if (response.diagnostics.smtp_plugins_active && response.diagnostics.smtp_plugins_active.length > 0) {
                        errorMsg += '\n- Active SMTP plugins: ' + response.diagnostics.smtp_plugins_active.join(', ');
                    }
                }
                
                if (response.suggestions && response.suggestions.length > 0) {
                    errorMsg += '\n\nTroubleshooting:\n' + response.suggestions.join('\n');
                }
                
                errorMsg += '\n\nCheck the Email Log below for more details.';
                
                // Show in modal for better readability
                showEmailErrorModal(errorMsg, response);
                
                // Reload page to show updated email log
                setTimeout(function() {
                    location.reload();
                }, 2000);
            }
        }).fail(function(xhr) {
            console.error('Test Email Error:', xhr);
            var errorMsg = '<?php _e('Error sending test email', 'clubworx-integration'); ?>';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg += '\n\n' + xhr.responseJSON.message;
                if (xhr.responseJSON.error) {
                    errorMsg += '\n\nError: ' + xhr.responseJSON.error;
                }
            }
            alert(errorMsg);
        }).always(function() {
            button.prop('disabled', false);
            button.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-email-alt');
            button.contents().last()[0].textContent = '<?php _e('Test Email', 'clubworx-integration'); ?>';
        });
    });
    
    // Function to show email error modal with detailed information
    function showEmailErrorModal(message, response) {
        var modal = $('<div>').css({
            'position': 'fixed',
            'top': '50px',
            'left': '50px',
            'right': '50px',
            'bottom': '50px',
            'background': 'white',
            'border': '2px solid #dc3232',
            'border-radius': '5px',
            'padding': '20px',
            'z-index': '10000',
            'overflow': 'auto',
            'box-shadow': '0 4px 20px rgba(0,0,0,0.3)'
        });
        
        var html = '<h2 style="color: #dc3232; margin-top: 0;">Email Test Failed</h2>';
        html += '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px;">';
        html += '<pre style="white-space: pre-wrap; font-family: monospace; font-size: 12px; margin: 0;">' + message + '</pre>';
        html += '</div>';
        
        if (response && response.diagnostics) {
            html += '<h3>System Diagnostics:</h3>';
            html += '<table style="width: 100%; border-collapse: collapse; margin: 15px 0;">';
            html += '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>PHP mail() function:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' + (response.diagnostics.mail_function_exists ? '✅ Available' : '❌ Not available') + '</td></tr>';
            html += '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>SMTP Configured:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' + (response.diagnostics.smtp_configured ? '✅ Yes' : '❌ No') + '</td></tr>';
            if (response.diagnostics.plugin_smtp_configured) {
                html += '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Plugin SMTP:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">✅ Enabled (' + (response.diagnostics.plugin_smtp_host || '') + ':' + (response.diagnostics.plugin_smtp_port || '') + ', ' + (response.diagnostics.plugin_smtp_encryption || 'none') + ')</td></tr>';
            } else if (response.diagnostics.plugin_smtp_enabled) {
                html += '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Plugin SMTP:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">⚠️ Enabled but not fully configured</td></tr>';
            }
            if (response.diagnostics.smtp_plugins_active && response.diagnostics.smtp_plugins_active.length > 0) {
                html += '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>SMTP Plugins:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' + response.diagnostics.smtp_plugins_active.join(', ') + '</td></tr>';
            }
            html += '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>Server:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' + (response.diagnostics.server_name || 'Unknown') + '</td></tr>';
            html += '<tr><td style="padding: 8px; border: 1px solid #ddd;"><strong>PHP Version:</strong></td><td style="padding: 8px; border: 1px solid #ddd;">' + (response.diagnostics.php_version || 'Unknown') + '</td></tr>';
            html += '</table>';
        }
        
        html += '<button type="button" class="button button-primary" onclick="jQuery(this).closest(\'div\').remove()" style="margin-top: 15px;">Close</button>';
        
        modal.html(html);
        $('body').append(modal);
    }
    
    // View full email error
    $(document).on('click', '.view-email-error', function() {
        var error = $(this).data('error');
        var modal = $('<div>').css({
            'position': 'fixed',
            'top': '50px',
            'left': '50px',
            'right': '50px',
            'bottom': '50px',
            'background': 'white',
            'border': '2px solid #dc3232',
            'border-radius': '5px',
            'padding': '20px',
            'z-index': '10000',
            'overflow': 'auto',
            'box-shadow': '0 4px 20px rgba(0,0,0,0.3)'
        });
        
        var html = '<h2 style="color: #dc3232; margin-top: 0;">Email Error Details</h2>';
        html += '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 4px;">';
        html += '<pre style="white-space: pre-wrap; font-family: monospace; font-size: 12px; margin: 0;">' + error + '</pre>';
        html += '</div>';
        html += '<button type="button" class="button button-primary" onclick="jQuery(this).closest(\'div\').remove()" style="margin-top: 15px;">Close</button>';
        
        modal.html(html);
        $('body').append(modal);
    });
    
    // Check for updates
    $('#check-updates').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-update-spin"></span> <?php _e('Checking...', 'clubworx-integration'); ?>');
        
        $.ajax({
            url: '<?php echo rest_url('clubworx/v1/check-updates'); ?>',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(response) {
                button.prop('disabled', false).html(originalText);
                
                var modal = $('<div>').css({
                    'position': 'fixed',
                    'top': '50px',
                    'left': '50px',
                    'right': '50px',
                    'max-width': '600px',
                    'margin': '0 auto',
                    'background': 'white',
                    'border': '2px solid #' + (response.update_available ? '46b450' : '0073aa'),
                    'border-radius': '5px',
                    'padding': '20px',
                    'z-index': '10000',
                    'overflow': 'auto',
                    'box-shadow': '0 4px 20px rgba(0,0,0,0.3)'
                });
                
                var html = '<h2 style="margin-top: 0; color: #' + (response.update_available ? '46b450' : '0073aa') + ';">' + (response.update_available ? 'Update Available!' : 'Plugin Up to Date') + '</h2>';
                html += '<p><strong>Current Version:</strong> ' + response.current_version + '</p>';
                html += '<p><strong>Latest Version:</strong> ' + response.latest_version + '</p>';
                
                if (response.update_available) {
                    html += '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 15px 0; border-radius: 4px;">';
                    html += '<p><strong>Update Available!</strong></p>';
                    html += '<p>An update is available. You can update the plugin from the <a href="<?php echo admin_url('plugins.php'); ?>" target="_blank">Plugins page</a> or from <a href="<?php echo admin_url('update-core.php'); ?>" target="_blank">Dashboard → Updates</a>.</p>';
                    if (response.release_url) {
                        html += '<p><a href="' + response.release_url + '" target="_blank" class="button button-primary">View Release Notes</a></p>';
                    }
                    html += '</div>';
                } else {
                    html += '<div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 15px 0; border-radius: 4px;">';
                    html += '<p>Your plugin is up to date!</p>';
                    html += '</div>';
                }
                
                if (response.release_notes) {
                    html += '<div style="margin-top: 15px;">';
                    html += '<h3>Release Notes:</h3>';
                    html += '<div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto;">';
                    html += '<pre style="white-space: pre-wrap; font-family: inherit; margin: 0;">' + response.release_notes + '</pre>';
                    html += '</div>';
                    html += '</div>';
                }
                
                html += '<button type="button" class="button button-primary" onclick="jQuery(this).closest(\'div\').remove()" style="margin-top: 15px;">Close</button>';
                
                modal.html(html);
                $('body').append(modal);
            },
            error: function(xhr) {
                button.prop('disabled', false).html(originalText);
                
                var errorMessage = 'Failed to check for updates.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                alert('Error: ' + errorMessage + '\n\nMake sure you have created a GitHub release. The plugin checks for the latest release on GitHub and compares it with the current installed version.');
            }
        });
    });
});
</script>

