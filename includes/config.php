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
 * each feature in both europe-countries.simple.geojson and
 * world-countries.simple.geojson — worldwide coverage, needed now that the
 * map supports zooming out to the World view, not just Europe). Sourced
 * from the `world-countries` npm package (verified ISO data), with one
 * deliberate override: 'xk' (Kosovo) maps to 'XKX' here to match the actual
 * `A3` property in the geojson source data (@geo-maps/countries-land-10km),
 * not 'UNK' which is what that npm package itself uses.
 */
function mv_geo_explorer_alpha2_to_alpha3(): array {
    return [
        'ad' => 'AND', 'ae' => 'ARE', 'af' => 'AFG', 'ag' => 'ATG', 'ai' => 'AIA', 'al' => 'ALB',
        'am' => 'ARM', 'ao' => 'AGO', 'aq' => 'ATA', 'ar' => 'ARG', 'as' => 'ASM', 'at' => 'AUT',
        'au' => 'AUS', 'aw' => 'ABW', 'ax' => 'ALA', 'az' => 'AZE', 'ba' => 'BIH', 'bb' => 'BRB',
        'bd' => 'BGD', 'be' => 'BEL', 'bf' => 'BFA', 'bg' => 'BGR', 'bh' => 'BHR', 'bi' => 'BDI',
        'bj' => 'BEN', 'bl' => 'BLM', 'bm' => 'BMU', 'bn' => 'BRN', 'bo' => 'BOL', 'bq' => 'BES',
        'br' => 'BRA', 'bs' => 'BHS', 'bt' => 'BTN', 'bv' => 'BVT', 'bw' => 'BWA', 'by' => 'BLR',
        'bz' => 'BLZ', 'ca' => 'CAN', 'cc' => 'CCK', 'cd' => 'COD', 'cf' => 'CAF', 'cg' => 'COG',
        'ch' => 'CHE', 'ci' => 'CIV', 'ck' => 'COK', 'cl' => 'CHL', 'cm' => 'CMR', 'cn' => 'CHN',
        'co' => 'COL', 'cr' => 'CRI', 'cu' => 'CUB', 'cv' => 'CPV', 'cw' => 'CUW', 'cx' => 'CXR',
        'cy' => 'CYP', 'cz' => 'CZE', 'de' => 'DEU', 'dj' => 'DJI', 'dk' => 'DNK', 'dm' => 'DMA',
        'do' => 'DOM', 'dz' => 'DZA', 'ec' => 'ECU', 'ee' => 'EST', 'eg' => 'EGY', 'eh' => 'ESH',
        'er' => 'ERI', 'es' => 'ESP', 'et' => 'ETH', 'fi' => 'FIN', 'fj' => 'FJI', 'fk' => 'FLK',
        'fm' => 'FSM', 'fo' => 'FRO', 'fr' => 'FRA', 'ga' => 'GAB', 'gb' => 'GBR', 'gd' => 'GRD',
        'ge' => 'GEO', 'gf' => 'GUF', 'gg' => 'GGY', 'gh' => 'GHA', 'gi' => 'GIB', 'gl' => 'GRL',
        'gm' => 'GMB', 'gn' => 'GIN', 'gp' => 'GLP', 'gq' => 'GNQ', 'gr' => 'GRC', 'gs' => 'SGS',
        'gt' => 'GTM', 'gu' => 'GUM', 'gw' => 'GNB', 'gy' => 'GUY', 'hk' => 'HKG', 'hm' => 'HMD',
        'hn' => 'HND', 'hr' => 'HRV', 'ht' => 'HTI', 'hu' => 'HUN', 'id' => 'IDN', 'ie' => 'IRL',
        'il' => 'ISR', 'im' => 'IMN', 'in' => 'IND', 'io' => 'IOT', 'iq' => 'IRQ', 'ir' => 'IRN',
        'is' => 'ISL', 'it' => 'ITA', 'je' => 'JEY', 'jm' => 'JAM', 'jo' => 'JOR', 'jp' => 'JPN',
        'ke' => 'KEN', 'kg' => 'KGZ', 'kh' => 'KHM', 'ki' => 'KIR', 'km' => 'COM', 'kn' => 'KNA',
        'kp' => 'PRK', 'kr' => 'KOR', 'kw' => 'KWT', 'ky' => 'CYM', 'kz' => 'KAZ', 'la' => 'LAO',
        'lb' => 'LBN', 'lc' => 'LCA', 'li' => 'LIE', 'lk' => 'LKA', 'lr' => 'LBR', 'ls' => 'LSO',
        'lt' => 'LTU', 'lu' => 'LUX', 'lv' => 'LVA', 'ly' => 'LBY', 'ma' => 'MAR', 'mc' => 'MCO',
        'md' => 'MDA', 'me' => 'MNE', 'mf' => 'MAF', 'mg' => 'MDG', 'mh' => 'MHL', 'mk' => 'MKD',
        'ml' => 'MLI', 'mm' => 'MMR', 'mn' => 'MNG', 'mo' => 'MAC', 'mp' => 'MNP', 'mq' => 'MTQ',
        'mr' => 'MRT', 'ms' => 'MSR', 'mt' => 'MLT', 'mu' => 'MUS', 'mv' => 'MDV', 'mw' => 'MWI',
        'mx' => 'MEX', 'my' => 'MYS', 'mz' => 'MOZ', 'na' => 'NAM', 'nc' => 'NCL', 'ne' => 'NER',
        'nf' => 'NFK', 'ng' => 'NGA', 'ni' => 'NIC', 'nl' => 'NLD', 'no' => 'NOR', 'np' => 'NPL',
        'nr' => 'NRU', 'nu' => 'NIU', 'nz' => 'NZL', 'om' => 'OMN', 'pa' => 'PAN', 'pe' => 'PER',
        'pf' => 'PYF', 'pg' => 'PNG', 'ph' => 'PHL', 'pk' => 'PAK', 'pl' => 'POL', 'pm' => 'SPM',
        'pn' => 'PCN', 'pr' => 'PRI', 'ps' => 'PSE', 'pt' => 'PRT', 'pw' => 'PLW', 'py' => 'PRY',
        'qa' => 'QAT', 're' => 'REU', 'ro' => 'ROU', 'rs' => 'SRB', 'ru' => 'RUS', 'rw' => 'RWA',
        'sa' => 'SAU', 'sb' => 'SLB', 'sc' => 'SYC', 'sd' => 'SDN', 'se' => 'SWE', 'sg' => 'SGP',
        'sh' => 'SHN', 'si' => 'SVN', 'sj' => 'SJM', 'sk' => 'SVK', 'sl' => 'SLE', 'sm' => 'SMR',
        'sn' => 'SEN', 'so' => 'SOM', 'sr' => 'SUR', 'ss' => 'SSD', 'st' => 'STP', 'sv' => 'SLV',
        'sx' => 'SXM', 'sy' => 'SYR', 'sz' => 'SWZ', 'tc' => 'TCA', 'td' => 'TCD', 'tf' => 'ATF',
        'tg' => 'TGO', 'th' => 'THA', 'tj' => 'TJK', 'tk' => 'TKL', 'tl' => 'TLS', 'tm' => 'TKM',
        'tn' => 'TUN', 'to' => 'TON', 'tr' => 'TUR', 'tt' => 'TTO', 'tv' => 'TUV', 'tw' => 'TWN',
        'tz' => 'TZA', 'ua' => 'UKR', 'ug' => 'UGA', 'um' => 'UMI', 'us' => 'USA', 'uy' => 'URY',
        'uz' => 'UZB', 'va' => 'VAT', 'vc' => 'VCT', 've' => 'VEN', 'vg' => 'VGB', 'vi' => 'VIR',
        'vn' => 'VNM', 'vu' => 'VUT', 'wf' => 'WLF', 'ws' => 'WSM', 'xk' => 'XKX', 'ye' => 'YEM',
        'yt' => 'MYT', 'za' => 'ZAF', 'zm' => 'ZMB', 'zw' => 'ZWE',
    ];
}

