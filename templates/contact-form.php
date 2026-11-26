<?php
/**
 * Contact Form Template
 * Collects first name, last name, email and phone, then creates a ClubWorx prospect.
 *
 * Expected variables (set by Clubworx_Integration::render_contact_form):
 * - $clubworx_location_choices (string[]) location slugs offered in the dropdown
 * - $clubworx_all_locations   (array)    all normalized locations keyed by slug
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($clubworx_location_choices) || !is_array($clubworx_location_choices)) {
    $clubworx_location_choices = array();
}
if (!isset($clubworx_all_locations) || !is_array($clubworx_all_locations)) {
    $clubworx_all_locations = Clubworx_Locations::all();
}

// With a single location there is nothing to choose: preselect it and hide the dropdown.
$clubworx_single_location = count($clubworx_location_choices) === 1;
$clubworx_initial_slug = $clubworx_single_location ? $clubworx_location_choices[0] : '';
?>

<div class="clubworx-booking-wrapper clubworx-contact-wrapper<?php echo $clubworx_single_location ? '' : ' clubworx-booking-wrapper--master'; ?>" data-account="<?php echo esc_attr($clubworx_initial_slug); ?>">
    <div class="main-layout">
        <main class="main-content">
            <div class="booking-card">

                <form id="clubworxContactForm" class="booking-form clubworx-contact-form" novalidate>
                    <input type="hidden" name="clubworx_account" id="clubworxContactAccount" value="<?php echo esc_attr($clubworx_initial_slug); ?>" />

                    <?php if (!$clubworx_single_location && count($clubworx_location_choices) > 1) : ?>
                    <div class="form-section clubworx-location-section">
                        <h3><?php _e('Select Location', 'clubworx-integration'); ?></h3>
                        <div class="form-group">
                            <label for="clubworxContactLocation"><?php _e('Which location are you contacting?', 'clubworx-integration'); ?> *</label>
                            <select id="clubworxContactLocation" name="clubworxLocation" required>
                                <option value=""><?php _e('Select a location', 'clubworx-integration'); ?></option>
                                <?php foreach ($clubworx_location_choices as $choice_slug) : ?>
                                    <?php
                                    $choice_label = isset($clubworx_all_locations[$choice_slug]['label'])
                                        ? $clubworx_all_locations[$choice_slug]['label']
                                        : $choice_slug;
                                    ?>
                                    <option value="<?php echo esc_attr($choice_slug); ?>"><?php echo esc_html($choice_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-section">
                        <h3><?php _e('Your Details', 'clubworx-integration'); ?></h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="clubworxContactFirstName"><?php _e('First Name', 'clubworx-integration'); ?> *</label>
                                <input type="text" id="clubworxContactFirstName" name="firstName" autocomplete="given-name" required>
                            </div>
                            <div class="form-group">
                                <label for="clubworxContactLastName"><?php _e('Last Name', 'clubworx-integration'); ?> *</label>
                                <input type="text" id="clubworxContactLastName" name="lastName" autocomplete="family-name" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="clubworxContactEmail"><?php _e('Email Address', 'clubworx-integration'); ?> *</label>
                            <input type="email" id="clubworxContactEmail" name="email" autocomplete="email" required>
                        </div>

                        <div class="form-group">
                            <label for="clubworxContactPhone"><?php _e('Phone Number', 'clubworx-integration'); ?> *</label>
                            <input type="tel" id="clubworxContactPhone" name="phone" autocomplete="tel" placeholder="0422 123 456" required>
                        </div>

                        <!-- Honeypot: hidden from people, bots tend to fill it in -->
                        <div class="clubworx-hp" aria-hidden="true">
                            <label for="clubworxContactWebsite"><?php _e('Leave this field empty', 'clubworx-integration'); ?></label>
                            <input type="text" id="clubworxContactWebsite" name="website" tabindex="-1" autocomplete="off">
                        </div>
                    </div>

                    <div id="clubworxContactStatus" class="form-status" role="status" aria-live="polite" style="display: none;"></div>

                    <button type="submit" class="submit-btn" id="clubworxContactSubmit">
                        <?php _e('Send My Details', 'clubworx-integration'); ?>
                    </button>
                </form>

                <div id="clubworxContactThanks" class="clubworx-contact-thanks" role="status" style="display: none;">
                    <h3><?php _e('Thank you!', 'clubworx-integration'); ?></h3>
                    <p><?php _e('We have received your details and will be in touch shortly.', 'clubworx-integration'); ?></p>
                </div>

            </div>
        </main>
    </div>
</div>
