<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

class Style_Optimizer implements Optimizer {
    private Settings $settings;

    // Only these handles remain render-blocking (truly critical)
    private array $critical_handles = [
        'wp-block-library', // Core block styles if using Gutenberg
    ];

    // These get completely removed (loaded conditionally or unused)
    private array $remove_handles = [
        'dashicons',
        'wp-block-library-theme',
    ];

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (is_admin()) {
            return;
        }

        add_action('wp_head', [$this, 'output_critical_css'], 1);
        add_action('wp_head', [$this, 'output_preloads'], 2);
        add_filter('style_loader_tag', [$this, 'modify_style_tag'], 10, 4);
        add_action('wp_enqueue_scripts', [$this, 'dequeue_unused_styles'], 9999);
        add_action('wp_footer', [$this, 'output_css_loader'], 999);
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('style_optimizer');
    }

    public function dequeue_unused_styles(): void {
        if (!is_admin()) {
            foreach ($this->remove_handles as $handle) {
                wp_dequeue_style($handle);
            }
        }
    }

    public function output_critical_css(): void {
        $critical_css = $this->settings->get('critical_css', '');
        
        if (empty($critical_css)) {
            $critical_css = $this->get_default_critical_css();
        }

        if (!empty($critical_css)) {
            echo '<style id="hbs-critical-css">' . $this->minify_css($critical_css) . '</style>' . "\n";
        }
    }

    private function minify_css(string $css): string {
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace([' {', '{ '], '{', $css);
        $css = str_replace([' }', '} '], '}', $css);
        $css = str_replace(': ', ':', $css);
        $css = str_replace('; ', ';', $css);
        $css = str_replace(';}', '}', $css);
        return trim($css);
    }

    private function get_default_critical_css(): string {
        // Comprehensive critical CSS for above-the-fold content
        return '
            *,*::before,*::after{box-sizing:border-box}
            html{-webkit-text-size-adjust:100%;line-height:1.15;scroll-behavior:smooth}
            body{margin:0;font-family:"Open Sans",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;line-height:1.6;-webkit-font-smoothing:antialiased}
            img,video,svg{max-width:100%;height:auto}
            a{color:inherit;text-decoration:none}
            h1,h2,h3,h4,h5,h6,p,ul,ol{margin:0 0 1rem}
            button,input,select,textarea{font:inherit}
            
            /* CRITICAL: Prevent ::before CLS on Divi sections */
            .et_pb_section{position:relative!important;background-size:cover;background-position:center;contain:layout paint style}
            .et_pb_section::before,.et_pb_section::after{content:"";position:absolute;top:0;left:0;right:0;bottom:0;pointer-events:none;z-index:1}
            .et_builder_inner_content{contain:layout style}
            
            /* Layout resets */
            .container,.wrapper,.wrap{width:100%;max-width:1200px;margin:0 auto;padding:0 20px}
            header,nav,main,section,footer{display:block}
            
            /* Divi rows/columns */
            .et_pb_row{width:80%;max-width:1080px;margin:auto;position:relative;contain:layout}
            .et_pb_column{float:left;position:relative;z-index:2;min-height:1px}
            .et_pb_column_4_4{width:100%}
            .et_pb_column_1_2{width:50%}
            .et_pb_column_1_3{width:33.333%}
            .et_pb_column_2_3{width:66.666%}
            .et_pb_column_1_4{width:25%}
            .et_pb_column_3_4{width:75%}
            .et_pb_text_inner{position:relative}
            .et_pb_image{margin-left:auto;margin-right:auto;line-height:0}
            .et_pb_button{font-size:20px;padding:.3em 1em;display:inline-block;text-decoration:none}
            .et_pb_module{position:relative;background-size:cover;background-position:center}
            
            /* Header - FIXED HEIGHT to prevent CLS */
            #main-header,.et-l--header{position:relative;z-index:99999;min-height:90px;contain:layout style}
            #et-top-navigation{min-height:90px}
            .logo_container,.et_pb_menu__logo-wrap{min-height:54px}
            #logo,.logo_container img,.et_pb_menu__logo img{height:auto;max-height:80px;width:auto;aspect-ratio:500/337}
            .et_pb_menu__menu nav{display:flex;align-items:center}
            .et_pb_menu__menu ul{list-style:none;margin:0;padding:0;display:flex}
            .et_pb_menu__menu li{position:relative}
            .et_pb_menu__menu a{display:block;padding:10px 15px}
            
            /* Hero section - reserve space */
            .et_pb_section:first-of-type,.et_pb_fullwidth_section{min-height:400px}
            .et_pb_fullwidth_header{padding:100px 0}
            .et_pb_fullwidth_header_container{width:80%;max-width:1080px;margin:auto}
            
            /* Testimonial section */
            #testimonial,[id*="testimonial"],.et_pb_testimonial,.dipl_testimonial{min-height:300px;contain:layout style}
            .dipl_testimonial_author_image img{width:150px;height:100px;aspect-ratio:327/150;object-fit:cover}
            
            /* Images with dimensions */
            img[width][height]{aspect-ratio:attr(width)/attr(height);height:auto}
            
            /* Visibility utilities */
            .hidden,[hidden]{display:none!important}
            .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
            
            @media(max-width:980px){
                .et_pb_column{width:100%!important;margin-bottom:30px}
                .et_pb_row{width:90%}
                #main-header{min-height:60px}
            }
        ';
    }

    public function output_preloads(): void {
        // Preload the main Divi stylesheet since it's large and needed
        echo '<link rel="preload" href="' . esc_url(get_template_directory_uri()) . '/style.css" as="style">' . "\n";
    }

    public function modify_style_tag(string $tag, string $handle, string $href, string $media): string {
        if (is_admin()) {
            return $tag;
        }

        // Keep critical handles as render-blocking
        if (in_array($handle, $this->critical_handles, true)) {
            return $tag;
        }

        // Skip if already processed
        if (strpos($tag, 'data-hbs-defer') !== false) {
            return $tag;
        }

        // Use the print media + onload technique for all other CSS
        $noscript = '<noscript>' . $tag . '</noscript>';
        
        // Replace rel="stylesheet" with media="print" onload technique
        $deferred_tag = str_replace(
            "media='all'",
            "media='print' onload=\"this.media='all'\"",
            $tag
        );
        $deferred_tag = str_replace(
            'media="all"',
            'media="print" onload="this.media=\'all\'"',
            $deferred_tag
        );
        
        // Handle cases without explicit media attribute
        if ($deferred_tag === $tag) {
            $deferred_tag = str_replace(
                "rel='stylesheet'",
                "rel='stylesheet' media='print' onload=\"this.media='all'\"",
                $tag
            );
            $deferred_tag = str_replace(
                'rel="stylesheet"',
                'rel="stylesheet" media="print" onload="this.media=\'all\'"',
                $deferred_tag
            );
        }
        
        $deferred_tag = str_replace('<link', '<link data-hbs-defer="1"', $deferred_tag);
        
        return $deferred_tag . $noscript;
    }

    public function output_css_loader(): void {
        // Fallback loader for browsers that may have issues with onload
        ?>
        <script id="hbs-css-loader">
        (function(){
            var d=document,links=d.querySelectorAll('link[data-hbs-defer]');
            if(!links.length)return;
            function load(){links.forEach(function(l){if(l.media==='print')l.media='all'});}
            if(d.readyState==='complete'){load();}
            else{window.addEventListener('load',load);}
        })();
        </script>
        <?php
    }
}
