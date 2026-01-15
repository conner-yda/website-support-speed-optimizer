<?php
namespace HBS_PSO;

use HBS_PSO\Admin\Settings;
use HBS_PSO\Admin\Admin_Page;
use HBS_PSO\Modules\Page_Cache;
use HBS_PSO\Modules\Script_Optimizer;
use HBS_PSO\Modules\Style_Optimizer;
use HBS_PSO\Modules\Image_Optimizer;
use HBS_PSO\Modules\Resource_Hints;
use HBS_PSO\Modules\HTML_Minifier;
use HBS_PSO\Modules\Render_Blocker;
use HBS_PSO\Modules\Font_Optimizer;
use HBS_PSO\Modules\CLS_Optimizer;
use HBS_PSO\Modules\Cache_Headers;
use HBS_PSO\Modules\Speculation_Rules;

defined('ABSPATH') || exit;

class Plugin {
    private Settings $settings;
    private array $modules = [];

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    public function init(): void {
        $this->register_modules();
        $this->init_admin();
        
        foreach ($this->modules as $module) {
            if ($module->is_enabled()) {
                $module->init();
            }
        }
    }

    private function register_modules(): void {
        $this->modules = [
            'page_cache' => new Page_Cache($this->settings),
            'cache_headers' => new Cache_Headers($this->settings),
            'resource_hints' => new Resource_Hints($this->settings),
            'font_optimizer' => new Font_Optimizer($this->settings),
            'cls_optimizer' => new CLS_Optimizer($this->settings),
            'render_blocker' => new Render_Blocker($this->settings),
            'script_optimizer' => new Script_Optimizer($this->settings),
            'style_optimizer' => new Style_Optimizer($this->settings),
            'image_optimizer' => new Image_Optimizer($this->settings),
            'html_minifier' => new HTML_Minifier($this->settings),
            'speculation_rules' => new Speculation_Rules($this->settings),
        ];
    }

    private function init_admin(): void {
        if (is_admin()) {
            $admin = new Admin_Page($this->settings);
            $admin->init();
        }
    }

    public function get_module(string $name): ?Optimizer {
        return $this->modules[$name] ?? null;
    }
}
