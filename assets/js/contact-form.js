/**
 * Clubworx contact form: collects name, email and phone and creates a ClubWorx prospect.
 */

class ClubworxContactForm {
    constructor(form) {
        this.form = form;
        this.wrapper = form.closest('.clubworx-booking-wrapper');
        this.card = form.closest('.booking-card');
        this.statusEl = form.querySelector('#clubworxContactStatus');
        this.submitBtn = form.querySelector('#clubworxContactSubmit');
        this.thanksEl = this.card ? this.card.querySelector('#clubworxContactThanks') : null;
        this.locationSelect = form.querySelector('#clubworxContactLocation');
        this.accountField = form.querySelector('#clubworxContactAccount');
        this.isSubmitting = false;

        const settings = typeof clubworxBookingSettings !== 'undefined' ? clubworxBookingSettings : {};
        this.baseUrl = settings.restUrl || '/wp-json/clubworx/v1/';
        this.restNonce = settings.restNonce || '';

        this.rules = {
            firstName: { minLength: 2, message: 'Please enter your first name (at least 2 characters).' },
            lastName: { minLength: 2, message: 'Please enter your last name (at least 2 characters).' },
            email: { pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, message: 'Please enter a valid email address.' },
            phone: {
                pattern: /^(0[1-9][\d\s]{8,9}|[\+]?[1-9][\d\s]{0,20})$/,
                message: 'Please enter a valid phone number.'
            }
        };

        this.attributionTracker = typeof AttributionTracker !== 'undefined' ? new AttributionTracker() : null;

        this.bind();
    }

    bind() {
        if (this.locationSelect) {
            this.locationSelect.addEventListener('change', () => this.onLocationChange());
        }
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSubmit();
        });
    }

    onLocationChange() {
        const slug = this.locationSelect.value ? String(this.locationSelect.value).trim() : '';
        if (this.wrapper) {
            this.wrapper.setAttribute('data-account', slug);
        }
        if (this.accountField) {
            this.accountField.value = slug;
        }
        this.clearFieldError(this.locationSelect);
    }

    getAccountSlug() {
        if (this.locationSelect) {
            return this.locationSelect.value ? String(this.locationSelect.value).trim() : '';
        }
        return this.accountField && this.accountField.value ? String(this.accountField.value).trim() : '';
    }

    field(name) {
        return this.form.querySelector(`[name="${name}"]`);
    }

    setFieldError(el, message) {
        if (!el) return;
        el.classList.add('error');
        el.setAttribute('aria-invalid', 'true');
        const group = el.closest('.form-group');
        if (!group) return;
        let msg = group.querySelector('.field-error');
        if (!msg) {
            msg = document.createElement('div');
            msg.className = 'field-error';
            group.appendChild(msg);
        }
        msg.textContent = message;
    }

    clearFieldError(el) {
        if (!el) return;
        el.classList.remove('error');
        el.removeAttribute('aria-invalid');
        const group = el.closest('.form-group');
        const msg = group ? group.querySelector('.field-error') : null;
        if (msg) msg.remove();
    }

    validate() {
        let valid = true;
        let firstInvalid = null;

        const fail = (el, message) => {
            this.setFieldError(el, message);
            valid = false;
            if (!firstInvalid) firstInvalid = el;
        };

        if (this.locationSelect) {
            this.clearFieldError(this.locationSelect);
            if (!this.getAccountSlug()) {
                fail(this.locationSelect, 'Please select a location.');
            }
        }

        Object.keys(this.rules).forEach((name) => {
            const el = this.field(name);
            if (!el) return;
            this.clearFieldError(el);
            const rule = this.rules[name];
            const value = (el.value || '').trim();
            if (!value || (rule.minLength && value.length < rule.minLength) || (rule.pattern && !rule.pattern.test(value))) {
                fail(el, rule.message);
            }
        });

        if (firstInvalid && typeof firstInvalid.focus === 'function') {
            firstInvalid.focus();
        }
        return valid;
    }

    getAttribution() {
        const urlParams = new URLSearchParams(window.location.search);
        let stored = null;
        try {
            const raw = sessionStorage.getItem('clubworx_attribution');
            stored = raw ? JSON.parse(raw) : null;
        } catch (e) {
            stored = null;
        }

        const defaultSource = 'Contact Form';
        let source = defaultSource;
        let medium = null;

        const utmSource = urlParams.get('utm_source');
        const utmMedium = urlParams.get('utm_medium');

        if (utmSource) {
            source = utmSource;
        } else if (stored && stored.utm_source) {
            source = stored.utm_source;
        }

        if (utmMedium) {
            medium = utmMedium;
        } else if (stored && stored.utm_medium) {
            medium = stored.utm_medium;
        }

        if (source === defaultSource && document.referrer) {
            try {
                source = new URL(document.referrer).hostname;
            } catch (e) {
                // Keep default source when the referrer cannot be parsed.
            }
        }

        return { source, medium };
    }

    showStatus(message, type) {
        if (!this.statusEl) return;
        this.statusEl.textContent = message;
        this.statusEl.className = 'form-status ' + (type || '');
        this.statusEl.style.display = message ? 'block' : 'none';
    }

    setSubmitting(state) {
        this.isSubmitting = state;
        if (this.submitBtn) {
            this.submitBtn.disabled = state;
            this.submitBtn.classList.toggle('loading', state);
        }
    }

    async handleSubmit() {
        if (this.isSubmitting) return;

        // Honeypot: silently pretend success for bots.
        const honeypot = this.field('website');
        if (honeypot && honeypot.value.trim() !== '') {
            this.showThanks();
            return;
        }

        this.showStatus('', '');
        if (!this.validate()) return;

        this.setSubmitting(true);

        const attribution = this.getAttribution();
        const payload = {
            account: this.getAccountSlug(),
            first_name: this.field('firstName').value.trim(),
            last_name: this.field('lastName').value.trim(),
            email: this.field('email').value.trim(),
            phone: this.field('phone').value.trim(),
            status: 'Initial Contact',
            source: attribution.source,
            medium: attribution.medium,
            programInterest: 'Contact form',
            website: honeypot ? honeypot.value : ''
        };

        try {
            const response = await fetch(`${this.baseUrl}prospects`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.restNonce
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            const result = await response.json();
            const data = result && typeof result.data === 'object' && result.data ? result.data : {};
            const contactKey = (result && (result.contact_key || result.id || result.contact_id))
                || data.contact_key || data.id || data.contact_id || null;

            this.showThanks();

            if (this.attributionTracker && typeof this.attributionTracker.trackFormSubmission === 'function') {
                try {
                    await this.attributionTracker.trackFormSubmission(contactKey, {
                        programInfo: 'contact_form',
                        bookingCompleted: false
                    });
                } catch (trackErr) {
                    console.warn('Clubworx contact form tracking failed:', trackErr);
                }
            }
        } catch (error) {
            console.error('Clubworx contact form error:', error);
            this.showStatus('Sorry, something went wrong sending your details. Please try again or contact us directly.', 'error');
            this.setSubmitting(false);
        }
    }

    showThanks() {
        this.form.style.display = 'none';
        if (this.thanksEl) {
            this.thanksEl.style.display = 'block';
            if (typeof this.thanksEl.scrollIntoView === 'function') {
                this.thanksEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    }
}

(function () {
    function init() {
        document.querySelectorAll('.clubworx-contact-form').forEach((form) => {
            if (!form.dataset.clubworxContactInit) {
                form.dataset.clubworxContactInit = '1';
                new ClubworxContactForm(form);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
