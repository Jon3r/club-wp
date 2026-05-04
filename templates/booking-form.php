<?php
/**
 * Booking Form Template
 * This template renders the trial booking form
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!isset($clubworx_location_slug)) {
    $clubworx_location_slug = Clubworx_Locations::get_default_slug();
}
?>

<div class="clubworx-booking-wrapper" data-account="<?php echo esc_attr($clubworx_location_slug); ?>">
    <!-- Main container -->
    <div class="main-layout">
        <main class="main-content">
            <!-- Booking form -->
            <div class="booking-card">
                
                <form id="trialBookingForm" class="booking-form">
                    <input type="hidden" name="clubworx_account" id="clubworxAccountField" value="<?php echo esc_attr($clubworx_location_slug); ?>" />
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3><?php _e('Personal Information', 'clubworx-integration'); ?></h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName"><?php _e('First Name', 'clubworx-integration'); ?> *</label>
                                <input type="text" id="firstName" name="firstName" required>
                            </div>
                            <div class="form-group">
                                <label for="lastName"><?php _e('Last Name', 'clubworx-integration'); ?> *</label>
                                <input type="text" id="lastName" name="lastName" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email"><?php _e('Email Address', 'clubworx-integration'); ?> *</label>
                            <input type="email" id="email" name="email" required>
                        </div>

                        <div class="form-group">
                            <label for="phone"><?php _e('Phone Number', 'clubworx-integration'); ?> *</label>
                            <input type="tel" id="phone" name="phone" pattern="(0[1-9][\d\s]{8,9}|[\+]?[1-9][\d\s]{0,20})" placeholder="0422 123 456" required>
                        </div>
                    </div>

                    <!-- Contact Us Section -->
                    <div class="form-section">
                        <h3><?php _e('Contact Us - Trial Class Booking Follows (optional)', 'clubworx-integration'); ?></h3>
                        
                        <div class="form-group">
                            <label for="programInfo"><?php _e('Which program would you like to know more about?', 'clubworx-integration'); ?> *</label>
                            <select id="programInfo" name="programInfo" required>
                                <option value=""><?php _e('Select a program', 'clubworx-integration'); ?></option>
                                <option value="kids"><?php _e('Kids', 'clubworx-integration'); ?></option>
                                <option value="teens"><?php _e('Teens', 'clubworx-integration'); ?></option>
                                <option value="adults"><?php _e('Adults', 'clubworx-integration'); ?></option>
                                <option value="women"><?php _e('Women', 'clubworx-integration'); ?></option>
                            </select>
                        </div>

                        <div class="form-group" id="contactPreferenceContainer" style="display: none;">
                            <label for="contactPreference"><?php _e('How do you wish to be contacted?', 'clubworx-integration'); ?> *</label>
                            <select id="contactPreference" name="contactPreference">
                                <option value=""><?php _e('Select contact method', 'clubworx-integration'); ?></option>
                                <option value="email"><?php _e('Email', 'clubworx-integration'); ?></option>
                                <option value="phone"><?php _e('Phone Call', 'clubworx-integration'); ?></option>
                                <option value="book_trial"><?php _e('No need to contact me I am happy to book a trial class', 'clubworx-integration'); ?></option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="submitWithoutBookingContainer" style="display: none;">
                            <button type="button" id="submitWithoutBooking" class="btn btn-secondary submit-info-btn">
                                <?php 
                                $settings = get_option('clubworx_integration_settings', array());
                                $secondary_text = isset($settings['form_secondary_button_text']) && !empty($settings['form_secondary_button_text']) 
                                    ? esc_html($settings['form_secondary_button_text']) 
                                    : __('Submit Information Only', 'clubworx-integration');
                                echo $secondary_text;
                                ?>
                            </button>
                            <p class="form-helper-text"><?php _e('Just want program information? Submit your details and we\'ll get in touch. You can book your trial class below.', 'clubworx-integration'); ?></p>
                        </div>
                    </div>

                    <!-- Book a Trial Class Section -->
                    <div class="form-section" id="bookingSection" style="display: none;">
                        <h3><?php _e('Book a Trial Class', 'clubworx-integration'); ?></h3>

                        <div class="form-group" id="ageGroupContainer" style="display: none;">
                            <label for="ageGroup" id="ageGroupLabel"><?php _e('Age Group', 'clubworx-integration'); ?> *</label>
                            <select id="ageGroup" name="ageGroup">
                                <option value=""><?php _e('Select...', 'clubworx-integration'); ?></option>
                            </select>
                        </div>

                        <div class="form-group" id="dayContainer" style="display: none;">
                            <label for="day"><?php _e('Day', 'clubworx-integration'); ?> *</label>
                            <select id="day" name="day">
                                <option value=""><?php _e('Select a day', 'clubworx-integration'); ?></option>
                            </select>
                        </div>

                        <div class="form-group" id="classContainer" style="display: none;">
                            <label for="class"><?php _e('Class', 'clubworx-integration'); ?> *</label>
                            <select id="class" name="class">
                                <option value=""><?php _e('Select a class', 'clubworx-integration'); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Optional Information Section -->
                    <div class="form-section">
                        <h3><?php _e('Additional Information (Optional)', 'clubworx-integration'); ?></h3>
                        
                        <div class="form-group">
                            <label for="experience"><?php _e('Previous Martial Arts Experience', 'clubworx-integration'); ?></label>
                            <select id="experience" name="experience">
                                <option value="none"><?php _e('No experience', 'clubworx-integration'); ?></option>
                                <option value="beginner"><?php _e('Beginner (less than 1 year)', 'clubworx-integration'); ?></option>
                                <option value="intermediate"><?php _e('Intermediate (1-3 years)', 'clubworx-integration'); ?></option>
                                <option value="advanced"><?php _e('Advanced (3+ years)', 'clubworx-integration'); ?></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="goals"><?php _e('Anything you want us to know?', 'clubworx-integration'); ?></label>
                            <textarea id="goals" name="goals" rows="3"></textarea>
                        </div>
                    </div>

                    <!-- Form status display -->
                    <div id="formStatus" class="form-status ready" style="display: none;"></div>
                    
                    <button type="submit" class="submit-btn">
                        <?php 
                        $settings = get_option('clubworx_integration_settings', array());
                        $submit_text = isset($settings['form_submit_button_text']) && !empty($settings['form_submit_button_text']) 
                            ? esc_html($settings['form_submit_button_text']) 
                            : __('Book My Trial Class', 'clubworx-integration');
                        echo $submit_text;
                        ?>
                    </button>
                </form>
            </div>
        </main>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3 id="confirmTitle"><?php _e('Booking Submitted!', 'clubworx-integration'); ?></h3>
            <p id="confirmMessage"><?php _e('Thank you for your interest! We\'ll contact you within 24 hours to confirm your trial class appointment.', 'clubworx-integration'); ?></p>
            <div class="modal-actions">
                <button id="addToCalendar" class="btn btn-secondary">📅 <?php _e('Add to Calendar', 'clubworx-integration'); ?></button>
                <button id="confirmOk" class="btn btn-primary"><?php _e('Continue to What to Expect', 'clubworx-integration'); ?></button>
            </div>
        </div>
    </div>

    <!-- Loading overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p id="loadingText"><?php _e('Submitting your booking...', 'clubworx-integration'); ?></p>
        </div>
    </div>
</div>

