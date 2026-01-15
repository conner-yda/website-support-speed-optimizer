<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Implements Chrome's Speculation Rules API for instant page navigations.
 * Prerenders internal links on hover for near-instant page loads.
 */
class Speculation_Rules implements Optimizer {
    private Settings $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (is_admin()) {
            return;
        }

        add_action('wp_footer', [$this, 'output_speculation_rules'], 100);
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('speculation_rules');
    }

    public function output_speculation_rules(): void {
        $eagerness = $this->settings->get('speculation_eagerness', 'moderate');
        $excluded_paths = $this->get_excluded_paths();
        
        $rules = $this->build_rules($eagerness, $excluded_paths);
        
        ?>
        <script type="speculationrules">
        <?php echo wp_json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
        </script>
        <?php
    }

    private function build_rules(string $eagerness, array $excluded_paths): array {
        $where_conditions = [
            ['href_matches', '/*'],
            ['not', ['href_matches', '\\?*']], // Exclude URLs with query strings
            ['not', ['selector_matches', '[target="_blank"]']], // Exclude external links
            ['not', ['selector_matches', '[download]']], // Exclude downloads
            ['not', ['selector_matches', '.no-prerender']], // Allow opt-out class
        ];

        // Add excluded paths
        foreach ($excluded_paths as $path) {
            $where_conditions[] = ['not', ['href_matches', $path]];
        }

        // Build the "and" condition
        $and_conditions = [];
        foreach ($where_conditions as $condition) {
            if ($condition[0] === 'not') {
                $and_conditions[] = ['not' => [$condition[1][0] => $condition[1][1]]];
            } else {
                $and_conditions[] = [$condition[0] => $condition[1]];
            }
        }

        return [
            'prefetch' => [
                [
                    'source' => 'document',
                    'where' => ['and' => $and_conditions],
                    'eagerness' => $eagerness,
                ]
            ],
            'prerender' => [
                [
                    'source' => 'document',
                    'where' => ['and' => $and_conditions],
                    'eagerness' => $eagerness,
                ]
            ]
        ];
    }

    private function get_excluded_paths(): array {
        $defaults = [
            '/wp-admin/*',
            '/wp-login.php',
            '/wp-json/*',
            '/feed/*',
            '/cart/*',
            '/checkout/*',
            '/my-account/*',
            '/logout/*',
            '/login/*',
            '/register/*',
            '/*\\?add-to-cart=*',
            '/*\\?remove_item=*',
        ];

        // Add user-defined excluded URLs
        $custom_excluded = $this->settings->get_excluded_urls();
        foreach ($custom_excluded as $url) {
            $url = trim($url, '/');
            if (!empty($url)) {
                $defaults[] = '/' . $url . '*';
            }
        }

        return array_unique($defaults);
    }
}
