/**
 * Clubworx trial class booking (front-end form).
 */

class TrialClassBookingManager {
    constructor() {
        // Form state management
        this.formData = {};
        this.isSubmitting = false;
        
        // Base URL for API calls - use WordPress REST API
        this.baseUrl = typeof clubworxBookingSettings !== 'undefined' ? clubworxBookingSettings.restUrl : '/wp-json/clubworx/v1/';
        this.restNonce = typeof clubworxBookingSettings !== 'undefined' ? clubworxBookingSettings.restNonce : '';
        
        // DOM elements (will be set after DOM ready)
        this.elements = {};
        
        // Age group options for different groups
        this.ageGroupOptions = {
            kids: [
                { value: 'under6', text: '5-7 years' },
                { value: 'over6', text: '8-12 years' }
            ],
            teens: [
                { value: 'under13', text: 'Under 13 years' },
                { value: 'over13', text: 'Over 13 years' }
            ],
            adults: [
                { value: 'general', text: 'General' }
            ]
        };
        
        // Schedule data will be loaded dynamically from API
        this.schedule = null;
        this.scheduleLoaded = false;
        
        // Form validation rules
        this.validationRules = {
            firstName: { required: true, minLength: 2 },
            lastName: { required: true, minLength: 2 },
            email: { required: true, pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/ },
            phone: { required: true, pattern: /^(0[1-9][\d\s]{8,9}|[\+]?[1-9][\d\s]{0,20})$/ },
            programInfo: { required: true },
            contactPreference: { required: true },
            day: { required: true },
            class: { required: true }
        };
        
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }

    getAccountSlug() {
        const cfg = typeof clubworxBookingSettings !== 'undefined' ? clubworxBookingSettings : {};
        const def = cfg.defaultLocation || 'primary';
        const form = document.getElementById('trialBookingForm');
        const wrap = form ? form.closest('.clubworx-booking-wrapper') : document.querySelector('.clubworx-booking-wrapper');
        const ds = wrap && wrap.getAttribute('data-account');
        if (ds && String(ds).trim()) {
            return String(ds).trim();
        }
        const hid = form ? form.querySelector('input[name="clubworx_account"]') : null;
        if (hid && hid.value && String(hid.value).trim()) {
            return String(hid.value).trim();
        }
        return def;
    }

    getCxSettings() {
        const cfg = typeof clubworxBookingSettings !== 'undefined' ? clubworxBookingSettings : {};
        const slug = this.getAccountSlug();
        const loc = cfg.locations && cfg.locations[slug] ? cfg.locations[slug] : {};
        return Object.assign({}, cfg, loc);
    }

    withAccountPayload(body) {
        const slug = this.getAccountSlug();
        const base = body !== null && typeof body === 'object' && !Array.isArray(body) ? body : {};
        return Object.assign({}, base, { account: slug });
    }

    getClubDisplayName() {
        const n = this.getCxSettings().clubDisplayName;
        return n && String(n).trim() ? String(n).trim() : document.title || 'Club';
    }

    getClubWebsiteUrl() {
        const u = this.getCxSettings().clubWebsiteUrl;
        return u && String(u).trim() ? String(u).trim() : window.location.origin + '/';
    }

    getTrialIntro() {
        const t = this.getCxSettings().trialEventIntro;
        return t && String(t).trim() ? String(t).trim() : 'Trial class';
    }

    getIcsDomain() {
        const d = this.getCxSettings().icsUidDomain;
        const raw = d && String(d).trim() ? String(d).trim() : window.location.hostname || 'localhost';
        return raw.replace(/[^a-zA-Z0-9.-]/g, '') || 'localhost';
    }

    getGaCurrency() {
        const c = this.getCxSettings().ga4Currency;
        return c && String(c).length === 3 ? String(c).toUpperCase() : 'USD';
    }

    pushOrGtagEvent(eventName, params) {
        const mode = this.getCxSettings().analyticsMode || 'none';
        if (mode === 'gtm') {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({ event: eventName, ...params });
            return;
        }
        if (mode === 'ga4' && typeof gtag === 'function') {
            gtag('event', eventName, params);
        }
    }
    
    async init() {
        console.log('🥋 Clubworx booking form initialized');
        try {
            this.cacheElements();
            console.log('✅ Elements cached successfully');
            
            // Initialize attribution tracker - ensure it's available
            try {
                if (typeof AttributionTracker !== 'undefined') {
                    this.attributionTracker = new AttributionTracker();
                    console.log('✅ Attribution tracker initialized');
                } else {
                    console.error('❌ AttributionTracker class not found - check script loading order');
                    // Wait a bit for script to load, then try again
                    setTimeout(() => {
                        if (typeof AttributionTracker !== 'undefined') {
                            this.attributionTracker = new AttributionTracker();
                            console.log('✅ Attribution tracker initialized (delayed)');
                        } else {
                            console.error('❌ AttributionTracker still not available');
                        }
                    }, 100);
                }
            } catch (attrError) {
                console.error('❌ Attribution tracker failed to initialize:', attrError.message);
                this.attributionTracker = null;
            }
            
            // Load schedule data from API
            await this.loadScheduleData();
            
            this.bindEvents();
            console.log('✅ Events bound successfully');
            
            if (this.scheduleLoaded) {
                this.updateFormStatus('Ready');
                console.log('✅ Form status updated');
            }
        } catch (error) {
            console.error('❌ Initialization error:', error);
            this.updateFormStatus('Failed to load schedule data', 'error');
        }
    }
    
