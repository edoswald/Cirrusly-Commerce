=== Cirrusly Commerce ===

Contributors: edoswald
Tags: Google Merchant Center, WooCommerce, pricing, MSRP, profit margin
Requires at least: 5.8 
Tested up to: 6.9 
Stable tag: 1.7
Requires PHP: 8.1 
License: GPLv2 or later 
License URI: http://www.gnu.org/licenses/gpl-2.0.html

The Financial Operating System for WooCommerce that doesn't cost an arm and a leg.

**Stop guessing if your Google Ads are profitable. Stop worrying about Merchant Center suspensions.** Cirrusly Commerce is the financial operating system for WooCommerce stores. It is the only plugin that combines **Google Merchant Center compliance**, **Net Profit Auditing**, and **Dynamic Pricing** into a single, powerful suite.

Originally a set of code snippets used on our site Cirrusly Weather, Cirrusly Commerce works alongside your existing feed plugin to fix data errors, visualize true profit margins (after COGS and fees), and increase conversion rates with psychological pricing.

### 🚀 1. Google Merchant Center Compliance & Feed Repair
Your product feed is your business's lifeline. Don't let a suspension kill your revenue.

* **Diagnostics Scan:** Scan your entire catalog for critical policy violations like missing GTINs, prohibited words (e.g., "cure," "weight loss"), and title length issues.
* **Suspension Prevention:** actively blocks you from saving products with critical errors (such as banned words in titles), preventing bad data from ever reaching your feed.

### 📈 2. WooCommerce Profit Calculator & Margin Tracking
Revenue is vanity; profit is sanity. Most stores don't know their true margin after ad spend and fees.

* **Real-Time Net Profit:** See your exact Net Profit ($) and Margin (%) directly on the product edit screen.
* **Automated Cost Calculations:** We automatically deduct:
    * **Cost of Goods Sold (COGS)**
    * **Shipping Estimates**
    * **Payment Gateway Fees** (Stripe, PayPal, Square)

### 💰 3. Dynamic Pricing & Google Shopping Automation
Maximize your ROAS (Return on Ad Spend) with advanced pricing strategies.

* **MSRP Display:** Boost conversion by displaying "List Price" vs. "Our Price" comparisons on product pages.
* **Pricing Engine** Stop guessing how a sale affects your margins. See real-time margin data on the product edit screen as you enter prices.

### 📊 4. Financial Audit Dashboard
* **Loss Maker Report:** Instantly identify products that are losing money or have dangerously low margins.
* **Bulk COGS Management:** Quickly find and fix products missing cost data without opening every single product page.

### 🎨 5. Conversion Tools & Gutenberg Blocks
* **Smart Badges:** Automatically display badges for "Low Stock," "New Arrival," or "Best Seller" based on real inventory and sales data.
* **MSRP Block:** Customizable "Original Price" block for the Site Editor.

### Compatibility
Cirrusly Commerce is optimized to work with the best WooCommerce plugins:
* **Feed Plugins:** Product Feed PRO (AdTribes), Google Product Feed (Ademti), CTX Feed.
* **SEO Plugins:** Rank Math, Yoast SEO, All in One SEO (AIOSEO), SEOPress (Schema support included).
* **COGS Plugins:** WooCommerce Cost of Goods (SkyVerge), WPFactory.

### Like the Plugin?
Upgrade to Pro or Pro Plus for added functionality.
* **Real-Time API Sync (Pro):** Bypass the 24-hour feed fetch delay. Updates to price, stock status, or titles are pushed to Google's Content API immediately.
* **Intelligent Issue Deduplication (Pro Plus):** Uses Google NLP logic to group related errors, so you can fix bulk issues faster.
* **Multi-Profile Financials (Pro):** Calculate blended fee rates for stores using multiple payment processors (e.g., 60% Stripe + 40% PayPal) for 100% accuracy.
* **Inline Editing (Pro):** Update costs and prices directly from the audit table via AJAX.
* **CSV Import/Export (Pro):** Bulk manage your financial data via CSV for external analysis.
* **Countdown Timer (Pro):** Add urgency to your product pages.
* **Discount Notices (Pro):** Show dynamic "You saved $X!" messages in the cart.
* **Automated Discounts (Pro Plus):** Full integration with Google's "Automated Discounts" program. We validate Google's secure pricing tokens (JWT) to dynamically update the cart price to match the discounted ad price, without the high starting costs of other plugins.
* **Psychological Repricing (Pro Plus):** Automatically round calculated prices to .99, .50, or the nearest $5 to maximize click-through rate (CTR) and conversion.

