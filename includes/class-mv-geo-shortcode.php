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
                'lang'           => 'current',
                'default_view'   => 'europe',
                'default_region' => '',
                'show_list'      => '1',
                'show_counts'    => '1',
                'show_posts'     => '1',
                'show_drilldown' => '1',
                'max_posts'      => '3',
                'theme'          => 'default',
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

        $default_view = sanitize_key($atts['default_view']);
        if (!in_array($default_view, ['europe', 'world', 'regions'], true)) {
            $default_view = 'europe';
        }

        // default_region is an ISO alpha-2 country code (e.g. "fr"), resolved
        // here to the alpha-3 id used as a map shape's id — the frontend
        // looks up which place/slug that belongs to in the loaded index for
        // the current language, since slugs themselves are language-specific
        // and not knowable at shortcode-render time. Falls back to "europe"
        // rather than starting a "regions" view with nothing to drill into.
        $default_region_shape_id = null;
        if ('regions' === $default_view) {
            $region_code             = sanitize_key($atts['default_region']);
            $default_region_shape_id = mv_geo_explorer_alpha2_to_alpha3()[$region_code] ?? null;
            if (null === $default_region_shape_id) {
                $default_view = 'europe';
            }
        }

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
            'lang'                 => $lang,
            'defaultView'          => $default_view,
            'defaultRegionShapeId' => $default_region_shape_id,
            'showList'             => (bool) absint($atts['show_list']),
            'showCounts'           => (bool) absint($atts['show_counts']),
            'showPosts'            => (bool) absint($atts['show_posts']),
            'showDrilldown'        => (bool) absint($atts['show_drilldown']),
            'maxPosts'             => $max_posts,
            'theme'                => $theme,
            'indexUrl'             => self::index_url($lang),
            'geoUrl'               => add_query_arg('ver', MV_GEO_EXPLORER_VERSION, MV_GEO_EXPLORER_URL . 'assets/geo/europe-countries.simple.geojson'),
            'worldGeoUrl'          => add_query_arg('ver', MV_GEO_EXPLORER_VERSION, MV_GEO_EXPLORER_URL . 'assets/geo/world-countries.simple.geojson'),
            'geoBaseUrl'           => MV_GEO_EXPLORER_URL . 'assets/geo/',
            'assetVersion'         => MV_GEO_EXPLORER_VERSION,
            'strings'              => $strings,
        ];

        // Computed once here (rather than inside either template) so the
        // no-JS fallback list — which has no JS to fetch+render the index
        // client-side — can build real, crawlable links from the exact same
        // data the interactive map uses, and so the *always-rendered* list
        // heading (filled in by JS once it loads, but visible as static
        // markup until then) shows the right text for this instance's view
        // instead of unconditionally the Europe one. Local vars defined here
        // are visible inside both nested includes below (PHP `include` runs
        // in the including scope).
        $index       = mv_geo_explorer_load_index_data($lang);
        $nojs_places = is_array($index)
            ? mv_geo_explorer_nojs_places($index, $default_view, $default_region_shape_id)
            : ['heading_label' => null, 'places' => []];
        $list_heading = $nojs_places['heading_label']
            ?? (('world' === $default_view) ? $strings['list_heading_world'] : $strings['list_heading']);

        ob_start();
        include MV_GEO_EXPLORER_DIR . 'templates/geo-explorer.php';
        return (string) ob_get_clean();
    }

    /**
     * Generated index if the admin has already run a rebuild, otherwise the
     * bundled fixture so the map still shows something sensible on first use.
     *
     * Cache-busted so browsers don't keep serving a stale copy after a
     * rebuild — none of the geo/data fetch URLs go through wp_enqueue_*, so
     * unlike the plugin's CSS/JS they get no `?ver=` automatically. The
     * generated index changes independently of the plugin version (every
     * admin rebuild), so it's busted by the file's own mtime instead.
     */
    private static function index_url(string $lang): string {
        $path = mv_geo_explorer_index_path($lang);
        if (file_exists($path)) {
            $url = mv_geo_explorer_uploads_url() . 'geo-index-' . $lang . '.json';
            return add_query_arg('ver', filemtime($path), $url);
        }
        $url = MV_GEO_EXPLORER_URL . 'assets/data/geo-index-' . $lang . '.json';
        return add_query_arg('ver', MV_GEO_EXPLORER_VERSION, $url);
    }
}
