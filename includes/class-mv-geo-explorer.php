<?php

defined('ABSPATH') || exit;

/**
 * Bootstraps the plugin: wires the shortcode, asset registration, and the
 * admin screen. Holds no rendering/data logic itself.
 */
class MV_Geo_Explorer {

    public static function activate(): void {
        wp_mkdir_p(mv_geo_explorer_uploads_dir());
    }

    public static function init(): void {
        add_action('wp_enqueue_scripts', ['MV_Geo_Assets', 'register']);
        add_action('init', ['MV_Geo_Shortcode', 'register']);

        if (is_admin()) {
            (new MV_Geo_Admin())->init();
        }
    }
}
