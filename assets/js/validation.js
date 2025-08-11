/**
 * Zod Validation Schemas for Phynite Signup Form
 * Matches Stewie's CheckoutSchema for consistency
 */

import { z } from 'zod';

/**
 * List of disposable email domains to block
 * Matches the list from Stewie's validation
 */
const disposableEmailDomains = [
    'tempmail.org',
    '10minutemail.com', 
    'guerrillamail.com',
    'mailinator.com',
    'yopmail.com',
    'temp-mail.org',
    'throwaway.email',
    'maildrop.cc',
    'tempmail.net',
    'dispostable.com'
];

/**
 * Enhanced validation schema for signup form
 * Matches Stewie's CheckoutSchema exactly
 */
export const SignupFormSchema = z.object({
    website: z
        .string()
        .min(1, 'Website URL is required')
        .url('Please enter a valid URL')
        .refine(
            (url) => {
                try {
                    const urlObj = new URL(url);
                    // Allow root domain and common subdomains
                    if (urlObj.pathname !== '/' && urlObj.pathname !== '') return false;
                    if (urlObj.search !== '') return false;
                    if (urlObj.hash !== '') return false;
                    if (!['http:', 'https:'].includes(urlObj.protocol)) return false;
                    // Basic domain validation
                    if (urlObj.hostname.length < 4) return false;
                    if (!urlObj.hostname.includes('.')) return false;
                    return true;
                } catch (e) {
                    return false;
                }
            },
            {
                message: 'Please enter a valid domain URL without paths or query parameters',
            },
        ),
    firstName: z
        .string()
        .min(1, 'First name is required')
        .max(64, 'First name must be less than 64 characters')
        .trim()
        .refine(
            (name) => {
                // Basic name validation - no special characters except spaces, hyphens, apostrophes
                return /^[a-zA-Z\s\-']+$/.test(name);
            },
            {
                message: 'First name can only contain letters, spaces, hyphens, and apostrophes',
            },
        ),
    lastName: z
        .string()
        .min(1, 'Last name is required')
        .max(128, 'Last name must be less than 128 characters')
        .trim()
        .refine(
            (name) => {
                // Basic name validation - no special characters except spaces, hyphens, apostrophes
                return /^[a-zA-Z\s\-']+$/.test(name);
            },
            {
                message: 'Last name can only contain letters, spaces, hyphens, and apostrophes',
            },
        ),
    email: z
        .string()
        .min(1, 'Email is required')
        .email('Please enter a valid email address')
        .max(255, 'Email must be less than 255 characters')
        .toLowerCase()
        .refine(
            (email) => {
                // Basic disposable email detection
                const emailParts = email.split('@');
                const domain = emailParts.length > 1 ? emailParts[1] : '';
                return !disposableEmailDomains.includes(domain);
            },
            {
                message: 'Please use a permanent email address',
            },
        ),
    planId: z.enum(['monthly', 'yearly'], {
        errorMap: () => ({ message: 'Please select a valid plan' }),
    }),
    acceptTerms: z.boolean().refine((val) => val === true, {
        message: 'You must accept the Terms of Service to continue',
    }),
    // Honeypot field - should be empty for legitimate users
    website_confirm: z
        .string()
        .optional()
        .refine((val) => !val || val === '', {
            message: 'Bot detected',
        }),
});

/**
 * Validation schema for individual fields (for real-time validation)
 */
export const FieldSchemas = {
    website: SignupFormSchema.shape.website,
    firstName: SignupFormSchema.shape.firstName,
    lastName: SignupFormSchema.shape.lastName,
    email: SignupFormSchema.shape.email,
    planId: SignupFormSchema.shape.planId,
    acceptTerms: SignupFormSchema.shape.acceptTerms,
    website_confirm: SignupFormSchema.shape.website_confirm,
};

/**
 * Validate individual field
 * @param {string} fieldName - Name of the field to validate
 * @param {any} value - Value to validate
 * @returns {Object} - Validation result with success and error
 */
export function validateField(fieldName, value) {
    const schema = FieldSchemas[fieldName];
    if (!schema) {
        return { success: false, error: 'Unknown field' };
    }

    try {
        schema.parse(value);
        return { success: true };
    } catch (error) {
        if (error.errors && error.errors.length > 0) {
            return { success: false, error: error.errors[0].message };
        }
        return { success: false, error: 'Validation failed' };
    }
}

/**
 * Validate field with async availability checking
 * @param {string} fieldName - Name of the field to validate 
 * @param {any} value - Value to validate
 * @returns {Promise<Object>} - Validation result with success and error
 */
export async function validateFieldWithAvailability(fieldName, value) {
    // First run basic Zod validation
    const basicValidation = validateField(fieldName, value);
    if (!basicValidation.success) {
        return basicValidation;
    }

    // Then check availability for email and website fields
    if (fieldName === 'email' && value && typeof value === 'string') {
        try {
            const response = await fetch(`${window.phyniteSignupForm.apiUrl}check-email`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.phyniteSignupForm.nonce
                },
                body: JSON.stringify({ email: value.trim() })
            });
            
            const result = await response.json();
            if (result.exists) {
                return { 
                    success: false, 
                    error: 'This email is already registered. Please use a different email or sign in instead.' 
                };
            }
        } catch (error) {
            // If availability check fails, don't block the user - just log and continue
            // Email availability check failed
        }
    }

    if (fieldName === 'website' && value && typeof value === 'string') {
        try {
            const response = await fetch(`${window.phyniteSignupForm.apiUrl}check-website`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.phyniteSignupForm.nonce
                },
                body: JSON.stringify({ website: value.trim() })
            });
            
            const result = await response.json();
            if (result.exists) {
                return { 
                    success: false, 
                    error: 'This website is already registered. Please use a different website or sign in instead.' 
                };
            }
        } catch (error) {
            // If availability check fails, don't block the user - just log and continue
            // Website availability check failed
        }
    }

    return { success: true };
}

/**
 * Validate entire form data
 * @param {Object} formData - Form data to validate
 * @returns {Object} - Validation result with success, data, and errors
 */
export function validateFormData(formData) {
    try {
        const validatedData = SignupFormSchema.parse(formData);
        return { success: true, data: validatedData };
    } catch (error) {
        const errors = {};
        if (error.errors) {
            error.errors.forEach((err) => {
                const field = err.path[0];
                if (field) {
                    errors[field] = err.message;
                }
            });
        }
        return { success: false, errors };
    }
}

// Form data type is inferred from the Zod schema