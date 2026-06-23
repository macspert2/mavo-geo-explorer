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
        add_action('init', [self::class, 'maybe_flush_autoptimize_cache']);

        if (is_admin()) {
            (new MV_Geo_Admin())->init();
        }
    }

    /**
     * Autoptimize keys its combined JS/CSS cache off whatever <script>/<link>
     * tags it sees on a rendered page, not off this plugin's own version —
     * so after a deploy, visitors can keep getting served an old combined
     * bundle until someone manually clicks Autoptimize's "Save Changes and
     * Empty Cache" button, with no way for them to know a hard reload would
     * even help. Detect our own version changing (MV_GEO_EXPLORER_VERSION is
     * already bumped on every deploy) and clear Autoptimize's cache exactly
     * once, on the first request after each deploy — same effect as that
     * button — so the very next page load for ANY visitor gets a fresh,
     * differently-named combined bundle automatically. Hooked on `init`
     * (not `plugins_loaded`, when this method is registered) so Autoptimize
     * has already defined its classes by the time this runs, regardless of
     * plugin load order.
     */
    public static function maybe_flush_autoptimize_cache(): void {
        if (get_option('mv_geo_explorer_last_version') === MV_GEO_EXPLORER_VERSION) {
            return;
        }
        update_option('mv_geo_explorer_last_version', MV_GEO_EXPLORER_VERSION);
        if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
            autoptimizeCache::clearall();
        }
    }
}
