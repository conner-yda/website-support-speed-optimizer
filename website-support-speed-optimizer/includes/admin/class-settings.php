<?php
namespace HBS_PSO\Admin;

defined('ABSPATH') || exit;

class Settings {
    private const OPTION_KEY = 'hbs_pso_settings';
    private array $defaults = [
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
        'defer_js' => true,
        'critical_css' => '',
        'preconnect_urls' => '',
        'lcp_image_selector' => '',
        'speculation_eagerness' => 'moderate',
    ];

    public function get(string $key, $default = null) {
        $options = get_option(self::OPTION_KEY, $this->defaults);
        return $options[$key] ?? $default ?? ($this->defaults[$key] ?? null);
    }

    public function get_all(): array {
        return wp_parse_args(get_option(self::OPTION_KEY, []), $this->defaults);
    }

    public function set(string $key, $value): bool {
        $options = $this->get_all();
        $options[$key] = $value;
        return update_option(self::OPTION_KEY, $options);
    }

    public function update(array $values): bool {
        $options = $this->get_all();
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $this->defaults)) {
                $options[$key] = $value;
            }
        }
        return update_option(self::OPTION_KEY, $options);
    }

    public function is_module_enabled(string $module): bool {
        return (bool) $this->get($module, false);
    }

    public function get_excluded_urls(): array {
        $urls = $this->get('excluded_urls', '');
        if (empty($urls)) {
            return [];
        }
        return array_filter(array_map('trim', explode("\n", $urls)));
    }

    public function get_preconnect_urls(): array {
        $urls = $this->get('preconnect_urls', '');
        if (empty($urls)) {
            return [];
        }
        return array_filter(array_map('trim', explode("\n", $urls)));
    }

    public function clear_cache(): void {
        $cache_dir = HBS_PSO_CACHE_DIR;
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*.html');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
