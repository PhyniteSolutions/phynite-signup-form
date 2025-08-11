/**
 * Admin JavaScript for Phynite Signup Form
 */

(function($) {
    'use strict';
    
    /**
     * Admin functionality
     */
    const PhyniteAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initializeColorPickers();
            this.checkApiStatus();
        },
        
        bindEvents: function() {
            // Password visibility toggle
            $('.password-toggle-btn').on('click', this.togglePasswordVisibility);
            
            // Form validation
            $('form').on('submit', this.validateForm);
            
            // API connection test
            $('.phynite-test-connection').on('click', this.testConnection);
            
            // Environment change handler
            $('select[name*="environment"]').on('change', this.updateEnvironmentBadge);
            
            // Settings save confirmation
            $('#submit').on('click', this.showSaveConfirmation);
            
            // Tabs functionality (if implemented)
            $('.phynite-tab').on('click', this.switchTab);
            
            // Help toggles
            $('.phynite-help-toggle').on('click', this.toggleHelp);
        },
        
        /**
         * Toggle password field visibility
         */
        togglePasswordVisibility: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const input = button.siblings('input');
            const currentType = input.attr('type');
            
            if (currentType === 'password') {
                input.attr('type', 'text');
                button.text(phyniteAdmin.hide);
            } else {
                input.attr('type', 'password');
                button.text(phyniteAdmin.show);
            }
        },
        
        /**
         * Initialize color pickers
         */
        initializeColorPickers: function() {
            if (typeof wp !== 'undefined' && wp.colorPicker) {
                $('input[type="color"]').wpColorPicker({
                    change: function(event, ui) {
                        const element = event.target;
                        const color = ui.color.toString();
                        $(element).val(color).trigger('change');
                    }
                });
            }
        },
        
        /**
         * Validate admin form
         */
        validateForm: function(e) {
            let isValid = true;
            const errors = [];
            
            // Validate API key
            const apiKey = $('input[name*="api_key"]').val();
            if (apiKey && !apiKey.startsWith('phyn_')) {
                errors.push('API key should start with "phyn_"');
                isValid = false;
            }
            
            // Validate Stewie URL
            const stewieUrl = $('input[name*="stewie_url"]').val();
            if (stewieUrl && !stewieUrl.match(/^https?:\/\/.+/)) {
                errors.push('Stewie URL must be a valid HTTP/HTTPS URL');
                isValid = false;
            } else if (stewieUrl && stewieUrl.startsWith('http://') && !stewieUrl.includes('localhost')) {
                errors.push('HTTP URLs are only allowed for localhost development. Use HTTPS for production.');
                isValid = false;
            }
            
            // Validate rate limit
            const rateLimit = $('input[name*="rate_limit"]').val();
            if (rateLimit && (isNaN(rateLimit) || rateLimit < 1 || rateLimit > 60)) {
                errors.push('Rate limit must be between 1 and 60');
                isValid = false;
            }
            
            // Show errors if any
            if (!isValid) {
                e.preventDefault();
                PhyniteAdmin.showErrors(errors);
            }
            
            return isValid;
        },
        
        /**
         * Test API connection
         */
        testConnection: function(e) {
            e.preventDefault();
            
            const button = $(this);
            const originalText = button.text();
            const statusContainer = $('.phynite-connection-status');
            
            // Update button state
            button.prop('disabled', true)
                .text('Testing...')
                .addClass('phynite-loading');
            
            // Show loading status
            statusContainer.html('<div class="phynite-status-indicator checking">Checking API connection...</div>');
            
            // Make AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'phynite_test_connection',
                    nonce: phyniteAdmin.nonce
                },
                timeout: 10000,
                success: function(response) {
                    if (response.success) {
                        statusContainer.html('<div class="phynite-status-indicator connected">API connection successful!</div>');
                        PhyniteAdmin.showSuccess('API connection test successful');
                    } else {
                        statusContainer.html('<div class="phynite-status-indicator error">Connection failed: ' + response.data.message + '</div>');
                        PhyniteAdmin.showError('API connection failed: ' + response.data.message);
                    }
                },
                error: function(xhr, status) {
                    let errorMessage = 'Connection test failed';
                    
                    if (status === 'timeout') {
                        errorMessage = 'Connection test timed out';
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    
                    statusContainer.html('<div class="phynite-status-indicator error">' + errorMessage + '</div>');
                    PhyniteAdmin.showError(errorMessage);
                },
                complete: function() {
                    button.prop('disabled', false)
                        .text(originalText)
                        .removeClass('phynite-loading');
                }
            });
        },
        
        /**
         * Check API status on page load
         */
        checkApiStatus: function() {
            const apiKey = $('input[name*="api_key"]').val();
            const stewieUrl = $('input[name*="stewie_url"]').val();
            
            if (!apiKey || !stewieUrl) {
                $('.phynite-api-status').html('<div class="phynite-status-indicator error">API not configured</div>');
                return;
            }
            
            $('.phynite-api-status').html('<div class="phynite-status-indicator checking">Checking API status...</div>');
            
            // Auto-test connection if credentials are present
            setTimeout(function() {
                $('.phynite-test-connection').trigger('click');
            }, 1000);
        },
        
        /**
         * Update environment badge and suggest API URL
         */
        updateEnvironmentBadge: function() {
            const environment = $(this).val();
            const badge = $('.phynite-environment-badge');
            const stewieUrlField = $('input[name*="stewie_url"]');
            
            badge.removeClass('production staging development')
                .addClass(environment)
                .text(environment.toUpperCase());
            
            // Suggest appropriate API URL based on environment
            if (stewieUrlField.length && confirm('Would you like to set the recommended API URL for this environment?')) {
                let recommendedUrl = '';
                
                switch (environment) {
                case 'production':
                    recommendedUrl = 'https://api.phynitesolutions.com';
                    break;
                case 'development':
                    recommendedUrl = 'http://localhost:4000';
                    break;
                case 'staging':
                    // You can add staging URL here if you have one
                    recommendedUrl = 'https://api.phynitesolutions.com';
                    break;
                }
                
                if (recommendedUrl) {
                    stewieUrlField.val(recommendedUrl);
                    PhyniteAdmin.showSuccess('API URL updated to: ' + recommendedUrl);
                }
            }
        },
        
        /**
         * Show save confirmation
         */
        showSaveConfirmation: function(e) {
            const hasChanges = PhyniteAdmin.detectChanges();
            
            if (hasChanges) {
                const confirmed = confirm('Are you sure you want to save these settings?');
                if (!confirmed) {
                    e.preventDefault();
                    return false;
                }
            }
        },
        
        /**
         * Switch tabs
         */
        switchTab: function(e) {
            e.preventDefault();
            
            const tab = $(this);
            const targetId = tab.attr('href');
            
            // Update tab states
            $('.phynite-tab').removeClass('active');
            tab.addClass('active');
            
            // Update content
            $('.phynite-tab-content').hide();
            $(targetId).show();
            
            // Save active tab
            localStorage.setItem('phynite-active-tab', targetId);
        },
        
        /**
         * Toggle help sections
         */
        toggleHelp: function(e) {
            e.preventDefault();
            
            const toggle = $(this);
            const helpContent = toggle.next('.phynite-help-content');
            
            helpContent.slideToggle(200);
            toggle.toggleClass('expanded');
        },
        
        /**
         * Detect form changes
         */
        detectChanges: function() {
            const form = $('form');
            const currentData = form.serialize();
            const originalData = form.data('original-data') || currentData;
            
            form.data('original-data', originalData);
            
            return currentData !== originalData;
        },
        
        /**
         * Show success message
         */
        showSuccess: function(message) {
            const notice = $('<div class="notice notice-success is-dismissible phynite-notice"><p>' + message + '</p></div>');
            $('.wrap h1').after(notice);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            const notice = $('<div class="notice notice-error is-dismissible phynite-notice"><p>' + message + '</p></div>');
            $('.wrap h1').after(notice);
        },
        
        /**
         * Show multiple errors
         */
        showErrors: function(errors) {
            const errorList = errors.map(error => '<li>' + error + '</li>').join('');
            const notice = $('<div class="notice notice-error is-dismissible phynite-notice"><ul>' + errorList + '</ul></div>');
            $('.wrap h1').after(notice);
        },
        
        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function() {
                    PhyniteAdmin.showSuccess('Copied to clipboard');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'absolute';
                textArea.style.left = '-999999px';
                
                document.body.prepend(textArea);
                textArea.select();
                
                try {
                    document.execCommand('copy');
                    PhyniteAdmin.showSuccess('Copied to clipboard');
                } catch (error) {
                    PhyniteAdmin.showError('Failed to copy to clipboard');
                } finally {
                    textArea.remove();
                }
            }
        },
        
        /**
         * Format code examples
         */
        formatCodeExamples: function() {
            $('.phynite-code').each(function() {
                const code = $(this);
                const text = code.text().trim();
                
                // Add copy button
                const copyBtn = $('<button class="phynite-copy-btn" type="button">Copy</button>');
                code.append(copyBtn);
                
                copyBtn.on('click', function(e) {
                    e.preventDefault();
                    PhyniteAdmin.copyToClipboard(text);
                });
            });
        },
        
        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            if ($.fn.tooltip) {
                $('[data-tooltip]').tooltip({
                    position: { my: 'center bottom-20', at: 'center top' },
                    content: function() {
                        return $(this).attr('data-tooltip');
                    }
                });
            }
        },
        
        /**
         * Auto-save draft settings
         */
        autoSaveDraft: function() {
            const form = $('form');
            let autoSaveTimer;
            
            form.find('input, select, textarea').on('change input', function() {
                clearTimeout(autoSaveTimer);
                
                autoSaveTimer = setTimeout(function() {
                    const formData = form.serialize();
                    localStorage.setItem('phynite-draft-settings', formData);
                    
                    // Show draft saved indicator
                    const indicator = $('.phynite-draft-indicator');
                    indicator.text('Draft saved').fadeIn(200);
                    
                    setTimeout(function() {
                        indicator.fadeOut(200);
                    }, 2000);
                }, 2000);
            });
        },
        
        /**
         * Load draft settings
         */
        loadDraftSettings: function() {
            const draftData = localStorage.getItem('phynite-draft-settings');
            
            if (draftData) {
                const params = new URLSearchParams(draftData);
                
                params.forEach((value, name) => {
                    const field = $('[name="' + name + '"]');
                    
                    if (field.is(':checkbox') || field.is(':radio')) {
                        field.filter('[value="' + value + '"]').prop('checked', true);
                    } else {
                        field.val(value);
                    }
                });
                
                PhyniteAdmin.showSuccess('Draft settings loaded');
            }
        }
    };
    
    /**
     * Global utility functions
     */
    window.togglePasswordVisibility = function(fieldId) {
        const field = $('#' + fieldId);
        const currentType = field.attr('type');
        
        if (currentType === 'password') {
            field.attr('type', 'text');
        } else {
            field.attr('type', 'password');
        }
    };
    
    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        PhyniteAdmin.init();
        PhyniteAdmin.formatCodeExamples();
        PhyniteAdmin.initTooltips();
        PhyniteAdmin.autoSaveDraft();
        
        // Load draft settings if requested
        if (localStorage.getItem('phynite-load-draft') === 'true') {
            PhyniteAdmin.loadDraftSettings();
            localStorage.removeItem('phynite-load-draft');
        }
        
        // Restore active tab
        const activeTab = localStorage.getItem('phynite-active-tab');
        if (activeTab) {
            $('.phynite-tab[href="' + activeTab + '"]').trigger('click');
        }
        
        // Handle dismiss buttons for notices
        $(document).on('click', '.notice-dismiss', function() {
            $(this).closest('.notice').fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                $('#submit').trigger('click');
            }
            
            // Ctrl/Cmd + T to test connection
            if ((e.ctrlKey || e.metaKey) && e.key === 't') {
                e.preventDefault();
                $('.phynite-test-connection').trigger('click');
            }
        });
    });
    
})(jQuery);

/**
 * WordPress specific integration
 */
if (typeof wp !== 'undefined' && wp.hooks) {
    // Add hooks for other plugins to extend functionality
    wp.hooks.addAction('phynite.admin.init', 'phynite-signup-form', function() {
        // Phynite Admin initialized
    });
    
    wp.hooks.addFilter('phynite.admin.validate', 'phynite-signup-form', function(isValid) {
        // Allow other plugins to add validation
        return isValid;
    });
}