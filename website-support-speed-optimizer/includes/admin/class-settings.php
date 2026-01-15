<?php
namespace HBS_PSO\Admin;

defined('ABSPATH') || exit;

class Settings {
    private const OPTION_KEY = 'hbs_pso_settings';
    private const CACHE_CLEAR_KEY = 'hbs_pso_last_cache_clear';

    // Detects Divi Visual Builder, Elementor, Beaver Builder, and other page builders
    public static function is_page_builder_context(): bool {
        // Divi Visual Builder
        if (isset($_GET['et_fb']) && $_GET['et_fb'] === '1') {
            return true;
        }

        // Explicit PageSpeed bypass parameter
        if (isset($_GET['PageSpeed']) && strtolower($_GET['PageSpeed']) === 'off') {
            return true;
        }

        // Divi Theme Builder
        if (isset($_GET['et_pb_preview']) && $_GET['et_pb_preview'] === 'true') {
            return true;
        }

        // Elementor editor/preview
        if (isset($_GET['elementor-preview']) || isset($_GET['elementor_library'])) {
            return true;
        }
        if (class_exists('\Elementor\Plugin')) {
            $elementor = \Elementor\Plugin::$instance;
            if (isset($elementor->preview) && method_exists($elementor->preview, 'is_preview_mode') && $elementor->preview->is_preview_mode()) {
                return true;
            }
        }

        // Beaver Builder
        if (isset($_GET['fl_builder']) || isset($_GET['fl_builder_preview'])) {
            return true;
        }

        // WPBakery (Visual Composer)
        if (isset($_GET['vc_editable']) || isset($_GET['vc_action'])) {
            return true;
        }

        // Oxygen Builder
        if (isset($_GET['ct_builder']) || isset($_GET['oxygen_iframe'])) {
            return true;
        }

        // Bricks Builder
        if (isset($_GET['bricks']) && $_GET['bricks'] === 'run') {
            return true;
        }

        // WordPress Customizer
        if (is_customize_preview()) {
            return true;
        }

        return false;
    }

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
        update_option(self::CACHE_CLEAR_KEY, time());
    }

    public function get_cache_size(): int {
        $cache_dir = HBS_PSO_CACHE_DIR;
        $size = 0;
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*.html');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size += filesize($file);
                }
            }
        }
        return $size;
    }

    public function get_cache_file_count(): int {
        $cache_dir = HBS_PSO_CACHE_DIR;
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '*.html');
            return $files ? count($files) : 0;
        }
        return 0;
    }

    public function get_last_cache_clear(): ?int {
        $timestamp = get_option(self::CACHE_CLEAR_KEY);
        return $timestamp ? (int) $timestamp : null;
    }

    public function format_size(int $bytes): string {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