== External Services ==

This plugin sends data to the Google Platform API to enable Google Customer Reviews surveys for your customers.

* **Service:** Google Platform API (Google Customer Reviews)
* **Data Transmitted:** Google Merchant Center ID (Merchant ID) and the customer's Order ID.
* **Trigger Point:** Data is transmitted on the WooCommerce "Order Received" (Thank You) page via the `woocommerce_thankyou` hook.
* **Privacy Policy:** [Google Privacy Policy](https://policies.google.com/privacy)
* **Terms of Service:** [Google Terms of Service](https://policies.google.com/terms)

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/cirrusly-commerce` directory, or install directly from the WordPress plugins screen.
2.  Activate the plugin.
3.  **Run the Setup Wizard:** Follow the milestone-based onboarding to connect your Google Merchant Center ID and configure your payment fees.
4.  **Check your Dashboard:** Go to **Cirrusly Commerce > Dashboard** to see your store's health score.

**Note:** Please ensure "Cost of Goods Sold" is enabled in **WooCommerce > Settings > Advanced**.

== Frequently Asked Questions ==

= Does this replace my Google Feed plugin? =
No. Cirrusly Commerce is a **Compliance and Optimization** layer. We work *alongside* plugins like Product Feed PRO. They generate the XML file; we ensure the data inside it doesn't get you banned and is priced profitably.

= How does the Real-Time API Sync improve SEO? =
By keeping your price and stock status in perfect sync with Google, you improve your "Quality Score" in Merchant Center. This can lead to lower CPCs and better ad placement because Google trusts your data accuracy.

= Can I track profit for Stripe and PayPal separately? =
Yes. The Pro version allows you to set a "Split Profile" (e.g., 70% Stripe / 30% PayPal), calculating a weighted average fee for your entire store to give you a realistic Net Profit margin.

= What is the difference between Free, Pro, and Pro Plus? =
* **Free:** Health scans, Profit calculation, MSRP display, and Manual auditing.
* **Pro:** Real-time API sync, Inline editing, Smart Badges, and CSV export.
* **Pro Plus:** Automated Discounts (Google Sync), Psychological Pricing, and the full Analytics Dashboard.

== Screenshots ==

1. **Dashboard:** A complete picture of store performance in one screenful.
2. **Compliance Hub:** Pinpoint potential issues with your catalog that could affect Google Merchant Center visibility, and address them easily.
3. **Financial Audit:** A spreadsheet-style view of your catalog's financial health.
4. **Settings:** Tons of settings for you to configure to your heart's content, for free.
5. **Analytics (Pro Plus):** The only plugin to combine your WooCommerce and Google Shopping analytics on one screen. In beta now, ahead of our v2.0 release!
6. **Pricing Intelligence:** Stop guessing what your margins are. Our Pricing Intelligence provides some real-time smarts for your product editing page.

== Changelog ==

= 1.7 =
Happy Holidays from the Cirrusly Commerce Team! We have a few updates to share. We're also slowing down development here for a bit to focus on stability and performance.
* **New:** Sync Status Dashboard: real-time queue, manual/auto refresh, KPI cards, animated status indicator, and detailed queue table. (Pro/Pro Plus)
* **Fix:** We caught a bug where some emails for Pro users were not properly coded and not sending. With this fix, we've confirmed that *all* functionality works as intended. Because of the extensive work, we're making this a point versus a patch release.
* **Enhancement:** Some changes to our quota system to provide more accurate usage metrics.
* **UI/UX Update:** Small copy fixes to remove 'GMC' plugin wide, replaced with Google Merchant Center/Google Shopping/Compliance or removed to improve context and prepare for potential non-Google support.
* **Refactor:** Plugin emails have been relocated to their own classes to make changes easier. (Pro/Pro Plus)

= 1.6.10 =
* **UI/UX Update:** Small UI fixes (preparing for 2.0!).
* **Enhancement:** We've updated our in plugin documentation to match recently added functionality.
* **Enhancement:** Cirrusly Commerce APIs (NOT Google's) are now automatically generated by our service worker to ease setup.
* **Fix:** One of these days we'll roll out new functionality without breaking our Pro API functionality in the process. Nonce errors and database errors fixed.
* **Development:** Automated Discounts proving to be difficult to code for! We're continuing work on this feature for Pro Plus users in this release. Stay tuned.


= 1.6.6 =
* **Critical Fix:** Resolved settings wipe bug where all general settings were being lost on save (only service account metadata was preserved). Settings now correctly merge with existing values instead of replacing them.
* **UX Enhancement:** Consolidated three duplicate "Merchant ID" fields into a single global "Google Merchant Center" field. Users now enter their Merchant ID once and it automatically populates all features (Customer Reviews, Content API, Automated Discounts).
* **Enhancement:** Added automatic migration logic to consolidate existing Merchant IDs from old locations into the new global field. Migration runs automatically on first save after update.
* **Code Quality:** Improved settings preservation logic to properly handle disabled fields based on license tier, preventing data loss when saving settings.
* **Code Quality:** Enhanced checkbox handling to distinguish between intentionally unchecked fields vs. disabled Pro/Pro Plus fields.
* **Enhancement:** Analytics now includes data from Google Merchant Center in addition to sales data for better decision-making!
* **New Feature:** Product Studio in app for Pro Plus users. Got an alert about poor images? Need help with better titles or descriptions? Fix them with AI! (Look for the Fix with AI button)
* **Fix:** Fixed bugs that prevented Pro functionality from working as intended since 1.4. All functionality is now correctly querying our service worker.
* **Fix:** Duplicated issues quieted with better deduplication code.
* **UI/UX Update:** Buttons all follow a similar design and are color-coded based on functionality.

= 1.4.5 =
* **Refactor:** Changes help areas to reflect support forum location of plugin following WP Plugin Directory approval. A thank you to the Plugin Review team for their work!
* **Fix:** API functionality that was broken following migration to SaaS model for Premium features has been restored. Subscribers must request an API key for access. (Pro/Pro Plus)
* **Enhancement:** Ensured all advanced functionality is correctly passing through the service worker in encrypted form for privacy.
* **Enhancement:** All scripts and styles are now dedicated external assets.
* **Fix:** Standardized codebase naming conventions to prevent conflicts with other plugins (cc* and cw* to cirrusly).
* **Fix:** README styling fix to align with WordPress best practices, and correct false short description error in Plugin Check.
* **UI Update:** Improvements to design of analytics to match rest of plugin. (Pro Plus)

= 1.4 =
* **Enhancement:** Frontend asset registration refactored for better architecture and compliance.
* **Security:** Tightened nonce verification and file-upload sanitization to align with WordPress security best practices.
* **Refactor:** Migrated option/transient naming for consistency and future extensibility.
* **Enhancement:** Centralized admin inline JavaScript for improved maintainability.
* **Enhancement (Pro):** Refactored analytics data preparation and chart rendering.
* **Enhancement (Pro):** Moved to service worker for API calls, allowing for easier upgrades of functionality without bloating plugin for Pro users.
* **New Feature:** Admin Setup Wizard - Automated onboarding runs on activation with milestone-based prompts.
* **New Feature:** Analytics Dashboard (Pro Plus) - Real-time P&L summaries, inventory velocity tracking, and daily GMC performance snapshots.
* **Enhancement:** Intelligent Issue Deduplication (Pro Plus) - Signature-based deduplication with Google NLP integration merges related audit issues.
* **Enhancement:** Addition of helpful explainer text and tooltips throughout the UI.
* **Fix:** Corrected audit regex that was mistakenly flagging acceptable terms (who vs WHO, cure being found in secure, etc.)
* **Fix:** Fix for orders not appearing in analytics and other functions due to strict adherence to WordPress default statuses. Plugin now correctly handles custom order statuses.
* **Fix:** Small security and best practices improvements to align with WP Plugin Directory guidelines.

= 1.3 =
* **Refactor:** Split plugin into three tiers: Free, Pro, and Pro Plus.
* **New Feature:** Gutenberg Blocks - MSRP display, Sale Countdown, Smart Badges, Automated Discount Notice.
* **UI Update:** "GMC Hub" is now "Compliance Hub".
* **Enhancement:** MSRP injection location is now customizable on the product page via Hooks or Blocks.
* **Requirement:** Plugin now requires PHP 8.1.

= 1.2.1 =
* **New Feature:** Introduced Freemius architecture for license management.
* **Enhancement:** Added "System Info" tool for troubleshooting.
* **Enhancement:** Pricing Engine now supports 5%, 15%, and 25% "Off MSRP" strategies.
* **Enhancement:** Pricing Engine now supports "Nearest 5/0" rounding.
* **UI Update:** Reorganized settings into tabbed cards.

= 1.1 =
* **New Feature:** Payment Processor Fees configuration.
* **New Feature:** "Profit at a Glance" column in All Products list.
* **New Feature:** "New Arrival" Badge module.
* **Enhancement:** Expanded GMC Health Check for suspicious image names.

= 1.0 =
* Initial release.
