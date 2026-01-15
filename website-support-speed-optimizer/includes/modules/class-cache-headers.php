<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Sets browser cache headers to maximize client-side caching.
 * Note: Cannot override CDN cache headers for CDN-served assets,
 * but CAN set headers for WordPress-served content and instruct
 * browsers to cache more aggressively.
 */
class Cache_Headers implements Optimizer {
    private Settings $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (is_admin()) {
            return;
        }

        add_action('send_headers', [$this, 'set_cache_headers']);
        add_action('wp_head', [$this, 'output_cache_instructions'], 0);
        add_filter('wp_headers', [$this, 'filter_headers']);
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('page_cache');
    }

    public function set_cache_headers(): void {
        if (is_user_logged_in() || is_admin()) {
            return;
        }

        // For HTML pages - cache for 1 hour, stale-while-revalidate for smooth UX
        $ttl = (int) $this->settings->get('cache_ttl', 3600);
        
        header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=86400');
        header('Vary: Accept-Encoding');
    }

    public function filter_headers(array $headers): array {
        if (is_user_logged_in()) {
            return $headers;
        }

        $ttl = (int) $this->settings->get('cache_ttl', 3600);
        $headers['Cache-Control'] = 'public, max-age=' . $ttl . ', stale-while-revalidate=86400';
        $headers['Vary'] = 'Accept-Encoding';

        return $headers;
    }

    public function output_cache_instructions(): void {
        // Output stale-while-revalidate hint for browsers that support it
        // This allows using cached content while fetching fresh content in background
        ?>
        <meta http-equiv="Cache-Control" content="public, max-age=3600, stale-while-revalidate=86400">
        <?php
    }
}