    // Load schedule data dynamically from API
    async loadScheduleData() {
        console.log('📅 Loading schedule data from API...');
        this.updateFormStatus('Loading schedule...', 'processing');
        
        try {
            const account = encodeURIComponent(this.getAccountSlug());
            const response = await fetch(`${this.baseUrl}schedule-simple?account=${account}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.restNonce
                }
            });
            
            if (!response.ok) {
                throw new Error(`Schedule API error: ${response.status}`);
            }
            
            const result = await response.json();
            console.log('📊 Schedule data received:', result);
            
            if (result.success && result.schedule) {
                this.schedule = result.schedule;
                this.scheduleLoaded = true;
                this.updateFormStatus('Schedule loaded successfully', 'ready');
                console.log('✅ Schedule data loaded successfully');
                console.log('📋 Available programs:', {
                    kids_under6_days: Object.keys(this.schedule.kids.under6),
                    kids_over6_days: Object.keys(this.schedule.kids.over6),
                    adults_general_days: Object.keys(this.schedule.adults.general),
                    adults_foundations_days: Object.keys(this.schedule.adults.foundations),
                    womens_days: Object.keys(this.schedule.women)
                });
            } else {
                throw new Error('Invalid schedule data received');
            }
            
        } catch (error) {
            console.error('❌ Failed to load schedule data:', error);
            this.updateFormStatus('Failed to load schedule data', 'error');
            // Fallback to basic schedule structure to prevent total failure
            this.schedule = {
                kids: { under6: {}, over6: {} },
                adults: { general: {}, foundations: {} },
                women: {}
            };
            this.scheduleLoaded = false;
            throw error; // Re-throw to be handled by init()
        }
    }
    
    // Cache DOM elements for performance (pattern from performance-management.js:39-40)
    cacheElements() {
        this.elements = {
            form: document.getElementById('trialBookingForm'),
            formStatus: document.getElementById('formStatus'),
            submitBtn: document.querySelector('.submit-btn'),
            modal: document.getElementById('confirmationModal'),
            modalTitle: document.getElementById('confirmTitle'),
            modalMessage: document.getElementById('confirmMessage'),
            modalOk: document.getElementById('confirmOk'),
            addToCalendar: document.getElementById('addToCalendar'),
            loadingOverlay: document.getElementById('loadingOverlay'),
            loadingText: document.getElementById('loadingText'),
            
            // Form fields
            firstName: document.getElementById('firstName'),
            lastName: document.getElementById('lastName'),
            email: document.getElementById('email'),
            phone: document.getElementById('phone'),
            programInfo: document.getElementById('programInfo'),
            contactPreference: document.getElementById('contactPreference'),
            ageGroup: document.getElementById('ageGroup'),
            day: document.getElementById('day'),
            class: document.getElementById('class'),
            experience: document.getElementById('experience'),
            goals: document.getElementById('goals'),
            
            // Containers for show/hide logic
            contactPreferenceContainer: document.getElementById('contactPreferenceContainer'),
            submitWithoutBookingContainer: document.getElementById('submitWithoutBookingContainer'),
            submitWithoutBooking: document.getElementById('submitWithoutBooking'),
            bookingSection: document.getElementById('bookingSection'),
            ageGroupContainer: document.getElementById('ageGroupContainer'),
            ageGroupLabel: document.getElementById('ageGroupLabel'),
            dayContainer: document.getElementById('dayContainer'),
            classContainer: document.getElementById('classContainer')
        };
    }
    
    // Bind event handlers
    bindEvents() {
        // Form submission
        this.elements.form.addEventListener('submit', (e) => this.handleFormSubmit(e));
        
        // Modal close with redirect
        this.elements.modalOk.addEventListener('click', () => this.handleModalClose());
        
        // Add to calendar
        this.elements.addToCalendar.addEventListener('click', () => this.handleAddToCalendar());
        
        // Submit without booking
        this.elements.submitWithoutBooking.addEventListener('click', () => this.handleSubmitWithoutBooking());
        
        // Modal background click to close
        this.elements.modal.addEventListener('click', (e) => {
            if (e.target === this.elements.modal) {
                this.handleModalClose();
            }
        });
        
        // Real-time validation and form start tracking
        let formStartTracked = false;
        Object.keys(this.validationRules).forEach(fieldName => {
            const element = this.elements[fieldName];
            if (element) {
                element.addEventListener('blur', () => this.validateField(fieldName));
                element.addEventListener('input', () => {
                    this.clearFieldError(fieldName);
                    // Track form start on first input
                    if (!formStartTracked) {
                        this.trackGA4FormStart();
                        formStartTracked = true;
                    }
                });
            }
        });
        
        // Program info selection handler
        if (this.elements.programInfo) {
            this.elements.programInfo.addEventListener('change', () => this.handleProgramInfoChange());
            console.log('✅ Program info change event bound');
        } else {
            console.error('❌ Program info element not found');
        }
        
        // Contact preference selection handler (MISSING - this was the bug!)
        if (this.elements.contactPreference) {
            this.elements.contactPreference.addEventListener('change', () => this.handleContactPreferenceChange());
            console.log('✅ Contact preference change event bound');
        } else {
            console.error('❌ Contact preference element not found');
        }
        
        // Cascading dropdown handlers
        console.log('🔗 Binding cascading dropdown events...');
        
        if (this.elements.ageGroup) {
            this.elements.ageGroup.addEventListener('change', () => this.handleAgeGroupChange());
            console.log('✅ Age group change event bound');
        } else {
            console.error('❌ Age group element not found');
        }
        
        if (this.elements.day) {
            this.elements.day.addEventListener('change', () => this.handleDayChange());
            console.log('✅ Day change event bound');
        } else {
            console.error('❌ Day element not found');
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    }
    
    // Form submission handler
    async handleFormSubmit(e) {
        e.preventDefault();
        
        if (this.isSubmitting) return;
        
        console.log('📝 Form submission started');
        
        // Validate all fields
        if (!this.validateForm()) {
            this.updateFormStatus('Please fix validation errors', 'error');
            return;
        }
        
        this.isSubmitting = true;
        this.updateFormStatus('Processing...', 'processing');
        this.showLoading('Submitting your booking...');
        this.elements.submitBtn.disabled = true;
        
        try {
            // Collect form data
            this.collectFormData();
            
            // Simulate API call (replace with actual submission)
            await this.submitBooking();

            this.showSuccessModal();
            this.updateFormStatus('Booking submitted successfully!');
            
        } catch (error) {
            console.error('❌ Booking submission failed:', error);
            
            // Show more specific error message
            let errorMessage = 'Submission failed. Please try again.';
            if (error.message) {
                if (error.message.includes('network') || error.message.includes('fetch')) {
                    errorMessage = 'Network error. Please check your connection and try again.';
                } else if (error.message.includes('ClubWorx')) {
                    errorMessage = 'ClubWorx integration error. Please try again or contact support.';
                } else if (error.message.includes('available')) {
                    errorMessage = 'No available classes found. Please try a different day or time.';
                } else {
                    errorMessage = `Error: ${error.message}`;
                }
            }
            
            this.updateFormStatus(errorMessage, 'error');
            
        } finally {
            this.hideLoading();
            this.isSubmitting = false;
            this.elements.submitBtn.disabled = false;
        }
    }
    
    // Collect form data
    collectFormData() {
        // For testing purposes, add timestamp to test emails to prevent duplicates
        let email = this.elements.email.value.trim();
        if (email.includes('test@') || email.includes('example.com')) {
            const timestamp = Date.now().toString().slice(-4);
            email = email.replace('@', `+${timestamp}@`);
            console.log(`🧪 Test email detected - adding timestamp: ${email}`);
        }
        
        this.formData = {
            type: 'trial_class_booking',
            personal: {
                firstName: this.elements.firstName.value.trim(),
                lastName: this.elements.lastName.value.trim(),
                email: email,
                phone: this.elements.phone.value.trim()
            },
            programInfo: {
                interestedIn: this.elements.programInfo.value,
                contactPreference: this.elements.contactPreference.value
            },
            program: {
                group: this.elements.programInfo.value, // Use program info as group
                ageGroup: this.elements.ageGroup.value || null,
                day: this.elements.day.value,
                selectedClass: this.elements.class.value
            },
            preferences: {
                experience: this.elements.experience.value || 'none',
                goals: this.elements.goals.value.trim() || null
            },
            status: 'pending_waiver',
            bookingId: this.generateBookingId(),
            submittedAt: new Date().toISOString()
        };
        
        console.log('📊 Form data collected:', this.formData);
    }
    
    // Generate unique booking ID
    generateBookingId() {
        return 'trial_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    // Get display text for contact preference
    getContactPreferenceDisplay(preference) {
        switch(preference) {
            case 'email':
                return 'EMAIL';
            case 'phone':
                return 'PHONE CALL';
            case 'book_trial':
                return 'READY TO BOOK TRIAL CLASS';
            default:
                return preference?.toUpperCase() || 'NOT SPECIFIED';
        }
    }
    
    // Get follow-up notes based on contact preference
    getFollowUpNotes(preference) {
        switch(preference) {
            case 'email':
                return 'Please contact via email as requested by prospect.';
            case 'phone':
                return 'Please contact via phone call as requested by prospect.';
            case 'book_trial':
                return 'PRIORITY: Prospect is ready to book a trial class immediately. Contact ASAP to schedule.';
            default:
                return 'Please contact prospect to discuss trial class options.';
        }
    }
    
    // Collect form data for info-only submission
    collectInfoOnlyFormData() {
        this.formData = {
            type: 'program_info_request',
            personal: {
                firstName: this.elements.firstName.value.trim(),
                lastName: this.elements.lastName.value.trim(),
                email: this.elements.email.value.trim(),
                phone: this.elements.phone.value.trim()
            },
            programInfo: {
                interestedIn: this.elements.programInfo.value,
                contactPreference: this.elements.contactPreference.value
            },
            preferences: {
                experience: this.elements.experience.value || 'not_specified',
                goals: this.elements.goals.value.trim() || 'Program information requested'
            },
            status: 'info_request',
            submittedAt: new Date().toISOString()
        };
        
        console.log('📊 Info-only form data collected:', this.formData);
    }
    
    // Submit info request to ClubWorx
    async submitInfoOnly() {
        console.log('📧 Submitting program info request to ClubWorx...');
        
        // Get attribution data directly from browser (UTM parameters)
        let source = 'Program Info Request Form';
        let medium = null;
        
        // Get UTM parameters directly from URL
        const urlParams = new URLSearchParams(window.location.search);
        const utmSource = urlParams.get('utm_source');
        const utmMedium = urlParams.get('utm_medium');
        
        // Also check sessionStorage for attribution data (in case URL changed)
        let storedAttribution = null;
        try {
            const stored = sessionStorage.getItem('clubworx_attribution');
            if (stored) {
                storedAttribution = JSON.parse(stored);
            }
        } catch (e) {
            console.warn('Could not parse stored attribution data');
        }
        
        // Use UTM source/medium from URL or stored attribution
        if (utmSource) {
            source = utmSource;
        } else if (storedAttribution && storedAttribution.utm_source) {
            source = storedAttribution.utm_source;
        }
        
        if (utmMedium) {
            medium = utmMedium;
        } else if (storedAttribution && storedAttribution.utm_medium) {
            medium = storedAttribution.utm_medium;
        }
        
        // Fallback to referrer-based source if no UTM
        if (source === 'Program Info Request Form' && document.referrer) {
            try {
                const referrerDomain = new URL(document.referrer).hostname.replace('www.', '');
                source = referrerDomain;
            } catch (e) {
                // Invalid referrer URL
            }
        }
        
        console.log('📊 Browser attribution - source:', source, 'medium:', medium);
        
        const prospectData = {
            first_name: this.formData.personal.firstName,
            last_name: this.formData.personal.lastName,
            email: this.formData.personal.email,
            phone: this.formData.personal.phone,
            source: source,
            medium: medium,
            status: 'Initial Contact',
            programInterest: this.formData.programInfo.interestedIn,
            contactPreference: this.getContactPreferenceDisplay(this.formData.programInfo.contactPreference),
            notes: `=== PROGRAM INFORMATION REQUEST ===\nPreferred Contact Method: ${this.getContactPreferenceDisplay(this.formData.programInfo.contactPreference)}\nProgram Interest: ${this.formData.programInfo.interestedIn}\n\n=== FOLLOW-UP NOTES ===\n${this.getFollowUpNotes(this.formData.programInfo.contactPreference)}\n\nNOTE: This is an information request only, not a trial class booking.`
        };
        
        console.log('📝 Creating info request prospect:', prospectData);
        const prospect = await this.createProspectInClubWorx(prospectData);
        const contactKey = prospect.data.contact_key || prospect.data.id;
        
        // Track attribution data for this info request
        if (this.attributionTracker) {
            await this.attributionTracker.trackFormSubmission(contactKey, {
                programInfo: this.formData.programInfo.interestedIn,
                contactPreference: this.formData.programInfo.contactPreference,
                bookingCompleted: false
            });
        }
        
        console.log('✅ Program info request submitted successfully');
        return prospect;
    }
    
    // Track GA4 info submission
    // GA4 automatically uses beacon transport when needed
    trackGA4InfoSubmission() {
        this.pushOrGtagEvent('contact_us_no_trial', {
            event_category: 'form',
            event_label: 'program_info_step',
            value: 1
        });
    }
    
    // Show info submission success modal
    showInfoSubmissionSuccess() {
        this.elements.modalTitle.textContent = 'Information Submitted!';
        this.elements.modalMessage.textContent = `Thank you for your interest in our ${this.formData.programInfo.interestedIn} program!\n\nWe'll contact you via ${this.formData.programInfo.contactPreference} within 24 hours with program information and answers to any questions you may have.`;
        this.elements.modal.style.display = 'flex';
        
        // Hide calendar button for info requests
        this.elements.addToCalendar.style.display = 'none';
        
        // Update OK button text
        this.elements.modalOk.textContent = 'Close';
        
        console.log('✅ Info submission success modal displayed');
    }
    
    // Submit booking to ClubWorx via API
    async submitBooking() {
        console.log('🚀 Submitting trial booking to ClubWorx...');
        
        // Get UTM parameters once at the start of the function
        const urlParams = new URLSearchParams(window.location.search);
        const utmSource = urlParams.get('utm_source');
        const utmMedium = urlParams.get('utm_medium');
        
        // Also check sessionStorage for attribution data (in case URL changed)
        let storedAttribution = null;
        try {
            const stored = sessionStorage.getItem('clubworx_attribution');
            if (stored) {
                storedAttribution = JSON.parse(stored);
            }
        } catch (e) {
            console.warn('Could not parse stored attribution data');
        }
        
        // Helper function to get attribution source/medium
        const getAttributionData = (defaultSource) => {
            let source = defaultSource;
            let medium = null;
            
            // Use UTM source/medium from URL or stored attribution
            if (utmSource) {
                source = utmSource;
            } else if (storedAttribution && storedAttribution.utm_source) {
                source = storedAttribution.utm_source;
            }
            
            if (utmMedium) {
                medium = utmMedium;
            } else if (storedAttribution && storedAttribution.utm_medium) {
                medium = storedAttribution.utm_medium;
            }
            
            // Fallback to referrer-based source if no UTM
            if (source === defaultSource && document.referrer) {
                try {
                    const referrerDomain = new URL(document.referrer).hostname.replace('www.', '');
                    source = referrerDomain;
                } catch (e) {
                    // Invalid referrer URL
                }
            }
            
            return { source, medium };
        };
        
        try {
            // Get attribution data for prospect creation
            const prospectAttribution = getAttributionData('Trial Class Booking Form');
            let source = prospectAttribution.source;
            let medium = prospectAttribution.medium;
            
            console.log('📊 Browser attribution - source:', source, 'medium:', medium);
            
            // Step 1: Create prospect in ClubWorx
            const prospectData = {
                first_name: this.formData.personal.firstName,
                last_name: this.formData.personal.lastName,
                email: this.formData.personal.email,
                phone: this.formData.personal.phone,
                source: source,
                medium: medium,
                status: 'Initial Contact',
                programInterest: this.formData.programInfo.interestedIn,
                contactPreference: this.getContactPreferenceDisplay(this.formData.programInfo.contactPreference),
                classDetails: {
                    ageGroup: this.formData.program.ageGroup,
                    day: this.formData.program.day,
                    class: this.formData.program.selectedClass,
                    bookingStatus: 'Pending'
                },
                preferences: {
                    experience: this.formData.preferences.experience,
                    goals: this.formData.preferences.goals || 'Not specified'
                },
                notes: `=== CONTACT INFORMATION ===\nPreferred Contact Method: ${this.getContactPreferenceDisplay(this.formData.programInfo.contactPreference)}\nProgram Interest: ${this.formData.programInfo.interestedIn}\n\n=== TRIAL CLASS DETAILS ===\nSelected Class: ${this.formData.program.selectedClass}\nMartial Arts Experience: ${this.formData.preferences.experience}\nTraining Goals: ${this.formData.preferences.goals || 'Not specified'}\n\n=== FOLLOW-UP NOTES ===\n${this.getFollowUpNotes(this.formData.programInfo.contactPreference)}`
            };
            
            console.log('📝 Creating prospect:', prospectData);
            let prospect, contactKey;
            
            try {
                prospect = await this.createProspectInClubWorx(prospectData);
                console.log('📊 Prospect response:', prospect);
                
                // Handle different possible response structures from ClubWorx
                contactKey = null;
                console.log('🔍 Extracting contact key from prospect response:', prospect);
                
                // Check direct properties first (most common structure)
                if (prospect.contact_key) {
                    contactKey = prospect.contact_key;
                    console.log('✅ Found contact_key directly:', contactKey);
                } else if (prospect.id) {
                    contactKey = prospect.id;
                    console.log('✅ Found id directly:', contactKey);
                } else if (prospect.contact_id) {
                    contactKey = prospect.contact_id;
                    console.log('✅ Found contact_id directly:', contactKey);
                } else if (prospect.data && prospect.data.contact_key) {
                    contactKey = prospect.data.contact_key;
                    console.log('✅ Found contact_key in data:', contactKey);
                } else if (prospect.data && prospect.data.id) {
                    contactKey = prospect.data.id;
                    console.log('✅ Found id in data:', contactKey);
                } else if (prospect.data && prospect.data.contact_id) {
                    contactKey = prospect.data.contact_id;
                    console.log('✅ Found contact_id in data:', contactKey);
                }
                
                console.log('🔑 Final contactKey value:', contactKey);
                
                if (!contactKey) {
                    console.error('❌ No contact key found in prospect response:', prospect);
                    throw new Error('Failed to get contact key from ClubWorx response');
                }
                
            } catch (prospectError) {
                console.error('❌ Prospect creation failed:', prospectError);
                console.warn('⚠️ Continuing with fallback contact key for local storage');
                
                // Create a fallback contact key for local storage purposes
                contactKey = `fallback_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
                prospect = {
                    fallback: true,
                    error: prospectError.message,
                    contact_key: contactKey
                };
            }
            
            console.log('📞 Contact created with key:', contactKey);
            
            // Safety check for contactKey
            if (!contactKey) {
                console.error('❌ ContactKey is undefined or null after prospect creation');
                throw new Error('Failed to obtain contact key for booking');
            }
            
            // Track attribution data for this lead
            if (this.attributionTracker) {
                await this.attributionTracker.trackFormSubmission(contactKey, {
                    programInfo: this.formData.programInfo.interestedIn,
                    contactPreference: this.formData.programInfo.contactPreference,
                    bookingCompleted: true
                });
            }
            
            // Step 2: Find next available event for the selected class
            console.log('🔍 Finding next available event...');
            const eventData = await this.findNextAvailableEvent({
                group: this.formData.program.group,
                ageGroup: this.formData.program.ageGroup,
                day: this.formData.program.day,
                selectedClass: this.formData.program.selectedClass
            });
            
            console.log('📋 Event data received:', eventData);
            const eventId = eventData.event.event_id;
            console.log(`📅 Found next available class: ${eventData.event.event_name} on ${eventData.event.event_start_at}`);
            console.log('🆔 Event ID extracted:', eventId);
            
            // Step 3: Create trial booking for the selected class
            // Final safety check before creating booking data
            if (!contactKey) {
                console.error('❌ ContactKey is still undefined before booking creation');
                throw new Error('Contact key is required for booking creation');
            }
            
            // Get attribution data for booking (reuse same data)
            const bookingAttribution = getAttributionData('Trial Class Booking Form');
            const bookingSource = bookingAttribution.source;
            const bookingMedium = bookingAttribution.medium;
            
            console.log('📊 Browser attribution for booking - source:', bookingSource, 'medium:', bookingMedium);
            
            const bookingData = {
                contact_key: contactKey,
                event_id: eventId,
                source: bookingSource,
                medium: bookingMedium,
                // Include full form data for email notifications
                personal: this.formData.personal,
                programInfo: this.formData.programInfo,
                program: this.formData.program,
                preferences: this.formData.preferences,
                status: this.formData.status,
                bookingId: this.formData.bookingId,
                submittedAt: this.formData.submittedAt
            };
            
            console.log('📝 Booking data being sent:', bookingData);
            console.log('🔍 Validation - contactKey:', contactKey, '| eventId:', eventId);
            
            console.log('🥋 Creating trial booking:', bookingData);
            const booking = await this.createTrialBookingInClubWorx(bookingData);
            console.log('📊 Booking response:', booking);
            
            // Handle different possible booking response structures
            let bookingId = null;
            if (booking.booking_id) {
                bookingId = booking.booking_id;
            } else if (booking.data && booking.data.booking_id) {
                bookingId = booking.data.booking_id;
            } else if (booking.id) {
                bookingId = booking.id;
            }
            
            if (!bookingId) {
                console.warn('⚠️ No booking ID found in response, using fallback');
                bookingId = `booking_${Date.now()}`;
            }
            
            // Use booking response data which includes location_name and instructor_name
            // Fall back to event data if booking response doesn't have these fields
            const eventDetails = {
                ...eventData.event, // Basic event info from events endpoint
                ...booking, // Override with booking response data (includes location_name, instructor_name)
                event_id: eventId, // Ensure event_id is preserved
                event_name: eventData.event.event_name, // Ensure event_name is preserved
                event_start_at: eventData.event.event_start_at, // Ensure event_start_at is preserved
                event_end_at: eventData.event.event_end_at // Ensure event_end_at is preserved
            };
            
            // Store in localStorage as backup
            try {
                const bookings = JSON.parse(localStorage.getItem('trialBookings') || '[]');
                
                this.formData.clubworx = {
                    contactKey: contactKey,
                    bookingId: bookingId,
                    eventId: eventId,
                    eventDetails: eventDetails,
                    status: 'success',
                    bookingData: booking
                };
                bookings.push(this.formData);
                localStorage.setItem('trialBookings', JSON.stringify(bookings));
            } catch (e) {
                console.warn('Failed to save to localStorage:', e);
            }
            
            console.log('✅ Trial booking completed successfully in ClubWorx');
            return { 
                success: true, 
                contactKey: contactKey,
                bookingId: bookingId,
                eventId: eventId,
                eventDetails: eventDetails
            };
            
        } catch (error) {
            console.error('❌ ClubWorx booking failed:', error.message);
            
            // Store failed attempt in localStorage for manual processing
            try {
                const failedBookings = JSON.parse(localStorage.getItem('failedTrialBookings') || '[]');
                this.formData.error = error.message;
                this.formData.timestamp = new Date().toISOString();
                failedBookings.push(this.formData);
                localStorage.setItem('failedTrialBookings', JSON.stringify(failedBookings));
            } catch (e) {
                console.warn('Failed to save failed booking:', e);
            }
            
            throw new Error(`ClubWorx integration failed: ${error.message}. Your booking has been saved for manual processing.`);
        }
    }
    
    // Create prospect in ClubWorx via API call to backend
    async createProspectInClubWorx(prospectData) {
        console.log('🌐 Calling /api/prospects with:', prospectData);
        console.log('🔗 API URL:', `${this.baseUrl}prospects`);
        
        try {
            const response = await fetch(`${this.baseUrl}prospects`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.restNonce
                },
                body: JSON.stringify(this.withAccountPayload(prospectData))
            });
            
            console.log('📊 Prospects API response status:', response.status);
            console.log('📊 Response headers:', Object.fromEntries(response.headers.entries()));
            
            if (!response.ok) {
                const errorData = await response.text();
                console.error('❌ Prospects API error:', errorData);
                throw new Error(`Failed to create prospect: ${response.status} - ${errorData}`);
            }
            
            const result = await response.json();
            console.log('✅ Prospect created:', result);
            
            // Validate the result structure
            if (!result) {
                console.error('❌ Prospect response is null or undefined');
                throw new Error('ClubWorx returned null/undefined response');
            }
            
            return result;
            
        } catch (error) {
            console.error('❌ Network or parsing error in createProspectInClubWorx:', error);
            throw error;
        }
    }
    
    // Find next available event based on form selection
    async findNextAvailableEvent(selectionData) {
        console.log('🌐 Calling /api/events with:', selectionData);
        
        const response = await fetch(`${this.baseUrl}events-simple`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.restNonce
            },
            body: JSON.stringify(this.withAccountPayload(selectionData))
        });
        
        console.log('📊 Events API response status:', response.status);
        
        if (!response.ok) {
            const errorData = await response.text();
            console.error('❌ Events API error:', errorData);
            throw new Error(`Failed to find available events: ${response.status} - ${errorData}`);
        }
        
        const result = await response.json();
        console.log('✅ Available event found:', result);
        return result;
    }
    
    // Create trial booking in ClubWorx via API call to backend
    async createTrialBookingInClubWorx(bookingData) {
        console.log('🌐 Calling /api/bookings with:', bookingData);
        
        const response = await fetch(`${this.baseUrl}bookings`, {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.restNonce
            },
            body: JSON.stringify(this.withAccountPayload(bookingData))
        });
        
        console.log('📊 Bookings API response status:', response.status);
        
        if (!response.ok) {
            const errorData = await response.text();
            console.error('❌ Bookings API error:', errorData);
            throw new Error(`Failed to create trial booking: ${response.status} - ${errorData}`);
        }
        
        const result = await response.json();
        
        if (result.existing_booking) {
            console.log('⚠️ Duplicate booking detected - continuing with existing booking');
            console.log('📋 Existing booking info:', result);
            return {
                booking_id: `existing_${result.contact_key}_${result.event_id}`,
                message: result.message,
                duplicate: true
            };
        }
        
        console.log('✅ Booking created:', result);
        return result;
    }
    
    // Validate entire form
    validateForm() {
        let isValid = true;
        let errorFields = [];
        let firstErrorField = null;
        
        Object.keys(this.validationRules).forEach(fieldName => {
            if (!this.validateField(fieldName)) {
                isValid = false;
                errorFields.push(this.getFieldLabel(fieldName));
                if (!firstErrorField) {
                    firstErrorField = this.elements[fieldName];
                }
            }
        });
        
        // Show summary of errors if validation fails
        if (!isValid) {
            const errorCount = errorFields.length;
            let errorMessage = '';
            
            if (errorCount === 1) {
                errorMessage = `Please fix the error in: ${errorFields[0]}`;
            } else if (errorCount === 2) {
                errorMessage = `Please fix the errors in: ${errorFields[0]} and ${errorFields[1]}`;
            } else {
                errorMessage = `Please fix the errors in: ${errorFields.slice(0, -1).join(', ')}, and ${errorFields[errorFields.length - 1]}`;
            }
            
            this.updateFormStatus(errorMessage, 'error');
            
            // Scroll to first error field and focus it
            if (firstErrorField) {
                firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                setTimeout(() => firstErrorField.focus(), 300);
            }
        }
        
        return isValid;
    }
    
    // Validate individual field
    validateField(fieldName) {
        const element = this.elements[fieldName];
        const rules = this.validationRules[fieldName];
        const value = element.type === 'checkbox' ? element.checked : element.value.trim();
        
        // Clear previous errors
        this.clearFieldError(fieldName);
        
        // Get user-friendly field name
        const fieldLabel = this.getFieldLabel(fieldName);
        
        // Required validation
        if (rules.required && (!value || value === '')) {
            this.showFieldError(fieldName, `${fieldLabel} is required`);
            return false;
        }
        
        // Pattern validation with specific error messages
        if (value && rules.pattern && !rules.pattern.test(value)) {
            let errorMessage = this.getPatternErrorMessage(fieldName, value);
            this.showFieldError(fieldName, errorMessage);
            return false;
        }
        
        // Minimum length validation
        if (value && rules.minLength && value.length < rules.minLength) {
            this.showFieldError(fieldName, `${fieldLabel} must be at least ${rules.minLength} characters long`);
            return false;
        }
        
        return true;
    }
    
    // Get user-friendly field names
    getFieldLabel(fieldName) {
        const fieldLabels = {
            firstName: 'First Name',
            lastName: 'Last Name', 
            email: 'Email Address',
            phone: 'Phone Number',
            programInfo: 'Program Information',
            contactPreference: 'Contact Preference',
            ageGroup: 'Age Group',
            day: 'Day',
            class: 'Class'
        };
        return fieldLabels[fieldName] || fieldName;
    }
    
    // Get specific error messages for pattern validation
    getPatternErrorMessage(fieldName, value) {
        switch(fieldName) {
            case 'email':
                if (!value.includes('@')) {
                    return 'Email Address must contain an @ symbol';
                } else if (!value.includes('.')) {
                    return 'Email Address must contain a domain (e.g., gmail.com)';
                } else {
                    return 'Please enter a valid email address (e.g., name@example.com)';
                }
                
            case 'phone':
                // Remove spaces for validation
                const cleanValue = value.replace(/\s/g, '');
                
                if (cleanValue.length < 10) {
                    return 'Phone Number must be at least 10 digits (e.g., 0422 123 456)';
                } else if (!cleanValue.match(/^0/) && !cleanValue.match(/^\+/)) {
                    return 'Phone Number should start with 0 for Australian numbers or + for international';
                } else if (cleanValue.match(/^0/) && cleanValue.length !== 10) {
                    return 'Australian phone numbers should be 10 digits (e.g., 0422 123 456)';
                } else if (cleanValue.match(/^\+/) && cleanValue.length < 11) {
                    return 'International phone numbers should include country code (e.g., +61 422 123 456)';
                } else {
                    return 'Please enter a valid phone number (e.g., 0422 123 456 or +61 422 123 456)';
                }
                
            default:
                return `Please enter a valid ${this.getFieldLabel(fieldName)}`;
        }
    }
    
    // Show field error
    showFieldError(fieldName, message) {
        const element = this.elements[fieldName];
        element.classList.add('error');
        
        // Remove existing error message
        const existingError = element.parentNode.querySelector('.field-error');
        if (existingError) existingError.remove();
        
        // Add error message
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.style.color = 'var(--danger-color)';
        errorElement.style.fontSize = '0.75rem';
        errorElement.style.marginTop = '0.25rem';
        errorElement.textContent = message;
        element.parentNode.appendChild(errorElement);
    }
    
    // Clear field error
    clearFieldError(fieldName) {
        const element = this.elements[fieldName];
        element.classList.remove('error');
        
        const errorElement = element.parentNode.querySelector('.field-error');
        if (errorElement) errorElement.remove();
    }
    
    // Handle submit without booking
    async handleSubmitWithoutBooking() {
        console.log('📧 Submit without booking clicked');
        
        if (this.isSubmitting) return;
        
        // Validate only the required fields for info submission
        const requiredFields = ['firstName', 'lastName', 'email', 'phone', 'programInfo', 'contactPreference'];
        let isValid = true;
        let errorFields = [];
        
        requiredFields.forEach(fieldName => {
            if (!this.validateField(fieldName)) {
                isValid = false;
                errorFields.push(this.getFieldLabel(fieldName));
            }
        });
        
        if (!isValid) {
            const errorMessage = errorFields.length === 1 
                ? `Please fix the error in: ${errorFields[0]}`
                : `Please fix the errors in: ${errorFields.slice(0, -1).join(', ')}, and ${errorFields[errorFields.length - 1]}`;
            
            this.updateFormStatus(errorMessage, 'error');
            return;
        }
        
        this.isSubmitting = true;
        this.updateFormStatus('Submitting your information...', 'processing');
        this.showLoading('Sending your information...');
        this.elements.submitWithoutBooking.disabled = true;
        
        try {
            // Collect minimal form data for info request
            this.collectInfoOnlyFormData();
            
            // Submit to ClubWorx as prospect only (no booking)
            await this.submitInfoOnly();

            // Track info submission in GA4 as backup
            this.trackGA4InfoSubmission();

            // Show success message
            this.showInfoSubmissionSuccess();
            this.updateFormStatus('Information submitted successfully!');
            
        } catch (error) {
            console.error('❌ Info submission failed:', error);
            this.updateFormStatus('Submission failed. Please try again.', 'error');
            
        } finally {
            this.hideLoading();
            this.isSubmitting = false;
            this.elements.submitWithoutBooking.disabled = false;
        }
    }
    
    // Handle program info selection change
    handleProgramInfoChange() {
        console.log('🚀 handleProgramInfoChange called');
        const programInfo = this.elements.programInfo.value;
        console.log('📊 Selected program info:', programInfo);
        
        // Show contact preference container and booking section when a program is selected
        if (programInfo) {
            console.log('📋 Showing contact preference options and booking section');
            this.showContainer(this.elements.contactPreferenceContainer);
            this.showContainer(this.elements.submitWithoutBookingContainer);
            this.showContainer(this.elements.bookingSection);
            this.elements.contactPreference.required = true;
            
            // Set up booking section based on program selection
            this.setupBookingSection(programInfo);
        } else {
            console.log('👻 Hiding contact preference options and booking section');
            this.hideContainer(this.elements.contactPreferenceContainer);
            this.hideContainer(this.elements.submitWithoutBookingContainer);
            this.hideContainer(this.elements.bookingSection);
            this.elements.contactPreference.required = false;
            this.elements.contactPreference.value = '';
            
            // Reset booking section
            this.resetBookingSection();
        }
        
        console.log('🔄 Program info changed to:', programInfo);
    }
    
    // Handle contact preference selection change (RESTORED MISSING FUNCTIONALITY)
    handleContactPreferenceChange() {
        console.log('🚀 handleContactPreferenceChange called');
        const contactPreference = this.elements.contactPreference.value;
        const programInfo = this.elements.programInfo.value;
        
        console.log('📊 Selected contact preference:', contactPreference);
        console.log('📊 Current program info:', programInfo);
        
        // If "book_trial" is selected, show the full booking section
        if (contactPreference === 'book_trial') {
            console.log('📋 "Book Trial Class" selected - showing full booking section');
            
            // Make sure booking section is visible
            this.showContainer(this.elements.bookingSection);
            
            // Set up the booking section based on the already selected program
            if (programInfo) {
                this.setupBookingSection(programInfo);
            }
        } else {
            console.log('📧 Other contact preference selected - only showing info request option');
            
            // For email/phone preferences, don't show the booking section initially
            // User can still manually scroll to booking section if they want
        }
        
        console.log('🔄 Contact preference changed to:', contactPreference);
    }
    
    // Set up booking section based on program selection
    setupBookingSection(programInfo) {
        console.log('🎯 Setting up booking section for:', programInfo);
        
        // Reset dependent dropdowns
        this.elements.ageGroup.value = '';
        this.resetDropdown(this.elements.day);
        this.resetDropdown(this.elements.class);
        
        // Hide containers initially
        this.hideContainer(this.elements.dayContainer);
        this.hideContainer(this.elements.classContainer);
        
        // Set up age group based on program selection
        if (programInfo === 'kids' || programInfo === 'teens' || programInfo === 'adults') {
            console.log(`🎯 ${programInfo} selected - showing age group options`);
            this.populateAgeGroupOptions(programInfo);
            this.showContainer(this.elements.ageGroupContainer);
            this.elements.ageGroup.required = true;
        } else if (programInfo === 'women') {
            console.log('👩 Women selected - skipping to day selection');
            this.hideContainer(this.elements.ageGroupContainer);
            this.elements.ageGroup.required = false;
            // For women, skip to day selection
            this.populateDays('women');
            this.showContainer(this.elements.dayContainer);
            this.elements.day.required = true;
        }
        
        console.log('🔄 Booking section set up for:', programInfo);
    }
    
    // Reset booking section
    resetBookingSection() {
        console.log('🔄 Resetting booking section');
        
        // Reset all dropdowns
        this.elements.ageGroup.value = '';
        this.resetDropdown(this.elements.day);
        this.resetDropdown(this.elements.class);
        
        // Hide all containers
        this.hideContainer(this.elements.ageGroupContainer);
        this.hideContainer(this.elements.dayContainer);
        this.hideContainer(this.elements.classContainer);
        
        // Reset requirements
        this.elements.ageGroup.required = false;
        this.elements.day.required = false;
        this.elements.class.required = false;
    }
    
    // Populate age group dropdown options based on selected group
    populateAgeGroupOptions(group) {
        const ageGroupDropdown = this.elements.ageGroup;
        const ageGroupLabel = this.elements.ageGroupLabel;
        
        // Clear existing options
        ageGroupDropdown.innerHTML = '<option value="">Select...</option>';
        
        if (this.ageGroupOptions[group]) {
            // Update label based on group
            if (group === 'kids') {
                ageGroupLabel.textContent = 'Age Group *';
            } else if (group === 'teens') {
                ageGroupLabel.textContent = 'Age Group *';
            } else if (group === 'adults') {
                ageGroupLabel.textContent = 'Select your class *';
            }
            
            // Add options for the selected group
            this.ageGroupOptions[group].forEach(option => {
                const optionElement = document.createElement('option');
                optionElement.value = option.value;
                optionElement.textContent = option.text;
                ageGroupDropdown.appendChild(optionElement);
            });
            
            console.log(`📊 Age group options populated for ${group}:`, this.ageGroupOptions[group]);
        }
    }
    
    // Handle age group selection change
    handleAgeGroupChange() {
        console.log('🚀 handleAgeGroupChange called');
        const group = this.elements.programInfo.value; // Use program info instead of group
        const ageGroup = this.elements.ageGroup.value;
        
        console.log('🔄 Age group changed to:', ageGroup, 'for group:', group);
        
        // Reset dependent dropdowns first
        this.resetDropdown(this.elements.day);
        this.resetDropdown(this.elements.class);
        this.hideContainer(this.elements.dayContainer);
        this.hideContainer(this.elements.classContainer);
        
        if ((group === 'kids' || group === 'adults') && ageGroup) {
            console.log('📊 Schedule for', group, ageGroup, ':', this.schedule[group][ageGroup]);
            this.populateDays(group, ageGroup);
        } else if (group === 'teens' && ageGroup) {
            // Teens logic: under13 follows kids schedule, over13 follows adults schedule
            if (ageGroup === 'under13') {
                console.log('📊 Teens under 13 - using kids schedule');
                // Use kids schedule but maintain teens group identity
                this.populateTeensDays('kids');
            } else if (ageGroup === 'over13') {
                console.log('📊 Teens over 13 - using adults general/foundations schedule');
                // Use adults schedule but maintain teens group identity
                this.populateTeensDays('adults');
            }
        }
    }
    
    // Handle day selection change
    handleDayChange() {
        const group = this.elements.programInfo.value; // Use program info instead of group
        const ageGroup = this.elements.ageGroup.value;
        const day = this.elements.day.value;
        
        if (day) {
            this.populateClasses(group, ageGroup, day);
        }
        
        console.log('🔄 Day changed to:', day);
    }
    
    // Populate days for teens using kids or adults schedule
    populateTeensDays(targetGroup) {
        const dayDropdown = this.elements.day;
        const ageGroup = this.elements.ageGroup.value;
        this.resetDropdown(dayDropdown);
        
        let availableDays = [];
        
        // Build day list from classes that actually match teens age rules.
        const allDays = new Set();
        if (this.schedule && this.schedule.kids) {
            Object.keys(this.schedule.kids).forEach(kidsAgeGroup => {
                Object.keys(this.schedule.kids[kidsAgeGroup] || {}).forEach(day => allDays.add(day));
            });
        }
        if (this.schedule && this.schedule.adults) {
            Object.keys(this.schedule.adults).forEach(adultAgeGroup => {
                Object.keys(this.schedule.adults[adultAgeGroup] || {}).forEach(day => allDays.add(day));
            });
        }
        
        availableDays = Array.from(allDays).filter(day => {
            const matches = this.getTeensClassesForDay(ageGroup, day);
            return matches.length > 0;
        });
        
        // Sort days in proper week order (Monday first)
        const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        availableDays.sort((a, b) => {
            return dayOrder.indexOf(a.toLowerCase()) - dayOrder.indexOf(b.toLowerCase());
        });
        
        // Add day options in correct order
        availableDays.forEach(day => {
            const option = document.createElement('option');
            option.value = day;
            option.textContent = this.capitalizeFirst(day);
            dayDropdown.appendChild(option);
        });
        
        if (availableDays.length > 0) {
            this.showContainer(this.elements.dayContainer);
            this.elements.day.required = true;
        }
        
        console.log('📅 Teens days populated from', targetGroup, 'schedule:', availableDays);
    }
    
    // Populate days dropdown based on group and age group
    populateDays(group, ageGroup = null) {
        const dayDropdown = this.elements.day;
        this.resetDropdown(dayDropdown);
        
        // Check if schedule data is loaded
        if (!this.scheduleLoaded || !this.schedule) {
            console.warn('⚠️ Schedule data not loaded yet');
            this.updateFormStatus('Schedule data not available. Please refresh the page.', 'error');
            return;
        }
        
        let availableDays = [];
        
        if (group === 'kids' && ageGroup) {
            // Days must include adults/women buckets — kid-labelled classes can live there.
            availableDays = Array.from(this.collectScheduleDayKeys()).filter(dayKey => {
                const classes = this.getKidsClassesForDay(dayKey);
                return this.filterKidsClassesByBracket(classes, ageGroup).length > 0;
            });
        } else if (group === 'adults' && ageGroup) {
            const schedule = this.schedule[group][ageGroup];
            if (schedule) {
                availableDays = Object.keys(schedule);
            }
        } else if (group === 'women') {
            const schedule = this.schedule[group];
            if (schedule) {
                availableDays = Object.keys(schedule);
            }
        }
        
        console.log(`📅 Day selection initiated:`, group, ageGroup, 'Available days:', availableDays);
        
        // Sort days in proper week order (Monday first)
        const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        availableDays.sort((a, b) => {
            return dayOrder.indexOf(a.toLowerCase()) - dayOrder.indexOf(b.toLowerCase());
        });
        
        // Add day options in correct order
        availableDays.forEach(day => {
            const option = document.createElement('option');
            option.value = day;
            option.textContent = this.capitalizeFirst(day);
            dayDropdown.appendChild(option);
        });
        
        if (availableDays.length > 0) {
            this.showContainer(this.elements.dayContainer);
            this.elements.day.required = true;
            console.log('✅ Days populated successfully:', availableDays);
        } else {
            console.warn('⚠️ No available days found for:', group, ageGroup);
            this.updateFormStatus(this.formatNoClassesMessage(group, ageGroup, null), 'error');
        }
    }
    
    /**
     * Human-readable “no classes” message (avoids raw keys like under6).
     */
    formatNoClassesMessage(group, ageGroup, day) {
        const agePretty = {
            under6: '5–7 years',
            over6: '8–12 years',
            under13: 'Under 13 years',
            over13: 'Over 13 years',
            general: 'General'
        };
        const ag = agePretty[ageGroup] || ageGroup || '';
        const dayBit = day ? ` on ${day}` : '';
        if (group === 'kids') {
            return `No classes available for Kids (${ag})${dayBit}.`;
        }
        if (group === 'teens') {
            return `No classes available for Teens (${ag})${dayBit}.`;
        }
        if (group === 'adults') {
            return `No classes available for Adults${dayBit}.`;
        }
        return `No classes available for ${group} ${ag}${dayBit}.`;
    }
    
    // Populate classes dropdown based on selections
    populateClasses(group, ageGroup, day) {
        const classDropdown = this.elements.class;
        this.resetDropdown(classDropdown);
        
        // Check if schedule data is loaded
        if (!this.scheduleLoaded || !this.schedule) {
            console.warn('⚠️ Schedule data not loaded yet');
            this.updateFormStatus('Schedule data not available. Please refresh the page.', 'error');
            return;
        }
        
        let availableClasses = [];
        
        if (group === 'kids') {
            const kidsClasses = this.getKidsClassesForDay(day);
            availableClasses = this.filterKidsClassesByBracket(kidsClasses, ageGroup);
        } else if (group === 'teens') {
            availableClasses = this.getTeensClassesForDay(ageGroup, day);
        } else if (group === 'adults' && ageGroup) {
            const dayClasses = this.getAllClassesForDay(day);
            availableClasses = dayClasses.filter(className => this.isAdultClassName(className));
        } else if (group === 'women') {
            availableClasses = this.schedule[group][day] || [];
        }
        
        console.log('🥋 Class selection for', group, ageGroup, day, '- Available classes:', availableClasses);
        
        // Remove duplicates and sort by time
        availableClasses = [...new Set(availableClasses)].sort(this.compareClassTimes);
        
        // Add class options
        availableClasses.forEach(className => {
            const option = document.createElement('option');
            option.value = className;
            option.textContent = className;
            classDropdown.appendChild(option);
        });
        
        if (availableClasses.length > 0) {
            this.showContainer(this.elements.classContainer);
            this.elements.class.required = true;
            console.log('✅ Classes populated successfully:', availableClasses);
        } else {
            console.warn('⚠️ No available classes found for:', group, ageGroup, day);
            this.updateFormStatus(this.formatNoClassesMessage(group, ageGroup, day), 'error');
        }
    }
    
    /**
     * All weekday keys that appear anywhere in the schedule (kids, adults, women).
     * Needed because kid classes may be stored under adults buckets in API data.
     *
     * @return Set<string>
     */
    collectScheduleDayKeys() {
        const days = new Set();
        const kidsSchedule = this.schedule && this.schedule.kids ? this.schedule.kids : {};
        Object.keys(kidsSchedule).forEach(kidsAgeGroup => {
            Object.keys(kidsSchedule[kidsAgeGroup] || {}).forEach(d => days.add(d));
        });
        const adultsSchedule = this.schedule && this.schedule.adults ? this.schedule.adults : {};
        Object.keys(adultsSchedule).forEach(adultsAgeGroup => {
            Object.keys(adultsSchedule[adultsAgeGroup] || {}).forEach(d => days.add(d));
        });
        const womenSchedule = this.schedule && this.schedule.women ? this.schedule.women : {};
        Object.keys(womenSchedule).forEach(d => days.add(d));
        return days;
    }
    
    // Gather all classes for a given day from every schedule bucket.
    getAllClassesForDay(day) {
        let classes = [];
        
        const kidsSchedule = this.schedule && this.schedule.kids ? this.schedule.kids : {};
        Object.keys(kidsSchedule).forEach(kidsAgeGroup => {
            if (kidsSchedule[kidsAgeGroup] && kidsSchedule[kidsAgeGroup][day]) {
                classes = classes.concat(kidsSchedule[kidsAgeGroup][day]);
            }
        });
        
        const adultsSchedule = this.schedule && this.schedule.adults ? this.schedule.adults : {};
        Object.keys(adultsSchedule).forEach(adultsAgeGroup => {
            if (adultsSchedule[adultsAgeGroup] && adultsSchedule[adultsAgeGroup][day]) {
                classes = classes.concat(adultsSchedule[adultsAgeGroup][day]);
            }
        });
        
        const womenSchedule = this.schedule && this.schedule.women ? this.schedule.women : {};
        if (womenSchedule[day]) {
            classes = classes.concat(womenSchedule[day]);
        }
        
        return [...new Set(classes)];
    }
    
    // Gather kids classes for a day (name-based in case API buckets are mixed).
    getKidsClassesForDay(day) {
        const allClasses = this.getAllClassesForDay(day);
        return allClasses.filter(className => this.isKidsClassName(className));
    }
    
    isKidsClassName(className) {
        if (typeof className !== 'string') {
            return false;
        }
        const hasKidsMarker = /(mini\s*warriors?|tiny\s*warriors?|warriors?\s*\(8\s*[-–]\s*12\)|5\s*[-–]\s*7|3\s*[-–]\s*4|8\s*[-–]\s*12|\bkids?\b)/i.test(className);
        const hasNonKidsMarker = /(adults?|core\s*skills|foundations?|teens?|13\+|combatives)/i.test(className);
        return hasKidsMarker && !hasNonKidsMarker;
    }
    
    isAdultClassName(className) {
        if (typeof className !== 'string') {
            return false;
        }
        const hasAdultMarker = /(adults?|all\s*levels|core\s*skills|foundations?)/i.test(className);
        const hasNonAdultMarker = /(mini\s*warriors?|tiny\s*warriors?|warriors?\s*\(8\s*[-–]\s*12\)|5\s*[-–]\s*7|3\s*[-–]\s*4|8\s*[-–]\s*12|teens?|13\+|combatives)/i.test(className);
        return hasAdultMarker && !hasNonAdultMarker;
    }
    
    // Filter kids classes by visible age bracket in class name.
    filterKidsClassesByBracket(classes, ageGroup) {
        const list = Array.isArray(classes) ? classes : [];
        if (!ageGroup) {
            return list;
        }
        
        const miniWarriorsBracket = /(mini\s*warriors?|5\s*[-–]\s*7|\(5\s*[-–]\s*7\))/i;
        const warriors812Bracket = /(warriors?\s*\(8\s*[-–]\s*12\)|\b8\s*[-–]\s*12\b)/i;
        
        let filtered = list.filter(className => {
            if (typeof className !== 'string') {
                return false;
            }
            if (ageGroup === 'under6') {
                // Kids younger bracket: prefer Mini/Tiny Warriors classes.
                const isMiniOrTiny = /(mini\s*warriors?|tiny\s*warriors?|5\s*[-–]\s*7|3\s*[-–]\s*4|\(5\s*[-–]\s*7\)|\(3\s*[-–]\s*4\))/i.test(className);
                const isOlderOrTeen = /(8\s*[-–]\s*12|13\+|teens?|combatives)/i.test(className);
                return isMiniOrTiny && !isOlderOrTeen;
            }
            if (ageGroup === 'over6') {
                // Kids older bracket: show Warriors (8-12) classes.
                const isWarriors812 = warriors812Bracket.test(className);
                const isMini = /mini\s*warriors?/i.test(className);
                return isWarriors812 && !isMini;
            }
            return true;
        });
        
        // Safety fallback: if naming doesn't include bracket markers, keep kids flow usable.
        if (filtered.length === 0) {
            filtered = list.filter(className => {
                if (typeof className !== 'string') {
                    return false;
                }
                const isTeen = /(13\+|teens?|combatives)/i.test(className);
                return !isTeen;
            });
        }

        return filtered;
    }
    
    // Collect classes for teens based on strict age-group rules.
    getTeensClassesForDay(ageGroup, day) {
        const kidsClasses = this.getKidsClassesForDay(day);
        const adultsClasses = [];
        const adultsSchedule = this.schedule && this.schedule.adults ? this.schedule.adults : {};
        Object.keys(adultsSchedule).forEach(adultsAgeGroup => {
            if (adultsSchedule[adultsAgeGroup] && adultsSchedule[adultsAgeGroup][day]) {
                adultsClasses.push(...adultsSchedule[adultsAgeGroup][day]);
            }
        });
        
        const allClasses = [...new Set([...(kidsClasses || []), ...adultsClasses])];
        
        if (ageGroup === 'under13') {
            // Under 13 teens: Warriors (8–12) only — not Mini/Tiny (5–7 / 3–4).
            return allClasses.filter(className => {
                if (typeof className !== 'string') {
                    return false;
                }
                if (/mini\s*warriors?|tiny\s*warriors?|\(5\s*[-–]\s*7\)|\(3\s*[-–]\s*4\)|\b5\s*[-–]\s*7\b|\b3\s*[-–]\s*4\b/i.test(className)) {
                    return false;
                }
                const isWarriors812 = /warriors?\s*\(\s*8\s*[-–]\s*12\s*\)|\b8\s*[-–]\s*12\b/i.test(className);
                const isTeens13 = /(teens?\s*\(?\s*13\+|13\+|combatives)/i.test(className);
                return isWarriors812 && !isTeens13;
            });
        }
        
        if (ageGroup === 'over13') {
            // Over 13: show only the Teens 13+/Combatives class.
            return allClasses.filter(className => {
                if (typeof className !== 'string') {
                    return false;
                }
                return /(teens?.*13\+|13\+|teens?\s*&\s*warriors?\s*combatives|combatives)/i.test(className);
            });
        }
        
        return allClasses;
    }
    
    // Utility methods for dropdown management
    resetDropdown(dropdown) {
        // Clear all options except the first one
        dropdown.innerHTML = '<option value="">Select...</option>';
        dropdown.value = '';
    }
    
    showContainer(container) {
        container.style.display = 'block';
    }
    
    hideContainer(container) {
        container.style.display = 'none';
    }
    
    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    // Helper method to compare class times for sorting
    compareClassTimes(classA, classB) {
        // Extract time from class strings like "General Gi Class - 6:00 PM"
        const extractTime = (classStr) => {
            const timeMatch = classStr.match(/- (\d{1,2}:\d{2} [AP]M)$/);
            return timeMatch ? timeMatch[1] : '12:00 AM';
        };
        
        // Convert time to minutes for comparison
        const timeToMinutes = (time) => {
            const parts = time.match(/(\d{1,2}):(\d{2}) ([AP]M)/);
            if (!parts) return 0;
            
            let hours = parseInt(parts[1]);
            const minutes = parseInt(parts[2]);
            const ampm = parts[3];
            
            if (ampm === 'PM' && hours !== 12) hours += 12;
            if (ampm === 'AM' && hours === 12) hours = 0;
            
            return hours * 60 + minutes;
        };
        
        const timeA = extractTime(classA);
        const timeB = extractTime(classB);
        
        return timeToMinutes(timeA) - timeToMinutes(timeB);
    }
    
    // Update form status (reusing pattern from management.css:102-123)
    updateFormStatus(message, type = 'ready') {
        if (this.elements && this.elements.formStatus) {
            this.elements.formStatus.textContent = message;
            this.elements.formStatus.className = `form-status ${type}`;
            this.elements.formStatus.style.display = message ? 'block' : 'none';
        } else {
            console.log('📊 Form status:', message, `(${type})`);
        }
    }
    
    // Show loading overlay (reusing pattern from management.html:149-155)
    showLoading(message = 'Processing...') {
        this.elements.loadingText.textContent = message;
        this.elements.loadingOverlay.style.display = 'flex';
    }
    
    // Hide loading overlay
    hideLoading() {
        this.elements.loadingOverlay.style.display = 'none';
    }
    
    // Show success modal (reusing pattern from management.html:157-167)
    showSuccessModal() {
        // Check if this was a duplicate booking
        const isDuplicate = this.formData.clubworx && this.formData.clubworx.bookingData && this.formData.clubworx.bookingData.duplicate;
        
        if (isDuplicate) {
            this.elements.modalTitle.textContent = 'Already Booked!';
            this.elements.modalMessage.textContent = 'Good news! You already have a trial class booking for this session.\n\nWe\'ll contact you within 24 hours with additional details.\n\nClick OK to learn what to expect at your first class!';
        } else {
            this.elements.modalTitle.textContent = 'Trial Class Booked!';
            
            let message = 'Thank you! Your trial class has been booked successfully.\n\nClick OK to learn what to expect at your first class!';
            
            // Add specific class details if available
            if (this.formData.clubworx && this.formData.clubworx.eventDetails) {
            const event = this.formData.clubworx.eventDetails;
            const eventDate = new Date(event.event_start_at);
            const dateStr = eventDate.toLocaleDateString('en-AU');
            const timeStr = eventDate.toLocaleTimeString('en-AU', { hour: '2-digit', minute: '2-digit' });
            
            message = `Your trial class has been booked!\n\n` +
                     `📛 Class: ${event.event_name}\n` +
                     `📅 Date: ${dateStr}\n` +
                     `🕐 Time: ${timeStr}\n` +
                     `📍 Location: ${event.location_name}\n` +
                     `🎓 Instructor: ${event.instructor_name}\n\n` +
                     `We'll contact you within 24 hours with additional details and waiver information.\n\n` +
                     `Click OK to learn what to expect at your first class!`;
        }
            
            this.elements.modalMessage.textContent = message;
        }
        
        this.elements.modal.style.display = 'flex';
        
        // Store Phase 1 data for Phase 2 continuation
        try {
            localStorage.setItem('trialBooking_phase1', JSON.stringify(this.formData));
            console.log('💾 Phase 1 data stored for continuation');
        } catch (e) {
            console.warn('Failed to store Phase 1 data:', e);
        }
    }
    
    // Handle add to calendar
    handleAddToCalendar() {
        console.log('📅 Add to calendar clicked');
        
        if (this.formData.clubworx && this.formData.clubworx.eventDetails) {
            this.createCalendarEvent(this.formData.clubworx.eventDetails);
        } else {
            // Fallback for basic booking info
            this.createBasicCalendarEvent();
        }
        
        // Track calendar event in GA4
        if (typeof gtag === 'function') {
            gtag('event', 'click', {
                event_category: 'engagement',
                event_label: 'add_to_calendar'
            });
        }
    }
    
    // Create calendar event with booking details
    createCalendarEvent(eventDetails) {
        const startDate = new Date(eventDetails.event_start_at);
        const endDate = new Date(eventDetails.event_end_at || eventDetails.event_start_at);
        
        // If no end time provided, assume 1 hour duration
        if (!eventDetails.event_end_at) {
            endDate.setHours(startDate.getHours() + 1);
        }
        
        const eventData = {
            title: `Trial Class: ${eventDetails.event_name}`,
            start: this.formatDateForCalendar(startDate),
            end: this.formatDateForCalendar(endDate),
            description: this.buildEventDescription(eventDetails),
            location: eventDetails.location_name || this.getClubDisplayName()
        };
        
        this.showCalendarOptions(eventData);
    }
    
    // Create basic calendar event when detailed info isn't available
    createBasicCalendarEvent() {
        const eventData = {
            title: `Trial Class: ${this.formData.program.selectedClass}`,
            start: '', // Will be filled in by academy
            end: '',
            description: this.buildBasicEventDescription(),
            location: this.getClubDisplayName()
        };
        
        this.showCalendarOptions(eventData);
    }
    
    // Format date for calendar systems (YYYYMMDDTHHMMSSZ)
    formatDateForCalendar(date) {
        return date.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
    }
    
    // Build detailed event description
    buildEventDescription(eventDetails) {
        let description = `${this.getTrialIntro()}\n\n`;
        description += `Class: ${eventDetails.event_name}\n`;
        if (eventDetails.instructor_name) {
            description += `Instructor: ${eventDetails.instructor_name}\n`;
        }
        description += `Location: ${eventDetails.location_name || this.getClubDisplayName()}\n\n`;
        description += `Student: ${this.formData.personal.firstName} ${this.formData.personal.lastName}\n`;
        description += `Experience: ${this.formData.preferences.experience}\n\n`;
        description += `Please arrive 15 minutes early for your trial class.\n`;
        description += `What to bring: Comfortable workout clothes, water bottle.\n\n`;
        description += `Contact: ${this.getClubDisplayName()}\n`;
        description += `Website: ${this.getClubWebsiteUrl()}`;
        
        return description;
    }
    
    // Build basic event description
    buildBasicEventDescription() {
        let description = `${this.getTrialIntro()}\n\n`;
        description += `Class: ${this.formData.program.selectedClass}\n`;
        description += `Student: ${this.formData.personal.firstName} ${this.formData.personal.lastName}\n`;
        description += `Experience: ${this.formData.preferences.experience}\n\n`;
        description += `Please arrive 15 minutes early for your trial class.\n`;
        description += `What to bring: Comfortable workout clothes, water bottle.\n\n`;
        description += `Note: We will contact you within 24 hours to confirm the exact time and date.\n\n`;
        description += `Contact: ${this.getClubDisplayName()}\n`;
        description += `Website: ${this.getClubWebsiteUrl()}`;
        
        return description;
    }
    
    // Show calendar options to user
    showCalendarOptions(eventData) {
        const googleUrl = this.createGoogleCalendarUrl(eventData);
        const outlookUrl = this.createOutlookCalendarUrl(eventData);
        const icsData = this.createICSFile(eventData);
        
        // Create temporary modal content for calendar options
        const originalTitle = this.elements.modalTitle.textContent;
        const originalMessage = this.elements.modalMessage.textContent;
        
        this.elements.modalTitle.textContent = '📅 Add to Calendar';
        this.elements.modalMessage.innerHTML = `
            <p>Choose your calendar app:</p>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; margin: 1rem 0;">
                <a href="${googleUrl}" target="_blank" class="btn btn-secondary" style="text-decoration: none;">
                    📅 Google Calendar
                </a>
                <a href="${outlookUrl}" target="_blank" class="btn btn-secondary" style="text-decoration: none;">
                    📅 Outlook Calendar
                </a>
                <button onclick="bookingManager.downloadICS('${btoa(icsData)}')" class="btn btn-secondary">
                    📅 Download ICS File
                </button>
            </div>
            <p style="font-size: 0.875rem; color: var(--text-secondary);">
                Or continue to learn what to expect at your first class.
            </p>
        `;
        
        console.log('📅 Calendar options displayed');
    }
    
    // Create Google Calendar URL
    createGoogleCalendarUrl(eventData) {
        const baseUrl = 'https://calendar.google.com/calendar/render?action=TEMPLATE';
        const params = new URLSearchParams({
            text: eventData.title,
            dates: `${eventData.start}/${eventData.end}`,
            details: eventData.description,
            location: eventData.location
        });
        
        return `${baseUrl}&${params.toString()}`;
    }
    
    // Create Outlook Calendar URL
    createOutlookCalendarUrl(eventData) {
        const baseUrl = 'https://outlook.live.com/calendar/0/deeplink/compose';
        const params = new URLSearchParams({
            subject: eventData.title,
            startdt: eventData.start,
            enddt: eventData.end,
            body: eventData.description,
            location: eventData.location
        });
        
        return `${baseUrl}?${params.toString()}`;
    }
    
    // Create ICS file content
    createICSFile(eventData) {
        const ics = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Clubworx//Trial Booking//EN',
            'BEGIN:VEVENT',
            `UID:${Date.now()}@${this.getIcsDomain()}`,
            `DTSTAMP:${this.formatDateForCalendar(new Date())}`,
            `DTSTART:${eventData.start}`,
            `DTEND:${eventData.end}`,
            `SUMMARY:${eventData.title}`,
            `DESCRIPTION:${eventData.description.replace(/\n/g, '\\n')}`,
            `LOCATION:${eventData.location}`,
            'END:VEVENT',
            'END:VCALENDAR'
        ].join('\r\n');
        
        return ics;
    }
    
    // Download ICS file
    downloadICS(encodedData) {
        const icsData = atob(encodedData);
        const blob = new Blob([icsData], { type: 'text/calendar;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'trial-class-booking.ics';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        console.log('📅 ICS file downloaded');
        
        // Track download in GA4
        const mode = this.getCxSettings().analyticsMode || 'none';
        if (mode === 'gtm') {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'calendar_download',
                event_category: 'engagement',
                event_label: 'calendar_download'
            });
        } else if (mode === 'ga4' && typeof gtag === 'function') {
            gtag('event', 'download', {
                event_category: 'engagement',
                event_label: 'calendar_download'
            });
        }
    }
    
    // Handle modal close with potential redirect
    handleModalClose() {
        // Check if this was a successful booking completion
        const wasSuccessfulBooking = this.formData.clubworx && this.formData.clubworx.status === 'success';
        const redirectUrl = (this.getCxSettings().postBookingRedirectUrl || '').trim();
        
        if (wasSuccessfulBooking && redirectUrl !== '') {
            console.log('🔗 Redirecting after successful booking');

            this.pushOrGtagEvent('click', {
                event_category: 'navigation',
                event_label: 'redirect_after_booking'
            });

            setTimeout(() => {
                if (window.top !== window.self) {
                    window.top.location.href = redirectUrl;
                } else {
                    window.location.href = redirectUrl;
                }
            }, 1500);
        } else {
            this.hideModal();
        }
    }
    
    // Hide modal
    hideModal() {
        this.elements.modal.style.display = 'none';
        
        // Reset form after successful submission
        if (!this.isSubmitting) {
            this.resetForm();
        }
    }
    
    // Reset form
    resetForm() {
        this.elements.form.reset();
        this.formData = {};
        
        // Clear all field errors
        Object.keys(this.validationRules).forEach(fieldName => {
            this.clearFieldError(fieldName);
        });
        
        this.updateFormStatus('Ready');
        console.log('🔄 Form reset');
    }
    
    // Track GA4 form start event - Attribution focused
    // GA4 automatically uses beacon transport when needed
    trackGA4FormStart() {
        this.pushOrGtagEvent('form_start', {
            event_category: 'form',
            event_label: 'trial_booking_started'
        });
    }
    
    // Keyboard shortcuts
    handleKeyboard(e) {
        // ESC to close modal
        if (e.key === 'Escape' && this.elements.modal.style.display === 'flex') {
            this.handleModalClose();
        }
        
        // Ctrl+Enter to submit form (if valid)
        if (e.ctrlKey && e.key === 'Enter' && !this.isSubmitting) {
            e.preventDefault();
            this.elements.form.dispatchEvent(new Event('submit'));
        }
    }
}

// Initialize the booking manager
const bookingManager = new TrialClassBookingManager();