<?php

defined('ABSPATH') || exit;

/**
 * Builds geo-index-{lang}.json from whatever geo data is available:
 *
 *   1. The mavo-geotag-plus hierarchy (wp_geo_tagger_places), via a fast
 *      per-country tag__in count — this covers the vast majority of posts,
 *      since that plugin already tags every level (continent/country/
 *      region/city) onto each post.
 *   2. For the few posts that fall through (no country-level tag at all),
 *      a per-post fallback chain: explicit hierarchy → Geo Mashup location →
 *      manual name map → unmatched (logged for the admin diagnostics screen).
 *
 * Works with either source unavailable (Section 6.6 of the plan) — if
 * wp_geo_tagger_places doesn't exist, step 1 is skipped entirely and every
 * post goes through the per-post fallback chain.
 */
class MV_Geo_Data_Builder {

    private const TOP_POSTS_LIMIT = 5;

    public static function build_all(): array {
        $results = [];
        foreach (mv_geo_explorer_options()['enabled_languages'] as $lang) {
            $results[$lang] = self::build($lang);
        }
        return $results;
    }

    public static function build(string $lang): array {
        if (!in_array($lang, MV_GEO_EXPLORER_ALLOWED_LANGS, true)) {
            return ['lang' => $lang, 'error' => 'unsupported language'];
        }

        $places              = [];
        $shape_map           = [];
        $bucket_index_by_cc  = []; // country_code => slug, for the fallback pass
        $matched_post_ids    = [];
        $matched_hierarchy   = 0;
        $matched_geomashup   = 0;
        $matched_manual      = 0;
        $unmatched           = 0;
        $unmatched_log       = [];

        // ---- Step 1: fast path via wp_geo_tagger_places --------------------
        foreach (self::get_country_places() as $row) {
            $term_id = (int) ($row->{'term_id_' . $lang} ?? 0);
            if (!$term_id) {
                continue;
            }

            $post_ids = self::query_post_ids_for_term($term_id, $lang);
            if (empty($post_ids)) {
                continue;
            }

            $label = (string) ($row->{'name_' . $lang} ?? '');
            if ('' === $label) {
                continue;
            }

            $slug = sanitize_title($label);
            $cc   = strtolower((string) ($row->country_code ?? ''));

            $places[$slug] = self::build_place_node($label, $term_id, $cc, $post_ids, $lang);
            self::register_shape($shape_map, $cc, $slug);

            $bucket_index_by_cc[$cc] = $slug;
            $matched_hierarchy      += count($post_ids);
            foreach ($post_ids as $id) {
                $matched_post_ids[$id] = true;
            }
        }

        // ---- Step 2: per-post fallback for posts the fast path missed -----
        $all_post_ids = self::query_all_post_ids($lang);
        $leftover     = array_diff($all_post_ids, array_keys($matched_post_ids));

        foreach ($leftover as $post_id) {
            $resolved = self::resolve_leftover_post($post_id, $lang, $bucket_index_by_cc, $places);

            if (null === $resolved) {
                $unmatched++;
                $name                  = self::unmatched_signal($post_id, $lang);
                $unmatched_log[$name] = ($unmatched_log[$name] ?? 0) + 1;
                continue;
            }

            [$slug, $via] = $resolved;

            if (!isset($places[$slug])) {
                // Ad-hoc bucket: no existing wp_geo_tagger_places row for this
                // country yet. Built with whatever we could resolve; hub_url
                // falls back to the language's default destination page.
                $places[$slug] = [
                    'type'        => 'country',
                    'label'       => $resolved[2] ?? $slug,
                    'post_count'  => 0,
                    'url'         => mv_geo_explorer_lang_fallback_url($lang),
                    'map_shape_id' => $resolved[3] ?? null,
                    'drilldown'   => false,
                    'top_posts'   => [],
                    '_candidates' => [],
                ];
            }

            $places[$slug]['post_count']++;
            $places[$slug]['_candidates'][] = $post_id;

            if ('hierarchy' === $via) {
                $matched_hierarchy++;
            } elseif ('geomashup' === $via) {
                $matched_geomashup++;
            } else {
                $matched_manual++;
            }
        }

        // ---- Finalize: resolve top_posts for any place still carrying
        //      raw candidate IDs (ad-hoc buckets created in step 2) --------
        foreach ($places as $slug => &$place) {
            if (!empty($place['_candidates'])) {
                $needed              = max(0, self::TOP_POSTS_LIMIT - count($place['top_posts']));
                $place['top_posts'] = array_merge(
                    $place['top_posts'],
                    self::posts_to_top_posts(array_slice($place['_candidates'], 0, $needed))
                );
            }
            unset($place['_candidates']);
        }
        unset($place);

        $processed = count($all_post_ids);
        $data      = [
            'lang'         => $lang,
            'generated_at' => current_time('c'),
            'default_view' => 'europe',
            'shape_map'    => $shape_map,
            'places'       => $places,
        ];

        $file_path = self::write_index($lang, $data);

        $diagnostics = [
            'lang'              => $lang,
            'generated_at'      => $data['generated_at'],
            'processed'         => $processed,
            'matched_hierarchy' => $matched_hierarchy,
            'matched_geomashup' => $matched_geomashup,
            'matched_manual'    => $matched_manual,
            'unmatched'         => $unmatched,
            'unmatched_names'   => self::top_unmatched($unmatched_log),
            'places_count'      => count($places),
            'file_path'         => $file_path,
        ];

        $options                            = mv_geo_explorer_options();
        $options['last_generated'][$lang]   = $diagnostics;
        mv_geo_explorer_update_options($options);

        return $diagnostics;
    }

