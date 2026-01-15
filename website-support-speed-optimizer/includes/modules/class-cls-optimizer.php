<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Prevents Cumulative Layout Shift (CLS) by reserving space for dynamic elements.
 */
class CLS_Optimizer implements Optimizer {
    private Settings $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (is_admin() || Settings::is_page_builder_context()) {
            return;
        }

        add_action('wp_head', [$this, 'output_cls_prevention_css'], 1);
        add_filter('the_content', [$this, 'fix_image_dimensions'], 999);
        add_filter('post_thumbnail_html', [$this, 'fix_thumbnail_dimensions'], 999);
        add_action('wp_footer', [$this, 'output_cls_footer_fix'], 1);
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('image_optimizer');
    }

    public function output_cls_prevention_css(): void {
        ?>
        <style id="hbs-cls-prevention">
        /* CRITICAL: Prevent CLS from Divi section ::before overlays */
        .et_pb_section {
            position: relative !important;
            contain: layout paint style;
        }
        
        /* Force ::before to be positioned immediately without causing reflow */
        .et_pb_section::before {
            content: "" !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            z-index: 1;
            pointer-events: none;
        }
        
        /* Divi inner content wrapper */
        .et_builder_inner_content {
            contain: layout style;
        }
        
        /* Header - reserve fixed height */
        #main-header,
        .et-l--header {
            min-height: 90px;
            contain: layout style;
        }
        
        #et-top-navigation {
            min-height: 90px;
        }
        
        .et_header_style_left #et-top-navigation,
        .et_header_style_split #et-top-navigation {
            padding: 32px 0 0 0;
        }
        
        /* Logo container - prevent shift */
        #logo,
        .logo_container img,
        .et_pb_menu__logo img,
        .et_pb_menu__logo-wrap img {
            width: auto;
            height: auto;
            max-height: 80px;
            aspect-ratio: 500 / 337;
            object-fit: contain;
        }
        
        /* Hero/first sections - reserve minimum height */
        .et_pb_section:first-of-type,
        .et_pb_section_0,
        .et_pb_fullwidth_section:first-of-type {
            min-height: 400px;
        }
        
        /* Sections with backgrounds - contain paint */
        .et_pb_section.et_pb_with_background,
        .et_pb_section.et_section_regular {
            contain: layout paint style;
            min-height: 200px;
        }
        
        /* Testimonial sections */
        #testimonial,
        [id*="testimonial"],
        .et_pb_testimonial,
        .dipl_testimonial,
        .dipl_single_testimonial {
            min-height: 300px;
            contain: layout style;
        }
        
        /* Testimonial author images */
        .dipl_testimonial_author_image img,
        .et_pb_testimonial_portrait img,
        .dipl_testimonial_meta img {
            width: 150px !important;
            height: 100px !important;
            aspect-ratio: 327 / 150;
            object-fit: cover;
        }
        
        /* All images with width/height - use aspect-ratio */
        img[width][height] {
            aspect-ratio: attr(width number) / attr(height number);
            height: auto;
        }
        
        /* Force aspect ratio for common image sizes */
        img[width="327"][height="150"] {
            aspect-ratio: 327 / 150;
        }
        img[width="500"][height="337"] {
            aspect-ratio: 500 / 337;
        }
        
        /* Rows should contain their layout */
        .et_pb_row {
            contain: layout;
        }
        
        /* Prevent Web Font FOUT shifts */
        .wf-loading * {
            opacity: 0;
        }
        .wf-active *,
        .wf-inactive * {
            opacity: 1;
            transition: opacity 0.1s ease-out;
        }
        
        /* Font loading - size adjust for common fallbacks */
        body {
            font-family: 'Open Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size-adjust: 0.5;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size-adjust: 0.5;
        }
        
        /* Sliders and carousels */
        .et_pb_slider,
        .et_pb_fullwidth_slider,
        .dipl_logo_slider,
        .swiper-container {
            min-height: 100px;
            contain: layout paint;
        }
        
        /* Blurb icons - reserve space for ETmodules font */
        .et-pb-icon,
        .et_pb_blurb_container .et-pb-icon {
            min-width: 96px;
            min-height: 96px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Video placeholders */
        .et_pb_video,
        .et_pb_video_box {
            min-height: 300px;
            aspect-ratio: 16 / 9;
        }
        
        /* Counter modules */
        .et_pb_number_counter,
        .dipl_number_counter {
            min-height: 100px;
        }
        
        /* Button wrappers */
        .et_pb_button_module_wrapper {
            min-height: 50px;
        }
        
        /* Columns - prevent collapse */
        .et_pb_column {
            min-height: 1px;
        }
        
        /* Animation classes - disable until loaded */
        .et_pb_animation_off {
            animation: none !important;
            opacity: 1 !important;
        }
        
        /* Waypoint animations - start visible */
        .et-waypoint:not(.et_pb_animation_off) {
            opacity: 1;
            visibility: visible;
        }
        </style>
        <?php
    }

    public function output_cls_footer_fix(): void {
        // Remove web font loading class delay to prevent CLS
        ?>
        <script id="hbs-cls-font-fix">
        (function(){
            // Immediately show content if fonts take too long
            setTimeout(function(){
                document.documentElement.classList.remove('wf-loading');
                document.documentElement.classList.add('wf-active');
            }, 100);
        })();
        </script>
        <?php
    }

    public function fix_image_dimensions(string $content): string {
        if (empty($content)) {
            return $content;
        }

        // Add inline aspect-ratio style to images with width/height
        $content = preg_replace_callback(
            '/<img\s+([^>]*?)width=["\'](\d+)["\']([^>]*?)height=["\'](\d+)["\']([^>]*?)>/i',
            [$this, 'add_aspect_ratio_to_img'],
            $content
        );

        // Handle reverse order (height before width)
        $content = preg_replace_callback(
            '/<img\s+([^>]*?)height=["\'](\d+)["\']([^>]*?)width=["\'](\d+)["\']([^>]*?)>/i',
            function($matches) {
                // Swap height and width for aspect ratio calculation
                $reordered = [
                    $matches[0],
                    $matches[1],
                    $matches[4], // width
                    $matches[3],
                    $matches[2], // height
                    $matches[5]
                ];
                return $this->add_aspect_ratio_to_img($reordered);
            },
            $content
        );

        return $content;
    }

    private function add_aspect_ratio_to_img(array $matches): string {
        $full_tag = $matches[0];
        $width = (int) $matches[2];
        $height = (int) $matches[4];
        
        if ($width <= 0 || $height <= 0) {
            return $full_tag;
        }
        
        // Skip if already has aspect-ratio
        if (stripos($full_tag, 'aspect-ratio') !== false) {
            return $full_tag;
        }
        
        $aspect_style = "aspect-ratio: {$width}/{$height}; height: auto;";
        
        // Check if style attribute exists
        if (preg_match('/style=["\']([^"\']*)["\']/', $full_tag, $style_match)) {
            $existing_style = rtrim($style_match[1], ';');
            $new_style = $existing_style . '; ' . $aspect_style;
            return preg_replace('/style=["\'][^"\']*["\']/', 'style="' . $new_style . '"', $full_tag);
        }
        
        // Add new style attribute
        return str_replace('<img', '<img style="' . $aspect_style . '"', $full_tag);
    }

    public function fix_thumbnail_dimensions(string $html): string {
        return $this->fix_image_dimensions($html);
    }
}
