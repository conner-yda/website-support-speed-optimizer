<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

class Image_Optimizer implements Optimizer {
    private Settings $settings;
    private int $image_count = 0;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (is_admin() || Settings::is_page_builder_context()) {
            return;
        }

        add_filter('the_content', [$this, 'optimize_content_images'], 999);
        add_filter('post_thumbnail_html', [$this, 'optimize_image_tag'], 999, 5);
        add_filter('wp_get_attachment_image_attributes', [$this, 'add_image_attributes'], 999, 3);
        add_action('wp_head', [$this, 'preload_lcp_image'], 1);
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('image_optimizer');
    }

    public function optimize_content_images(string $content): string {
        if (empty($content)) {
            return $content;
        }

        $content = preg_replace_callback(
            '/<img\s+([^>]*)>/i',
            [$this, 'process_image_tag'],
            $content
        );

        return $content;
    }

    public function optimize_image_tag(string $html, int $post_id = 0, $post_thumbnail_id = 0, $size = '', $attr = ''): string {
        return preg_replace_callback(
            '/<img\s+([^>]*)>/i',
            [$this, 'process_image_tag'],
            $html
        );
    }

    private function process_image_tag(array $matches): string {
        $this->image_count++;
        $tag = $matches[0];
        $attrs = $matches[1];

        $is_above_fold = $this->image_count <= 2;
        $is_lcp = $this->is_lcp_image($attrs);

        if (strpos($attrs, 'loading=') === false) {
            if ($is_above_fold || $is_lcp) {
                $tag = str_replace('<img', '<img loading="eager"', $tag);
            } else {
                $tag = str_replace('<img', '<img loading="lazy"', $tag);
            }
        }

        if (strpos($attrs, 'decoding=') === false) {
            $tag = str_replace('<img', '<img decoding="async"', $tag);
        }

        if ($is_lcp && strpos($attrs, 'fetchpriority=') === false) {
            $tag = str_replace('<img', '<img fetchpriority="high"', $tag);
        }

        if (strpos($attrs, 'width=') === false || strpos($attrs, 'height=') === false) {
            $tag = $this->add_dimensions($tag);
        }

        return $tag;
    }

    private function is_lcp_image(string $attrs): bool {
        $lcp_selector = $this->settings->get('lcp_image_selector', '');
        
        if (empty($lcp_selector)) {
            return $this->image_count === 1;
        }

        $patterns = array_map('trim', explode(',', $lcp_selector));
        
        foreach ($patterns as $pattern) {
            $pattern = trim($pattern, '.');
            if (strpos($attrs, $pattern) !== false) {
                return true;
            }
            
            if (preg_match('/class=["\'][^"\']*' . preg_quote($pattern, '/') . '[^"\']*["\']/i', $attrs)) {
                return true;
            }
        }

        return false;
    }

    private function add_dimensions(string $tag): string {
        if (preg_match('/src=["\']([^"\']+)["\']/i', $tag, $src_match)) {
            $src = $src_match[1];
            
            $attachment_id = attachment_url_to_postid($src);
            if ($attachment_id) {
                $metadata = wp_get_attachment_metadata($attachment_id);
                if ($metadata && isset($metadata['width'], $metadata['height'])) {
                    if (strpos($tag, 'width=') === false) {
                        $tag = str_replace('<img', '<img width="' . $metadata['width'] . '"', $tag);
                    }
                    if (strpos($tag, 'height=') === false) {
                        $tag = str_replace('<img', '<img height="' . $metadata['height'] . '"', $tag);
                    }
                }
            }
        }

        return $tag;
    }

    public function add_image_attributes(array $attr, $attachment, $size): array {
        if (!isset($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }
        
        if (!isset($attr['decoding'])) {
            $attr['decoding'] = 'async';
        }

        return $attr;
    }

    public function preload_lcp_image(): void {
        $lcp_selector = $this->settings->get('lcp_image_selector', '');
        
        if (empty($lcp_selector)) {
            return;
        }
        
        echo "<!-- HBS PSO: LCP image should be preloaded via theme or specified URL -->\n";
    }
}
