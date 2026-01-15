<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

class HTML_Minifier implements Optimizer {
    private Settings $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (is_admin()) {
            return;
        }

        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        add_action('template_redirect', [$this, 'start_buffer'], 0);
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('html_minifier');
    }

    public function start_buffer(): void {
        ob_start([$this, 'minify_html']);
    }

    public function minify_html(string $html): string {
        if (empty($html)) {
            return $html;
        }

        if (strpos($html, '</html>') === false) {
            return $html;
        }

        $html = $this->remove_comments($html);
        $html = $this->remove_whitespace($html);

        return $html;
    }

    private function remove_comments(string $html): string {
        $html = preg_replace('/<!--(?!\s*\[if)(?!-->).*?-->/s', '', $html);
        return $html;
    }

    private function remove_whitespace(string $html): string {
        $protected = [];
        $index = 0;

        $html = preg_replace_callback(
            '/<(script|style|pre|textarea|code)[^>]*>.*?<\/\1>/is',
            function($matches) use (&$protected, &$index) {
                $placeholder = "<!--HBS_PROTECTED_{$index}-->";
                $protected[$placeholder] = $matches[0];
                $index++;
                return $placeholder;
            },
            $html
        );

        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        $html = preg_replace('/\s+>/', '>', $html);
        $html = preg_replace('/<\s+/', '<', $html);

        foreach ($protected as $placeholder => $content) {
            $html = str_replace($placeholder, $content, $html);
        }

        return trim($html);
    }
}
