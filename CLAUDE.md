# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Build and Assets
```bash
npm install                # Install frontend dependencies
composer install          # Install PHP dependencies
npm run build             # Build production assets (creates dist/ folder)
npm run dev               # Watch mode for development
npm run clean             # Clean build artifacts
```

### Testing
```bash
composer test             # Run all PHP tests
composer test:coverage    # Run tests with coverage report
npm test                  # Run JavaScript tests
npm run test:watch        # Watch mode for JS tests

# Run specific test groups
composer test -- --group=api
composer test -- --group=security
composer test -- --group=validation
```

### Code Quality
```bash
composer lint             # Run all PHP linting
composer lint:fix         # Auto-fix PHP coding standards
npm run lint              # Run all frontend linting
npm run format            # Format code with Prettier
composer analyse          # Run PHPStan static analysis
```

## Architecture Overview

### Plugin Structure
This is a WordPress plugin that provides a Gutenberg block for Phynite Analytics signup forms with secure API integration to the Stewie backend.

**Core Components:**
- **Main Plugin Class**: `Phynite_Signup_Form` (singleton pattern) in `phynite-signup-form.php`
- **API Integration**: `Phynite_Signup_Form_API` handles all Stewie API communication via REST endpoints
- **Admin Interface**: `Phynite_Signup_Form_Admin` manages settings and configuration
- **Gutenberg Block**: `Phynite_Signup_Form_Block` handles block registration and rendering
- **Security Layer**: `Phynite_Signup_Form_Security` provides rate limiting and validation

### Security Architecture
The plugin implements a **server-side proxy pattern** to protect API keys:
- WordPress REST API endpoints (`/wp-json/phynite-signup/v1/*`) act as secure proxies
- All Stewie API calls happen server-side, never exposing keys to frontend
- Rate limiting, input sanitization, and bot detection are enforced server-side
- Frontend JavaScript only communicates with WordPress, never directly with Stewie

### API Endpoints Structure
```
/wp-json/phynite-signup/v1/
├── check-email      (POST) - Check if email exists in Stewie
├── create-checkout  (POST) - Create Stripe checkout session
├── get-products     (GET)  - Retrieve subscription plans
└── test-connection  (GET)  - Admin-only API health check
```

### Asset Pipeline
- **Source**: `assets/js/` and `assets/css/` 
- **Build Output**: `dist/js/` and `dist/css/` (webpack)
- **WordPress Integration**: Built assets are enqueued by respective classes
- **Development**: Webpack provides source maps and watch mode

### Testing Infrastructure
- **PHP Tests**: PHPUnit with WordPress test suite integration
- **Mock System**: `MockStewie` and `PhyniteTestHelper` classes for API mocking
- **Test Groups**: Organized by functionality (api, security, validation, block, admin)
- **Coverage**: HTML and Clover reports generated in `coverage/`

### Configuration Flow
1. **Admin Settings** (`class-admin.php`) - UI and validation
2. **API Configuration** (`class-api.php`) - Uses settings for endpoint URLs and keys  
3. **Environment Awareness** - Development uses `http://localhost:4000`, Production uses `https://api.phynitesolutions.com`

### WordPress Integration Points
- **Plugin Activation**: Creates database tables for rate limiting and security logs
- **Gutenberg**: Custom block with server-side rendering and live preview
- **REST API**: Custom namespace with proper nonce verification
- **Settings API**: Native WordPress settings with sanitization callbacks
- **Enqueue System**: Proper asset loading with dependencies and versioning

### Key Files to Understand
- `includes/class-api.php` - Central API communication and REST endpoint definitions
- `assets/js/frontend.js` - Client-side form handling and validation
- `tests/utils/MockStewie.php` - Comprehensive API mocking for tests
- `webpack.config.js` - Asset compilation with CSS extraction and minification

## Environment Configuration

### API Endpoints
- **Production**: `https://api.phynitesolutions.com`
- **Development**: `http://localhost:4000`

### Required Settings
The plugin requires configuration in WordPress admin under **Settings > Phynite Signup**:
- Stewie API key (format: `phyn_*`)
- Environment selection (production/staging/development)
- Rate limiting and security settings

### Local Development
For Local WP testing, symlink the plugin directory:
```bash
ln -sf "/path/to/phynite-signup-form" "/path/to/Local Sites/site/app/public/wp-content/plugins/"
```

## WordPress Dependencies
- **Minimum WordPress**: 5.8 (for Gutenberg API features)
- **Minimum PHP**: 7.4
- **Required Extensions**: cURL, JSON
- **WordPress APIs Used**: REST API, Settings API, Gutenberg Block API, Enqueue System
- No self attribution in commit messages