    // -------------------------------------------------------------------
    // Step 1 helpers
    // -------------------------------------------------------------------

    private static function get_country_places(): array {
        if (!mv_geo_explorer_table_exists('geo_tagger_places')) {
            return [];
        }
        global $wpdb;
        $table = $wpdb->prefix . 'geo_tagger_places';
        return $wpdb->get_results("SELECT * FROM {$table} WHERE level = 'country'") ?: [];
    }

    /**
     * @return int[] Post IDs, newest first.
     */
    private static function query_post_ids_for_term(int $term_id, string $lang): array {
        $args = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'tag__in'             => [$term_id],
            'orderby'             => 'date',
            'order'               => 'DESC',
            'posts_per_page'      => -1,
            'fields'              => 'ids',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ];
        if (function_exists('pll_languages_list')) {
            $args['lang'] = $lang;
        }
        return (new WP_Query($args))->posts;
    }

    /**
     * @return int[]
     */
    private static function query_all_post_ids(string $lang): array {
        $args = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'fields'              => 'ids',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ];
        if (function_exists('pll_languages_list')) {
            $args['lang'] = $lang;
        }
        return (new WP_Query($args))->posts;
    }

    private static function build_place_node(string $label, int $term_id, string $country_code, array $post_ids, string $lang): array {
        $url = get_term_link($term_id, 'post_tag');

        return [
            'type'         => 'country',
            'label'        => $label,
            'post_count'   => count($post_ids),
            'url'          => is_wp_error($url) ? mv_geo_explorer_lang_fallback_url($lang) : $url,
            'map_shape_id' => mv_geo_explorer_alpha2_to_alpha3()[$country_code] ?? null,
            'drilldown'    => false,
            'top_posts'    => self::posts_to_top_posts(array_slice($post_ids, 0, self::TOP_POSTS_LIMIT)),
        ];
    }

    private static function register_shape(array &$shape_map, string $country_code, string $slug): void {
        $shape_id = mv_geo_explorer_alpha2_to_alpha3()[$country_code] ?? null;
        if ($shape_id) {
            $shape_map[$shape_id] = $slug;
        }
    }

    /**
     * @param int[] $post_ids
     * @return array<int, array{id:int,title:string,url:string,thumb:string}>
     */
    private static function posts_to_top_posts(array $post_ids): array {
        $out = [];
        foreach ($post_ids as $post_id) {
            $thumb = get_the_post_thumbnail_url($post_id, 'medium');
            $out[] = [
                'id'    => $post_id,
                'title' => get_the_title($post_id),
                'url'   => get_permalink($post_id),
                'thumb' => $thumb ?: '',
            ];
        }
        return $out;
    }

    // -------------------------------------------------------------------
    // Step 2: per-post fallback chain
    // -------------------------------------------------------------------

    /**
     * Tries, in order: explicit hierarchy (a country tag the fast path
     * somehow missed — e.g. its term existed but had 0 cached post count),
     * Geo Mashup location, then the manual name map.
     *
     * @return array{0:string,1:string,2?:string,3?:string}|null [slug, matched_via, label?, map_shape_id?]
     */
    private static function resolve_leftover_post(int $post_id, string $lang, array $bucket_index_by_cc, array $places): ?array {
        $explicit = self::get_explicit_geo_for_post($post_id, $lang);
        if (isset($explicit['country'])) {
            $country = $explicit['country'];
            $cc      = strtolower($country['country_code'] ?? '');
            if (isset($bucket_index_by_cc[$cc])) {
                return [$bucket_index_by_cc[$cc], 'hierarchy'];
            }
            $slug = $country['slug'] ?: sanitize_title($country['label']);
            return [$slug, 'hierarchy', $country['label'], mv_geo_explorer_alpha2_to_alpha3()[$cc] ?? null];
        }

        if (MV_Geo_GeoMashup::is_available()) {
            $admin = MV_Geo_GeoMashup::get_admin_names_for_post($post_id);
            $cc    = strtolower((string) ($admin['country_code'] ?? ''));
            if ($cc) {
                if (isset($bucket_index_by_cc[$cc])) {
                    return [$bucket_index_by_cc[$cc], 'geomashup'];
                }
                $label = $admin['country'] ?: strtoupper($cc);
                return [sanitize_title($label), 'geomashup', $label, mv_geo_explorer_alpha2_to_alpha3()[$cc] ?? null];
            }
        }

        $manual_map = mv_geo_explorer_manual_name_map()[$lang] ?? [];
        if (!empty($manual_map)) {
            $tag_names = wp_get_post_terms($post_id, 'post_tag', ['fields' => 'names']);
            if (!is_wp_error($tag_names)) {
                foreach ($tag_names as $name) {
                    if (isset($manual_map[$name])) {
                        $slug = $manual_map[$name];
                        if (isset($places[$slug])) {
                            return [$slug, 'manual'];
                        }
                        return [$slug, 'manual', $name];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Site-specific adapter (per Section 13 of the implementation plan):
     * resolves a post's continent/country/region/city via the
     * mavo-geotag-plus place hierarchy. All knowledge of that plugin's
     * schema is isolated to this method and includes/helpers.php.
     *
     * @return array<string, array{slug:string,label:string,term_id:int,country_code:string}>
     */
    private static function get_explicit_geo_for_post(int $post_id, string $lang): array {
        $chain  = mv_geo_explorer_get_geotagger_chain_for_post($post_id, $lang);
        $result = [];
        foreach ($chain as $entry) {
            if ('' === $entry['name']) {
                continue;
            }
            $result[$entry['level']] = [
                'slug'         => sanitize_title($entry['name']),
                'label'        => $entry['name'],
                'term_id'      => $entry['term_id'],
                'country_code' => $entry['country_code'],
            ];
        }
        return $result;
    }

    private static function unmatched_signal(int $post_id, string $lang): string {
        if (MV_Geo_GeoMashup::is_available()) {
            $admin = MV_Geo_GeoMashup::get_admin_names_for_post($post_id);
            if (!empty($admin['country'])) {
                return $admin['country'];
            }
            if (!empty($admin['country_code'])) {
                return strtoupper($admin['country_code']);
            }
        }
        return get_the_title($post_id) ?: ('#' . $post_id);
    }

    private static function top_unmatched(array $unmatched_log, int $limit = 20): array {
        arsort($unmatched_log);
        $out = [];
        foreach (array_slice($unmatched_log, 0, $limit, true) as $name => $count) {
            $out[] = ['name' => $name, 'count' => $count];
        }
        return $out;
    }

    // -------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------

    private static function write_index(string $lang, array $data): string {
        $dir = mv_geo_explorer_uploads_dir();
        wp_mkdir_p($dir);
        $path = $dir . 'geo-index-' . $lang . '.json';
        file_put_contents($path, wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $path;
    }
}
