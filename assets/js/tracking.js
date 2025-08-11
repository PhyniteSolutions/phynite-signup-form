/**
 * Analytics & Tracking Manager for Phynite Signup Form
 * 
 * Handles Google Analytics (GA4) and Facebook Pixel event tracking
 * for form interactions and conversions.
 */

/* global gtag, ga, fbq */

export class PhyniteTrackingManager {
    constructor(config = {}) {
        this.config = {
            ga: {
                enabled: false,
                measurementId: null,
                ...config.ga
            },
            facebook: {
                enabled: false,
                pixelId: null,
                ...config.facebook
            },
            debug: config.debug || false
        };
        
        this.isReady = {
            ga: false,
            facebook: false
        };
        
        this.eventQueue = [];
        this.init();
    }

    /**
     * Initialize tracking systems
     */
    init() {
        this.checkAnalyticsAvailability();
        this.processQueuedEvents();
        
        // Recheck availability every few seconds until ready
        if (!this.isReady.ga || !this.isReady.facebook) {
            setTimeout(() => this.init(), 2000);
        }
    }

    /**
     * Check if Google Analytics and Facebook Pixel are available
     */
    checkAnalyticsAvailability() {
        // Check Google Analytics (GA4)
        if (this.config.ga.enabled) {
            this.isReady.ga = (
                typeof gtag !== 'undefined' || 
                typeof ga !== 'undefined' || 
                (window.google_tag_manager && window.dataLayer)
            );
        }

        // Check Facebook Pixel
        if (this.config.facebook.enabled) {
            this.isReady.facebook = (
                typeof fbq !== 'undefined' && 
                window._fbq && 
                window._fbq.loaded
            );
        }

        if (this.config.debug) {
            // eslint-disable-next-line no-console
            console.log('Phynite Tracking:', {
                ga_ready: this.isReady.ga,
                fb_ready: this.isReady.facebook,
                config: this.config
            });
        }
    }

    /**
     * Process any queued events that were triggered before analytics was ready
     */
    processQueuedEvents() {
        if (this.eventQueue.length === 0) return;

        const processedEvents = [];
        
        for (const event of this.eventQueue) {
            if (this.sendEvent(event.type, event.data)) {
                processedEvents.push(event);
            }
        }
        
        // Remove processed events from queue
        this.eventQueue = this.eventQueue.filter(event => 
            !processedEvents.includes(event)
        );
    }

    /**
     * Track form view event
     */
    trackFormView(formData = {}) {
        this.sendEvent('form_view', {
            form_type: 'phynite_signup',
            form_style: formData.style || 'default',
            plan_selection_enabled: formData.planSelection || false
        });
    }

    /**
     * Track when user starts interacting with form
     */
    trackFormStart(formData = {}) {
        this.sendEvent('form_start', {
            form_type: 'phynite_signup',
            engagement_time_msec: formData.timeSinceView || 0
        });
    }

    /**
     * Track field completion events
     */
    trackFieldComplete(fieldName, fieldData = {}) {
        const eventData = {
            field_name: fieldName,
            form_type: 'phynite_signup'
        };

        // Add field-specific data
        if (fieldName === 'email') {
            eventData.email_domain = fieldData.domain;
        } else if (fieldName === 'plan') {
            eventData.selected_plan = fieldData.planId;
            eventData.plan_price = fieldData.price;
            eventData.plan_interval = fieldData.interval;
        }

        this.sendEvent('form_field_complete', eventData);
    }

    /**
     * Track form submission (before API call)
     */
    trackFormSubmit(formData = {}) {
        const eventData = {
            form_type: 'phynite_signup',
            selected_plan: formData.planId,
            email_domain: this.extractDomain(formData.email),
            form_completion_time: formData.completionTime || 0
        };

        this.sendEvent('form_submit', eventData);
    }

    /**
     * Track successful checkout initiation (most important conversion)
     */
    trackCheckoutStart(formData = {}) {
        const eventData = {
            form_type: 'phynite_signup',
            selected_plan: formData.planId,
            email_domain: this.extractDomain(formData.email),
            website: formData.website,
            value: formData.planPrice || 0,
            currency: 'USD'
        };

        // This is a key conversion event
        this.sendEvent('begin_checkout', eventData);
    }

    /**
     * Track form errors for optimization
     */
    trackFormError(errorData = {}) {
        this.sendEvent('form_error', {
            form_type: 'phynite_signup',
            error_type: errorData.type,
            error_field: errorData.field,
            error_message: errorData.message
        });
    }

