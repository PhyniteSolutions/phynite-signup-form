/**
 * Gutenberg Block for Phynite Signup Form
 */

(function(wp) {
    const { blocks, element, components, blockEditor, apiFetch, i18n, serverSideRender } = wp;
    const { registerBlockType } = blocks;
    const { createElement: el, Fragment, useState, useEffect } = element;
    const { 
        PanelBody, 
        TextControl, 
        ToggleControl, 
        SelectControl, 
        ColorPicker, 
        RangeControl,
        Notice,
        Button,
        Spinner
    } = components;
    const { InspectorControls, BlockControls } = blockEditor;
    const { __ } = i18n;
    const ServerSideRender = serverSideRender || wp.components.ServerSideRender;
    
    registerBlockType('phynite/signup-form', {
        title: __('Phynite Signup Form', 'phynite-signup-form'),
        description: __('Add a Phynite Analytics signup form to your page', 'phynite-signup-form'),
        icon: 'chart-line',
        category: 'widgets',
        keywords: [
            __('phynite', 'phynite-signup-form'),
            __('analytics', 'phynite-signup-form'),
            __('signup', 'phynite-signup-form'),
            __('form', 'phynite-signup-form')
        ],
        supports: {
            align: ['left', 'center', 'right', 'wide', 'full'],
            className: true,
            customClassName: true,
        },
        attributes: {
            title: {
                type: 'string',
                default: __('Get Started with Phynite Analytics', 'phynite-signup-form')
            },
            subtitle: {
                type: 'string',
                default: __('Enter your details below to begin tracking your analytics.', 'phynite-signup-form')
            },
            showTitle: {
                type: 'boolean',
                default: true
            },
            showSubtitle: {
                type: 'boolean',
                default: true
            },
            showLogo: {
                type: 'boolean',
                default: false
            },
            formStyle: {
                type: 'string',
                default: 'default'
            },
            primaryColor: {
                type: 'string',
                default: '#007cba'
            },
            buttonText: {
                type: 'string',
                default: __('Continue to Payment', 'phynite-signup-form')
            },
            showPlanSelection: {
                type: 'boolean',
                default: true
            },
            defaultPlan: {
                type: 'string',
                default: 'monthly'
            },
            showTermsLinks: {
                type: 'boolean',
                default: true
            },
            termsUrl: {
                type: 'string',
                default: '/terms-of-service'
            },
            privacyUrl: {
                type: 'string',
                default: '/privacy-policy'
            },
            alignment: {
                type: 'string',
                default: 'center'
            },
            maxWidth: {
                type: 'number',
                default: 600
            },
            className: {
                type: 'string',
                default: ''
            }
        },
        
        edit: function(props) {
            const { attributes, setAttributes, className } = props;
            const [apiStatus, setApiStatus] = useState('checking');
            const [products, setProducts] = useState(null);
            const [testingConnection, setTestingConnection] = useState(false);
            
            // Check API status on load
            useEffect(() => {
                checkApiStatus();
                loadProducts();
            }, []);
            
            const checkApiStatus = async () => {
                try {
                    const response = await apiFetch({
                        path: '/phynite-signup/v1/test-connection',
                        method: 'GET'
                    });
                    
                    setApiStatus(response && response.success ? 'connected' : 'error');
                } catch (error) {
                    console.error('API status check failed:', error);
                    setApiStatus('error');
                }
            };
            
            const loadProducts = async () => {
                try {
                    const response = await apiFetch({
                        path: '/phynite-signup/v1/get-products',
                        method: 'GET'
                    });
                    
                    setProducts(response || null);
                } catch (error) {
                    console.error('Failed to load products:', error);
                    setProducts(null);
                }
            };
            
            const testConnection = async () => {
                setTestingConnection(true);
                try {
                    await checkApiStatus();
                    await loadProducts();
                } catch (error) {
                    console.error('Connection test failed:', error);
                } finally {
                    setTestingConnection(false);
                }
            };
            
            const formStyleOptions = [
                { label: __('Default', 'phynite-signup-form'), value: 'default' },
                { label: __('Minimal', 'phynite-signup-form'), value: 'minimal' },
                { label: __('Modern', 'phynite-signup-form'), value: 'modern' },
                { label: __('Compact', 'phynite-signup-form'), value: 'compact' },
                { label: __('Card', 'phynite-signup-form'), value: 'card' }
            ];
            
            const getStyleDescription = (style) => {
                const descriptions = {
                    'default': __('Balanced design with standard spacing and borders', 'phynite-signup-form'),
                    'minimal': __('Clean and simple with underlines and lowercase typography', 'phynite-signup-form'),
                    'modern': __('Contemporary style with gradients and elevated shadows', 'phynite-signup-form'),
                    'compact': __('Space-saving design with smaller elements and tight spacing', 'phynite-signup-form'),
                    'card': __('Professional card layout with distinct sections and backgrounds', 'phynite-signup-form')
                };
                return descriptions[style] || '';
            };
            
            const alignmentOptions = [
                { label: __('Left', 'phynite-signup-form'), value: 'left' },
                { label: __('Center', 'phynite-signup-form'), value: 'center' },
                { label: __('Right', 'phynite-signup-form'), value: 'right' }
            ];
            
            const defaultPlanOptions = [
                { label: __('Monthly', 'phynite-signup-form'), value: 'monthly' },
                { label: __('Yearly', 'phynite-signup-form'), value: 'yearly' }
            ];
            
            return el(Fragment, {},
                // Inspector Controls (Sidebar)
                el(InspectorControls, {},
                    // API Status Panel
                    el(PanelBody, {
                        title: __('API Configuration', 'phynite-signup-form'),
                        initialOpen: apiStatus === 'error'
                    },
                    apiStatus === 'checking' && el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
                        el(Spinner),
                        el('span', {}, __('Checking API connection...', 'phynite-signup-form'))
                    ),
                        
                    apiStatus === 'connected' && el(Notice, {
                        status: 'success',
                        isDismissible: false
                    }, __('API connection successful', 'phynite-signup-form')),
                        
                    apiStatus === 'error' && el(Fragment, {},
                        el(Notice, {
                            status: 'error',
                            isDismissible: false
                        }, __('API not configured or connection failed. Please check the plugin settings.', 'phynite-signup-form')),
                            
                        el(Button, {
                            isPrimary: true,
                            href: '/wp-admin/options-general.php?page=phynite-signup-form',
                            target: '_blank',
                            style: { marginTop: '12px' }
                        }, __('Open Settings', 'phynite-signup-form'))
                    ),
                        
                    el(Button, {
                        isSecondary: true,
                        isBusy: testingConnection,
                        disabled: testingConnection,
                        onClick: testConnection,
                        style: { marginTop: '12px' }
                    }, __('Test Connection', 'phynite-signup-form'))
                    ),
                    
                    // Content Panel
                    el(PanelBody, {
                        title: __('Form Content', 'phynite-signup-form'),
                        initialOpen: true
                    },
                    el(ToggleControl, {
                        label: __('Show Logo', 'phynite-signup-form'),
                        checked: attributes.showLogo,
                        onChange: (value) => setAttributes({ showLogo: value })
                    }),
                        
                    el(ToggleControl, {
                        label: __('Show Title', 'phynite-signup-form'),
                        checked: attributes.showTitle,
                        onChange: (value) => setAttributes({ showTitle: value })
                    }),
                        
                    attributes.showTitle && el(TextControl, {
                        label: __('Form Title', 'phynite-signup-form'),
                        value: attributes.title,
                        onChange: (value) => setAttributes({ title: value })
                    }),
                        
                    el(ToggleControl, {
                        label: __('Show Subtitle', 'phynite-signup-form'),
                        checked: attributes.showSubtitle,
                        onChange: (value) => setAttributes({ showSubtitle: value })
                    }),
                        
                    attributes.showSubtitle && el(TextControl, {
                        label: __('Form Subtitle', 'phynite-signup-form'),
                        value: attributes.subtitle,
                        onChange: (value) => setAttributes({ subtitle: value })
                    }),
                        
                    el(TextControl, {
                        label: __('Button Text', 'phynite-signup-form'),
                        value: attributes.buttonText,
                        onChange: (value) => setAttributes({ buttonText: value })
                    })
                    ),
                    
                    // Pricing Panel
                    el(PanelBody, {
                        title: __('Pricing Options', 'phynite-signup-form'),
                        initialOpen: false
                    },
                    products && el('div', { style: { marginBottom: '16px', fontSize: '13px', color: '#757575' } },
                        products.monthly && products.monthly.amount ? `Monthly: $${products.monthly.amount / 100}` : '', 
                        products.monthly && products.yearly && products.monthly.amount && products.yearly.amount ? ' | ' : '',
                        products.yearly && products.yearly.amount ? `Yearly: $${products.yearly.amount / 100}` : ''
                    ),
                        
                    el(ToggleControl, {
                        label: __('Show Plan Selection', 'phynite-signup-form'),
                        help: __('Allow users to choose between monthly and yearly plans', 'phynite-signup-form'),
                        checked: attributes.showPlanSelection,
                        onChange: (value) => setAttributes({ showPlanSelection: value })
                    }),
                        
                    !attributes.showPlanSelection && el(SelectControl, {
                        label: __('Default Plan', 'phynite-signup-form'),
                        value: attributes.defaultPlan,
                        options: defaultPlanOptions,
                        onChange: (value) => setAttributes({ defaultPlan: value })
                    })
                    ),
                    
                    // Style Panel
                    el(PanelBody, {
                        title: __('Appearance', 'phynite-signup-form'),
                        initialOpen: false
                    },
                    el(SelectControl, {
                        label: __('Form Style', 'phynite-signup-form'),
                        value: attributes.formStyle,
                        options: formStyleOptions,
                        onChange: (value) => setAttributes({ formStyle: value }),
                        help: getStyleDescription(attributes.formStyle)
                    }),
                        
                    el(SelectControl, {
                        label: __('Alignment', 'phynite-signup-form'),
                        value: attributes.alignment,
                        options: alignmentOptions,
                        onChange: (value) => setAttributes({ alignment: value })
                    }),
                        
                    el(RangeControl, {
                        label: __('Maximum Width (px)', 'phynite-signup-form'),
                        value: attributes.maxWidth,
                        onChange: (value) => setAttributes({ maxWidth: value }),
                        min: 300,
                        max: 1200,
                        step: 50
                    }),
                        
                    el('div', { style: { marginBottom: '16px' } },
                        el('label', { style: { fontWeight: '600', marginBottom: '8px', display: 'block' } }, 
                            __('Primary Color', 'phynite-signup-form')
                        ),
                        el(ColorPicker, {
                            color: attributes.primaryColor,
                            onChange: (value) => setAttributes({ primaryColor: value })
                        })
                    )
                    ),
                    
                    // Legal Panel
                    el(PanelBody, {
                        title: __('Terms & Privacy', 'phynite-signup-form'),
                        initialOpen: false
                    },
                    el(ToggleControl, {
                        label: __('Show Terms & Privacy Links', 'phynite-signup-form'),
                        checked: attributes.showTermsLinks,
                        onChange: (value) => setAttributes({ showTermsLinks: value })
                    }),
                        
                    attributes.showTermsLinks && el(Fragment, {},
                        el(TextControl, {
                            label: __('Terms of Service URL', 'phynite-signup-form'),
                            value: attributes.termsUrl,
                            onChange: (value) => setAttributes({ termsUrl: value }),
                            help: __('Relative or absolute URL to your terms page', 'phynite-signup-form')
                        }),
                            
                        el(TextControl, {
                            label: __('Privacy Policy URL', 'phynite-signup-form'),
                            value: attributes.privacyUrl,
                            onChange: (value) => setAttributes({ privacyUrl: value }),
                            help: __('Relative or absolute URL to your privacy policy page', 'phynite-signup-form')
                        })
                    )
                    )
                ),
                
                // Block Content
                el('div', { 
                    className: `phynite-signup-form-block-wrapper ${className || ''}`,
                    style: { 
                        textAlign: attributes.alignment,
                        '--primary-color': attributes.primaryColor
                    }
                },
                apiStatus === 'error' ? 
                    el('div', {
                        className: 'phynite-signup-form-placeholder',
                        style: {
                            padding: '40px',
                            border: '2px dashed #ddd',
                            borderRadius: '8px',
                            textAlign: 'center',
                            backgroundColor: '#f9f9f9'
                        }
                    },
                    el('div', {
                        style: {
                            fontSize: '16px',
                            marginBottom: '12px',
                            color: '#d63638'
                        }
                    }, '⚠️ ' + __('Phynite Signup Form', 'phynite-signup-form')),
                            
                    el('p', {
                        style: {
                            margin: '0 0 16px',
                            color: '#757575'
                        }
                    }, __('API configuration required. Please configure your Stewie API key in the plugin settings.', 'phynite-signup-form')),
                            
                    el(Button, {
                        isPrimary: true,
                        href: '/wp-admin/options-general.php?page=phynite-signup-form',
                        target: '_blank'
                    }, __('Configure Settings', 'phynite-signup-form'))
                    )
                    :
                    el('div', {
                        className: 'phynite-signup-form-preview-container',
                        style: {
                            border: '1px solid #ddd',
                            borderRadius: '4px',
                            overflow: 'hidden'
                        }
                    },
                    el('div', {
                        style: {
                            padding: '10px',
                            backgroundColor: '#f0f0f1',
                            borderBottom: '1px solid #ddd',
                            fontSize: '12px',
                            color: '#666',
                            textAlign: 'center'
                        }
                    }, __('Live Preview', 'phynite-signup-form')),
                    
                    ServerSideRender && el(ServerSideRender, {
                        block: 'phynite/signup-form',
                        attributes: attributes,
                        className: 'phynite-block-server-preview',
                        LoadingResponsePlaceholder: () => el('div', {
                            style: {
                                padding: '40px',
                                textAlign: 'center',
                                color: '#666'
                            }
                        }, el(Spinner), ' ', __('Loading preview...', 'phynite-signup-form')),
                        ErrorResponsePlaceholder: ({ response }) => el('div', {
                            style: {
                                padding: '20px',
                                textAlign: 'center',
                                color: '#d63638',
                                backgroundColor: '#fcf0f1'
                            }
                        }, 
                        el('p', {}, __('Preview Error:', 'phynite-signup-form')),
                        el('code', { style: { fontSize: '11px' } }, (response && response.message) || __('Unable to render preview', 'phynite-signup-form'))
                        ),
                        EmptyResponsePlaceholder: () => el('div', {
                            style: {
                                padding: '20px',
                                textAlign: 'center',
                                color: '#666'
                            }
                        }, __('No content to preview', 'phynite-signup-form'))
                    })
                    )
                )
            );
        },
        
        save: function() {
            // Server-side rendered block
            return null;
        }
    });
    
})(window.wp);