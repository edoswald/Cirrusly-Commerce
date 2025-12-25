<?php 
defined( 'ABSPATH' ) || exit; 
?>

<div class="wrap">
    <?php Cirrusly_Commerce_Core::render_global_header( __( 'User Manual', 'cirrusly-commerce' ) ); ?>

    <div class="card cc-manual-container">
        
        <div class="cc-manual-grid">
            <div class="cc-manual-sidebar">
                <h3>Table of Contents</h3>
                <ul>
                    <li><a href="#setup">Setup & Configuration</a></li>
                    <li><a href="#dashboard">Dashboard</a></li>
                    <li><a href="#audit">Financial Audit</a></li>
                    <li><a href="#compliance">Compliance Hub</a></li>
                    <li><a href="#profit">Profit Engine</a></li>
                    <li><a href="#pricing">Pricing Intelligence</a></li>
                    <li><a href="#badges">Badge Manager</a></li>
                    <li><a href="#ai-studio"><strong>Product Studio AI</strong></a></li>
                    <li><a href="#analytics"><strong>GMC Analytics</strong></a></li>
                    <li><a href="#automation">Automation</a></li>
                    <li><a href="#dev-ref">Developer Reference</a></li>
                    <li><a href="#troubleshooting">Troubleshooting</a></li>
                </ul>
            </div>

            <div class="cc-manual-content">
                
                <div style="background: #f0f9ff; padding: 20px; margin-bottom: 30px; border-left: 4px solid #2271b1; border-radius: 4px;">
                    <p style="margin: 0; font-size: 15px; line-height: 1.6;">
                        <span class="dashicons dashicons-book-alt" style="color: #2271b1; margin-right: 8px; vertical-align: middle;"></span>
                        <strong>Need more help?</strong> Visit our comprehensive 
                        <a href="https://commerce.cirruslyweather.com/documentation.html" target="_blank" rel="noopener noreferrer" style="color: #2271b1; text-decoration: none; font-weight: 600; border-bottom: 2px solid #2271b1;">
                            Online Documentation
                            <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle;"></span>
                        </a> 
                        for detailed guides, video tutorials, and the latest feature updates.
                    </p>
                </div>
                
                <div id="setup" class="cc-manual-section">
                    <h2>Setup & Configuration</h2>
                    
                    <h4>Before You Start</h4>
                    <p><strong>Cost of Goods Sold (COGS) must be enabled</strong> in WooCommerce → Settings → Products → Inventory. This is required for profit calculations throughout the plugin.</p>
                    
                    <h4>Setup Wizard</h4>
                    <p>For first-time setup, use the <strong>Setup Wizard</strong> button from the Settings page. The wizard guides you through:</p>
                    <ol>
                        <li><strong>License Activation</strong> - Enter your license key (or start with Free tier)</li>
                        <li><strong>Connect to Google Cloud</strong> - Upload Service Account JSON and enter Merchant Center ID</li>
                        <li><strong>Product Studio Setup</strong> (Pro/Pro Plus) - Configure API access with validation system</li>
                        <li><strong>Finance Configuration</strong> - Define shipping costs, revenue tiers, and payment fees</li>
                        <li><strong>Visual Settings</strong> - Enable badges, MSRP display, and countdown timers</li>
                    </ol>
                    
                    <h4>API Quota Management System (Pro/Pro Plus)</h4>
                    <p>All Google API calls are centrally tracked to prevent quota overruns. The system includes:</p>
                    <ul>
                        <li><strong>Live Quota Bar</strong> - Real-time usage indicator in Settings → General tab</li>
                        <li><strong>Color-Coded Warnings</strong>:
                            <ul>
                                <li>Green (0-70%): Normal operations</li>
                                <li>Yellow (71-90%): Warning - conserve usage</li>
                                <li>Red (91-95%): Critical - non-essential features disabled</li>
                                <li>Blocked (95%+): All API calls blocked until midnight reset</li>
                            </ul>
                        </li>
                        <li><strong>Automatic Reset</strong> - Quota resets daily at midnight (site timezone)</li>
                        <li><strong>Expandable Details</strong> - Click quota bar to see usage breakdown by feature</li>
                    </ul>
                    
                    <table class="widefat striped">
                        <thead>
                            <tr><th>Tier</th><th>Daily Quota</th><th>Best For</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>Free</strong></td><td>50 calls/day</td><td>Small catalogs or testing</td></tr>
                            <tr><td><strong>Pro</strong></td><td>500 calls/day</td><td>Medium stores with regular updates</td></tr>
                            <tr><td><strong>Pro Plus</strong></td><td>2,500 calls/day</td><td>Large catalogs or frequent AI usage</td></tr>
                        </tbody>
                    </table>
                    
                    <h4>What Counts Toward Quota?</h4>
                    <table class="widefat striped">
                        <thead><tr><th>Action</th><th>Cost (Calls)</th><th>Notes</th></tr></thead>
                        <tbody>
                            <tr><td><strong>Daily GMC Scan</strong></td><td>1</td><td>Automated (scheduled)</td></tr>
                            <tr><td><strong>Manual Scan</strong></td><td>1</td><td>Click "Scan Now" button</td></tr>
                            <tr><td><strong>GMC Analytics Import</strong></td><td>10-50</td><td>Depends on date range (Pro Plus)</td></tr>
                            <tr><td><strong>AI Image Enhancement (Basic)</strong></td><td>1 per image</td><td>Remove/white background</td></tr>
                            <tr><td><strong>AI Image Enhancement (Advanced)</strong></td><td>2 per image</td><td>AI scene + outpainting</td></tr>
                            <tr><td><strong>AI Text Generation</strong></td><td>1 per field</td><td>Descriptions, titles, alt text</td></tr>
                            <tr><td><strong>Product Validation</strong></td><td>0</td><td>Free - doesn't count</td></tr>
                        </tbody>
                    </table>
                    
                    <p><strong>Best Practices:</strong></p>
                    <ul>
                        <li>Run manual scans during low-traffic hours</li>
                        <li>Use AI enhancement in batches rather than one-by-one</li>
                        <li>Schedule analytics imports for weekends if possible</li>
                        <li>Monitor quota bar before bulk operations</li>
                        <li>Free tier users: Use daily scan sparingly, manually validate most products</li>
                    </ul>
                </div>

                <hr>

                <div id="dashboard" class="cc-manual-section" style="margin-bottom: 50px;">
                    <h3><span class="dashicons dashicons-dashboard" style="color:#2271b1;"></span> Dashboard</h3>
                    
                    <h4>Store Pulse</h4>
                    <p>Located at <em>Cirrusly Commerce > Dashboard</em>. Provides at-a-glance metrics:</p>
                    <ul>
                        <li><strong>7-Day Revenue</strong> - Total sales for the week with trend indicator</li>
                        <li><strong>Active Products</strong> - Published products with cost data</li>
                        <li><strong>GMC Issues</strong> - Products flagged in latest compliance scan</li>
                        <li><strong>Loss Makers</strong> - Products with negative net profit</li>
                    </ul>
                    
                    <h4>Dashboard Cards</h4>
                    <p>Quick-access cards for key features:</p>
                    <ul>
                        <li><strong>Compliance</strong> - Run manual scan or view latest results</li>
                        <li><strong>Financial Audit</strong> - Access detailed profit analysis</li>
                        <li><strong>Product Studio</strong> (Pro/Pro Plus) - AI enhancement tools</li>
                        <li><strong>Analytics</strong> (Pro Plus) - GMC performance insights</li>
                    </ul>
                    
                    <h4>WordPress Widget</h4>
                    <p>A condensed Store Pulse widget can be added to the main WordPress dashboard via <em>Screen Options > Cirrusly Commerce</em>.</p>
                </div>

                <hr>

                <div id="audit" class="cc-manual-section" style="margin-bottom: 50px;">
                    <h3><span class="dashicons dashicons-money-alt" style="color:#2271b1;"></span> Financial Audit</h3>
                    
                    <p>Located at <em>Cirrusly Commerce > Financial Audit</em>. This table reveals financial performance per product.</p>
                    <h4>Key Columns:</h4>
                    <ul>
                        <li><strong>Total Cost</strong> - Item cost + estimated shipping cost</li>
                        <li><strong>Price</strong> - Current selling price</li>
                        <li><strong>Ship P/L</strong> - Shipping revenue minus shipping cost (profit/loss on shipping)</li>
                        <li><strong>Net Profit</strong> - Revenue minus all costs (product cost + shipping + payment fees)</li>
                        <li><strong>Margin %</strong> - (Net Profit / Price) × 100</li>
                    </ul>
                    
                    <h4>Color-Coded Alerts:</h4>
                    <ul>
                        <li><span style="color:#d63638;">⚠ Red Badge</span> - Missing cost data (click to edit product)</li>
                        <li><span style="color:#dba617;">⚠ Yellow Badge</span> - Zero weight (may affect shipping calculations)</li>
                        <li><span style="color:#d63638;font-weight:bold;">Negative Net</span> - Losing money on this item</li>
                        <li><span style="color:#008a20;font-weight:bold;">Positive Net</span> - Profitable item</li>
                    </ul>
                    
                    <h4>Pro Features:</h4>
                    <ul>
                        <li><strong>Inline Editing</strong> - Update cost and shipping values directly in table</li>
                        <li><strong>Export to CSV</strong> - Download full audit report for analysis</li>
                        <li><strong>Scenario Matrix</strong> - Test profitability under different cost multipliers (e.g., "High Gas Prices")</li>
                    </ul>
                </div>

                <hr>

                <div id="compliance" class="cc-manual-section" style="margin-bottom: 50px;">
                    <h3><span class="dashicons dashicons-shield" style="color:#2271b1;"></span> Compliance Hub</h3>
                    <p>Ensure Google Merchant Center (GMC) acceptance. Located at <em>Cirrusly Commerce > Compliance Hub</em>.</p>
                    
                    <h4>Health Check</h4>
                    <ul>
                        <li><strong>Scan:</strong> Detects missing GTINs, missing images, or restricted terms (e.g., "cure", "covid", "miracle")</li>
                        <li><strong>Mark as Custom:</strong> Use for handmade/vintage items to set <code>identifier_exists="no"</code></li>
                        <li><strong>Real-Time API Scan</strong> (Pro) - Fetches live disapproval status from Google</li>
                        <li><strong>Batch Actions</strong> - Mark multiple products as custom at once</li>
                    </ul>

                    <h4>Promotion Manager (Pro)</h4>
                    <ul>
                        <li><strong>Live Google Promotions</strong> - View active promotions from Merchant Center</li>
                        <li><strong>Local Assignments</strong> - Assign promotions to specific products</li>
                        <li><strong>Promotion Feed Generator</strong> - Export promotion data for Google</li>
                        <li><strong>Sync Status</strong> - Track which promotions are successfully synced</li>
                    </ul>

                    <h4>Site Content</h4>
                    <p>Scans for required legal pages (Return Policy, Terms of Service, Contact, Privacy) and restricted terms on pages.</p>

                    <h4>Automation & Workflow Rules (Pro)</h4>
                    <p>Configure in <em>Settings > General > Automation</em>:</p>
                    <ul>
                        <li><strong>Block Save:</strong> Prevents saving a product if critical GMC errors are found (e.g., missing GTIN)</li>
                        <li><strong>Auto-Strip:</strong> Automatically removes banned words from titles/descriptions upon save</li>
                        <li><strong>Daily Compliance Reports:</strong> Email summary of flagged products (sent at 2 AM)</li>
                        <li><strong>Instant Disapproval Alerts:</strong> Real-time notification when Google disapproves a product</li>
                    </ul>
                </div>

                <hr>

                <div id="profit" class="cc-manual-section" style="margin-bottom: 50px;">
                    <h3><span class="dashicons dashicons-chart-line" style="color:#2271b1;"></span> Profit Engine</h3>
                    <p>Configure at <em>Settings > Profit Intelligence</em>. The engine calculates true net profit by factoring in all costs.</p>
                    
                    <h4>1. Shipping Revenue Tiers</h4>
                    <p>Define how much you charge customers for shipping based on cart total:</p>
                    <ul>
                        <li>Example: $0-$10 → charge $3.99 shipping</li>
                        <li>Example: $60+ → free shipping ($0 charge)</li>
                    </ul>
                    
                    <h4>2. Internal Shipping Costs</h4>
                    <p>Estimate actual shipping costs per WooCommerce Shipping Class:</p>
                    <ul>
                        <li>Default: $10.00 (no class assigned)</li>
                        <li>Economy: $8.00</li>
                        <li>Overnight: $25.00</li>
                    </ul>
                    <p><em>These costs are used to calculate shipping profit/loss (Ship P/L = Revenue - Cost).</em></p>
                    
                    <h4>3. Payment Processor Fees</h4>
                    <p>Enter your payment processor rates (e.g., Stripe: 2.9% + $0.30):</p>
                    <ul>
                        <li><strong>Single Profile:</strong> One rate applies to all orders</li>
                        <li><strong>Multi-Profile</strong> (Pro) - Define secondary rate and split percentage (useful for different payment methods)</li>
                    </ul>
                    
                    <h4>4. Scenario Matrix</h4>
                    <p>Create "what-if" scenarios by applying cost multipliers:</p>
                    <ul>
                        <li>Example: "High Gas Prices" → 1.5x shipping costs</li>
                        <li>Example: "Holiday Season" → 2.0x shipping costs</li>
                    </ul>
                    <p>Use these in the Financial Audit to stress-test margins under different conditions.</p>
                </div>

                <hr>

                <div id="pricing" class="cc-manual-section" style="margin-bottom: 50px;">
                    <h3><span class="dashicons dashicons-tag" style="color:#2271b1;"></span> Pricing Intelligence</h3>
                    
                    <h4>Regular Price Strategy</h4>
                    <p>When setting product prices, consider your target margin and all costs:</p>
                    <ul>
                        <li>Target Price = (COGS + Shipping + Fees) / (1 - Target Margin %)</li>
                        <li>Example: COGS $10, Ship $5, Fees $1 → For 30% margin: Price = $16 / 0.70 = $22.86</li>
                    </ul>
                    
                    <h4>Sale Pricing & Discount Automation</h4>
                    <ul>
                        <li><strong>Manual Sale Price:</strong> Set in product edit screen (WooCommerce standard)</li>
                        <li><strong>Automated Discounts</strong> (Pro Plus) - Let Google dynamically adjust prices via Shopping Ads</li>
                        <li><strong>Minimum Price Enforcement:</strong> Set "Google Min Price" to prevent discounts below cost + fees</li>
                    </ul>
                    
                    <h4>Rounding Rules</h4>
                    <p>Enable rounding in <em>Settings > Pricing</em> to create "psychological" prices:</p>
                    <ul>
                        <li>$19.99 instead of $20.00</li>
                        <li>$47.95 instead of $48.00</li>
                    </ul>
                    
                    <h4>Live Profit Display</h4>
                    <p>When editing a product, the plugin shows real-time margin and net profit calculations based on your current price, cost, and global settings.</p>
                    
                    <h4>Product List View</h4>
                    <p>In Products admin list, margin % is color-coded:</p>
                    <ul>
                        <li><span style="color:#d63638;font-weight:bold;">Red:</span> Margin < 10% (danger zone)</li>
                        <li><span style="color:#dba617;font-weight:bold;">Yellow:</span> Margin 10-25% (acceptable)</li>
                        <li><span style="color:#008a20;font-weight:bold;">Green:</span> Margin > 25% (healthy)</li>
                    </ul>
                </div>

                <hr>

                <div id="badges" class="cc-manual-section" style="margin-bottom: 50px;">
                    <h3><span class="dashicons dashicons-awards" style="color:#2271b1;"></span> Badge Manager</h3>
                    <p>Configure at <em>Settings > Badge Manager</em>. Badges appear automatically on product pages and catalog grids.</p>
                    
                    <h4>Global Settings</h4>
                    <ul>
                        <li><strong>Enable Module:</strong> Turn badge system on/off</li>
                        <li><strong>Badge Size:</strong> Small, Medium, or Large</li>
                        <li><strong>Discount Base:</strong> Calculate savings from MSRP or Regular Price</li>
                        <li><strong>"New" Badge:</strong> Show for products added within X days (default: 30)</li>
                    </ul>
                    
                    <h4>Custom Tag Badges</h4>
                    <p>Map product tags to custom badge images:</p>
                    <ol>
                        <li>Enter tag slug (e.g., "organic")</li>
                        <li>Upload badge image or enter URL</li>
                        <li>Set tooltip text (optional)</li>
                        <li>Define badge width in pixels</li>
                    </ol>
                    <p><em>Any product with that tag will automatically show your custom badge.</em></p>
                    
                    <h4>Smart Badges (Pro)</h4>
                    <ul>
                        <li><strong>Low Stock:</strong> Show when quantity < 5 (creates urgency)</li>
                        <li><strong>Best Seller:</strong> Show for top-performing products based on 30-day sales</li>
                        <li><strong>Scheduler:</strong> Display "Event" or "Limited Time" badges between specific dates</li>
                    </ul>
                    
                    <h4>Countdown Timers</h4>
                    <p>Add urgency with live countdown timers. Configure in <em>Settings > General > Smart Countdown</em>:</p>
                    <ul>
                        <li><strong>Per-Product Timer:</strong> Set <code>_cirrusly_sale_timer</code> meta field to end date</li>
                        <li><strong>Smart Rules</strong> (Pro) - Auto-show timer for products matching taxonomy term (e.g., category "flash-sale")</li>
                        <li><strong>Manual Timer:</strong> Use shortcode <code>[cirrusly_countdown end="YYYY-MM-DD HH:MM:SS"]</code></li>
                    </ul>
                </div>

                <hr>

                <div id="ai-studio" class="cc-manual-section" style="margin-bottom: 50px; background: #f6f7f7; padding: 20px; border-left: 4px solid #72aee6;">
                    <h3 style="margin-top:0;">Product Studio AI (Pro/Pro Plus)</h3>
                    
                    <h4>Prerequisites</h4>
                    <p>Product Studio requires a Google Cloud Project with proper API access and permissions:</p>
                    <ul>
                        <li>Google Cloud Project with billing enabled</li>
                        <li>Service Account JSON (uploaded in plugin settings)</li>
                        <li>Merchant Center account linked to project</li>
                    </ul>
                    
                    <h4>Setup & Validation</h4>
                    <p>The Setup Wizard includes a 4-step validation system:</p>
                    <ol>
                        <li><strong>Enable Required APIs</strong> - Vision AI, Natural Language AI, Vertex AI (Imagen), Content API for Shopping</li>
                        <li><strong>Configure IAM Roles</strong> - Service Account needs "AI Platform User" and "Content API for Shopping Admin"</li>
                        <li><strong>Link Merchant Center</strong> - Project must be linked to your Merchant Center account</li>
                        <li><strong>Validate Setup</strong> - 3 tests run automatically:
                            <ul>
                                <li>Test API Call (Vision API health check)</li>
                                <li>Test Image Analysis (sample product image)</li>
                                <li>Test Merchant Access (read account info)</li>
                            </ul>
                        </li>
                    </ol>
                    <p><em>Each test shows ✓ (pass), ⚠ (warning), or ✗ (fail) with detailed error messages.</em></p>
                    
                    <h4>Using the Enhancement Modal</h4>
                    <p>AI enhancement is accessed from product edit screens via the "Enhance with AI" button. The modal workflow:</p>
                    <ol>
                        <li><strong>Trigger:</strong> Click "Enhance with AI" in product gallery or description field</li>
                        <li><strong>Modal Opens:</strong> Shows current content (image or text) on left side</li>
                        <li><strong>Style Selector:</strong> Choose from available enhancement styles (see lists below)</li>
                        <li><strong>Live Preview:</strong> Click "Generate" to see AI-enhanced version on right side</li>
                        <li><strong>Edit Option:</strong> Manually adjust generated content if needed</li>
                        <li><strong>Character Counter:</strong> Shows current length (green < 80%, yellow 80-100%, red > 100%)</li>
                        <li><strong>Apply:</strong> Click "Apply" to save enhanced content to product</li>
                        <li><strong>Success:</strong> Modal closes, changes saved to product</li>
                    </ol>
                    
                    <h4>AI Image Enhancement Modes</h4>
                    <table class="widefat" style="background:#fff;">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Tier</th>
                                <th>Modes Available</th>
                                <th>Cost/Image</th>
                                <th>Technology</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>FREE</strong></td>
                                <td>Blur Background, Color Background, Gradient Background</td>
                                <td>$0.00</td>
                                <td>PHP GD Library (local processing)</td>
                            </tr>
                            <tr>
                                <td><strong>Basic</strong> (Pro/Pro Plus)</td>
                                <td>Remove Background, White Background, Generate New Background</td>
                                <td>~$0.02</td>
                                <td>Vision API + Imagen 2</td>
                            </tr>
                            <tr>
                                <td><strong>Advanced</strong> (Pro/Pro Plus)</td>
                                <td>AI Scene Background (with contextual outpainting)</td>
                                <td>~$0.04</td>
                                <td>Vision API + Imagen 2 + Outpainting</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p><strong>Cost Optimization Tips:</strong></p>
                    <ul>
                        <li>FREE tier modes don't count toward API quota or billing</li>
                        <li>Basic tier is best for most product photography (clean, professional)</li>
                        <li>Advanced tier recommended for lifestyle/contextual imagery</li>
                        <li>Batch process images to reduce per-image overhead</li>
                        <li>AI-enhanced images are cached - re-enhancements are free if source unchanged</li>
                    </ul>
                    
                    <p><strong>UI Cost Badges:</strong> Each enhancement mode shows a cost indicator:</p>
                    <ul>
                        <li><span style="color:#008a20;">●</span> <strong>FREE</strong> - No cost</li>
                        <li><span style="color:#dba617;">●</span> <strong>BASIC</strong> - ~$0.02/image</li>
                        <li><span style="color:#d63638;">●</span> <strong>ADVANCED</strong> - ~$0.04/image</li>
                    </ul>
                    
                    <h4>Intelligent Image Resolution System</h4>
                    <p>Product Studio automatically optimizes images for API processing:</p>
                    <ul>
                        <li><strong>Original Preservation:</strong> Source images are never modified</li>
                        <li><strong>Dynamic Sizing:</strong> Images resized to optimal resolution based on enhancement mode</li>
                        <li><strong>CDN Compatibility:</strong> Works with WordPress, Cloudflare, and third-party CDN plugins</li>
                        <li><strong>Format Optimization:</strong> Converts HEIC/WebP to JPEG for maximum API compatibility</li>
                        <li><strong>AJAX Endpoint:</strong> Direct image access via <code>/wp-json/cirrusly/v1/resolve-image/{attachment_id}</code></li>
                    </ul>
                    
                    <h4>AI Text Generation Styles</h4>
                    
                    <p><strong>Product Descriptions (6 Styles):</strong></p>
                    <table class="widefat" style="background:#fff; margin-top:10px;">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Style</th>
                                <th>Best For</th>
                                <th>Character Limit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>Balanced</strong></td><td>General use, informative yet engaging</td><td>~500</td></tr>
                            <tr><td><strong>Professional</strong></td><td>B2B, technical products</td><td>~400</td></tr>
                            <tr><td><strong>Engaging</strong></td><td>Consumer products, storytelling</td><td>~600</td></tr>
                            <tr><td><strong>Technical</strong></td><td>Specs-focused, detailed explanations</td><td>~450</td></tr>
                            <tr><td><strong>SEO Optimized</strong></td><td>Keyword-rich for search ranking</td><td>~550</td></tr>
                            <tr><td><strong>Concise</strong></td><td>Quick overviews, mobile-first</td><td>~250</td></tr>
                        </tbody>
                    </table>
                    
                    <p><strong>Product Titles (5 Strategies):</strong></p>
                    <table class="widefat" style="background:#fff; margin-top:10px;">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Strategy</th>
                                <th>Example Format</th>
                                <th>GMC Compliance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><strong>Balanced</strong></td><td>Brand + Product + Key Feature</td><td>✓ Enforced</td></tr>
                            <tr><td><strong>Keyword First</strong></td><td>Primary Keyword + Product Type + Brand</td><td>✓ Enforced</td></tr>
                            <tr><td><strong>Brand Focus</strong></td><td>Brand + Product + Model Number</td><td>✓ Enforced</td></tr>
                            <tr><td><strong>Feature Rich</strong></td><td>Product + 2-3 Key Features + Benefit</td><td>✓ Enforced</td></tr>
                            <tr><td><strong>Benefit Driven</strong></td><td>Primary Benefit + Product + Key Feature</td><td>✓ Enforced</td></tr>
                        </tbody>
                    </table>
                    <p><em>GMC Compliance: All title strategies automatically exclude restricted terms ("sale", "free shipping", etc.)</em></p>
                    
                    <p><strong>Image Alt Text (3 Styles):</strong></p>
                    <ul>
                        <li><strong>Standard:</strong> Basic description (50-100 characters)</li>
                        <li><strong>Descriptive:</strong> Detailed visual description (100-150 characters)</li>
                        <li><strong>SEO Focused:</strong> Keyword-optimized description (75-125 characters)</li>
                    </ul>
                </div>

                <hr>

                <div id="analytics" class="cc-manual-section" style="margin-bottom: 50px; background: #f0f0f1; padding: 20px; border-left: 4px solid #8c8f94;">
                    <h3 style="margin-top:0;">GMC Analytics (Pro Plus)</h3>
                    <p>Syncs Google Shopping performance data directly to WordPress. Requires Service Account JSON and proper API permissions.</p>
                    
                    <h4>Initial Setup</h4>
                    <ol>
                        <li>Navigate to <em>Cirrusly Commerce > Analytics</em></li>
                        <li>Click <strong>"Start Import"</strong> button to begin initial data sync</li>
                        <li><strong>Progress Bar:</strong> Import runs in 9 batches (10 days per batch) to stay within API quota</li>
                        <li><strong>Email Confirmation:</strong> Receive notification when import completes (~5-10 minutes)</li>
                        <li><strong>Product Mapping:</strong>
                            <ul>
                                <li>Automatic matching by SKU (most products map instantly)</li>
                                <li>Manual mapping tool available in Settings > GMC Product Mapping for unmatched items</li>
                                <li>Bulk mapping actions for efficient setup</li>
                            </ul>
                        </li>
                    </ol>
                    
                    <h4>Daily Automatic Sync</h4>
                    <p>Analytics data refreshes automatically each day:</p>
                    <ul>
                        <li><strong>Schedule:</strong> 2:00 AM site timezone</li>
                        <li><strong>Data Storage:</strong> 30-day rolling transients (fast access)</li>
                        <li><strong>Archives:</strong> 90-day aggregates stored as options (historical trends)</li>
                    </ul>
                    
                    <h4>Manual Re-Sync</h4>
                    <p>Click the <strong>"Refresh Data"</strong> button to force immediate sync (useful after major catalog changes).</p>
                    
                    <h4>Understanding the Analytics Dashboard</h4>
                    
                    <p><strong>1. Stat Cards (with Trend Arrows):</strong></p>
                    <ul>
                        <li><strong>Impressions:</strong> Times your products appeared in Google Shopping</li>
                        <li><strong>Clicks:</strong> Clicks from Google Shopping to your site</li>
                        <li><strong>CTR (Click-Through Rate):</strong> Percentage of impressions that resulted in clicks</li>
                        <li><strong>Cost:</strong> Total spend on Google Shopping Ads (if using paid campaigns)</li>
                    </ul>
                    <p><em>Trend arrows show % change vs. previous period (green up = improvement, red down = decline).</em></p>
                    
                    <p><strong>2. Traffic Funnel Chart:</strong></p>
                    <ul>
                        <li>Visual representation: Impressions → Clicks → Add to Cart → Orders</li>
                        <li>Percentage drop-offs shown between each stage</li>
                        <li>Identify where customers abandon the purchase journey</li>
                    </ul>
                    
                    <p><strong>3. Price Competitiveness Alert:</strong></p>
                    <ul>
                        <li>Red banner appears when products flagged as "Price Too High" by Google</li>
                        <li>Shows number of products affected and average price difference vs. competitors</li>
                        <li>Links to competitive pricing reports</li>
                    </ul>
                    
                    <p><strong>4. Top GMC Products Table:</strong></p>
                    <ul>
                        <li>Sortable columns: Impressions, Clicks, CTR, Conversions</li>
                        <li>Click product name to edit directly in WooCommerce</li>
                        <li>Export to CSV for external analysis</li>
                    </ul>
                    
                    <h4>Troubleshooting</h4>
                    <table class="widefat striped" style="background:#fff; margin-top:10px;">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Issue</th>
                                <th>Solution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Import stuck at batch X/9</td>
                                <td>Check API quota (Settings > General). Wait 24 hours or upgrade plan.</td>
                            </tr>
                            <tr>
                                <td>Products showing $0.00 clicks</td>
                                <td>Products not yet receiving traffic or mapping failed. Verify SKU match.</td>
                            </tr>
                            <tr>
                                <td>Daily sync not running</td>
                                <td>Check WP-Cron status. Use WP Crontrol plugin to verify scheduled events.</td>
                            </tr>
                            <tr>
                                <td>Data mismatch vs. Merchant Center</td>
                                <td>Click "Refresh Data". Google's API has ~24hr reporting delay.</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <p><strong>API Cost Estimates:</strong></p>
                    <ul>
                        <li>Initial 90-day import: ~18 API calls (uses 3.6% of Pro Plus daily quota)</li>
                        <li>Daily sync (30-day rolling): ~6 API calls per day</li>
                        <li>Manual refresh: ~6 API calls per use</li>
                    </ul>
                </div>

                <hr>

                <div id="automation" class="cc-manual-section" style="margin-bottom: 50px;">
                    <h3><span class="dashicons dashicons-update" style="color:#2271b1;"></span> Automation</h3>
                    <p>Cirrusly Commerce automates compliance monitoring, pricing updates, and reporting tasks. Automation features vary by subscription tier.</p>
                    
                    <h4>Automation by Subscription Tier</h4>
                    <table class="widefat striped">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Feature</th>
                                <th>Free</th>
                                <th>Pro</th>
                                <th>Pro Plus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Daily Compliance Scan</td>
                                <td>✓</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Email Compliance Reports</td>
                                <td>—</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Instant Disapproval Alerts</td>
                                <td>—</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Weekly Profit Reports</td>
                                <td>—</td>
                                <td>✓</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>Automated Discounts (Dynamic Pricing)</td>
                                <td>—</td>
                                <td>—</td>
                                <td>✓</td>
                            </tr>
                            <tr>
                                <td>GMC Analytics Auto-Sync</td>
                                <td>—</td>
                                <td>—</td>
                                <td>✓</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>Configuration</h4>
                    <p>Navigate to <strong>Settings > General</strong> to configure automation features:</p>
                    
                    <p><strong>Daily Compliance Reports (Pro/Pro Plus):</strong></p>
                    <ul>
                        <li>Enable checkbox in Automation section</li>
                        <li>Scheduled delivery: 2:00 AM site timezone</li>
                        <li>Email includes: New issues detected, resolved issues, products requiring attention</li>
                        <li>Recipients: Site admin email (customizable in Settings > Alerts)</li>
                    </ul>
                    
                    <p><strong>Workflow Rules (All Tiers):</strong></p>
                    <ul>
                        <li><strong>Block Save When Errors:</strong> Prevents publishing products with GMC compliance violations</li>
                        <li><strong>Auto-Strip Banned Terms:</strong> Automatically removes restricted terms from titles/descriptions on save</li>
                    </ul>
                    <p><em>Located in Settings > Compliance Hub > Workflow Rules</em></p>
                    
                    <p><strong>Alerts & Notifications (Pro/Pro Plus):</strong></p>
                    <ul>
                        <li><strong>Weekly Profit Reports:</strong> P&L summary every Monday at 9 AM</li>
                        <li><strong>Instant Disapproval Alerts:</strong> Email notification within 15 minutes of Google flagging product</li>
                    </ul>
                    
                    <p><strong>Automated Discounts (Pro Plus Only):</strong></p>
                    <ul>
                        <li>Requires Google Public Key (PEM) in Settings > General > Automated Discounts</li>
                        <li>Dynamic pricing via JWT tokens from Google Shopping Ads</li>
                        <li>Respects Google Min Price (floor price) set in product edit screen</li>
                        <li>Cart synchronization - discounts apply automatically at checkout</li>
                    </ul>
                    
                    <h4>What's NOT Automated</h4>
                    <p>Cirrusly Commerce intentionally does not auto-apply fixes to preserve merchant control:</p>
                    <ul>
                        <li><strong>Price changes:</strong> Profit calculations are recommendations - you set final prices</li>
                        <li><strong>Content modifications:</strong> AI enhancements require explicit approval (modal workflow)</li>
                        <li><strong>GMC submissions:</strong> Product edits don't auto-sync to Merchant Center (manual push required)</li>
                        <li><strong>Promotion assignments:</strong> Merchant must map promotions to specific products</li>
                    </ul>
                    <p><em>Philosophy: Automation should inform decisions, not make them for you.</em></p>
                </div>

                <hr>

                <div id="dev-ref" class="cc-manual-section">
                    <h3><span class="dashicons dashicons-database" style="color:#2271b1;"></span> Developer Reference</h3>
                    <p>Cirrusly Commerce stores product data in WordPress post meta. These keys can be used for custom integrations, feed mapping, or programmatic queries.</p>
                    
                    <h4>Financial Fields</h4>
                    <table class="widefat striped">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Meta Key</th>
                                <th>Description</th>
                                <th>Data Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>_cogs_total_value</code></td>
                                <td>Cost of Goods Sold (item cost only)</td>
                                <td>float</td>
                            </tr>
                            <tr>
                                <td><code>_cirrusly_est_shipping</code></td>
                                <td>Estimated fulfillment/shipping cost</td>
                                <td>float</td>
                            </tr>
                            <tr>
                                <td><code>_alg_msrp</code></td>
                                <td>Manufacturer's Suggested Retail Price</td>
                                <td>float</td>
                            </tr>
                            <tr>
                                <td><code>_cirrusly_map_price</code></td>
                                <td>Minimum Advertised Price (MAP)</td>
                                <td>float</td>
                            </tr>
                            <tr>
                                <td><code>_auto_pricing_min_price</code></td>
                                <td>Google Min Price (Automated Discounts floor)</td>
                                <td>float</td>
                            </tr>
                            <tr>
                                <td><code>_cirrusly_profit_target</code></td>
                                <td>Target margin percentage</td>
                                <td>float (e.g., 25.0 for 25%)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>Marketing & Urgency Fields</h4>
                    <table class="widefat striped">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Meta Key</th>
                                <th>Description</th>
                                <th>Data Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>_cirrusly_sale_timer</code></td>
                                <td>Countdown timer end date</td>
                                <td>string (YYYY-MM-DD HH:MM:SS format)</td>
                            </tr>
                            <tr>
                                <td><code>_cirrusly_badge_custom</code></td>
                                <td>Custom badge override</td>
                                <td>string (badge slug)</td>
                            </tr>
                            <tr>
                                <td><code>_cirrusly_show_urgency</code></td>
                                <td>Display urgency indicators</td>
                                <td>bool (yes/no)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>GMC Fields</h4>
                    <table class="widefat striped">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Meta Key</th>
                                <th>Description</th>
                                <th>Data Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>_gmc_promotion_id</code></td>
                                <td>Active Google Merchant promotion ID</td>
                                <td>string</td>
                            </tr>
                            <tr>
                                <td><code>_gla_identifier_exists</code></td>
                                <td>Has GTIN/UPC (yes) or custom product (no)</td>
                                <td>string (yes/no)</td>
                            </tr>
                            <tr>
                                <td><code>_gmc_custom_label_0</code></td>
                                <td>GMC Custom Label 0 (through _4)</td>
                                <td>string</td>
                            </tr>
                            <tr>
                                <td><code>_gmc_excluded_destination</code></td>
                                <td>Excluded ad destinations</td>
                                <td>string (comma-separated)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>Analytics & Tracking (Pro Plus)</h4>
                    <table class="widefat striped">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Meta Key</th>
                                <th>Description</th>
                                <th>Data Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>_gmc_impressions</code></td>
                                <td>Google Shopping impressions (30-day)</td>
                                <td>int</td>
                            </tr>
                            <tr>
                                <td><code>_gmc_clicks</code></td>
                                <td>Google Shopping clicks (30-day)</td>
                                <td>int</td>
                            </tr>
                            <tr>
                                <td><code>_gmc_ctr</code></td>
                                <td>Click-through rate percentage</td>
                                <td>float</td>
                            </tr>
                            <tr>
                                <td><code>_gmc_conversions</code></td>
                                <td>Attributed conversions (30-day)</td>
                                <td>int</td>
                            </tr>
                            <tr>
                                <td><code>_gmc_last_sync</code></td>
                                <td>Last analytics sync timestamp</td>
                                <td>int (Unix timestamp)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>Product Studio AI Fields</h4>
                    <table class="widefat striped">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Meta Key</th>
                                <th>Description</th>
                                <th>Data Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>_cirrusly_ps_desc_style</code></td>
                                <td>Last used description style</td>
                                <td>string (balanced/professional/etc.)</td>
                            </tr>
                            <tr>
                                <td><code>_cirrusly_ps_title_strategy</code></td>
                                <td>Last used title optimization strategy</td>
                                <td>string</td>
                            </tr>
                            <tr>
                                <td><code>_cirrusly_ps_alt_style</code></td>
                                <td>Last used alt text style</td>
                                <td>string</td>
                            </tr>
                            <tr>
                                <td><code>_cirrusly_ps_last_enhanced</code></td>
                                <td>Timestamp of last AI enhancement</td>
                                <td>int (Unix timestamp)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>Internal System Fields</h4>
                    <table class="widefat striped">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th>Meta Key</th>
                                <th>Description</th>
                                <th>Data Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>_cirrusly_quota_used</code></td>
                                <td>API quota consumed by this product</td>
                                <td>int</td>
                            </tr>
                            <tr>
                                <td><code>_cirrusly_validation_status</code></td>
                                <td>Last GMC compliance scan result</td>
                                <td>string (pass/warning/fail)</td>
                            </tr>
                            <tr>
                                <td><code>_cirrusly_last_scan</code></td>
                                <td>Last compliance scan timestamp</td>
                                <td>int (Unix timestamp)</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>Usage Examples</h4>
                    
                    <p><strong>Feed Plugin Mapping (e.g., WooCommerce Product Feed):</strong></p>
                    <ul>
                        <li>Navigate to feed plugin settings</li>
                        <li>Create custom attribute mapping</li>
                        <li>Map <code>_alg_msrp</code> to feed field "price" or "list_price"</li>
                        <li>Map <code>_gmc_promotion_id</code> to "promotion_id" field</li>
                    </ul>
                    
                    <p><strong>Code Querying:</strong></p>
                    <pre style="background:#f6f7f7; padding:10px; border-left:3px solid #2271b1;">