    /**
     * Generic event sending method
     */
    sendEvent(eventName, eventData = {}) {
        const canSend = this.canSendEvent();
        
        if (!canSend) {
            // Queue event for later if tracking isn't ready
            this.eventQueue.push({
                type: eventName,
                data: eventData
            });
            return false;
        }

        // Send to Google Analytics
        if (this.config.ga.enabled && this.isReady.ga) {
            this.sendGoogleAnalyticsEvent(eventName, eventData);
        }

        // Send to Facebook Pixel
        if (this.config.facebook.enabled && this.isReady.facebook) {
            this.sendFacebookPixelEvent(eventName, eventData);
        }

        if (this.config.debug) {
            // eslint-disable-next-line no-console
            console.log('Phynite Event Tracked:', eventName, eventData);
        }

        return true;
    }

    /**
     * Check if we can send an event (analytics ready, not disabled, etc.)
     */
    canSendEvent() {
        // Check if any tracking is enabled
        if (!this.config.ga.enabled && !this.config.facebook.enabled) {
            return false;
        }

        // Check if at least one service is ready
        const gaReady = this.config.ga.enabled ? this.isReady.ga : true;
        const fbReady = this.config.facebook.enabled ? this.isReady.facebook : true;
        
        return gaReady || fbReady;
    }

    /**
     * Send event to Google Analytics
     */
    sendGoogleAnalyticsEvent(eventName, eventData) {
        try {
            if (typeof gtag !== 'undefined') {
                // GA4 gtag implementation
                gtag('event', eventName, {
                    custom_map: eventData,
                    send_to: this.config.ga.measurementId
                });
            } else if (typeof ga !== 'undefined') {
                // Universal Analytics fallback
                ga('send', 'event', 'Form', eventName, eventData.form_type);
            } else if (window.dataLayer) {
                // Google Tag Manager
                window.dataLayer.push({
                    event: eventName,
                    ...eventData
                });
            }
        } catch (error) {
            if (this.config.debug) {
                // eslint-disable-next-line no-console
                console.warn('GA tracking error:', error);
            }
        }
    }

    /**
     * Send event to Facebook Pixel
     */
    sendFacebookPixelEvent(eventName, eventData) {
        try {
            if (typeof fbq !== 'undefined') {
                // Map our events to Facebook standard events where possible
                const fbEventName = this.mapToFacebookEvent(eventName);
                
                if (eventName === 'begin_checkout') {
                    // Track as conversion with custom data
                    fbq('track', fbEventName, {
                        content_name: 'Phynite Signup',
                        content_category: 'Analytics Subscription',
                        value: eventData.value || 0,
                        currency: eventData.currency || 'USD',
                        custom_data: eventData
                    });
                } else {
                    // Track as custom event
                    fbq('trackCustom', fbEventName, eventData);
                }
            }
        } catch (error) {
            if (this.config.debug) {
                // eslint-disable-next-line no-console
                console.warn('Facebook Pixel tracking error:', error);
            }
        }
    }

    /**
     * Map our event names to Facebook standard events
     */
    mapToFacebookEvent(eventName) {
        const mapping = {
            'form_view': 'ViewContent',
            'form_start': 'InitiateCheckout', 
            'form_field_complete': 'CompleteRegistration',
            'form_submit': 'SubmitApplication',
            'begin_checkout': 'InitiateCheckout',
            'form_error': 'ErrorOccurred'
        };

        return mapping[eventName] || eventName;
    }

    /**
     * Extract domain from email address
     */
    extractDomain(email) {
        if (!email || typeof email !== 'string') return null;
        const match = email.match(/@([^@]+)$/);
        return match ? match[1].toLowerCase() : null;
    }

    /**
     * Update configuration
     */
    updateConfig(newConfig) {
        this.config = {
            ...this.config,
            ...newConfig,
            ga: { ...this.config.ga, ...newConfig.ga },
            facebook: { ...this.config.facebook, ...newConfig.facebook }
        };
        
        this.init(); // Re-initialize with new config
    }

    /**
     * Enable debug mode
     */
    enableDebug() {
        this.config.debug = true;
        // eslint-disable-next-line no-console
        console.log('Phynite Tracking Debug Enabled', this.config);
    }
}

// Export a factory function for easy initialization
export function createPhyniteTracker(config) {
    return new PhyniteTrackingManager(config);
}

// Export default instance (will be initialized by frontend.js)
export let tracker = null;

export function initializeTracker(config) {
    tracker = new PhyniteTrackingManager(config);
    return tracker;
}