<?php

defined('ABSPATH') || exit;

/**
 * Read-only adapter over Geo Mashup's own tables (queried directly, the same
 * way mavo-geotag-plus's GeoMashupDB does, since Geo Mashup's PHP API isn't
 * guaranteed stable across versions). Used only as a fallback source for
 * posts that have no explicit continent/country/region/city hierarchy yet
 * (see MV_Geo_Data_Builder) — never as the primary source, and never fatal
 * if Geo Mashup is inactive.
 */
class MV_Geo_GeoMashup {

    public static function is_available(): bool {
        return mv_geo_explorer_table_exists('geo_mashup_locations')
            && mv_geo_explorer_table_exists('geo_mashup_location_relationships');
    }

    /**
     * @return array{lat: float, lng: float, country: ?string, region: ?string, city: ?string, raw: object}|null
     */
    public static function get_post_location(int $post_id): ?array {
        if (!self::is_available()) {
            return null;
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT l.*
                 FROM {$wpdb->prefix}geo_mashup_locations l
                 INNER JOIN {$wpdb->prefix}geo_mashup_location_relationships r ON l.id = r.location_id
                 WHERE r.object_name = 'post'
                   AND r.object_id = %d
                 LIMIT 1",
                $post_id
            )
        );

        if (!$row || empty($row->lat) || empty($row->lng)) {
            return null;
        }

        return [
            'lat'          => (float) $row->lat,
            'lng'          => (float) $row->lng,
            'country'      => self::first_nonempty([$row->country_name ?? null]),
            'country_code' => isset($row->country_code) ? strtolower((string) $row->country_code) : null,
            'region'       => self::first_nonempty([$row->admin_name ?? null]),
            'city'         => self::first_nonempty([$row->locality_name ?? null, $row->address ?? null]),
            'raw'          => $row,
        ];
    }

    /**
     * Best-effort raw admin names for a post, in whatever language Geo
     * Mashup happened to store them (it isn't language-aware). The caller
     * is responsible for mapping these to a canonical, language-specific
     * place — e.g. via the country_code against wp_geo_tagger_places, or
     * via the manual name map.
     *
     * @return array{country: ?string, country_code: ?string, region: ?string, city: ?string}
     */
    public static function get_admin_names_for_post(int $post_id): array {
        $location = self::get_post_location($post_id);
        if (!$location) {
            return ['country' => null, 'country_code' => null, 'region' => null, 'city' => null];
        }

        return [
            'country'      => $location['country'],
            'country_code' => $location['country_code'],
            'region'       => $location['region'],
            'city'         => $location['city'],
        ];
    }

    private static function first_nonempty(array $candidates): ?string {
        foreach ($candidates as $value) {
            if (is_string($value) && '' !== trim($value)) {
                return trim($value);
            }
        }
        return null;
    }
}