// Get MSRP for product ID 123
$msrp = get_post_meta( 123, '_alg_msrp', true );

// Query products with MSRP over $100
$args = array(
    'post_type' => 'product',
    'meta_query' => array(
        array(
            'key' => '_alg_msrp',
            'value' => 100,
            'compare' => '>',
            'type' => 'NUMERIC'
        )
    )
);
$products = get_posts( $args );
                    </pre>
                    
                    <p><strong>Programmatic Meta Updates:</strong></p>
                    <pre style="background:#f6f7f7; padding:10px; border-left:3px solid #2271b1;">
// Set Google Min Price to 80% of MSRP
$product_id = 123;
$msrp = get_post_meta( $product_id, '_alg_msrp', true );
$min_price = $msrp * 0.80;
update_post_meta( $product_id, '_auto_pricing_min_price', $min_price );
                    </pre>
                </div>

                <hr>

                <div id="troubleshooting" class="cc-manual-section" style="margin-bottom: 50px;">
                    <h3><span class="dashicons dashicons-sos" style="color:#2271b1;"></span> Troubleshooting</h3>
                    
                    <h4>Common Issues & Solutions</h4>
                    <table class="widefat striped">
                        <thead>
                            <tr style="background:#f0f0f1;">
                                <th style="width:40%;">Issue</th>
                                <th>Solution</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Q: Why doesn't Cirrusly use WooCommerce native fields for everything?</strong></td>
                                <td>WooCommerce doesn't have native fields for MSRP, MAP, or Google-specific requirements. Using custom meta keys ensures compatibility with all themes and avoids conflicts with other pricing plugins.</td>
                            </tr>
                            <tr>
                                <td><strong>Q: I can't save my product - it says there are errors</strong></td>
                                <td>The "Block Save" workflow rule is enabled (Settings > Automation > Workflow Rules). Either fix the flagged compliance issues or disable this rule to save products with warnings.</td>
                            </tr>
                            <tr>
                                <td><strong>Q: API Sync shows "Inactive" status</strong></td>
                                <td>
                                    <ol style="margin:5px 0;">
                                        <li>Verify Service Account JSON uploaded (Settings > General)</li>
                                        <li>Check Google Cloud Project has required APIs enabled</li>
                                        <li>Confirm Service Account has "Content API for Shopping" permissions</li>
                                        <li>Test connection using "Validate Setup" button in Setup Wizard</li>
                                    </ol>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Q: No profit margins are showing in Financial Audit</strong></td>
                                <td>
                                    <ol style="margin:5px 0;">
                                        <li>Enable COGS: WooCommerce > Settings > Products > Enable "Cost of Goods Sold"</li>
                                        <li>Enter cost values in product edit screen (Inventory tab)</li>
                                        <li>Verify Profit Engine configured (Settings > Profit Intelligence)</li>
                                        <li>Check that products have prices set</li>
                                    </ol>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Q: MSRP field not appearing in my product feed</strong></td>
                                <td>Map <code>_alg_msrp</code> meta key in your feed plugin settings. Example for WooCommerce Product Feed: Add Custom Field → Name: "MSRP" → Value: "Static Field" → Meta Key: "_alg_msrp"</td>
                            </tr>
                            <tr>
                                <td><strong>Q: MSRP not showing on product page</strong></td>
                                <td>
                                    <ol style="margin:5px 0;">
                                        <li>Enable display: Settings > Visual Settings > "Show MSRP Strikethrough"</li>
                                        <li>Enter MSRP value in product edit screen</li>
                                        <li>Check theme compatibility (Settings > Developer Tools > Theme Compatibility Test)</li>
                                        <li>If using page builder, add MSRP block manually</li>
                                    </ol>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Q: How do I upgrade from Free to Pro?</strong></td>
                                <td>Navigate to Cirrusly Commerce > Settings > License tab. Enter your new license key and click "Activate". Features unlock instantly (no plugin reinstall needed).</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h4>Need Help?</h4>
                    <p>Can't find the answer here? Contact support:</p>
                    <ul>
                        <li><strong>Email:</strong> help@cirruslyweather.com</li>
                        <li><strong>Response Time:</strong> Within 24 hours (weekdays)</li>
                        <li><strong>Documentation:</strong> <a href="https://commerce.cirruslyweather.com/documentation.html" target="_blank" rel="noopener noreferrer">Online Knowledge Base</a></li>
                    </ul>
                </div>

            </div>
        </div>
    </div>
</div>