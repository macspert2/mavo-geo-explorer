<?php
/**
 * Small standalone helpers shared across the plugin. Site-specific geo
 * extraction (reading the mavo-geotag-plus hierarchy) lives here rather than
 * being spread across classes, per the implementation plan.
 */

defined('ABSPATH') || exit;

const MV_GEO_EXPLORER_ALLOWED_LANGS = ['fr', 'en', 'de'];

function mv_geo_explorer_current_lang(): string {
    $lang = function_exists('pll_current_language') ? (string) pll_current_language() : '';
    return in_array($lang, MV_GEO_EXPLORER_ALLOWED_LANGS, true) ? $lang : 'fr';
}

/**
 * Lowercase, accent-stripped, alphanumeric-only form of a place name — used
 * to match a wp_geo_tagger_places region name (whatever punctuation/accents
 * Nominatim happened to return) against the static region-code lookup
 * tables in config.php, without depending on sanitize_title()'s exact
 * transliteration behaviour.
 */
function mv_geo_explorer_normalize_name(string $name): string {
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    $ascii = $ascii !== false ? $ascii : $name;
    return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($ascii));
}

function mv_geo_explorer_options(): array {
    $stored = get_option('mv_geo_explorer_options', []);
    if (!is_array($stored)) {
        $stored = [];
    }
    return array_merge(mv_geo_explorer_default_options(), $stored);
}

function mv_geo_explorer_update_options(array $partial): void {
    update_option('mv_geo_explorer_options', array_merge(mv_geo_explorer_options(), $partial));
}

function mv_geo_explorer_uploads_dir(): string {
    $uploads = wp_upload_dir();
    return trailingslashit($uploads['basedir']) . 'mv-geo-explorer/';
}

function mv_geo_explorer_uploads_url(): string {
    $uploads = wp_upload_dir();
    return trailingslashit($uploads['baseurl']) . 'mv-geo-explorer/';
}

function mv_geo_explorer_index_path(string $lang): string {
    return mv_geo_explorer_uploads_dir() . 'geo-index-' . $lang . '.json';
}

/**
 * Same generated-index-or-bundled-fixture fallback as
 * MV_Geo_Shortcode::index_url(), but returns the decoded data itself rather
 * than a URL — used server-side by the no-JS fallback list, which has no JS
 * to fetch+render the index client-side.
 */
function mv_geo_explorer_load_index_data(string $lang): ?array {
    $path = mv_geo_explorer_index_path($lang);
    if (!file_exists($path)) {
        $path = MV_GEO_EXPLORER_DIR . 'assets/data/geo-index-' . $lang . '.json';
    }
    if (!file_exists($path)) {
        return null;
    }
    $json = file_get_contents($path);
    if (false === $json) {
        return null;
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/**
 * Resolves which places the no-JS fallback list should show for a given
 * shortcode instance, mirroring the same view-dependent scoping/ordering
 * `renderList()` in mv-geo-explorer.js uses — kept as a plain array
 * transform (no WP dependency) so it's testable standalone. Unlike the
 * interactive World list, the no-JS World list includes Europe's countries
 * individually (there's no map to "merge" them into a single Europe shape
 * here, so a flat link-per-country list is more useful for both crawlers
 * and real no-JS visitors).
 *
 * @param array       $index           Decoded geo-index JSON for one language.
 * @param string      $view            'europe' | 'world' | 'regions'.
 * @param string|null $region_shape_id Alpha-3 code to drill into, only used when $view === 'regions'.
 * @return array{heading_label: ?string, places: array} `heading_label` is the
 *         drilled-into country's own label for a successfully-resolved
 *         'regions' case, null otherwise (caller picks the right europe/world
 *         heading string itself).
 */
function mv_geo_explorer_nojs_places(array $index, string $view, ?string $region_shape_id): array {
    $places    = is_array($index['places'] ?? null) ? $index['places'] : [];
    $shape_map = is_array($index['shape_map'] ?? null) ? $index['shape_map'] : [];

    if ('regions' === $view && $region_shape_id) {
        $country_slug = null;
        foreach ($places as $slug => $place) {
            if (($place['map_shape_id'] ?? null) === $region_shape_id) {
                $country_slug = $slug;
                break;
            }
        }
        if (null !== $country_slug) {
            $regions = array_values(array_filter(
                $places,
                static fn($p) => ($p['parent'] ?? null) === $country_slug && ($p['post_count'] ?? 0) > 0
            ));
            if (!empty($regions)) {
                usort($regions, static fn($a, $b) => $b['post_count'] <=> $a['post_count']);
                return ['heading_label' => $places[$country_slug]['label'] ?? null, 'places' => $regions];
            }
        }
        // Falls through to the Europe/World list below if nothing resolved
        // (e.g. this language has no region data backfilled yet) rather than
        // showing an empty list.
    }

    $top_level = array_values(array_filter(
        $places,
        static fn($p) => empty($p['parent']) && ($p['post_count'] ?? 0) > 0
    ));

    if ('world' === $view) {
        usort($top_level, static fn($a, $b) => strnatcasecmp($a['label'] ?? '', $b['label'] ?? ''));
        return ['heading_label' => null, 'places' => $top_level];
    }

    $europe_only = array_values(array_filter(
        $top_level,
        static fn($p) => array_key_exists($p['map_shape_id'] ?? '', $shape_map)
    ));
    usort($europe_only, static fn($a, $b) => $b['post_count'] <=> $a['post_count']);
    return ['heading_label' => null, 'places' => $europe_only];
}

/**
 * Checks whether a given (unprefixed) table name exists, cached per request
 * since it's queried on every shortcode render and every rebuild.
 */
function mv_geo_explorer_table_exists(string $table): bool {
    static $cache = [];
    global $wpdb;

    $full = $wpdb->prefix . $table;
    if (array_key_exists($full, $cache)) {
        return $cache[$full];
    }

    $exists       = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $full)) === $full;
    $cache[$full] = $exists;
    return $exists;
}

