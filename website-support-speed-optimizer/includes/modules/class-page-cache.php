<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

class Page_Cache implements Optimizer {
    private Settings $settings;
    private string $cache_dir;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
        $this->cache_dir = HBS_PSO_CACHE_DIR;
    }

    public function init(): void {
        if ($this->should_skip_cache()) {
            return;
        }

        $cached = $this->serve_cached_page();
        if ($cached) {
            exit;
        }

        ob_start([$this, 'cache_output']);
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('page_cache');
    }

    private function should_skip_cache(): bool {
        if (is_admin() || Settings::is_page_builder_context()) {
            return true;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return true;
        }

        if (defined('DOING_CRON') && DOING_CRON) {
            return true;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return true;
        }

        if (is_user_logged_in()) {
            return true;
        }

        if (!empty($_GET)) {
            $allowed_params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid'];
            foreach (array_keys($_GET) as $param) {
                if (!in_array($param, $allowed_params, true)) {
                    return true;
                }
            }
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $excluded = $this->settings->get_excluded_urls();
        
        $default_excluded = ['/wp-admin', '/wp-login', '/wp-cron', '/cart', '/checkout', '/my-account', '/xmlrpc.php', '/wp-json'];
        $excluded = array_merge($excluded, $default_excluded);

        foreach ($excluded as $pattern) {
            if (strpos($request_uri, $pattern) !== false) {
                return true;
            }
        }

        if (isset($_COOKIE['woocommerce_items_in_cart']) || isset($_COOKIE['wordpress_logged_in_'])) {
            return true;
        }

        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'wordpress_logged_in') === 0) {
                return true;
            }
        }

        return false;
    }

    private function get_cache_key(): string {
        $url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $url = preg_replace('/[?&](utm_[^&]+|fbclid|gclid)=[^&]*/i', '', $url);
        $url = rtrim($url, '?&');
        return md5($url);
    }

    private function get_cache_file(): string {
        return $this->cache_dir . $this->get_cache_key() . '.html';
    }

    private function serve_cached_page(): bool {
        $cache_file = $this->get_cache_file();
        
        if (!file_exists($cache_file)) {
            return false;
        }

        $ttl = (int) $this->settings->get('cache_ttl', 3600);
        $age = time() - filemtime($cache_file);
        
        if ($age > $ttl) {
            @unlink($cache_file);
            return false;
        }

        $content = file_get_contents($cache_file);
        if ($content === false) {
            return false;
        }

        header('X-HBS-Cache: HIT');
        header('X-HBS-Cache-Age: ' . $age);
        header('Cache-Control: public, max-age=' . ($ttl - $age));
        
        echo $content;
        return true;
    }

    public function cache_output(string $html): string {
        if (http_response_code() !== 200) {
            return $html;
        }

        if (strlen($html) < 255) {
            return $html;
        }

        if (strpos($html, '</html>') === false) {
            return $html;
        }

        if (!is_dir($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }

        $timestamp = gmdate('Y-m-d H:i:s') . ' UTC';
        $html .= "\n<!-- Cached by HBS PageSpeed Optimizer on {$timestamp} -->";

        $cache_file = $this->get_cache_file();
        file_put_contents($cache_file, $html, LOCK_EX);

        header('X-HBS-Cache: MISS');
        
        return $html;
    }
}
