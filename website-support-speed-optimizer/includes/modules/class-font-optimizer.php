<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Prevents layout shifts caused by web font loading (FOUT/FOIT).
 * Uses CSS-only approach to force font-display: swap.
 */
class Font_Optimizer implements Optimizer {
    private Settings $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (is_admin() || Settings::is_page_builder_context()) {
            return;
        }

        // Output font-display fix very early in head
        add_action('wp_head', [$this, 'output_font_display_css'], 1);
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('style_optimizer');
    }

    public function output_font_display_css(): void {
        // Force font-display: swap using CSS @font-face override technique
        // By declaring font-face with same family name, browsers merge the descriptors
        ?>
        <style id="hbs-font-display">
        /* Force font-display: swap on all web fonts */
        @font-face { font-family: 'ETmodules'; font-display: swap; }
        @font-face { font-family: 'Font Awesome 6 Free'; font-display: swap; }
        @font-face { font-family: 'Font Awesome 6 Brands'; font-display: swap; }
        @font-face { font-family: 'Font Awesome 5 Free'; font-display: swap; }
        @font-face { font-family: 'Font Awesome 5 Brands'; font-display: swap; }
        @font-face { font-family: 'FontAwesome'; font-display: swap; }
        @font-face { font-family: 'Roboto'; font-display: swap; }
        @font-face { font-family: 'Open Sans'; font-display: swap; }
        @font-face { font-family: 'Nunito Sans'; font-display: swap; }
        @font-face { font-family: 'Lato'; font-display: swap; }
        @font-face { font-family: 'Montserrat'; font-display: swap; }
        @font-face { font-family: 'Poppins'; font-display: swap; }
        @font-face { font-family: 'Raleway'; font-display: swap; }
        @font-face { font-family: 'Source Sans Pro'; font-display: swap; }
        @font-face { font-family: 'Oswald'; font-display: swap; }
        @font-face { font-family: 'Merriweather'; font-display: swap; }
        @font-face { font-family: 'PT Sans'; font-display: swap; }
        @font-face { font-family: 'Ubuntu'; font-display: swap; }
        @font-face { font-family: 'Playfair Display'; font-display: swap; }
        @font-face { font-family: 'Noto Sans'; font-display: swap; }
        </style>
        <?php
    }
}