/**
 * Resolves a post's geographic hierarchy via the mavo-geotag-plus place tree
 * (wp_geo_tagger_places), the same table that plugin's own breadcrumb and
 * related-posts features read from. Returns [] if that plugin isn't
 * installed/active (table missing) or the post has no geo tags at all.
 *
 * This is the explicit-hierarchy adapter referenced by the implementation
 * plan (MV_Geo_Data_Builder::get_explicit_geo_for_post()) — kept here as a
 * plain DB read so it has no hard class dependency on mavo-geotag-plus.
 *
 * @return array Ordered continent → leaf, each as
 *               ['level'=>string,'name'=>string,'term_id'=>int,'country_code'=>string]
 */
function mv_geo_explorer_get_geotagger_chain_for_post(int $post_id, string $lang): array {
    if (!in_array($lang, MV_GEO_EXPLORER_ALLOWED_LANGS, true) || !mv_geo_explorer_table_exists('geo_tagger_places')) {
        return [];
    }

    global $wpdb;
    $table = $wpdb->prefix . 'geo_tagger_places';
    $col   = 'term_id_' . $lang;

    $term_ids = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'ids']);
    if (empty($term_ids) || is_wp_error($term_ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
    $places       = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, parent_id, level, name_fr, name_en, name_de, term_id_fr, term_id_en, term_id_de, country_code
             FROM {$table} WHERE {$col} IN ({$placeholders})",
            ...$term_ids
        )
    );
    if (empty($places)) {
        return [];
    }

    $level_order = ['continent' => 1, 'country' => 2, 'region' => 3, 'city' => 4];
    $leaf        = null;
    $max_depth   = 0;
    foreach ($places as $place) {
        $depth = $level_order[$place->level] ?? 0;
        if ($depth > $max_depth) {
            $max_depth = $depth;
            $leaf      = $place;
        }
    }
    if (!$leaf) {
        return [];
    }

    // Walk up parent_id from the leaf to build the continent → leaf chain.
    $chain      = [];
    $current_id = (int) $leaf->id;
    for ($depth = 0; $depth < 6; $depth++) {
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $current_id)
        );
        if (!$row || $row->level === 'world') {
            break;
        }
        array_unshift($chain, [
            'level'        => $row->level,
            'name'         => (string) ($row->{'name_' . $lang} ?? ''),
            'term_id'      => (int) ($row->{'term_id_' . $lang} ?? 0),
            'country_code' => (string) ($row->country_code ?? ''),
        ]);
        if (empty($row->parent_id)) {
            break;
        }
        $current_id = (int) $row->parent_id;
    }

    return $chain;
}
