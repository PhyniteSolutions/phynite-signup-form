/**
 * Frontend JavaScript for Phynite Signup Form
 */

/* global Stripe */

import { validateField, validateFieldWithAvailability, validateFormData } from './validation.js';
import { initializeTracker } from './tracking.js';

(function($) {
    'use strict';
    
    class PhyniteSignupForm {
        constructor(formElement) {
            this.form = $(formElement);
            this.formId = this.form.attr('id');
            this.submitButton = this.form.find('.phynite-form-submit');
            this.messages = this.form.find('.phynite-form-messages');
            this.emailCheck = null;
            this.products = null;
            this.formStartTime = Date.now();
            this.formViewTime = Date.now();
            this.userHasInteracted = false;
            this.emailValidating = false;
            this.websiteValidating = false;
            this.isEditorPreview = this.form.closest('[data-is-preview="true"]').length > 0 || 
                                  (window.phyniteSignupForm && window.phyniteSignupForm.isEditor);
            
            // Initialize tracking if not in preview mode
            if (!this.isEditorPreview) {
                this.initializeTracking();
            }
            
            this.init();
        }
        
        initializeTracking() {
            // Get tracking config from WordPress localized data
            const trackingConfig = (window.phyniteSignupForm && window.phyniteSignupForm.tracking) || {};
            
            if (trackingConfig.ga_enabled || trackingConfig.fb_enabled) {
                this.tracker = initializeTracker({
                    ga: {
                        enabled: trackingConfig.ga_enabled,
                        measurementId: trackingConfig.ga_measurement_id
                    },
                    facebook: {
                        enabled: trackingConfig.fb_enabled, 
                        pixelId: trackingConfig.fb_pixel_id
                    },
                    debug: trackingConfig.debug || false
                });
                
                // Track form view
                this.trackFormView();
            }
        }
        
        trackFormView() {
            if (!this.tracker) return;
            
            this.tracker.trackFormView({
                style: this.form.data('style') || 'default',
                planSelection: this.form.find('.phynite-plans-container').length > 0
            });
        }
        
        init() {
            // Don't initialize interactive functionality in editor preview
            if (this.isEditorPreview) {
                this.initPreviewMode();
                return;
            }
            
            this.bindEvents();
            this.loadProducts();
            this.initializeValidation();
            this.setupAccessibility();
        }
        
        initPreviewMode() {
            // Set up preview-specific behavior
            this.form.find('input, button').prop('disabled', true);
            this.form.find('.phynite-form-submit').css({
                'pointer-events': 'none',
                'opacity': '0.8'
            });
            
            // Show static plans if plan selection is enabled
            if (this.form.find('.phynite-plans-container').length && 
                this.form.find('.phynite-plans-container').attr('data-loading') !== 'false') {
                this.renderStaticPlans();
            }
        }
        
        bindEvents() {
            // Form submission
            this.form.on('submit', (e) => {
                e.preventDefault();
                this.handleSubmit();
            });
            
            // Real-time validation
            this.form.find('input[name="website"]').on('blur', () => {
                this.validateWebsite();
            });
            
            this.form.find('input[name="firstName"]').on('blur', () => {
                this.validateFirstName();
            });
            
            this.form.find('input[name="lastName"]').on('blur', () => {
                this.validateLastName();
            });
            
            this.form.find('input[name="email"]').on('blur', () => {
                this.validateEmail();
            });
            
            this.form.find('input[name="acceptTerms"]').on('change', () => {
                this.updateSubmitButton();
            });
            
            // Plan selection
            this.form.on('change', 'input[name="planId"]', (e) => {
                this.updateSubmitButton();
                this.trackPlanSelection($(e.target).val());
            });
            
            // Input cleanup and interaction tracking
            this.form.find('input').on('input', (e) => {
                const field = $(e.target);
                this.clearFieldError(field);
                this.trackUserInteraction();
                
                // Clear validation loading if user starts typing during validation
                if (field.attr('name') === 'email' && this.emailValidating) {
                    this.hideFieldValidationLoading(field);
                }
                if (field.attr('name') === 'website' && this.websiteValidating) {
                    this.hideFieldValidationLoading(field);
                }
            });
            
            // Prevent honeypot field interaction
            this.form.find('.phynite-honeypot').on('focus input', (e) => {
                $(e.target).blur().val('');
            });
        }
        
        trackUserInteraction() {
            if (!this.userHasInteracted && this.tracker) {
                this.userHasInteracted = true;
                this.tracker.trackFormStart({
                    timeSinceView: Date.now() - this.formViewTime
                });
            }
        }
        
        trackPlanSelection(planId) {
            if (!this.tracker || !planId) return;
            
            const planData = this.getPlanData(planId);
            this.tracker.trackFieldComplete('plan', {
                planId: planId,
                price: planData ? planData.price : null,
                interval: planData ? planData.interval : null
            });
        }
        
        getPlanData(planId) {
            if (!this.products) return null;
            
            const plan = this.products[planId];
            if (!plan) return null;
            
            return {
                price: plan.amount / 100,
                interval: planId === 'yearly' ? 'year' : 'month'
            };
        }
        
        async loadProducts() {
            // Skip API calls in editor preview
            if (this.isEditorPreview) {
                this.renderStaticPlans();
                return;
            }
            
            try {
                const response = await fetch(`${phyniteSignupForm.apiUrl}get-products`);
                const products = await response.json();
                
                if (products && (products.monthly || products.yearly)) {
                    this.products = products;
                    this.renderPlanOptions();
                } else {
                    throw new Error('Invalid products data received');
                }
            } catch (error) {
                // Failed to load products
                this.showError('Unable to load pricing information. Please refresh the page and try again.');
            }
        }
        
        renderStaticPlans() {
            const container = this.form.find('.phynite-plans-container');
            const defaultPlan = this.form.find('input[name="planId"]').val() || 'monthly';
            
            if (!container.length) return;
            
            const staticProducts = {
                monthly: {
                    title: 'Monthly Plan',
                    price: 29,
                    interval: 'month',
                    description: 'Perfect for getting started'
                },
                yearly: {
                    title: 'Annual Plan', 
                    price: 290,
                    interval: 'year',
                    description: 'Best value - Save 17%',
                    savings: 58
                }
            };
            
            let html = '';
            
            html += this.createPlanOption('monthly', staticProducts.monthly, defaultPlan === 'monthly');
            html += this.createPlanOption('yearly', staticProducts.yearly, defaultPlan === 'yearly');
            
            container.html(html).attr('data-loading', 'false');
            
            // Disable interactivity for preview
            container.find('input').prop('disabled', true);
        }
        
        renderPlanOptions() {
            const container = this.form.find('.phynite-plans-container');
            const defaultPlan = this.form.find('input[name="planId"]').val() || 'monthly';
            
            if (!this.products) return;
            
            let html = '';
            
            if (this.products.monthly) {
                const monthly = this.products.monthly;
                html += this.createPlanOption('monthly', {
                    title: 'Monthly Plan',
                    price: monthly.amount / 100,
                    interval: 'month',
                    description: 'Perfect for getting started'
                }, defaultPlan === 'monthly');
            }
            
            if (this.products.yearly) {
                const yearly = this.products.yearly;
                const savings = yearly.savings || {};
                html += this.createPlanOption('yearly', {
                    title: 'Annual Plan',
                    price: yearly.amount / 100,
                    interval: 'year',
                    description: `Best value - Save ${savings.percentage || 17}%`,
                    savings: savings.amount ? savings.amount / 100 : null
                }, defaultPlan === 'yearly');
            }
            
            container.html(html).attr('data-loading', 'false');
            this.updateSubmitButton();
        }
        
        createPlanOption(planId, plan, selected) {
            return `
                <label class="phynite-plan-option ${selected ? 'selected' : ''}" for="${this.formId}-plan-${planId}">
                    <input type="radio" 
                           id="${this.formId}-plan-${planId}"
                           name="planId" 
                           value="${planId}" 
                           ${selected ? 'checked' : ''}
                           class="phynite-plan-radio">
                    <div class="phynite-plan-content">
                        <div class="phynite-plan-header">
                            <h3 class="phynite-plan-title">${plan.title}</h3>
                            <div class="phynite-plan-price">
                                <span class="phynite-price-amount">$${plan.price}</span>
                                <span class="phynite-price-interval">/${plan.interval}</span>
                            </div>
                        </div>
                        <p class="phynite-plan-description">${plan.description}</p>
                        <ul class="phynite-plan-features">
                            <li>Full Analytics Access</li>
                            <li>GA4 Integration</li>
                            <li>Pinterest Analytics</li>
                            <li>Email Support</li>
                        </ul>
                        ${plan.savings ? `<div class="phynite-plan-savings">Save $${plan.savings}</div>` : ''}
                    </div>
                    <div class="phynite-plan-checkmark">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20,6 9,17 4,12"></polyline>
                        </svg>
                    </div>
                </label>
            `;
        }
        
        initializeValidation() {
            // Mark required fields
            this.form.find('input[required]').each(function() {
                $(this).attr('aria-required', 'true');
            });
            
            // Initial submit button state
            this.updateSubmitButton();
        }
        
        setupAccessibility() {
            // Add ARIA labels and descriptions
            this.form.find('.phynite-field-error').each(function() {
                const field = $(this).siblings('input');
                const errorId = field.attr('id') + '-error';
                $(this).attr('id', errorId);
                field.attr('aria-describedby', errorId);
            });
        }
        
        showFieldValidationLoading(field) {
            const fieldContainer = field.closest('.phynite-form-field');
            const loadingIndicator = fieldContainer.find('.phynite-field-loading');
            if (loadingIndicator.length) {
                loadingIndicator.show();
                field.addClass('validating');
            }
        }
        
        hideFieldValidationLoading(field) {
            const fieldContainer = field.closest('.phynite-form-field');
            const loadingIndicator = fieldContainer.find('.phynite-field-loading');
            if (loadingIndicator.length) {
                loadingIndicator.hide();
                field.removeClass('validating');
            }
        }
        
        async validateWebsite() {
            const field = this.form.find('input[name="website"]');
            const value = field.val().trim();
            
            // Skip API validation in editor preview
            if (this.isEditorPreview) {
                return true;
            }
            
            // Prevent multiple simultaneous validations
            if (this.websiteValidating) {
                return false;
            }
            
            this.websiteValidating = true;
            this.showFieldValidationLoading(field);
            
            try {
                // Use unified Zod validation with availability checking
                const validationResult = await validateFieldWithAvailability('website', value);
                
                if (!validationResult.success) {
                    this.showFieldError(field, validationResult.error);
                    return false;
                }
                
                this.clearFieldError(field);
                return true;
            } finally {
                this.websiteValidating = false;
                this.hideFieldValidationLoading(field);
            }
        }
        
        validateFirstName() {
            const field = this.form.find('input[name="firstName"]');
            const value = field.val().trim();
            
            // Use Zod validation
            const validationResult = validateField('firstName', value);
            if (!validationResult.success) {
                this.showFieldError(field, validationResult.error);
                return false;
            }
            
            this.clearFieldError(field);
            return true;
        }
        
        validateLastName() {
            const field = this.form.find('input[name="lastName"]');
            const value = field.val().trim();
            
            // Use Zod validation
            const validationResult = validateField('lastName', value);
            if (!validationResult.success) {
                this.showFieldError(field, validationResult.error);
                return false;
            }
            
            this.clearFieldError(field);
            return true;
        }
        
        async validateEmail() {
            const field = this.form.find('input[name="email"]');
            const value = field.val().trim();
            
            // Skip API validation in editor preview
            if (this.isEditorPreview) {
                return true;
            }
            
            // Prevent multiple simultaneous validations
            if (this.emailValidating) {
                return false;
            }
            
            this.emailValidating = true;
            this.showFieldValidationLoading(field);
            
            try {
                // Use unified Zod validation with availability checking
                const validationResult = await validateFieldWithAvailability('email', value);
                
                if (!validationResult.success) {
                    this.showFieldError(field, validationResult.error);
                    return false;
                }
                
                this.clearFieldError(field);
                
                // Track email field completion
                if (this.tracker) {
                    this.tracker.trackFieldComplete('email', {
                        domain: this.extractDomain(value)
                    });
                }
                
                return true;
            } finally {
                this.emailValidating = false;
                this.hideFieldValidationLoading(field);
            }
        }
        
        extractDomain(email) {
            if (!email || typeof email !== 'string') return null;
            const match = email.match(/@([^@]+)$/);
            return match ? match[1].toLowerCase() : null;
        }
        
        validateForm() {
            // Get form data
            const formData = {
                website: this.form.find('input[name="website"]').val().trim(),
                firstName: this.form.find('input[name="firstName"]').val().trim(),
                lastName: this.form.find('input[name="lastName"]').val().trim(),
                email: this.form.find('input[name="email"]').val().trim(),
                planId: this.form.find('input[name="planId"]:checked').val() || this.form.find('input[name="planId"][type="hidden"]').val(),
                acceptTerms: this.form.find('input[name="acceptTerms"]').is(':checked'),
                website_confirm: this.form.find('input[name="website_confirm"]').val()
            };
            
            // Use Zod validation for entire form
            const validationResult = validateFormData(formData);
            
            if (!validationResult.success) {
                // Show field-specific errors
                Object.keys(validationResult.errors).forEach(fieldName => {
                    const field = this.form.find(`input[name="${fieldName}"]`);
                    if (field.length) {
                        this.showFieldError(field, validationResult.errors[fieldName]);
                    }
                });
                return false;
            }
            
            // Clear any existing errors
            this.form.find('.phynite-field-error').empty();
            return true;
        }
        
        async handleSubmit() {
            // Prevent submission in editor preview
            if (this.isEditorPreview) {
                return false;
            }
            
            this.clearMessages();
            
            if (!this.validateForm()) {
                this.showError('Please correct the errors above and try again.');
                return;
            }
            
            // Check for bot behavior
            const formSubmitTime = Date.now() - this.formStartTime;
            if (formSubmitTime < 2000) {
                this.showError('Please take a moment to review your information.');
                return;
            }
            
            // Track form submission
            const formData = this.getFormData();
            if (this.tracker) {
                this.tracker.trackFormSubmit({
                    planId: formData.planId,
                    email: formData.email,
                    completionTime: Date.now() - this.formViewTime
                });
            }
            
            this.setLoading(true);
            
            try {
                const response = await fetch(`${phyniteSignupForm.apiUrl}create-checkout`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': phyniteSignupForm.nonce
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success && result.sessionId) {
                    // Track successful checkout start (key conversion event)
                    if (this.tracker) {
                        const planData = this.getPlanData(formData.planId);
                        this.tracker.trackCheckoutStart({
                            planId: formData.planId,
                            planPrice: planData ? planData.price : null,
                            email: formData.email,
                            website: formData.website
                        });
                    }
                    
                    // Use Stripe SDK for proper checkout redirection
                    const stripe = Stripe(phyniteSignupForm.stripePublishableKey);
                    const { error } = await stripe.redirectToCheckout({
                        sessionId: result.sessionId
                    });
                    
                    if (error) {
                        throw new Error(error.message || 'Stripe redirection failed');
                    }
                } else {
                    throw new Error(result.message || 'Failed to create checkout session');
                }
                
            } catch (error) {
                // Track form error
                if (this.tracker) {
                    this.tracker.trackFormError({
                        type: 'submission_error',
                        message: error.message || 'Unknown error'
                    });
                }
                
                // Form submission error
                this.showError(error.message || 'An unexpected error occurred. Please try again.');
                this.setLoading(false);
            }
        }
        
        getFormData() {
            return {
                website: this.form.find('input[name="website"]').val().trim(),
                firstName: this.form.find('input[name="firstName"]').val().trim(),
                lastName: this.form.find('input[name="lastName"]').val().trim(),
                email: this.form.find('input[name="email"]').val().trim(),
                planId: this.form.find('input[name="planId"]:checked').val() || this.form.find('input[name="planId"][type="hidden"]').val(),
                acceptTerms: this.form.find('input[name="acceptTerms"]').is(':checked'),
                website_confirm: this.form.find('input[name="website_confirm"]').val(),
                nonce: this.form.data('nonce'),
                formTiming: Date.now() - this.formStartTime
            };
        }
        
        updateSubmitButton() {
            const website = this.form.find('input[name="website"]').val();
            const firstName = this.form.find('input[name="firstName"]').val();
            const lastName = this.form.find('input[name="lastName"]').val();
            const email = this.form.find('input[name="email"]').val();
            const acceptTerms = this.form.find('input[name="acceptTerms"]').is(':checked');
            const planSelected = this.form.find('input[name="planId"]:checked').length > 0 || this.form.find('input[name="planId"][type="hidden"]').length > 0;
            // Check for errors by looking at non-empty error elements instead of :visible
            const hasErrors = this.form.find('.phynite-field-error').filter(function() {
                return $(this).text().trim() !== '';
            }).length > 0;
            
            const isValid = website && firstName && lastName && email && acceptTerms && planSelected && !hasErrors;
            
            this.submitButton.prop('disabled', !isValid);
        }
        
        setLoading(loading) {
            if (loading) {
                this.submitButton.addClass('loading').prop('disabled', true);
                this.submitButton.find('.phynite-submit-text').hide();
                this.submitButton.find('.phynite-submit-loading').show();
            } else {
                this.submitButton.removeClass('loading').prop('disabled', false);
                this.submitButton.find('.phynite-submit-text').show();
                this.submitButton.find('.phynite-submit-loading').hide();
                this.updateSubmitButton();
            }
        }
        
        showFieldError(field, message) {
            const errorElement = field.closest('.phynite-form-field').find('.phynite-field-error');
            errorElement.text(message);
            field.addClass('error').attr('aria-invalid', 'true');
            this.updateSubmitButton();
        }
        
        clearFieldError(field) {
            const errorElement = field.closest('.phynite-form-field').find('.phynite-field-error');
            errorElement.empty();
            field.removeClass('error').attr('aria-invalid', 'false');
            this.updateSubmitButton();
        }
        
        showError(message) {
            this.messages.html(`<div class="phynite-error">${message}</div>`);
            this.messages[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        showSuccess(message) {
            this.messages.html(`<div class="phynite-success">${message}</div>`);
        }
        
        clearMessages() {
            this.messages.empty();
        }
    }
    
    // Initialize forms when DOM is ready
    $(document).ready(function() {
        $('.phynite-signup-form').each(function() {
            new PhyniteSignupForm(this);
        });
    });
    
    // Handle dynamic content loading
    $(document).on('DOMNodeInserted', function(e) {
        $(e.target).find('.phynite-signup-form').each(function() {
            if (!$(this).data('phynite-initialized')) {
                $(this).data('phynite-initialized', true);
                new PhyniteSignupForm(this);
            }
        });
    });
    
})(jQuery);