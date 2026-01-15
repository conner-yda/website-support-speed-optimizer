<?php
namespace HBS_PSO;

defined('ABSPATH') || exit;

interface Optimizer {
    public function init(): void;
    public function is_enabled(): bool;
}
