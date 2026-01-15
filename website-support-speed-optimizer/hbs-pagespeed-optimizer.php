<?php
/**
 * Plugin Name: Website Support Speed Optimizer
 * Plugin URI: #
 * Description: Performance optimization plugin targeting TTFB, FCP, LCP, and CLS metrics.
 * Version: 1.0.4
 * Author: Website Support
 * License: GPL-2.0+
 * Text Domain: website-support
 */

defined('ABSPATH') || exit;

define('HBS_PSO_VERSION', '1.0.4');
define('HBS_PSO_PATH', plugin_dir_path(__FILE__));
define('HBS_PSO_URL', plugin_dir_url(__FILE__));
define('HBS_PSO_CACHE_DIR', WP_CONTENT_DIR . '/cache/website-support-speed/');

require_once HBS_PSO_PATH . 'includes/interface-optimizer.php';
require_once HBS_PSO_PATH . 'includes/class-plugin.php';
require_once HBS_PSO_PATH . 'includes/admin/class-settings.php';
require_once HBS_PSO_PATH . 'includes/admin/class-admin-page.php';
require_once HBS_PSO_PATH . 'includes/modules/class-page-cache.php';
require_once HBS_PSO_PATH . 'includes/modules/class-script-optimizer.php';
require_once HBS_PSO_PATH . 'includes/modules/class-style-optimizer.php';
require_once HBS_PSO_PATH . 'includes/modules/class-image-optimizer.php';
require_once HBS_PSO_PATH . 'includes/modules/class-resource-hints.php';
require_once HBS_PSO_PATH . 'includes/modules/class-html-minifier.php';
require_once HBS_PSO_PATH . 'includes/modules/class-render-blocker.php';
require_once HBS_PSO_PATH . 'includes/modules/class-font-optimizer.php';
require_once HBS_PSO_PATH . 'includes/modules/class-cls-optimizer.php';
require_once HBS_PSO_PATH . 'includes/modules/class-cache-headers.php';
require_once HBS_PSO_PATH . 'includes/modules/class-speculation-rules.php';

function hbs_pso_init() {
    $settings = new HBS_PSO\Admin\Settings();
    $plugin = new HBS_PSO\Plugin($settings);
    $plugin->init();
}
add_action('plugins_loaded', 'hbs_pso_init');

register_activation_hook(__FILE__, function() {
    if (!file_exists(HBS_PSO_CACHE_DIR)) {
        wp_mkdir_p(HBS_PSO_CACHE_DIR);
    }
    
    $htaccess = HBS_PSO_CACHE_DIR . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "deny from all\n");
    }
    
    add_option('hbs_pso_settings', [
        'page_cache' => true,
        'script_optimizer' => true,
        'style_optimizer' => true,
        'image_optimizer' => true,
        'resource_hints' => true,
        'html_minifier' => true,
        'speculation_rules' => true,
        'cache_ttl' => 3600,
        'excluded_urls' => '',
        'delay_js' => true,
        'critical_css' => '',
        'preconnect_urls' => "https://use.typekit.net\nhttps://cdnjs.cloudflare.com",
        'lcp_image_selector' => '',
        'speculation_eagerness' => 'moderate',
    ]);
});

register_deactivation_hook(__FILE__, function() {
    $cache_dir = HBS_PSO_CACHE_DIR;
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '*');
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== '.htaccess') {
                unlink($file);
            }
        }
    }
});
