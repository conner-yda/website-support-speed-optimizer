<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Catches and defers ALL render-blocking CSS/JS at the HTML output level.
 * Works as a safety net for resources not caught by wp_enqueue filters.
 */
class Render_Blocker implements Optimizer {
    private Settings $settings;
    
    // CSS files to keep render-blocking (truly critical)
    private array $critical_css_patterns = [];
    
    // JS files to keep render-blocking
    private array $critical_js_patterns = [
        'jquery.min.js',
        'jquery.js',
    ];

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            return;
        }

        // Run at template_redirect with high priority to wrap output
        add_action('template_redirect', [$this, 'start_buffer'], -999);
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('style_optimizer');
    }

    public function start_buffer(): void {
        ob_start([$this, 'process_html']);
    }

    public function process_html(string $html): string {
        if (empty($html) || strpos($html, '</head>') === false) {
            return $html;
        }

        $html = $this->defer_stylesheets($html);
        $html = $this->async_typekit($html);
        $html = $this->defer_external_css($html);

        return $html;
    }

    private function defer_stylesheets(string $html): string {
        // Match all stylesheet links in the head
        $pattern = '/<link[^>]+rel=[\'"]stylesheet[\'"][^>]*>/i';
        
        $html = preg_replace_callback($pattern, function($matches) {
            $tag = $matches[0];
            
            // Skip if already deferred
            if (strpos($tag, 'data-hbs-defer') !== false) {
                return $tag;
            }
            
            // Skip if already has print media (already deferred)
            if (preg_match('/media=[\'"]print[\'"]/', $tag)) {
                return $tag;
            }
            
            // Check if this is a critical CSS file
            foreach ($this->critical_css_patterns as $pattern) {
                if (stripos($tag, $pattern) !== false) {
                    return $tag;
                }
            }
            
            // Skip inline critical CSS marker
            if (strpos($tag, 'hbs-critical') !== false) {
                return $tag;
            }
            
            // Transform to non-render-blocking using media="print" trick
            $noscript = '<noscript>' . $tag . '</noscript>';
            
            // Add media="print" onload technique
            if (preg_match('/media=[\'"]([^"\']+)[\'"]/', $tag, $media_match)) {
                $original_media = $media_match[1];
                $tag = preg_replace(
                    '/media=[\'"][^"\']+[\'"]/',
                    'media="print" onload="this.media=\'' . $original_media . '\'"',
                    $tag
                );
            } else {
                // No media attribute, add one
                $tag = str_replace('<link', '<link media="print" onload="this.media=\'all\'"', $tag);
            }
            
            $tag = str_replace('<link', '<link data-hbs-defer="1"', $tag);
            
            return $tag . $noscript;
            
        }, $html);

        return $html;
    }

    private function async_typekit(string $html): string {
        // Find TypeKit CSS links and make them non-render-blocking
        $pattern = '/<link[^>]+href=[\'"]https?:\/\/use\.typekit\.net\/[^"\']+\.css[\'"][^>]*>/i';
        
        $html = preg_replace_callback($pattern, function($matches) {
            $tag = $matches[0];
            
            // Skip if already processed
            if (strpos($tag, 'data-hbs-defer') !== false || strpos($tag, 'media="print"') !== false) {
                return $tag;
            }
            
            $noscript = '<noscript>' . $tag . '</noscript>';
            $tag = preg_replace('/media=[\'"][^"\']*[\'"]/', '', $tag);
            $tag = str_replace('<link', '<link media="print" onload="this.media=\'all\'" data-hbs-defer="1"', $tag);
            
            return $tag . $noscript;
            
        }, $html);

        // Also handle p.typekit.net tracking pixel CSS
        $pattern2 = '/<link[^>]+href=[\'"]https?:\/\/p\.typekit\.net\/[^"\']+[\'"][^>]*>/i';
        $html = preg_replace_callback($pattern2, function($matches) {
            $tag = $matches[0];
            if (strpos($tag, 'data-hbs-defer') !== false) {
                return $tag;
            }
            $noscript = '<noscript>' . $tag . '</noscript>';
            $tag = str_replace('<link', '<link media="print" onload="this.media=\'all\'" data-hbs-defer="1"', $tag);
            return $tag . $noscript;
        }, $html);

        return $html;
    }

    private function defer_external_css(string $html): string {
        // Specifically target Cloudflare CDN CSS (Font Awesome)
        $pattern = '/<link[^>]+href=[\'"]https?:\/\/cdnjs\.cloudflare\.com\/[^"\']+\.css[\'"][^>]*>/i';
        
        $html = preg_replace_callback($pattern, function($matches) {
            $tag = $matches[0];
            
            if (strpos($tag, 'data-hbs-defer') !== false || strpos($tag, 'media="print"') !== false) {
                return $tag;
            }
            
            $noscript = '<noscript>' . $tag . '</noscript>';
            $tag = preg_replace('/media=[\'"][^"\']*[\'"]/', '', $tag);
            $tag = str_replace('<link', '<link media="print" onload="this.media=\'all\'" data-hbs-defer="1"', $tag);
            
            return $tag . $noscript;
            
        }, $html);

        return $html;
    }
}
