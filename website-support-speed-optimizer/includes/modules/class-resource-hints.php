<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

class Resource_Hints implements Optimizer {
    private Settings $settings;

    private array $default_preconnects = [
        'https://use.typekit.net',
        'https://p.typekit.net',
        'https://cdnjs.cloudflare.com',
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
    ];

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (is_admin() || Settings::is_page_builder_context()) {
            return;
        }

        // Output very early in head
        add_action('wp_head', [$this, 'output_resource_hints'], 0);
        add_filter('wp_resource_hints', [$this, 'filter_resource_hints'], 10, 2);
        
        // Intercept and modify TypeKit loading
        add_action('wp_head', [$this, 'async_typekit'], 0);
        remove_action('wp_head', 'et_builder_maybe_include_adobe_fonts', 3);
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('resource_hints');
    }

    public function output_resource_hints(): void {
        $urls = $this->get_preconnect_urls();

        foreach ($urls as $url) {
            $url = esc_url($url);
            if (empty($url)) {
                continue;
            }
            // dns-prefetch first (works in all browsers)
            echo '<link rel="dns-prefetch" href="' . $url . '">' . "\n";
            // preconnect with crossorigin for fonts/CSS
            echo '<link rel="preconnect" href="' . $url . '" crossorigin>' . "\n";
        }
    }

    private function get_preconnect_urls(): array {
        $custom_urls = $this->settings->get_preconnect_urls();
        $all_urls = array_merge($this->default_preconnects, $custom_urls);
        return array_unique(array_filter($all_urls));
    }

    public function filter_resource_hints(array $hints, string $relation): array {
        if ($relation === 'preconnect') {
            $urls = $this->get_preconnect_urls();
            foreach ($urls as $url) {
                $hints[] = [
                    'href' => $url,
                    'crossorigin' => 'anonymous',
                ];
            }
        }

        return $hints;
    }

    public function async_typekit(): void {
        // Check if TypeKit is being used by checking for Adobe Fonts options
        $adobe_fonts = get_option('et_adobe_fonts_api_key', '');
        if (empty($adobe_fonts)) {
            // Try to find TypeKit ID from theme settings
            $typekit_id = $this->detect_typekit_id();
            if (empty($typekit_id)) {
                return;
            }
        }
        
        $typekit_id = !empty($adobe_fonts) ? $adobe_fonts : $this->detect_typekit_id();
        if (empty($typekit_id)) {
            return;
        }

        // Output async TypeKit loader
        ?>
        <script id="hbs-typekit-loader">
        (function(d){
            var tk=d.createElement('link');
            tk.rel='stylesheet';
            tk.href='https://use.typekit.net/<?php echo esc_js($typekit_id); ?>.css';
            tk.media='print';
            tk.onload=function(){this.media='all';};
            d.head.appendChild(tk);
        })(document);
        </script>
        <noscript><link rel="stylesheet" href="https://use.typekit.net/<?php echo esc_attr($typekit_id); ?>.css"></noscript>
        <?php
    }

    private function detect_typekit_id(): string {
        // Common Divi option names for TypeKit
        $options = [
            'et_adobe_fonts_api_key',
            'divi_typekit_id', 
            'typekit_id',
        ];
        
        foreach ($options as $option) {
            $value = get_option($option, '');
            if (!empty($value)) {
                return $value;
            }
        }

        // Check theme mods
        $theme_mods = get_theme_mods();
        if (!empty($theme_mods['typekit_id'])) {
            return $theme_mods['typekit_id'];
        }

        return '';
    }
}
