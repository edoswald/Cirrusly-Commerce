# Cirrusly Commerce

A WooCommerce plugin combining Google Merchant Center compliance, financial auditing, and dynamic pricing.

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-Required-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## Requirements

- PHP 8.1+
- WordPress 5.8+
- WooCommerce (active)

## Project Structure

```
cirrusly-commerce/
├── cirrusly-commerce.php      # Main plugin file, Freemius init, bootstrap
├── includes/
│   ├── class-core.php         # Subsystem loader, Pro gating, hooks, cron router
│   ├── class-gmc.php          # Google Merchant Center compliance scanning
│   ├── class-audit.php        # Per-product P&L calculations
│   ├── class-pricing.php      # MSRP display, margin calculations
│   ├── class-badges.php       # Smart badge rendering
│   ├── class-blocks.php       # Gutenberg block registration
│   ├── class-countdown.php    # Time-limited promotions
│   ├── class-compatibility.php # SEO plugin integrations
│   ├── class-security.php     # AES-256-CBC encryption for API keys
│   ├── admin/
│   │   ├── class-settings-manager.php  # Settings, cron scheduling
│   │   ├── class-admin-assets.php      # Script/style enqueuing
│   │   ├── class-dashboard-ui.php      # WordPress dashboard widget
│   │   ├── class-gmc-ui.php            # Compliance hub interface
│   │   ├── class-audit-ui.php          # Financial audit table
│   │   ├── class-pricing-ui.php        # Pricing configuration
│   │   ├── class-setup-wizard.php      # Onboarding flow
│   │   └── class-debug-ui.php          # System diagnostics
│   └── pro/
│       ├── class-google-api-client.php   # Google API gateway, quota tracking
│       ├── class-gmc-pro.php             # Real-time API sync
│       ├── class-analytics-pro.php       # Dashboard UI (Pro Plus)
│       ├── class-product-studio.php      # AI product tools (Pro Plus)
│       ├── class-gmc-analytics.php       # GMC metrics (Pro Plus)
│       ├── class-automated-discounts.php # Dynamic pricing
│       └── class-pricing-sync.php        # Real-time price sync
├── assets/
│   ├── css/           # Stylesheets (no build process)
│   ├── js/            # Scripts (no build process)
│   └── images/        # Plugin assets
└── vendor/
    └── freemius/      # Licensing SDK
```

## Development Setup

1. Clone the repository into your WordPress plugins directory:
   ```bash
   git clone https://github.com/cirruslyweather/cirrusly-commerce.git wp-content/plugins/cirrusly-commerce
   ```

2. Activate the plugin in WordPress admin

3. For Pro feature development, use dev mode (requires `WP_DEBUG=true`):
   ```
   ?cirrusly_dev_mode=pro      # Enable Pro features
   ?cirrusly_dev_mode=free     # Force free mode
   ```

### No Build Process

This plugin has no build/transpilation pipeline. PHP, CSS, and JavaScript files are served directly—no webpack, gulp, or compilation required.

### No Test Suite

There is currently no automated test suite. Development relies on manual testing with WooCommerce and test products configured.

## Architecture Notes

### Context-Aware Loading

Pro classes load conditionally to reduce frontend overhead:

```php
// Admin-only
if ( is_admin() && file_exists( $path ) ) {
    require_once $path;
}

// Admin + cron (no frontend)
if ( ( is_admin() || wp_doing_cron() ) && file_exists( $path ) ) {
    require_once $path;
}
```

### Storage: Options vs Transients

**Use `update_option()` for persistent data.** Production environments with Redis/Memcached store transients in memory-only cache, causing data loss on cache flush.

```php
// Correct (Redis-safe)
update_option( 'cirrusly_audit_data', $data, false );

// Incorrect (data loss with Redis)
set_transient( 'cirrusly_audit_data', $data, 3600 );
```

### Pro Feature Gating

```php
if ( Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
    // Pro tier or higher
}

if ( Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
    // Pro Plus only
}
```

### Freemius Plan Constants

Use constants instead of hardcoded plan IDs:

```php
Cirrusly_Commerce_API_Key_Manager::PLAN_ID_FREE     // '36829'
Cirrusly_Commerce_API_Key_Manager::PLAN_ID_PRO      // '36830'
Cirrusly_Commerce_API_Key_Manager::PLAN_ID_PROPLUS  // '37116'
```

### Security Pattern

```php
// AJAX handler validation order
if ( ! check_ajax_referer( 'cirrusly_audit_save', '_nonce', false ) ) {
    wp_send_json_error( 'Invalid nonce' );
}
if ( ! current_user_can( 'edit_products' ) ) {
    wp_send_json_error( 'Permission denied' );
}
// Always wp_unslash() before sanitize_*()
$value = sanitize_text_field( wp_unslash( $_POST['field'] ) );
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Follow existing code patterns and WordPress coding standards
4. Test with WooCommerce active and configured
5. Submit a pull request against `main`

### Key Patterns to Follow

- Use `update_option()` for persistent data (not transients)
- Check Pro status before loading Pro files
- Use static `init()` methods to prevent duplicate hook registration
- Clear caches on product updates (`Cirrusly_Commerce_Core::clear_metrics_cache()`)
- Follow the v2.0 UI design system in `class-analytics-pro.php` for new admin pages

### Version Updates

Update version in three places:
1. `cirrusly-commerce.php` — Plugin header
2. `cirrusly-commerce.php` — `CIRRUSLY_COMMERCE_VERSION` constant
3. `readme.txt` — Stable tag

## License

GPLv2 or later. See [LICENSE](LICENSE) for details.
