<?php
namespace HBS_PSO\Modules;

use HBS_PSO\Optimizer;
use HBS_PSO\Admin\Settings;

defined('ABSPATH') || exit;

class Script_Optimizer implements Optimizer {
    private Settings $settings;

    private array $excluded_handles = [
        'jquery-core',
        'jquery-migrate',
        'wp-polyfill',
    ];

    private array $delay_handles = [
        'divi-custom-script',
        'et-builder-modules-script',
        'et-core-unified-cached-inline-scripts',
    ];

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        if (is_admin() || Settings::is_page_builder_context()) {
            return;
        }

        add_filter('script_loader_tag', [$this, 'modify_script_tag'], 10, 3);
        
        if ($this->settings->get('delay_js', true)) {
            add_action('wp_footer', [$this, 'output_delay_script'], 999);
        }
    }

    public function is_enabled(): bool {
        return $this->settings->is_module_enabled('script_optimizer');
    }

    public function modify_script_tag(string $tag, string $handle, string $src): string {
        if (is_admin()) {
            return $tag;
        }

        if (in_array($handle, $this->excluded_handles, true)) {
            return $tag;
        }

        if (strpos($tag, 'defer') !== false || strpos($tag, 'async') !== false) {
            return $tag;
        }

        $delay = $this->settings->get('delay_js', true);
        $defer = $this->settings->get('defer_js', true);

        if ($delay && $this->should_delay($handle, $src)) {
            $tag = str_replace(' src=', ' data-hbs-delay="1" data-hbs-src=', $tag);
            $tag = str_replace("type='text/javascript'", "type='text/hbs-delay'", $tag);
            $tag = str_replace('type="text/javascript"', 'type="text/hbs-delay"', $tag);
            return $tag;
        }

        if ($defer) {
            $tag = str_replace(' src=', ' defer src=', $tag);
        }

        return $tag;
    }

    private function should_delay(string $handle, string $src): bool {
        if (in_array($handle, $this->delay_handles, true)) {
            return true;
        }

        $delay_patterns = [
            'divi',
            'et-builder',
            'et_',
            'sticky',
            'smooth-scroll',
            'animations',
            'waypoints',
        ];

        foreach ($delay_patterns as $pattern) {
            if (stripos($handle, $pattern) !== false || stripos($src, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public function output_delay_script(): void {
        // Use requestIdleCallback and interaction events (NO scroll listener - causes jank)
        ?>
        <script id="hbs-delay-loader">
        (function(){
            var loaded=false,
                scripts=document.querySelectorAll('script[data-hbs-delay]');
            
            if(!scripts.length)return;
            
            function load(){
                if(loaded)return;
                loaded=true;
                
                var i=0;
                function next(){
                    if(i>=scripts.length)return;
                    var s=scripts[i++],
                        n=document.createElement('script'),
                        src=s.getAttribute('data-hbs-src');
                    if(src)n.src=src;
                    n.onload=n.onerror=next;
                    s.parentNode.replaceChild(n,s);
                }
                next();
            }
            
            // Only use click/touch/keydown - NO scroll (causes performance issues)
            ['click','touchstart','keydown'].forEach(function(e){
                document.addEventListener(e,load,{once:true,passive:true});
            });
            
            // Also load after 4 seconds idle or 8 seconds max
            if('requestIdleCallback' in window){
                requestIdleCallback(load,{timeout:4000});
            }else{
                setTimeout(load,4000);
            }
        })();
        </script>
        <?php
    }
}
