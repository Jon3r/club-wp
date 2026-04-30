/**
 * Clubworx attribution tracker — UTM, referrer, optional GA4 or GTM (single path; no duplicate GA4).
 */

class AttributionTracker {
    constructor() {
        this.sessionId = this.generateSessionId();
        this.attributionData = null;
        this.init();
    }

    getConfig() {
        return typeof clubworxBookingSettings !== 'undefined' ? clubworxBookingSettings : {};
    }

    getAnalyticsMode() {
        const m = this.getConfig().analyticsMode;
        return m === 'ga4' || m === 'gtm' ? m : 'none';
    }

    getCurrency() {
        const c = this.getConfig().ga4Currency;
        return c && String(c).length === 3 ? String(c).toUpperCase() : 'USD';
    }

    getGa4MeasurementId() {
        const id = this.getConfig().ga4MeasurementId;
        return id && String(id).trim() !== '' ? String(id).trim() : '';
    }

    init() {
        console.log('📊 Attribution tracker initialized');
        this.captureAttributionData();
        this.captureGA4ClientId();
    }

    captureAttributionData() {
        const urlParams = new URLSearchParams(window.location.search);

        const utmData = {
            utm_source: urlParams.get('utm_source'),
            utm_medium: urlParams.get('utm_medium'),
            utm_campaign: urlParams.get('utm_campaign'),
            utm_term: urlParams.get('utm_term'),
            utm_content: urlParams.get('utm_content')
        };

        const referrerData = {
            referrer: document.referrer,
            landing_page: window.location.href
        };

        this.attributionData = {
            ...utmData,
            ...referrerData,
            session_id: this.sessionId,
            timestamp: new Date().toISOString(),
            page_title: document.title,
            user_agent: navigator.userAgent
        };

        console.log('📈 Attribution data captured:', this.attributionData);

        sessionStorage.setItem('clubworx_attribution', JSON.stringify(this.attributionData));
    }

    async trackFormSubmission(contactKey, formData) {
        console.log('📊 Tracking form submission attribution...');

        const submissionData = {
            ...this.attributionData,
            contact_key: contactKey,
            program_interest: formData.programInfo,
            contact_preference: formData.contactPreference,
            booking_completed: formData.bookingCompleted || false
        };

        await this.sendAttributionToAnalytics(submissionData);

        try {
            const baseUrl = this.getConfig().restUrl || '/wp-json/clubworx/v1/';
            const restNonce = this.getConfig().restNonce || '';

            const response = await fetch(`${baseUrl}attribution`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce
                },
                body: JSON.stringify(submissionData)
            });

