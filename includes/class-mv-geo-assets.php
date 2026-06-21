<?php

defined('ABSPATH') || exit;

/**
 * Registers (but does not enqueue) the map's CSS/JS on every front-end
 * request, so WordPress knows about the handles — actual enqueuing only
 * happens from MV_Geo_Shortcode::render(), which is the one place that
 * guarantees the shortcode is actually present on the page.
 */
class MV_Geo_Assets {

    public static function register(): void {
        wp_register_style(
            'mv-geo-explorer',
            MV_GEO_EXPLORER_URL . 'assets/css/mv-geo-explorer.css',
            [],
            MV_GEO_EXPLORER_VERSION
        );

        wp_register_script(
            'mv-d3',
            MV_GEO_EXPLORER_URL . 'assets/js/vendor/d3.min.js',
            [],
            '7.9.0',
            true
        );

        wp_register_script(
            'mv-geo-explorer',
            MV_GEO_EXPLORER_URL . 'assets/js/mv-geo-explorer.js',
            ['mv-d3'],
            MV_GEO_EXPLORER_VERSION,
            true
        );
    }

    public static function enqueue(): void {
        wp_enqueue_style('mv-geo-explorer');
        wp_enqueue_script('mv-geo-explorer');
    }
}
