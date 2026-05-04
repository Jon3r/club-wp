<?php
/**
 * Admin Settings Page — tabbed by Clubworx location + Locations + Global (GitHub).
 */

if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('wp-color-picker');
wp_enqueue_script('wp-color-picker');

$all_locs = Clubworx_Locations::all();
$tab = '';
if (isset($_REQUEST['_clubworx_settings_tab'])) {
    $tab = sanitize_key(wp_unslash($_REQUEST['_clubworx_settings_tab']));
} elseif (isset($_GET['tab'])) {
    $tab = sanitize_key(wp_unslash($_GET['tab']));
}
if ($tab === '' || ($tab !== 'global' && $tab !== 'locations' && !isset($all_locs[$tab]))) {
    $tab = Clubworx_Locations::get_default_slug();
}

$default_slug = Clubworx_Locations::get_default_slug();
$settings_base = admin_url('admin.php?page=clubworx-integration-settings');

// Flash messages (Locations tab actions).
if (isset($_GET['added'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Location added.', 'clubworx-integration') . '</p></div>';
}
if (isset($_GET['deleted'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Location removed.', 'clubworx-integration') . '</p></div>';
}
if (isset($_GET['defaulted'])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Default location updated.', 'clubworx-integration') . '</p></div>';
}
if (isset($_GET['error'])) {
    $err = sanitize_key(wp_unslash($_GET['error']));
    $msg = __('Something went wrong.', 'clubworx-integration');
    if ($err === 'empty') {
        $msg = __('Enter a label for the new location.', 'clubworx-integration');
    } elseif ($err === 'exists') {
        $msg = __('That location slug already exists.', 'clubworx-integration');
    } elseif ($err === 'delete') {
        $msg = __('Cannot delete that location.', 'clubworx-integration');
    } elseif ($err === 'default') {
        $msg = __('Invalid default location.', 'clubworx-integration');
    }
    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($msg) . '</p></div>';
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('clubworx_integration_settings_group'); ?>

    <div class="clubworx-admin-tabs">
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url(admin_url('admin.php?page=clubworx-integration')); ?>" class="nav-tab"><?php esc_html_e('Dashboard', 'clubworx-integration'); ?></a>
            <a href="<?php echo esc_url($settings_base); ?>" class="nav-tab nav-tab-active"><?php esc_html_e('Settings', 'clubworx-integration'); ?></a>
        </h2>
    </div>

    <h2 class="nav-tab-wrapper" style="margin-top: 12px;">
        <?php foreach ($all_locs as $slug => $loc_row) : ?>
            <?php
            $label = isset($loc_row['label']) ? $loc_row['label'] : $slug;
            $tab_url = add_query_arg(array('page' => 'clubworx-integration-settings', 'tab' => $slug), admin_url('admin.php'));
            $active = ($tab === $slug) ? ' nav-tab-active' : '';
            ?>
            <a href="<?php echo esc_url($tab_url); ?>" class="nav-tab<?php echo esc_attr($active); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
        <?php
        $loc_tab_url = add_query_arg(array('page' => 'clubworx-integration-settings', 'tab' => 'locations'), admin_url('admin.php'));
        $gl_tab_url = add_query_arg(array('page' => 'clubworx-integration-settings', 'tab' => 'global'), admin_url('admin.php'));
        ?>
        <a href="<?php echo esc_url($loc_tab_url); ?>" class="nav-tab<?php echo $tab === 'locations' ? ' nav-tab-active' : ''; ?>"><?php esc_html_e('Locations', 'clubworx-integration'); ?></a>
        <a href="<?php echo esc_url($gl_tab_url); ?>" class="nav-tab<?php echo $tab === 'global' ? ' nav-tab-active' : ''; ?>"><?php esc_html_e('Global', 'clubworx-integration'); ?></a>
    </h2>

    <?php if ($tab === 'locations') : ?>
        <p><?php esc_html_e('Add or remove locations and choose which one is the default when a page does not specify a Clubworx location.', 'clubworx-integration'); ?></p>

        <h3><?php esc_html_e('Default location', 'clubworx-integration'); ?></h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('clubworx_set_default_location'); ?>
            <input type="hidden" name="action" value="clubworx_set_default_location" />
            <p>
                <label for="default_location_slug"><?php esc_html_e('Site-wide default', 'clubworx-integration'); ?></label><br />
                <select name="default_location_slug" id="default_location_slug">
                    <?php foreach ($all_locs as $slug => $loc_row) : ?>
                        <option value="<?php echo esc_attr($slug); ?>" <?php selected($default_slug, $slug); ?>>
                            <?php echo esc_html(isset($loc_row['label']) ? $loc_row['label'] : $slug); ?> (<?php echo esc_html($slug); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button(__('Save default', 'clubworx-integration'), 'secondary', 'submit', false); ?>
            </p>
        </form>

        <h3><?php esc_html_e('Add location', 'clubworx-integration'); ?></h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="clubworx-add-location">
            <?php wp_nonce_field('clubworx_add_location'); ?>
            <input type="hidden" name="action" value="clubworx_add_location" />
            <p>
                <label for="new_location_label"><?php esc_html_e('Display name', 'clubworx-integration'); ?></label><br />
                <input type="text" class="regular-text" name="new_location_label" id="new_location_label" required />
            </p>
            <p>
                <label for="new_location_slug"><?php esc_html_e('Slug (optional)', 'clubworx-integration'); ?></label><br />
                <input type="text" class="regular-text" name="new_location_slug" id="new_location_slug" placeholder="<?php esc_attr_e('auto from name', 'clubworx-integration'); ?>" />
            </p>
            <?php submit_button(__('Add location', 'clubworx-integration'), 'primary', 'submit', false); ?>
        </form>

        <h3><?php esc_html_e('Existing locations', 'clubworx-integration'); ?></h3>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Label', 'clubworx-integration'); ?></th>
                    <th><?php esc_html_e('Slug', 'clubworx-integration'); ?></th>
                    <th><?php esc_html_e('Actions', 'clubworx-integration'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_locs as $slug => $loc_row) : ?>
                    <tr>
                        <td><?php echo esc_html(isset($loc_row['label']) ? $loc_row['label'] : $slug); ?></td>
                        <td><code><?php echo esc_html($slug); ?></code></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(array('page' => 'clubworx-integration-settings', 'tab' => $slug), admin_url('admin.php'))); ?>"><?php esc_html_e('Edit settings', 'clubworx-integration'); ?></a>
                            <?php if ($slug !== $default_slug) : ?>
                                —
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js(__('Delete this location and its settings?', 'clubworx-integration')); ?>');">
                                    <?php wp_nonce_field('clubworx_delete_location'); ?>
                                    <input type="hidden" name="action" value="clubworx_delete_location" />
                                    <input type="hidden" name="delete_location_slug" value="<?php echo esc_attr($slug); ?>" />
                                    <button type="submit" class="button-link-delete"><?php esc_html_e('Delete', 'clubworx-integration'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php elseif ($tab === 'global') : ?>
        <?php
        $opt = get_option('clubworx_integration_settings', array());
        $gh = isset($opt['github']) && is_array($opt['github']) ? $opt['github'] : array();
        $has_github = !empty($gh['username']) && !empty($gh['repo']);
        ?>
        <?php if (!$has_github) : ?>
            <div class="notice notice-warning"><p><?php esc_html_e('Set GitHub repository details for private plugin updates.', 'clubworx-integration'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php
            settings_fields('clubworx_integration_settings_group');
            echo '<input type="hidden" name="_clubworx_settings_tab" value="global" />';
            do_settings_sections('clubworx-integration-settings');
            submit_button();
            ?>
        </form>

    <?php else : ?>
        <?php
        $loc_cfg = isset($all_locs[$tab]) ? $all_locs[$tab] : null;
        $configured = $loc_cfg && !empty($loc_cfg['api_url']) && !empty($loc_cfg['api_key']);
        ?>
        <?php if (!$configured) : ?>
            <div class="notice notice-warning is-dismissible">
                <p><strong><?php esc_html_e('ClubWorx API not configured for this location.', 'clubworx-integration'); ?></strong></p>
                <p><?php esc_html_e('The booking form will use fallback schedule data until API URL and key are set below.', 'clubworx-integration'); ?></p>
            </div>
        <?php else : ?>
            <div class="notice notice-success is-dismissible">
                <p><strong><?php esc_html_e('ClubWorx API configured', 'clubworx-integration'); ?></strong></p>
                <p><?php esc_html_e('API URL:', 'clubworx-integration'); ?> <code><?php echo esc_html($loc_cfg['api_url']); ?></code></p>
            </div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php
            settings_fields('clubworx_integration_settings_group');
            echo '<input type="hidden" name="_clubworx_settings_tab" value="' . esc_attr($tab) . '" />';
            do_settings_sections('clubworx-integration-settings');
            submit_button();
            ?>
        </form>

        <div class="clubworx-test-email-section" style="margin-top: 30px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h2><?php esc_html_e('Test email (this location)', 'clubworx-integration'); ?></h2>
            <p><?php esc_html_e('Sends using this location’s notification email and SMTP settings.', 'clubworx-integration'); ?></p>
            <button type="button" id="test-email-settings" class="button button-secondary" data-location="<?php echo esc_attr($tab); ?>">
                <span class="dashicons dashicons-email-alt" style="vertical-align: middle; margin-right: 5px;"></span>
                <?php esc_html_e('Send test email', 'clubworx-integration'); ?>
            </button>
            <div id="test-email-result" style="margin-top: 15px; display: none;"></div>
        </div>
    <?php endif; ?>

    <?php if ($tab !== 'locations') : ?>
        <div class="clubworx-settings-help">
            <h2><?php esc_html_e('Setup instructions', 'clubworx-integration'); ?></h2>
            <div class="clubworx-help-card">
                <h3><?php esc_html_e('Shortcodes', 'clubworx-integration'); ?></h3>
                <p><?php esc_html_e('Booking form:', 'clubworx-integration'); ?> <code>[clubworx_trial_booking]</code></p>
                <p><?php esc_html_e('Optional location:', 'clubworx-integration'); ?> <code>[clubworx_trial_booking account="studio-b"]</code></p>
                <p><?php esc_html_e('Timetable / pricing aggregation:', 'clubworx-integration'); ?> <code>account="all"</code> <?php esc_html_e('or', 'clubworx-integration'); ?> <code>account="primary,studio-b"</code></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($tab !== 'locations' && $tab !== 'global') : ?>
<script>
jQuery(document).ready(function($) {
    $('.clubworx-color-picker').wpColorPicker({
        clear: function() {
            var defaultColor = $(this).data('default-color');
            if (defaultColor) {
                $(this).wpColorPicker('color', defaultColor);
            }
        }
    });

    $('#test-email-settings').on('click', function() {
        var button = $(this);
        var slug = button.data('location') || '';
        var resultDiv = $('#test-email-result');
        button.prop('disabled', true);
        resultDiv.hide();

        $.ajax({
            url: <?php echo wp_json_encode(rest_url('clubworx/v1/test-email')); ?>,
            method: 'POST',
            headers: {
                'X-WP-Nonce': <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({ account: slug })
        }).done(function(response) {
            if (response.success) {
                resultDiv.html('<div class="notice notice-success inline"><p><strong>✅</strong> ' + response.message + '</p></div>');
            } else {
                resultDiv.html('<div class="notice notice-error inline"><p><strong>❌</strong> ' + (response.message || 'Failed') + '</p></div>');
            }
            resultDiv.show();
        }).fail(function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Request failed';
            resultDiv.html('<div class="notice notice-error inline"><p>' + msg + '</p></div>');
            resultDiv.show();
        }).always(function() {
            button.prop('disabled', false);
        });
    });
});
</script>
<?php endif; ?>

<style>
.clubworx-admin-tabs { margin: 20px 0; }
.clubworx-settings-help { margin-top: 40px; max-width: 800px; }
.clubworx-help-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-left: 4px solid #2271b1;
    padding: 20px;
    margin: 20px 0;
}
.clubworx-help-card h3 { margin-top: 0; }
</style>