            if (response.ok) {
                const result = await response.json();
                console.log('✅ Attribution tracked:', result);
                return result;
            }
            console.error('❌ Attribution tracking failed:', response.status);
        } catch (error) {
            console.error('❌ Attribution tracking error:', error);
        }
    }

    getLeadSource() {
        if (!this.attributionData) return 'direct';

        const { utm_source, utm_medium, referrer } = this.attributionData;

        if (utm_source) {
            if (utm_medium === 'social') return `social_${utm_source}`;
            if (utm_medium === 'cpc') return `paid_${utm_source}`;
            if (utm_medium === 'email') return 'email_marketing';
            return `utm_${utm_source}`;
        }

        if (referrer) {
            try {
                const domain = new URL(referrer).hostname.toLowerCase();
                if (domain.includes('facebook')) return 'social_facebook';
                if (domain.includes('google')) return 'organic_google';
                return `referrer_${domain}`;
            } catch (e) {
                return 'direct';
            }
        }

        return 'direct';
    }

    generateSessionId() {
        return 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    getAttributionForSubmission() {
        return {
            ...this.attributionData,
            lead_source: this.getLeadSource()
        };
    }

    pushDataLayerEvent(eventName, payload) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: eventName,
            ...payload
        });
    }

    captureGA4ClientId() {
        const mode = this.getAnalyticsMode();
        const mid = this.getGa4MeasurementId();

        if (mode !== 'ga4' || !mid || typeof gtag !== 'function') {
            return;
        }

        try {
            gtag('get', mid, 'client_id', (clientId) => {
                this.ga4ClientId = clientId;
                if (this.attributionData) {
                    this.attributionData.ga4_client_id = clientId;
                    sessionStorage.setItem('clubworx_attribution', JSON.stringify(this.attributionData));
                }
            });
            gtag('get', mid, 'session_id', (sessionId) => {
                this.ga4SessionId = sessionId;
            });
        } catch (error) {
            console.error('❌ Failed to capture GA4 client ID:', error);
        }
    }

    async sendAttributionToAnalytics(submissionData) {
        const mode = this.getAnalyticsMode();
        if (mode === 'none') {
            console.log('📊 Analytics mode none — skipping client-side tag events');
            return;
        }

        const eventName = submissionData.booking_completed ? 'trial_booking_complete' : 'form_submit';
        const currency = this.getCurrency();
        const baseParams = {
            event_category: submissionData.booking_completed ? 'conversion' : 'form',
            event_label: submissionData.booking_completed ? 'trial_booking' : 'program_info',
            value: submissionData.booking_completed ? 1 : 0.5,
            currency: currency,
            custom_lead_source: this.getLeadSource(),
            custom_utm_source: submissionData.utm_source || '(not set)',
            custom_utm_medium: submissionData.utm_medium || '(not set)',
            custom_utm_campaign: submissionData.utm_campaign || '(not set)',
            custom_program_interest: submissionData.program_interest,
            custom_contact_key: submissionData.contact_key
        };

        if (mode === 'gtm') {
            this.pushDataLayerEvent(eventName, {
                ...baseParams,
                booking_completed: !!submissionData.booking_completed
            });
            if (submissionData.booking_completed) {
                this.pushDataLayerEvent('clubworx_booking_conversion', {
                    ...baseParams
                });
            }
            await this.sendServerSideGA4Event(eventName, baseParams);
            return;
        }

        if (mode === 'ga4') {
            try {
                if (typeof gtag !== 'function') {
                    console.warn('⚠️ gtag not available');
                    return;
                }
                const mid = this.getGa4MeasurementId();
                if (!mid) {
                    return;
                }

                const ga4Parameters = {
                    ...baseParams,
                    ...(submissionData.booking_completed && {
                        item_id: 'trial_class_booking',
                        item_name: `${submissionData.program_interest} Trial Class`,
                        item_category: 'trial_bookings',
                        quantity: 1,
                        price: 0
                    })
                };

                gtag('event', eventName, ga4Parameters);

                if (submissionData.booking_completed) {
                    gtag('event', 'conversion', {
                        send_to: mid,
                        value: 1,
                        currency: currency,
                        custom_conversion_type: 'trial_booking',
                        custom_lead_source: ga4Parameters.custom_lead_source
                    });
                }

                await this.sendServerSideGA4Event(eventName, ga4Parameters);
            } catch (error) {
                console.error('❌ Failed to send attribution to GA4:', error);
            }
        }
    }

    async sendServerSideGA4Event(eventName, parameters) {
        try {
            const measurementData = {
                client_id: this.ga4ClientId || this.generateClientId(),
                events: [{
                    name: eventName,
                    params: {
                        ...parameters,
                        engagement_time_msec: 1000,
                        session_id: this.ga4SessionId || this.sessionId
                    }
                }]
            };

            const baseUrl = this.getConfig().restUrl || '/wp-json/clubworx/v1/';
            const restNonce = this.getConfig().restNonce || '';

            const response = await fetch(`${baseUrl}ga4-measurement`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': restNonce
                },
                body: JSON.stringify(measurementData)
            });

            if (response.ok) {
                console.log('✅ Server-side GA4 request completed');
            }
        } catch (error) {
            console.warn('⚠️ Server-side GA4 tracking unavailable:', error.message);
        }
    }

    generateClientId() {
        return Date.now() + '.' + Math.random().toString().substring(2);
    }
}
