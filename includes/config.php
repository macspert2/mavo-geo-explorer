<?php
/**
 * Site-specific configuration: ISO code mapping, manual fallback name map,
 * default options, and UI copy. Kept isolated here (per the implementation
 * plan) so future adjustments don't require touching plugin logic.
 */

defined('ABSPATH') || exit;

/**
 * ISO 3166-1 alpha-2 (as stored in wp_geo_tagger_places.country_code and
 * Geo Mashup's country_code column) to alpha-3 (as used for the `id` of
 * each feature in europe-countries.simple.geojson). Europe-only for the MVP.
 */
function mv_geo_explorer_alpha2_to_alpha3(): array {
    return [
        'ad' => 'AND', 'al' => 'ALB', 'am' => 'ARM', 'at' => 'AUT', 'ax' => 'ALA',
        'az' => 'AZE', 'ba' => 'BIH', 'be' => 'BEL', 'bg' => 'BGR', 'by' => 'BLR',
        'ch' => 'CHE', 'cy' => 'CYP', 'cz' => 'CZE', 'de' => 'DEU', 'dk' => 'DNK',
        'ee' => 'EST', 'es' => 'ESP', 'fi' => 'FIN', 'fo' => 'FRO', 'fr' => 'FRA',
        'gb' => 'GBR', 'ge' => 'GEO', 'gr' => 'GRC', 'hr' => 'HRV', 'hu' => 'HUN',
        'ie' => 'IRL', 'im' => 'IMN', 'is' => 'ISL', 'it' => 'ITA', 'li' => 'LIE',
        'lt' => 'LTU', 'lu' => 'LUX', 'lv' => 'LVA', 'md' => 'MDA', 'me' => 'MNE',
        'mk' => 'MKD', 'mt' => 'MLT', 'nl' => 'NLD', 'no' => 'NOR', 'pl' => 'POL',
        'pt' => 'PRT', 'ro' => 'ROU', 'rs' => 'SRB', 'se' => 'SWE', 'si' => 'SVN',
        'sk' => 'SVK', 'tr' => 'TUR', 'ua' => 'UKR', 'xk' => 'XKX',
    ];
}

/**
 * Last-resort fallback: maps a raw place name (as seen on a post's existing
 * tags, or returned by Geo Mashup) to a canonical place slug, for posts that
 * have no resolvable continent/country/region/city hierarchy at all. Matched
 * case-sensitively. Extend this table as unmatched names show up in the
 * admin diagnostics screen.
 */
function mv_geo_explorer_manual_name_map(): array {
    return [
        'fr' => [
            'France'         => 'france',
            'Italie'         => 'italie',
            'Royaume-Uni'    => 'royaume-uni',
            'United Kingdom' => 'royaume-uni',
            'England'        => 'angleterre',
            'Angleterre'     => 'angleterre',
        ],
        'en' => [
            'France'         => 'france',
            'Italy'          => 'italy',
            'United Kingdom' => 'united-kingdom',
            'England'        => 'england',
        ],
        'de' => [
            'Frankreich'              => 'frankreich',
            'France'                  => 'frankreich',
            'Italien'                 => 'italien',
            'Italy'                   => 'italien',
            'Vereinigtes Königreich'  => 'grossbritannien',
            'United Kingdom'          => 'grossbritannien',
            'England'                 => 'england',
        ],
    ];
}

/**
 * Registry of drill-down-enabled countries (Section 18 "Version 2" of the
 * implementation plan). Each entry's `codes` map a normalized region name
 * (mv_geo_explorer_normalize_name()) to the matching feature `id` in the
 * corresponding regions GeoJSON file, so the data builder can resolve
 * wp_geo_tagger_places region rows to map shapes without a shared code
 * column between the two. Add the next country (UK/Italy/Spain) here once
 * its regions GeoJSON exists — no other code changes needed.
 */
function mv_geo_explorer_drilldowns(): array {
    return [
        'france' => [
            'geo_file' => 'regions-france.simple.geojson',
            'codes'    => mv_geo_explorer_france_region_codes(),
        ],
    ];
}

