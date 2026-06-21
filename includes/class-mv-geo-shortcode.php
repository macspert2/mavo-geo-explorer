<?php

defined('ABSPATH') || exit;

/**
 * Registers and renders [mv_geo_explorer]. This is the only place that
 * enqueues the map's CSS/JS — pages without the shortcode never load them.
 */
class MV_Geo_Shortcode {

    private static int $instance_count = 0;

    public static function register(): void {
        add_shortcode('mv_geo_explorer', [__CLASS__, 'render']);
    }

    public static function render($atts): string {
        $atts = shortcode_atts(
            [
                'lang'         => 'current',
                'default_view' => 'europe',
                'show_list'    => '1',
                'show_counts'  => '1',
                'show_posts'   => '1',
                'max_posts'    => '3',
                'theme'        => 'default',
            ],
            $atts,
            'mv_geo_explorer'
        );

        $lang_attr = sanitize_key($atts['lang']);
        if ('current' === $lang_attr) {
            $lang = mv_geo_explorer_current_lang();
        } elseif (in_array($lang_attr, MV_GEO_EXPLORER_ALLOWED_LANGS, true)) {
            $lang = $lang_attr;
        } else {
            $lang = mv_geo_explorer_current_lang();
        }

        // 'world' isn't implemented yet (Section 1.3 of the plan) — 'europe'
        // is the only supported view regardless of what was requested, so the
        // map never ends up blank.
        $default_view = 'europe';

        $theme = sanitize_key($atts['theme']);
        if (!in_array($theme, ['default', 'minimal'], true)) {
            $theme = 'default';
        }

        $max_posts = absint($atts['max_posts']);
        $max_posts = max(1, min(10, $max_posts ?: 3));

        MV_Geo_Assets::enqueue();

        self::$instance_count++;
        $instance_id = 'mv-geo-explorer-' . self::$instance_count;

        $strings = mv_geo_explorer_ui_strings()[$lang] ?? mv_geo_explorer_ui_strings()['fr'];

        $config = [
            'lang'        => $lang,
            'defaultView' => $default_view,
            'showList'    => (bool) absint($atts['show_list']),
            'showCounts'  => (bool) absint($atts['show_counts']),
            'showPosts'   => (bool) absint($atts['show_posts']),
            'maxPosts'    => $max_posts,
            'theme'       => $theme,
            'indexUrl'    => self::index_url($lang),
            'geoUrl'      => MV_GEO_EXPLORER_URL . 'assets/geo/europe-countries.simple.geojson',
            'strings'     => $strings,
        ];

        ob_start();
        include MV_GEO_EXPLORER_DIR . 'templates/geo-explorer.php';
        return (string) ob_get_clean();
    }

    /**
     * Generated index if the admin has already run a rebuild, otherwise the
     * bundled fixture so the map still shows something sensible on first use.
     */
    private static function index_url(string $lang): string {
        if (file_exists(mv_geo_explorer_index_path($lang))) {
            return mv_geo_explorer_uploads_url() . 'geo-index-' . $lang . '.json';
        }
        return MV_GEO_EXPLORER_URL . 'assets/data/geo-index-' . $lang . '.json';
    }
}