/**
 * The country_codes actually present as features in
 * assets/geo/europe-countries.simple.geojson (the same curated set used to
 * build that file). Needed since mv_geo_explorer_alpha2_to_alpha3() above
 * now covers every country worldwide (for the World view) — without this,
 * the data builder can't tell "this country has a resolvable alpha-3 code"
 * apart from "this country is actually shown on the Europe map", and the
 * Europe view's destination list would wrongly include every country with
 * posts worldwide instead of just the ~49 European ones.
 */
function mv_geo_explorer_europe_country_codes(): array {
    return [
        'ad', 'al', 'am', 'at', 'ax', 'az', 'ba', 'be', 'bg', 'by', 'ch', 'cy',
        'cz', 'de', 'dk', 'ee', 'es', 'fi', 'fo', 'fr', 'gb', 'ge', 'gr', 'hr',
        'hu', 'ie', 'im', 'is', 'it', 'li', 'lt', 'lu', 'lv', 'md', 'me', 'mk',
        'mt', 'nl', 'no', 'pl', 'pt', 'ro', 'rs', 'se', 'si', 'sk', 'tr', 'ua', 'xk',
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
 * implementation plan), keyed by ISO 3166-1 alpha-2 country code (the same
 * `country_code` column wp_geo_tagger_places already stores) — NOT by place
 * slug, since the slug is language-specific (e.g. 'united-kingdom' in EN vs
 * 'royaume-uni' in FR) while the country code is the same across all three
 * language indexes. Each entry's `codes` map a normalized region name
 * (mv_geo_explorer_normalize_name(), checked against name_fr/en/de in turn —
 * see MV_Geo_Data_Builder::resolve_region_code()) to the matching feature
 * `id` in the corresponding regions GeoJSON file. Add the next country here
 * once its regions GeoJSON exists — no other code changes needed.
 */
function mv_geo_explorer_drilldowns(): array {
    return [
        'fr' => [
            'geo_file' => 'regions-france.simple.geojson',
            'codes'    => mv_geo_explorer_france_region_codes(),
        ],
        'gb' => [
            'geo_file' => 'regions-uk.simple.geojson',
            'codes'    => mv_geo_explorer_uk_region_codes(),
        ],
        'it' => [
            'geo_file' => 'regions-italy.simple.geojson',
            'codes'    => mv_geo_explorer_italy_region_codes(),
        ],
        'es' => [
            'geo_file' => 'regions-spain.simple.geojson',
            'codes'    => mv_geo_explorer_spain_region_codes(),
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

/**
 * Normalized region name => feature id in assets/geo/regions-uk.simple.geojson
 * (ENG/SCT/WLS/NIR — England merged from Eurostat's 9 NUTS1 sub-regions, the
 * other three are single NUTS1 units). Unlike France, the UK's region tag on
 * this site is inconsistent — Nominatim's `state` field returns one of these
 * four nations for most posts, but sometimes a ceremonial county instead
 * (confirmed live: "Cotswolds" and "Cornwall" both exist as real region-level
 * tags). Those have no shape here and will list-only, same as Canarias for
 * Spain below — covering every possible English county is out of scope for
 * the MVP drilldown. English/French/German name variants are included since
 * a UK post's region name could come from any of the three Nominatim calls.
 */
function mv_geo_explorer_uk_region_codes(): array {
    return [
        'england'        => 'ENG',
        'angleterre'     => 'ENG',
        'scotland'       => 'SCT',
        'ecosse'         => 'SCT',
        'schottland'     => 'SCT',
        'wales'          => 'WLS',
        'paysdegalles'   => 'WLS',
        'northernireland' => 'NIR',
        'irlandedunord'  => 'NIR',
        'nordirland'     => 'NIR',
    ];
}

/**
 * Normalized region name => ISTAT region code, matching
 * assets/geo/regions-italy.simple.geojson. Italian region names are rarely
 * translated by Nominatim, so the native Italian name is the primary key;
 * the handful of common English/French/German exonyms are included too.
 */
function mv_geo_explorer_italy_region_codes(): array {
    return [
        'piemonte'                       => '01',
        'piedmont'                       => '01',
        'valledaosta'                    => '02',
        'valleedaoste'                   => '02',
        'lombardia'                      => '03',
        'lombardy'                       => '03',
        'trentinoaltoadigesudtirol'      => '04',
        'veneto'                         => '05',
        'friuliveneziagiulia'            => '06',
        'liguria'                        => '07',
        'emiliaromagna'                  => '08',
        'toscana'                        => '09',
        'tuscany'                        => '09',
        'toskana'                        => '09',
        'umbria'                         => '10',
        'marche'                         => '11',
        'lazio'                          => '12',
        'abruzzo'                        => '13',
        'abruzzes'                       => '13',
        'molise'                         => '14',
        'campania'                       => '15',
        'puglia'                         => '16',
        'apulia'                         => '16',
        'pouilles'                       => '16',
        'basilicata'                     => '17',
        'calabria'                       => '18',
        'calabre'                        => '18',
        'kalabrien'                      => '18',
        'sicilia'                        => '19',
        'sicily'                         => '19',
        'sicile'                         => '19',
        'sizilien'                       => '19',
        'sardegna'                       => '20',
        'sardinia'                       => '20',
        'sardaigne'                      => '20',
        'sardinien'                      => '20',
    ];
}

/**
 * Normalized region name => INE region code, matching
 * assets/geo/regions-spain.simple.geojson. Canarias, Ceuta and Melilla have
 * no shape in that file (Canarias is ~1,300km from the mainland — would
 * force the regions map to zoom out the same way Russia would on the Europe
 * map; Ceuta/Melilla are tiny African enclaves, same treatment as San
 * Marino/Andorra-scale micro-territories). Posts tagged with those still
 * count toward Spain's total and list-only in the drilldown view. Several
 * autonomous communities are commonly returned by Nominatim under their
 * native (Catalan/Basque) name rather than the Castilian one — both are
 * included.
 */
function mv_geo_explorer_spain_region_codes(): array {
    return [
        'andalucia'                  => '01',
        'andalusia'                  => '01',
        'andalousie'                 => '01',
        'andalusien'                 => '01',
        'aragon'                     => '02',
        'aragonien'                  => '02',
        'asturiasprincipadode'       => '03',
        'principadodeasturias'       => '03',
        'asturias'                   => '03',
        'illesbalears'               => '04',
        'balearsilles'               => '04',
        'islasbaleares'              => '04',
        'balearicislands'            => '04',
        'ilesbaleares'               => '04',
        'balearen'                   => '04',
        'cantabria'                  => '06',
        'castillayleon'              => '07',
        'castileandleon'             => '07',
        'castillalamancha'           => '08',
        'catalunya'                  => '09',
        'cataluna'                   => '09',
        'catalonia'                  => '09',
        'catalogne'                  => '09',
        'katalonien'                 => '09',
        'comunitatvalenciana'        => '10',
        'comunidadvalenciana'        => '10',
        'extremadura'                => '11',
        'galicia'                    => '12',
        'galice'                     => '12',
        'madridcomunidadde'          => '13',
        'comunidaddemadrid'          => '13',
        'madrid'                     => '13',
        'murciaregionde'             => '14',
        'regiondemurcia'             => '14',
        'murcia'                     => '14',
        'navarracomunidadforalde'    => '15',
        'comunidadforaldenavarra'    => '15',
        'navarra'                    => '15',
        'navarre'                    => '15',
        'paisvasco'                  => '16',
        'euskadi'                    => '16',
        'basquecountry'              => '16',
        'paysbasque'                 => '16',
        'baskenland'                 => '16',
        'riojala'                    => '17',
        'larioja'                    => '17',
        'rioja'                      => '17',
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
            'view_articles'    => 'Tous les articles',
            'top_articles'     => 'Les plus lu :',
            'error'            => 'La carte n’a pas pu être chargée pour le moment.',
            'list_heading'     => 'Toutes nos destinations en Europe',
            'map_aria_label'   => 'Carte interactive des destinations',
            'view_regions'     => 'Voir les régions',
            'back_to_europe'   => 'Retour à l’Europe',
            'view_world'       => 'Voir le monde entier',
            'back_to_world'    => 'Retour au monde',
            'list_heading_world' => 'Toutes nos destinations dans le monde',
        ],
        'en' => [
            'loading'          => 'Loading the map…',
            'default_title'    => 'Explore our trips by destination',
            'default_text'     => 'Hover or tap a country to see our family travel articles and ideas.',
            'article_singular' => 'article',
            'article_plural'   => 'articles',
            'view_articles'    => 'All articles',
            'top_articles'     => 'Most read articles:',
            'error'            => 'The map could not be loaded right now.',
            'list_heading'     => 'All our destinations in Europe',
            'map_aria_label'   => 'Interactive map of destinations',
            'view_regions'     => 'View regions',
            'back_to_europe'   => 'Back to Europe',
            'view_world'       => 'View the whole world',
            'back_to_world'    => 'Back to the world',
            'list_heading_world' => 'All our destinations worldwide',
        ],
        'de' => [
            'loading'          => 'Karte wird geladen…',
            'default_title'    => 'Entdeckt unsere Reisen nach Reiseziel',
            'default_text'     => 'Fahrt mit der Maus über ein Land oder tippt darauf, um unsere Familienreise-Artikel zu sehen.',
            'article_singular' => 'Artikel',
            'article_plural'   => 'Artikel',
            'view_articles'    => 'Alle Artikel',
            'top_articles'     => 'Meistgelesen:',
            'error'            => 'Die Karte konnte gerade nicht geladen werden.',
            'list_heading'     => 'Alle unsere Reiseziele in Europa',
            'map_aria_label'   => 'Interaktive Karte der Reiseziele',
            'view_regions'     => 'Regionen ansehen',
            'back_to_europe'   => 'Zurück zu Europa',
            'view_world'       => 'Die ganze Welt ansehen',
            'back_to_world'    => 'Zurück zur Welt',
            'list_heading_world' => 'Alle unsere Reiseziele weltweit',
        ],
    ];
}
