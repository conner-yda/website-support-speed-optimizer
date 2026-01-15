<?php
namespace HBS_PSO\Admin;

defined('ABSPATH') || exit;

class Admin_Page {
    private Settings $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_post_hbs_pso_clear_cache', [$this, 'handle_clear_cache']);
    }

    public function add_menu(): void {
        add_options_page(
            'Speed Optimizer',
            'Speed Optimizer',
            'manage_options',
            'website-support-speed',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void {
        register_setting('hbs_pso_settings', 'hbs_pso_settings', [
            'sanitize_callback' => [$this, 'sanitize_settings']
        ]);
    }

    public function sanitize_settings(array $input): array {
        $sanitized = [];
        
        $bool_fields = ['page_cache', 'script_optimizer', 'style_optimizer', 'image_optimizer', 'resource_hints', 'html_minifier', 'speculation_rules', 'delay_js', 'defer_js'];
        foreach ($bool_fields as $field) {
            $sanitized[$field] = !empty($input[$field]);
        }
        
        $sanitized['cache_ttl'] = absint($input['cache_ttl'] ?? 3600);
        $sanitized['excluded_urls'] = sanitize_textarea_field($input['excluded_urls'] ?? '');
        $sanitized['critical_css'] = wp_strip_all_tags($input['critical_css'] ?? '');
        $sanitized['preconnect_urls'] = sanitize_textarea_field($input['preconnect_urls'] ?? '');
        $sanitized['lcp_image_selector'] = sanitize_text_field($input['lcp_image_selector'] ?? '');
        $sanitized['speculation_eagerness'] = in_array($input['speculation_eagerness'] ?? 'moderate', ['immediate', 'eager', 'moderate', 'conservative']) 
            ? $input['speculation_eagerness'] 
            : 'moderate';
        
        $this->settings->clear_cache();
        
        return $sanitized;
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'settings_page_website-support-speed') {
            return;
        }
        wp_enqueue_style('hbs-pso-admin', HBS_PSO_URL . 'assets/css/admin.css', [], HBS_PSO_VERSION);
    }

    public function handle_clear_cache(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('hbs_pso_clear_cache');
        $this->settings->clear_cache();
        wp_redirect(add_query_arg('cache_cleared', '1', admin_url('options-general.php?page=website-support-speed')));
        exit;
    }

    public function render_page(): void {
        $options = $this->settings->get_all();
        $cache_cleared = isset($_GET['cache_cleared']);
        $cache_size = $this->settings->get_cache_size();
        $cache_count = $this->settings->get_cache_file_count();
        $last_clear = $this->settings->get_last_cache_clear();
        ?>
        <div class="wrap hbs-pso-admin">
            <h1>Website Support Speed Optimizer</h1>
            
            <?php if ($cache_cleared): ?>
            <div class="notice notice-success is-dismissible"><p>Cache cleared successfully.</p></div>
            <?php endif; ?>
            
            <div class="hbs-pso-header">
                <p>Performance optimization targeting Core Web Vitals: TTFB, FCP, LCP, and CLS.</p>
                <div class="hbs-pso-cache-actions">
                    <span class="hbs-pso-cache-stats">
                        <span class="hbs-pso-cache-stat">
                            <strong><?php echo esc_html($cache_count); ?></strong> cached pages
                        </span>
                        <span class="hbs-pso-cache-stat">
                            <strong><?php echo esc_html($this->settings->format_size($cache_size)); ?></strong>
                        </span>
                        <?php if ($last_clear): ?>
                        <span class="hbs-pso-cache-stat hbs-pso-cache-cleared">
                            Last cleared: <strong><?php echo esc_html(human_time_diff($last_clear, time()) . ' ago'); ?></strong>
                        </span>
                        <?php endif; ?>
                    </span>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                        <?php wp_nonce_field('hbs_pso_clear_cache'); ?>
                        <input type="hidden" name="action" value="hbs_pso_clear_cache">
                        <button type="submit" class="button">Clear Cache</button>
                    </form>
                </div>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('hbs_pso_settings'); ?>
                
                <div class="hbs-pso-grid">
                    <div class="hbs-pso-card">
                        <h2>Page Cache</h2>
                        <p>Full-page HTML caching reduces TTFB dramatically.</p>
                        <label>
                            <input type="checkbox" name="hbs_pso_settings[page_cache]" value="1" <?php checked($options['page_cache']); ?>>
                            Enable Page Cache
                        </label>
                        <div class="hbs-pso-field">
                            <label>Cache TTL (seconds)</label>
                            <input type="number" name="hbs_pso_settings[cache_ttl]" value="<?php echo esc_attr($options['cache_ttl']); ?>" min="60" max="86400">
                        </div>
                        <div class="hbs-pso-field">
                            <label>Excluded URLs (one per line)</label>
                            <textarea name="hbs_pso_settings[excluded_urls]" rows="4" placeholder="/cart&#10;/checkout&#10;/my-account"><?php echo esc_textarea($options['excluded_urls']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="hbs-pso-card">
                        <h2>Script Optimizer</h2>
                        <p>Defer and delay JavaScript to improve FCP and reduce TBT.</p>
                        <label>
                            <input type="checkbox" name="hbs_pso_settings[script_optimizer]" value="1" <?php checked($options['script_optimizer']); ?>>
                            Enable Script Optimizer
                        </label>
                        <label>
                            <input type="checkbox" name="hbs_pso_settings[defer_js]" value="1" <?php checked($options['defer_js']); ?>>
                            Add defer to scripts
                        </label>
                        <label>
                            <input type="checkbox" name="hbs_pso_settings[delay_js]" value="1" <?php checked($options['delay_js']); ?>>
                            Delay non-critical JS until user interaction
                        </label>
                    </div>
                    
                    <div class="hbs-pso-card">
                        <h2>Style Optimizer</h2>
                        <p>Defers ALL render-blocking CSS and inlines critical styles. Eliminates FOUC with print media technique.</p>
                        <label>
                            <input type="checkbox" name="hbs_pso_settings[style_optimizer]" value="1" <?php checked($options['style_optimizer']); ?>>
                            Enable Style Optimizer (defers all CSS)
                        </label>
                        <div class="hbs-pso-field">
                            <label>Critical CSS (above-the-fold)</label>
                            <textarea name="hbs_pso_settings[critical_css]" rows="6" placeholder="/* Leave empty to use built-in Divi critical CSS */"><?php echo esc_textarea($options['critical_css']); ?></textarea>
                            <p class="description">Leave empty to use default critical CSS optimized for Divi themes.</p>
                        </div>
                    </div>
                    
                    <div class="hbs-pso-card">
                        <h2>Image Optimizer</h2>
                        <p>Lazy-load images and prioritize LCP image loading.</p>
                        <label>
                            <input type="checkbox" name="hbs_pso_settings[image_optimizer]" value="1" <?php checked($options['image_optimizer']); ?>>
                            Enable Image Optimizer
                        </label>
                        <div class="hbs-pso-field">
                            <label>LCP Image CSS Selector (optional)</label>
                            <input type="text" name="hbs_pso_settings[lcp_image_selector]" value="<?php echo esc_attr($options['lcp_image_selector']); ?>" placeholder=".hero-image, .banner img">
                            <p class="description">Images matching this selector get fetchpriority="high" and skip lazy-load.</p>
                        </div>
                    </div>
                    
                    <div class="hbs-pso-card">
                        <h2>Resource Hints</h2>
                        <p>Preconnect to external domains to reduce connection time.</p>
                        <label>
                            <input type="checkbox" name="hbs_pso_settings[resource_hints]" value="1" <?php checked($options['resource_hints']); ?>>
                            Enable Resource Hints
                        </label>
                        <div class="hbs-pso-field">
                            <label>Preconnect URLs (one per line)</label>
                            <textarea name="hbs_pso_settings[preconnect_urls]" rows="4" placeholder="https://use.typekit.net&#10;https://cdnjs.cloudflare.com"><?php echo esc_textarea($options['preconnect_urls']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="hbs-pso-card">
                        <h2>HTML Minifier</h2>
                        <p>Remove whitespace and comments from HTML output.</p>
                        <label>
                            <input type="checkbox" name="hbs_pso_settings[html_minifier]" value="1" <?php checked($options['html_minifier']); ?>>
                            Enable HTML Minifier
                        </label>
                    </div>
                    
                    <div class="hbs-pso-card">
                        <h2>Instant Page Navigation</h2>
                        <p>Uses Chrome's Speculation Rules API to prerender pages before click, making navigation feel instant.</p>
                        <label>
                            <input type="checkbox" name="hbs_pso_settings[speculation_rules]" value="1" <?php checked($options['speculation_rules']); ?>>
                            Enable Speculation Rules (Chrome 121+)
                        </label>
                        <div class="hbs-pso-field">
                            <label>Prerender Eagerness</label>
                            <select name="hbs_pso_settings[speculation_eagerness]">
                                <option value="conservative" <?php selected($options['speculation_eagerness'], 'conservative'); ?>>Conservative (on mousedown/touchstart)</option>
                                <option value="moderate" <?php selected($options['speculation_eagerness'], 'moderate'); ?>>Moderate (on hover) - Recommended</option>
                                <option value="eager" <?php selected($options['speculation_eagerness'], 'eager'); ?>>Eager (slight delay after page load)</option>
                                <option value="immediate" <?php selected($options['speculation_eagerness'], 'immediate'); ?>>Immediate (on page load)</option>
                            </select>
                            <p class="description">Controls when prerendering begins. "Moderate" prerenders on hover for best balance.</p>
                        </div>
                    </div>
                </div>
                
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
}
