# Phynite Analytics Signup Form WordPress Plugin

A professional WordPress plugin that provides a customizable Gutenberg block for Phynite Analytics signup forms with secure API integration.

## Features

- **Gutenberg Block Integration** - Custom block with live preview and extensive customization options
- **Secure API Integration** - Server-side proxy to Stewie API with encrypted API key storage
- **Advanced Security** - Rate limiting, input sanitization, bot detection, and CSRF protection
- **Responsive Design** - Mobile-friendly forms with multiple style variations
- **Real-time Validation** - Client-side validation with server-side verification
- **Email Verification** - Checks for existing accounts and blocks disposable email providers
- **Plan Selection** - Dynamic pricing display with monthly/yearly options
- **Accessibility** - WCAG 2.1 compliant with full keyboard navigation and screen reader support

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- cURL extension
- Valid Phynite Analytics Stewie API key

## Installation

### Automatic Installation

1. Download the plugin zip file
2. Go to **Plugins > Add New** in your WordPress admin
3. Click **Upload Plugin** and select the zip file
4. Install and activate the plugin

### Manual Installation

1. Upload the plugin files to `/wp-content/plugins/phynite-signup-form/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Configure the plugin settings under **Settings > Phynite Signup**

## Configuration

### API Settings

1. Go to **Settings > Phynite Signup** in your WordPress admin
2. Enter your Stewie API key (starts with `phyn_`)
3. Set the Stewie API URL:
   - **Production**: `https://api.phynitesolutions.com`
   - **Development**: `http://localhost:4000`
4. Select your environment (Production, Staging, or Development)
5. Click **Test API Connection** to verify your settings

### Form Customization

- **Primary Color**: Set the brand color for buttons and accents
- **Form Style**: Choose from Default, Minimal, Modern, or Compact styles
- **Terms & Privacy**: Configure links to your legal pages
- **Rate Limiting**: Adjust security settings (default: 5 requests per minute)

## Usage

### Adding the Signup Form

1. Edit any page or post in WordPress
2. Add a new block and search for "Phynite Signup Form"
3. Configure the block settings in the sidebar:
   - **Content**: Title, subtitle, button text
   - **Pricing**: Show/hide plan selection, default plan
   - **Appearance**: Style, colors, alignment, width
   - **Legal**: Terms and privacy policy URLs

### Block Attributes

The block supports the following customization options:

- `title` - Form title
- `subtitle` - Form description
- `showTitle` - Show/hide title
- `showSubtitle` - Show/hide subtitle
- `showLogo` - Show/hide Phynite logo
- `formStyle` - Style variation (default, minimal, modern, compact)
- `primaryColor` - Brand color for buttons and accents
- `buttonText` - Submit button text
- `showPlanSelection` - Enable/disable plan selection
- `defaultPlan` - Default selected plan (monthly/yearly)
- `showTermsLinks` - Show/hide legal links
- `termsUrl` - Terms of Service URL
- `privacyUrl` - Privacy Policy URL
- `alignment` - Form alignment (left, center, right)
- `maxWidth` - Maximum form width in pixels

## API Endpoints

The plugin creates the following REST API endpoints:

### Check Email Existence
```
POST /wp-json/phynite-signup/v1/check-email
```
Checks if an email address already exists in the Phynite system.

### Create Checkout Session
```
POST /wp-json/phynite-signup/v1/create-checkout
```
Creates a Stripe checkout session for subscription signup.

### Get Products
```
GET /wp-json/phynite-signup/v1/get-products
```
Retrieves available subscription plans and pricing.

### Test Connection
```
GET /wp-json/phynite-signup/v1/test-connection
```
Tests the API connection (admin only).

## Security Features

- **Rate Limiting**: Prevents spam and abuse with configurable limits
- **Input Sanitization**: All user inputs are validated and sanitized
- **Bot Detection**: Honeypot fields and behavior analysis
- **CSRF Protection**: WordPress nonces on all forms
- **API Key Encryption**: Secure storage of sensitive credentials
- **SQL Injection Prevention**: Prepared statements and WordPress APIs
- **XSS Protection**: Output escaping and content filtering

## Customization

### CSS Customization

The plugin uses CSS custom properties for easy theming:

```css
.phynite-signup-form-container {
    --primary-color: #007cba;
    --border-radius: 6px;
    --spacing-md: 16px;
    /* ... more variables */
}
```

### Hooks and Filters

**Actions:**
- `phynite_signup_form_loaded` - Plugin initialization
- `phynite_signup_form_api_request` - Before API requests
- `phynite_signup_form_validation_failed` - Form validation errors
- `phynite_signup_form_success` - Successful form submission

**Filters:**
- `phynite_signup_form_settings` - Modify plugin settings
- `phynite_signup_form_validation_rules` - Custom validation rules
- `phynite_signup_form_api_headers` - Modify API request headers
- `phynite_signup_form_block_attributes` - Block attribute defaults

## Development

### Local Development Setup

1. Clone the repository
2. Install dependencies: `composer install && npm install`
3. Build assets: `npm run build`
4. Set up WordPress test environment: `bin/install-wp-tests.sh`
5. Run tests: `composer test`

### Testing

The plugin includes comprehensive test coverage:

```bash
# Run all tests
composer test

# Run specific test groups
composer test -- --group=api
composer test -- --group=security
composer test -- --group=validation

# Generate coverage report
composer test-coverage
```

### Building Assets

```bash
# Development build with watch
npm run dev

# Production build
npm run build

# Lint JavaScript and CSS
npm run lint
```

## Troubleshooting

### Common Issues

**API Connection Failed**
- Verify your API key is correct and starts with `phyn_`
- Check that your server can make outbound HTTPS requests
- Ensure the Stewie API URL is correct for your environment

**Form Not Displaying**
- Check that the plugin is activated
- Verify your WordPress version meets requirements
- Look for JavaScript errors in browser console

**Styling Issues**
- Clear any caching plugins
- Check for theme conflicts by switching to a default theme
- Verify CSS files are loading correctly

**Rate Limiting Errors**
- Adjust rate limit settings in plugin configuration
- Check for IP address conflicts in shared hosting
- Clear rate limiting data from database if needed

### Debug Mode

Enable debug logging in plugin settings to troubleshoot API issues:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check logs at /wp-content/debug.log
```

## Support

- **Documentation**: [https://phynitesolutions.com/docs/wordpress-plugin](https://phynitesolutions.com/docs/wordpress-plugin)
- **Support Forum**: [WordPress Plugin Support](https://wordpress.org/support/plugin/phynite-signup-form)
- **GitHub Issues**: [https://github.com/PhyniteSolutions/phynite-signup-form/issues](https://github.com/PhyniteSolutions/phynite-signup-form/issues)

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature-name`
3. Make your changes and add tests
4. Run the test suite: `composer test`
5. Submit a pull request with a clear description

## Changelog

### 1.0.0
- Initial release
- Gutenberg block integration
- Secure API proxy
- Comprehensive security features
- Responsive design with multiple styles
- Full accessibility support

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Phynite Solutions](https://phynitesolutions.com) - Professional analytics solutions for modern websites.