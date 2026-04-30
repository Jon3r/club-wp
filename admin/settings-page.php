<?php
/**
 * Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

// Enqueue WordPress color picker
wp_enqueue_style('wp-color-picker');
wp_enqueue_script('wp-color-picker');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('clubworx_integration_settings_group'); ?>
    
    <?php 
    // Check ClubWorx configuration status
    $settings = get_option('clubworx_integration_settings', array());
    $clubworx_configured = !empty($settings['clubworx_api_url']) && !empty($settings['clubworx_api_key']);
    ?>
    
    <?php if (!$clubworx_configured): ?>
    <div class="notice notice-warning is-dismissible">
        <p><strong>⚠️ ClubWorx API Not Configured!</strong></p>
        <p>Your booking form is currently using <strong>static/hardcoded schedule data</strong>.</p>
        <p>To fetch live data from ClubWorx, please configure the ClubWorx API settings below.</p>
    </div>
    <?php else: ?>
    <div class="notice notice-success is-dismissible">
        <p><strong>✅ ClubWorx API Configured!</strong></p>
        <p>API URL: <code><?php echo esc_html($settings['clubworx_api_url']); ?></code></p>
        <p>Your form will fetch live schedule data from ClubWorx (cached for 1 hour).</p>
    </div>
    <?php endif; ?>
    
    <div class="clubworx-admin-tabs">
        <h2 class="nav-tab-wrapper">
            <a href="?page=clubworx-integration" class="nav-tab"><?php _e('Dashboard', 'clubworx-integration'); ?></a>
            <a href="?page=clubworx-integration-settings" class="nav-tab nav-tab-active"><?php _e('Settings', 'clubworx-integration'); ?></a>
        </h2>
    </div>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('clubworx_integration_settings_group');
        do_settings_sections('clubworx-integration-settings');
        submit_button();
        ?>
    </form>
    
    <div class="clubworx-settings-help">
        <h2><?php _e('Setup Instructions', 'clubworx-integration'); ?></h2>
        
        <div class="clubworx-help-card">
            <h3><?php _e('1. Add the Booking Form to Your Page', 'clubworx-integration'); ?></h3>
            <p><?php _e('Use this shortcode on any page or post:', 'clubworx-integration'); ?></p>
            <code>[clubworx_trial_booking]</code>
            <p><?php _e('Or use the Gutenberg block "Clubworx" in the block editor.', 'clubworx-integration'); ?></p>
        </div>
        
        <div class="clubworx-help-card">
            <h3><?php _e('2. Configure GA4 Tracking', 'clubworx-integration'); ?></h3>
            <ol>
                <li><?php _e('Enter your GA4 Measurement ID (found in GA4 Admin → Data Streams)', 'clubworx-integration'); ?></li>
                <li><?php _e('Create an API Secret in GA4 (Admin → Data Streams → Measurement Protocol API secrets)', 'clubworx-integration'); ?></li>
                <li><?php _e('Enable Debug Mode to test events in GA4 DebugView', 'clubworx-integration'); ?></li>
            </ol>
        </div>
        
        <div class="clubworx-help-card">
            <h3><?php _e('3. Connect to ClubWorx', 'clubworx-integration'); ?></h3>
            <ol>
                <li><?php _e('Enter your ClubWorx API URL', 'clubworx-integration'); ?></li>
                <li><?php _e('Enter your ClubWorx API Key', 'clubworx-integration'); ?></li>
                <li><?php _e('Test the connection using the Test API button on the Dashboard', 'clubworx-integration'); ?></li>
            </ol>
        </div>
        
        <div class="clubworx-help-card">
            <h3><?php _e('4. Configure Email Notifications', 'clubworx-integration'); ?></h3>
            <ol>
                <li><?php _e('Enable email notifications if you want to receive booking alerts', 'clubworx-integration'); ?></li>
                <li><?php _e('Set the admin email address for notifications', 'clubworx-integration'); ?></li>
                <li><?php _e('Use the "Test Email" button below to verify your email configuration', 'clubworx-integration'); ?></li>
            </ol>
        </div>
    </div>
    
    <!-- Test Email Section -->
    <div class="clubworx-test-email-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
        <h2><?php _e('Test Email Configuration', 'clubworx-integration'); ?></h2>
        <p><?php _e('Click the button below to send a test email to your configured admin email address. This will help you verify that email notifications are working correctly.', 'clubworx-integration'); ?></p>
        <button type="button" id="test-email-settings" class="button button-secondary" style="margin-top: 10px;">
            <span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 5px;"></span>
            <?php _e('Send Test Email', 'clubworx-integration'); ?>
        </button>
        <div id="test-email-result" style="margin-top: 15px; display: none;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Initialize WordPress color picker
    $('.clubworx-color-picker').wpColorPicker({
        change: function(event, ui) {
            // Optional: Add change handler if needed
        },
        clear: function() {
            // Reset to default color when cleared
            var defaultColor = $(this).data('default-color');
            if (defaultColor) {
                $(this).wpColorPicker('color', defaultColor);
            }
        }
    });
    
    // Test Email button in settings page
    $('#test-email-settings').on('click', function() {
        var button = $(this);
        var resultDiv = $('#test-email-result');
        button.prop('disabled', true);
        button.html('<span class="dashicons dashicons-update dashicons-update-spin" style="vertical-align: middle; margin-right: 5px;"></span> <?php _e('Sending...', 'clubworx-integration'); ?>');
        resultDiv.hide();
        
        $.ajax({
            url: '<?php echo rest_url('clubworx/v1/test-email'); ?>',
            method: 'POST',
            headers: {
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>',
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({})
        }).done(function(response) {
            console.log('Test Email Response:', response);
            
            if (response.success) {
                resultDiv.html('<div class="notice notice-success inline"><p><strong>✅ Success:</strong> ' + response.message + '</p><p>Check your inbox and the Email Log on the Dashboard page.</p></div>');
            } else {
                // Build detailed error message
                var errorHtml = '<div class="notice notice-error inline">';
                errorHtml += '<p><strong>❌ Failed:</strong> ' + response.message + '</p>';
                
                if (response.error) {
                    errorHtml += '<p><strong>Error:</strong> ' + response.error + '</p>';
                }
                
                if (response.error_details && response.error_details.length > 0) {
                    errorHtml += '<p><strong>Details:</strong></p><ul>';
                    response.error_details.forEach(function(detail) {
                        errorHtml += '<li>' + detail + '</li>';
                    });
                    errorHtml += '</ul>';
                }
                
                if (response.diagnostics) {
                    errorHtml += '<p><strong>Diagnostics:</strong></p>';
                    errorHtml += '<ul>';
                    errorHtml += '<li>PHP mail() function: ' + (response.diagnostics.mail_function_exists ? '✅ Available' : '❌ Not available') + '</li>';
                    errorHtml += '<li>SMTP Configured: ' + (response.diagnostics.smtp_configured ? '✅ Yes' : '❌ No') + '</li>';
                    if (response.diagnostics.plugin_smtp_configured) {
                        errorHtml += '<li>Plugin SMTP: ✅ Enabled (' + (response.diagnostics.plugin_smtp_host || '') + ':' + (response.diagnostics.plugin_smtp_port || '') + ', ' + (response.diagnostics.plugin_smtp_encryption || 'none') + ')</li>';
                    } else if (response.diagnostics.plugin_smtp_enabled) {
                        errorHtml += '<li>Plugin SMTP: ⚠️ Enabled but not fully configured (check SMTP settings above)</li>';
                    }
                    if (response.diagnostics.smtp_plugins_active && response.diagnostics.smtp_plugins_active.length > 0) {
                        errorHtml += '<li>Active SMTP plugins: ' + response.diagnostics.smtp_plugins_active.join(', ') + '</li>';
                    }
                    errorHtml += '</ul>';
                }
                
                if (response.suggestions && response.suggestions.length > 0) {
                    errorHtml += '<p><strong>Troubleshooting:</strong></p>';
                    errorHtml += '<ul style="list-style-type: decimal; margin-left: 20px;">';
                    response.suggestions.forEach(function(suggestion) {
                        if (suggestion.trim() !== '') {
                            errorHtml += '<li style="margin: 5px 0;">' + suggestion + '</li>';
                        }
                    });
                    errorHtml += '</ul>';
                }
                
                errorHtml += '<p><strong>Next Steps:</strong> Check the Email Log on the Dashboard page for more details.</p>';
                errorHtml += '</div>';
                
                resultDiv.html(errorHtml);
            }
            resultDiv.show();
        }).fail(function(xhr) {
            console.error('Test Email Error:', xhr);
            var errorMsg = '<?php _e('Error sending test email', 'clubworx-integration'); ?>';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
                if (xhr.responseJSON.error) {
                    errorMsg += '<br><strong>Error:</strong> ' + xhr.responseJSON.error;
                }
            }
            resultDiv.html('<div class="notice notice-error inline"><p><strong>❌ Error:</strong> ' + errorMsg + '</p></div>');
            resultDiv.show();
        }).always(function() {
            button.prop('disabled', false);
            button.html('<span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 5px;"></span> <?php _e('Send Test Email', 'clubworx-integration'); ?>');
        });
    });
});
</script>

<style>
.clubworx-admin-tabs {
    margin: 20px 0;
}

.clubworx-settings-help {
    margin-top: 40px;
    max-width: 800px;
}

.clubworx-help-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-left: 4px solid #2271b1;
    padding: 20px;
    margin: 20px 0;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.clubworx-help-card h3 {
    margin-top: 0;
}

.clubworx-help-card code {
    display: block;
    padding: 10px;
    background: #f0f0f1;
    margin: 10px 0;
    font-size: 14px;
}

.clubworx-help-card ol {
    margin-left: 20px;
}

.clubworx-help-card ol li {
    margin: 10px 0;
}
</style>