/**
 * Normalized French region name => INSEE region code (matches the `id`
 * property in assets/geo/regions-france.simple.geojson). The 13 metropolitan
 * regions as of the 2016 redistricting; overseas regions (Guadeloupe,
 * Martinique, etc.) have no shape in that file and are intentionally
 * omitted here — they'd still appear in the destination list if they ever
 * have posts, just with no map shape, same as Russia on the Europe map.
 */
function mv_geo_explorer_france_region_codes(): array {
    return [
        'iledefrance'            => '11',
        'centrevaldeloire'       => '24',
        'bourgognefranchecomte'  => '27',
        'normandie'              => '28',
        'hautsdefrance'          => '32',
        'grandest'               => '44',
        'paysdelaloire'          => '52',
        'bretagne'               => '53',
        'nouvelleaquitaine'      => '75',
        'occitanie'              => '76',
        'auvergnerhonealpes'     => '84',
        'provencealpescotedazur' => '93',
        'corse'                  => '94',
    ];
}

function mv_geo_explorer_default_options(): array {
    return [
        'enabled_languages' => ['fr', 'en', 'de'],
        'default_view'      => 'europe',
        'manual_top_posts'  => [],
        'last_generated'    => [],
    ];
}

/**
 * Per-language fallback destination URL, used only when a place has no
 * resolvable post_tag archive link and no hub page of its own. Real slugs
 * confirmed live on mamanvoyage.com.
 */
function mv_geo_explorer_lang_fallback_url(string $lang): string {
    $paths = [
        'fr' => '/commencez-ici/',
        'en' => '/en/home-prototype/',
        'de' => '/de/startseite-prototyp/',
    ];
    $path = $paths[$lang] ?? $paths['fr'];
    return home_url($path);
}

function mv_geo_explorer_ui_strings(): array {
    return [
        'fr' => [
            'loading'          => 'Chargement de la carte…',
            'default_title'    => 'Explorez nos voyages par destination',
            'default_text'     => 'Survolez ou touchez un pays pour voir nos articles et idées de voyages en famille.',
            'article_singular' => 'article',
            'article_plural'   => 'articles',
            'view_articles'    => 'Voir les articles',
            'top_articles'     => 'À lire pour commencer',
            'error'            => 'La carte n’a pas pu être chargée pour le moment.',
            'list_heading'     => 'Toutes nos destinations',
            'map_aria_label'   => 'Carte interactive des destinations',
            'view_regions'     => 'Voir les régions',
            'back_to_europe'   => 'Retour à l’Europe',
        ],
        'en' => [
            'loading'          => 'Loading the map…',
            'default_title'    => 'Explore our trips by destination',
            'default_text'     => 'Hover or tap a country to see our family travel articles and ideas.',
            'article_singular' => 'article',
            'article_plural'   => 'articles',
            'view_articles'    => 'View articles',
            'top_articles'     => 'Start with these articles',
            'error'            => 'The map could not be loaded right now.',
            'list_heading'     => 'All our destinations',
            'map_aria_label'   => 'Interactive map of destinations',
            'view_regions'     => 'View regions',
            'back_to_europe'   => 'Back to Europe',
        ],
        'de' => [
            'loading'          => 'Karte wird geladen…',
            'default_title'    => 'Entdeckt unsere Reisen nach Reiseziel',
            'default_text'     => 'Fahrt mit der Maus über ein Land oder tippt darauf, um unsere Familienreise-Artikel zu sehen.',
            'article_singular' => 'Artikel',
            'article_plural'   => 'Artikel',
            'view_articles'    => 'Artikel ansehen',
            'top_articles'     => 'Zum Einstieg',
            'error'            => 'Die Karte konnte gerade nicht geladen werden.',
            'list_heading'     => 'Alle unsere Reiseziele',
            'map_aria_label'   => 'Interaktive Karte der Reiseziele',
            'view_regions'     => 'Regionen ansehen',
            'back_to_europe'   => 'Zurück zu Europa',
        ],
    ];
}
