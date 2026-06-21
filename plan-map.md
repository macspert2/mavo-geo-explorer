# Maman Voyage Geo Explorer Plugin — Coding Agent Plan

## 0. Project Summary

Build a WordPress plugin that adds a shortcode-powered, D3/SVG-based geographic post-discovery map for Maman Voyage.

The map should be a beautiful editorial discovery layer, not a full GIS/map-tile application. It should use simplified outline maps, soft Maman Voyage-style colours, hover/tap labels, and a side/bottom panel that links readers to country/region hubs and top posts.

The WordPress site already uses:

- GeneratePress child theme.
- Polylang for French, English, and German.
- Geo Mashup for geolocation/location data.
- A separate custom recommendation/category table for some French posts, but this is **not** part of this map MVP.
- Existing hierarchical geographic information for many posts: continent, country, region, city (see mavo_geotagger-plus)

The Geo Mashup plugin is important because it already stores location information for WordPress objects and supports displaying those objects on interactive maps. The new plugin should not duplicate Geo Mashup’s map UI, but it should inspect/reuse Geo Mashup location/admin names where useful for matching posts to countries/regions/tags.

Primary goal:

> Create a shortcode-only D3/SVG map explorer whose assets load only when the shortcode is used.

---

## 1. Core Decisions

### 1.1 Rendering Choice

Use **D3 + SVG**.

Do not use:

- Leaflet.
- Google Maps.
- MapLibre.
- map tiles.
- full slippy-map behaviour.

Reason:

- The desired result is a simple beautiful outline map.
- Less geographic detail is preferred.
- SVG paths are easy to style with the blog’s colours.
- D3 is well suited to converting GeoJSON features into SVG paths.

### 1.2 Loading Choice

The plugin must load **no CSS, no JavaScript, no D3, and no GeoJSON** on pages that do not render the shortcode.

Implementation rule:

- Register assets on `wp_enqueue_scripts`.
- Enqueue assets only inside the shortcode render callback.
- Frontend JS loads JSON/GeoJSON files only after the shortcode root exists.

### 1.3 First User-Facing Version

Build an MVP focused on:

- Europe-first map.
- Countries with posts highlighted.
- Hover/tap label with country name and article count.
- Click/tap opens side panel or bottom panel.
- Side panel shows country name, post count, top posts, and hub link.
- Accessible fallback list below map.
- Language-aware FR/EN/DE.

Do **not** implement regional drill-down in the first coding pass unless the MVP is already complete and stable.

---

## 2. Plugin Name and Directory

Create a plugin directory:

```text
mavo-geo-explorer/
```

Suggested structure:

```text
mavo-geo-explorer/
  mavo-geo-explorer.php
  includes/
    class-mv-geo-explorer.php
    class-mv-geo-assets.php
    class-mv-geo-shortcode.php
    class-mv-geo-data-builder.php
    class-mv-geo-admin.php
    class-mv-geo-geomashup.php
    helpers.php
  assets/
    css/
      mv-geo-explorer.css
    js/
      mv-geo-explorer.js
      vendor/
        d3.min.js
    geo/
      europe-countries.simple.geojson
      world-continents.simple.geojson
      regions-france.simple.geojson
      regions-uk.simple.geojson
    data/
      geo-index-fr.json
      geo-index-en.json
      geo-index-de.json
  templates/
    geo-explorer.php
    no-js-list.php
  admin/
    admin.css
```

Notes:

- `assets/data/*.json` may eventually be written to `wp_upload_dir()` instead of the plugin directory to avoid write-permission issues.
- Prefer uploads directory for generated JSON in production.
- Keep static GeoJSON files in plugin assets.

Recommended generated JSON directory:

```text
wp-content/uploads/mv-geo-explorer/
  geo-index-fr.json
  geo-index-en.json
  geo-index-de.json
```

---

## 3. Shortcode API

Register shortcode:

```text
[mv_geo_explorer]
```

Initial supported attributes:

```text
lang="current|fr|en|de"
default_view="europe|world"
show_list="1|0"
show_counts="1|0"
show_posts="1|0"
max_posts="3"
theme="default|minimal"
```

Example:

```text
[mv_geo_explorer default_view="europe" show_list="1" show_posts="1"]
```

### 3.1 Attribute Defaults

```php
$defaults = array(
    'lang'         => 'current',
    'default_view' => 'europe',
    'show_list'    => '1',
    'show_counts'  => '1',
    'show_posts'   => '1',
    'max_posts'    => '3',
    'theme'        => 'default',
);
```

### 3.2 Shortcode Output Requirements

The shortcode should output:

- One root wrapper with a unique ID.
- A `data-config` JSON object.
- Empty map container for D3.
- Side panel container.
- Accessible destination list fallback.
- Optional loading state.
- Optional no-JS fallback.

Example rendered wrapper:

```html
<div
  id="mv-geo-explorer-1"
  class="mv-geo-explorer mv-geo-explorer--theme-default"
  data-mv-geo-explorer
  data-config="...escaped JSON..."
>
  <div class="mv-geo-explorer__layout">
    <div class="mv-geo-explorer__map-wrap">
      <div class="mv-geo-explorer__loading">Chargement de la carte…</div>
      <svg class="mv-geo-explorer__svg" aria-label="Carte interactive des destinations"></svg>
    </div>
    <aside class="mv-geo-explorer__panel" aria-live="polite"></aside>
  </div>
  <div class="mv-geo-explorer__list"></div>
</div>
```

---

## 4. Asset Loading Rules

### 4.1 Register Assets

In `MV_Geo_Assets`, register assets on `wp_enqueue_scripts`:

```php
wp_register_style(
    'mv-geo-explorer',
    MV_GEO_EXPLORER_URL . 'assets/css/mv-geo-explorer.css',
    array(),
    MV_GEO_EXPLORER_VERSION
);

wp_register_script(
    'mv-d3',
    MV_GEO_EXPLORER_URL . 'assets/js/vendor/d3.min.js',
    array(),
    '7.9.0',
    true
);

wp_register_script(
    'mv-geo-explorer',
    MV_GEO_EXPLORER_URL . 'assets/js/mv-geo-explorer.js',
    array( 'mv-d3' ),
    MV_GEO_EXPLORER_VERSION,
    true
);
```

### 4.2 Enqueue Only in Shortcode Callback

Inside shortcode rendering:

```php
MV_Geo_Assets::enqueue();
```

Where:

```php
public static function enqueue() {
    wp_enqueue_style( 'mv-geo-explorer' );
    wp_enqueue_script( 'mv-geo-explorer' );
}
```

### 4.3 Acceptance Tests

On pages without shortcode:

- No `mv-geo-explorer.css`.
- No `mv-geo-explorer.js`.
- No `d3.min.js`.
- No `geo-index-*.json` fetch.
- No `*.geojson` fetch.

On pages with shortcode:

- CSS loads.
- D3 loads.
- Plugin JS loads.
- Current language index JSON loads.
- Only the needed default-view GeoJSON loads.

---

## 5. Data Model

### 5.1 Canonical Geo Node

Each geographic place should be represented as a node:

```php
array(
    'slug'          => 'france',
    'type'          => 'country', // continent|country|region|city|editorial_region
    'label'         => 'France',
    'lang'          => 'fr',
    'parent'        => 'europe',
    'children'      => array( 'bretagne', 'provence' ),
    'post_count'    => 145,
    'hub_url'       => 'https://www.mamanvoyage.com/france/',
    'map_shape_id'  => 'FRA',
    'drilldown'     => false,
    'top_posts'     => array(),
)
```

### 5.2 Generated JSON Format

Generate one JSON file per language:

```text
geo-index-fr.json
geo-index-en.json
geo-index-de.json
```

Example:

```json
{
  "lang": "fr",
  "generated_at": "2026-06-21T10:30:00+00:00",
  "default_view": "europe",
  "shape_map": {
    "FRA": "france",
    "ITA": "italie",
    "ESP": "espagne",
    "GBR": "royaume-uni"
  },
  "places": {
    "france": {
      "type": "country",
      "label": "France",
      "post_count": 145,
      "url": "https://www.mamanvoyage.com/france/",
      "parent": "europe",
      "children": ["bretagne", "provence"],
      "map_shape_id": "FRA",
      "drilldown": false,
      "top_posts": [
        {
          "id": 123,
          "title": "France en famille : nos meilleures idées",
          "url": "https://www.mamanvoyage.com/france/",
          "thumb": "https://www.mamanvoyage.com/wp-content/uploads/...jpg"
        }
      ]
    }
  }
}
```

### 5.3 Minimum JSON Fields for MVP

Required:

- `lang`
- `generated_at`
- `shape_map`
- `places`
- for each country:
  - `type`
  - `label`
  - `post_count`
  - `url`
  - `map_shape_id`
  - `top_posts`

Optional:

- `children`
- `parent`
- `drilldown`
- `description`

---

## 6. Geo Mashup Integration

### 6.1 Purpose

The site already uses the Geo Mashup plugin. Use it as a source of location/admin naming data where possible.

Geo Mashup stores location information for posts/pages and can display those WordPress objects on interactive maps. The new D3 plugin should not use Geo Mashup’s frontend map renderer, but should use available Geo Mashup data to help match:

```text
post ID → location → admin country/region/city names → internal geo slug/tag
```

### 6.2 Create Integration Class

Create:

```text
includes/class-mv-geo-geomashup.php
```

Responsibilities:

- Detect whether Geo Mashup is active.
- Inspect available Geo Mashup tables/classes/functions safely.
- Provide a normalized method to get location/admin data for a post.
- Fail gracefully if Geo Mashup is inactive or data is missing.

Suggested public methods:

```php
class MV_Geo_GeoMashup {
    public static function is_available(): bool {}
    public static function get_post_location( int $post_id ): ?array {}
    public static function get_admin_names_for_post( int $post_id ): array {}
}
```

Expected normalized return shape:

```php
array(
    'lat'       => 48.8566,
    'lng'       => 2.3522,
    'country'   => 'France',
    'region'    => 'Île-de-France',
    'city'      => 'Paris',
    'raw'       => $raw_geo_mashup_location,
)
```

### 6.3 Matching Strategy

Use Geo Mashup admin names as an additional matching source, not as the only source.

Matching priority:

1. Existing explicit hierarchical geo metadata/tags if available.
2. Existing post tags/taxonomies used for continent/country/region/city.
3. Geo Mashup admin country/region/city names.
4. Manual mapping table.
5. Fallback: exclude from generated map index and log as unmatched.

### 6.4 Manual Mapping Table

Create a mapping array/config that maps Geo Mashup names to canonical slugs:

```php
$geo_name_map = array(
    'fr' => array(
        'France' => 'france',
        'Italie' => 'italie',
        'Royaume-Uni' => 'royaume-uni',
        'United Kingdom' => 'royaume-uni',
        'England' => 'angleterre',
        'Angleterre' => 'angleterre',
    ),
    'en' => array(
        'France' => 'france',
        'Italy' => 'italy',
        'United Kingdom' => 'united-kingdom',
        'England' => 'england',
    ),
    'de' => array(
        'Frankreich' => 'frankreich',
        'France' => 'frankreich',
        'Italien' => 'italien',
        'Italy' => 'italien',
        'Vereinigtes Königreich' => 'grossbritannien',
        'United Kingdom' => 'grossbritannien',
        'England' => 'england',
    ),
);
```

This can be a PHP config file in MVP, later moved to admin UI.

### 6.5 Admin Diagnostics

The admin rebuild screen should show:

- Number of posts processed per language.
- Number matched through explicit hierarchy.
- Number matched through Geo Mashup.
- Number matched through manual mapping.
- Number unmatched.
- List/table of top unmatched Geo Mashup names.

This is important for improving the mapping over time.

Example diagnostic output:

```text
FR rebuild complete:
- 612 posts processed
- 480 matched via existing hierarchy
- 89 matched via Geo Mashup country/region names
- 21 matched via manual map
- 22 unmatched

Unmatched names:
- The Cotswolds, 4 posts
- South Downs, 3 posts
```

### 6.6 Do Not Depend Hard on Geo Mashup

The plugin should still work if Geo Mashup is disabled.

If disabled:

- Use existing hierarchy/tag data only.
- Show admin notice on plugin settings page, not frontend.
- Do not fatal error.

---

## 7. Polylang Handling

### 7.1 Current Language

Use:

```php
$lang = function_exists( 'pll_current_language' ) ? pll_current_language() : 'fr';
```

### 7.2 Supported Languages

Initial languages:

```text
fr
en
de
```

### 7.3 Language-Specific Data

Each language gets its own JSON index and only shows places with posts in that language.

Rules:

- French map can be rich.
- English/German maps should remain simpler if fewer posts exist.
- Do not show countries with zero posts for the current language as active.
- Do not link EN/DE users to French pages unless explicitly configured.

### 7.4 Labels and URLs

Use language-specific labels and hub URLs.

Data builder should resolve:

- Translated labels.
- Translated hub page URLs if they exist.
- Fallback URLs if no translated hub exists.

Possible fallback:

- French: `/commencez-ici/` or country hub.
- English: `/en/home-prototype/` or future English destinations page.
- German: `/de/startseite-prototyp/` or future German destinations page.

For MVP, hard-code reasonable language fallback URLs in config.

---

## 8. GeoJSON Files

### 8.1 Required MVP File

Create/obtain:

```text
assets/geo/europe-countries.simple.geojson
```

This file should contain simplified country polygons for Europe.

Each feature should have a stable shape ID, ideally ISO alpha-3:

```json
{
  "type": "Feature",
  "properties": {
    "id": "FRA",
    "iso_a3": "FRA",
    "name": "France"
  },
  "geometry": { ... }
}
```

### 8.2 Optional Later Files

```text
assets/geo/world-continents.simple.geojson
assets/geo/regions-france.simple.geojson
assets/geo/regions-uk.simple.geojson
assets/geo/regions-italy.simple.geojson
assets/geo/regions-spain.simple.geojson
```

### 8.3 Simplification Requirements

Use low-detail outlines.

Guidelines:

- Keep file size small.
- Remove unnecessary properties.
- Simplify coastlines.
- Avoid too many small islands.
- Keep shapes recognisable, not precise.

Target MVP GeoJSON size:

```text
Europe countries GeoJSON: ideally < 250 KB uncompressed, lower if possible.
```

### 8.4 Shape Mapping

The data builder must export `shape_map`:

```json
{
  "FRA": "france",
  "ITA": "italie",
  "ESP": "espagne",
  "GBR": "royaume-uni"
}
```

Frontend uses this mapping to connect map shapes to site places.

---

## 9. Frontend JavaScript Requirements

### 9.1 Main Class

Implement:

```js
class MVGeoExplorer {
  constructor(root, config) {}
  async init() {}
  async loadIndex() {}
  async loadGeoJson(view) {}
  render() {}
  renderMap(featureCollection) {}
  renderPanel(placeSlug) {}
  renderList() {}
  selectPlace(placeSlug) {}
  clearHover() {}
  setHover(placeSlug) {}
}
```

Initialize all instances:

```js
document.querySelectorAll('[data-mv-geo-explorer]').forEach((root) => {
  const config = JSON.parse(root.dataset.config || '{}');
  new MVGeoExplorer(root, config).init();
});
```

### 9.2 Data Loading

The JS should fetch:

1. `geo-index-{lang}.json`
2. default view GeoJSON, initially `europe-countries.simple.geojson`

Do not fetch regional files in MVP.

### 9.3 D3 Rendering

Use:

```js
const projection = d3.geoNaturalEarth1();
projection.fitSize([width, height], featureCollection);
const path = d3.geoPath(projection);
```

Then render paths:

```js
svg.selectAll('path')
  .data(featureCollection.features)
  .join('path')
  .attr('d', path)
  .attr('class', ...);
```

### 9.4 Shape State Classes

Add classes:

```text
mv-geo-shape
mv-geo-shape--empty
mv-geo-shape--has-posts
mv-geo-shape--hover
mv-geo-shape--selected
```

A shape is active if:

```js
const placeSlug = index.shape_map[shapeId];
const place = index.places[placeSlug];
const hasPosts = place && place.post_count > 0;
```

### 9.5 Desktop Interaction

On mouseenter:

- Highlight shape.
- Show tooltip or update panel preview.
- Show label and post count.

On mouseleave:

- Remove hover class.
- Keep selected state if selected.

On click:

- Select place.
- Render side panel.

### 9.6 Mobile Interaction

On touch/click:

- Select place.
- Render bottom/side panel.
- Do not immediately navigate.

Panel buttons handle navigation:

- “Voir les articles” / “View articles” / “Artikel ansehen”.

### 9.7 Responsive SVG

SVG should use `viewBox` and resize with container.

Implement resize handler with debounce:

```js
window.addEventListener('resize', debounce(() => this.render(), 150));
```

### 9.8 Error Handling

If JSON or GeoJSON fails:

- Hide loading state.
- Show friendly error.
- Keep fallback list visible if available.

---

## 10. Frontend CSS Requirements

### 10.1 Visual Style

Use CSS variables:

```css
.mv-geo-explorer {
  --mv-geo-bg: #fbf7f1;
  --mv-geo-land-empty: #eee4d7;
  --mv-geo-land-active: #d9eadf;
  --mv-geo-land-hover: #f2c8a2;
  --mv-geo-land-selected: #e8a46f;
  --mv-geo-border: #ffffff;
  --mv-geo-text: #333333;
  --mv-geo-muted: #777777;
  --mv-geo-panel-bg: #ffffff;
  --mv-geo-panel-border: rgba(0,0,0,0.08);
}
```

Adjust exact colours later to match the blog.

### 10.2 Layout

Desktop:

```css
.mv-geo-explorer__layout {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
  gap: 1.5rem;
  align-items: stretch;
}
```

Mobile:

```css
@media (max-width: 760px) {
  .mv-geo-explorer__layout {
    display: block;
  }
}
```

### 10.3 Map Styling

```css
.mv-geo-explorer__map-wrap {
  background: var(--mv-geo-bg);
  border-radius: 18px;
  padding: 1rem;
  min-height: 360px;
}

.mv-geo-shape {
  fill: var(--mv-geo-land-empty);
  stroke: var(--mv-geo-border);
  stroke-width: 0.7;
  vector-effect: non-scaling-stroke;
  transition: fill 160ms ease, opacity 160ms ease;
}

.mv-geo-shape--has-posts {
  fill: var(--mv-geo-land-active);
  cursor: pointer;
}

.mv-geo-shape--hover {
  fill: var(--mv-geo-land-hover);
}

.mv-geo-shape--selected {
  fill: var(--mv-geo-land-selected);
}
```

### 10.4 Panel Styling

Panel should look editorial, not technical:

```css
.mv-geo-explorer__panel {
  background: var(--mv-geo-panel-bg);
  border: 1px solid var(--mv-geo-panel-border);
  border-radius: 18px;
  padding: 1.25rem;
}
```

### 10.5 Accessibility

Ensure focus styles:

```css
.mv-geo-shape:focus {
  outline: 2px solid var(--mv-geo-land-selected);
  outline-offset: 2px;
}
```

---

## 11. Panel Copy and UI Strings

### 11.1 Required Strings

Per language:

- Loading message.
- Default panel title.
- Default panel text.
- Post count singular/plural.
- “View articles” button.
- “Top articles” label.
- Error message.

### 11.2 French

```php
'loading' => 'Chargement de la carte…',
'default_title' => 'Explorez nos voyages par destination',
'default_text' => 'Survolez ou touchez un pays pour voir nos articles et idées de voyages en famille.',
'article_singular' => 'article',
'article_plural' => 'articles',
'view_articles' => 'Voir les articles',
'top_articles' => 'À lire pour commencer',
'error' => 'La carte n’a pas pu être chargée pour le moment.',
```

### 11.3 English

```php
'loading' => 'Loading the map…',
'default_title' => 'Explore our trips by destination',
'default_text' => 'Hover or tap a country to see our family travel articles and ideas.',
'article_singular' => 'article',
'article_plural' => 'articles',
'view_articles' => 'View articles',
'top_articles' => 'Start with these articles',
'error' => 'The map could not be loaded right now.',
```

### 11.4 German

```php
'loading' => 'Karte wird geladen…',
'default_title' => 'Entdeckt unsere Reisen nach Reiseziel',
'default_text' => 'Fahrt mit der Maus über ein Land oder tippt darauf, um unsere Familienreise-Artikel zu sehen.',
'article_singular' => 'Artikel',
'article_plural' => 'Artikel',
'view_articles' => 'Artikel ansehen',
'top_articles' => 'Zum Einstieg',
'error' => 'Die Karte konnte gerade nicht geladen werden.',
```

---

## 12. Data Builder Requirements

### 12.1 Manual Admin Rebuild

Add an admin page under Tools:

```text
Tools → Maman Voyage Geo Explorer
```

Buttons:

```text
Rebuild French index
Rebuild English index
Rebuild German index
Rebuild all indexes
```

Show status:

```text
Last generated
Number of posts processed
Number of places generated
Number of unmatched posts/names
Generated file path
```

### 12.2 Data Builder Process

For each language:

1. Query published posts in that language.
2. For each post, get explicit geo hierarchy/tags.
3. If explicit hierarchy incomplete, ask Geo Mashup integration for admin names.
4. Normalize names to canonical geo slugs.
5. Count posts by continent/country/region/city.
6. Build place nodes.
7. Select top posts per country.
8. Generate `shape_map`.
9. Write JSON to uploads directory.
10. Store status in option.

### 12.3 Top Posts Selection

For MVP, simple top posts logic:

1. Prefer manually curated/hub page if configured.
2. Otherwise use latest posts for that country.
3. Limit to `max_posts`, default 3.

Do not integrate the recommendation table yet.

### 12.4 Options

Store plugin options:

```php
mv_geo_explorer_options = array(
    'data_dir' => '/uploads/mv-geo-explorer/',
    'enabled_languages' => array( 'fr', 'en', 'de' ),
    'default_view' => 'europe',
    'manual_shape_map' => array(...),
    'manual_name_map' => array(...),
    'hub_urls' => array(...),
    'last_generated' => array(...),
)
```

MVP can hard-code most mappings and add admin UI later.

---

## 13. Matching Existing Geographic Tags/Hierarchy

### 13.1 Required Adapter

Because the exact current structure may be custom, create adapter methods and keep them isolated.

Suggested class/method:

```php
class MV_Geo_Data_Builder {
    private function get_explicit_geo_for_post( int $post_id, string $lang ): array {}
}
```

Return:

```php
array(
    'continent' => 'europe',
    'country'   => 'france',
    'region'    => 'bretagne',
    'city'      => 'saint-malo',
)
```

### 13.2 Implementation Instruction for Coding Agent

Do not spread taxonomy/meta-specific logic throughout the plugin.

All current-site-specific geo extraction should live in:

```text
MV_Geo_Data_Builder::get_explicit_geo_for_post()
```

and/or helper methods in:

```text
includes/helpers.php
```

This makes future changes easier.

---

## 14. Accessibility Requirements

### 14.1 Keyboard

Each active country path should be keyboard-focusable:

```js
.attr('tabindex', hasPosts ? 0 : -1)
.attr('role', hasPosts ? 'button' : 'img')
.attr('aria-label', ...)
```

Handle:

```text
Enter
Space
```

same as click.

### 14.2 Fallback List

Always render a destination list from JSON after load.

If JS fails completely, the PHP template should include at least a basic fallback message/list if available.

For MVP, because the list depends on JSON, it is acceptable for JS to render the list, but add `<noscript>` fallback copy:

```html
<noscript>
  <p>La carte interactive nécessite JavaScript. Vous pouvez aussi explorer nos destinations depuis la page Commencez ici.</p>
</noscript>
```

Later improve with server-rendered list.

### 14.3 Tiny Countries

Because tiny countries are hard to tap, the list below the map is required.

---

## 15. Security Requirements

### 15.1 Shortcode Attributes

Sanitize:

```php
sanitize_key()
absint()
```

Whitelist values for:

- `lang`
- `default_view`
- `theme`

### 15.2 Escaping

Use:

- `esc_html()` for visible text.
- `esc_url()` for URLs.
- `esc_attr()` for attributes.
- `wp_json_encode()` for config.

### 15.3 Admin Actions

Admin rebuild must check:

```php
current_user_can( 'manage_options' )
check_admin_referer()
```

### 15.4 File Writing

Write generated JSON only to the plugin’s configured uploads directory.

Do not write arbitrary paths from request data.

---

## 16. Performance Requirements

### 16.1 Non-Shortcode Pages

No plugin frontend assets loaded.

### 16.2 Shortcode Pages

Load only:

- D3.
- Plugin JS.
- Plugin CSS.
- Current language geo index JSON.
- Current/default view GeoJSON.

### 16.3 Lazy Loading

Regional GeoJSON files must not be loaded until regional drill-down is implemented and triggered.

### 16.4 File Size Goals

- CSS: small.
- Plugin JS: small.
- D3: only loaded on shortcode pages.
- Europe GeoJSON: simplified.
- Geo index JSON: compact, no excessive post data.

Top posts should include only:

```text
id
title
url
thumb
```

No full excerpts in MVP.

---

## 17. MVP Implementation Tasks

### Task 1: Plugin Skeleton

Create plugin files:

```text
mamanvoyage-geo-explorer.php
includes/class-mv-geo-explorer.php
includes/class-mv-geo-assets.php
includes/class-mv-geo-shortcode.php
includes/helpers.php
```

Acceptance:

- Plugin activates.
- No fatal errors.
- No frontend output without shortcode.

### Task 2: Asset Registration and Shortcode-Only Enqueue

Implement asset registration and enqueue only inside shortcode.

Acceptance:

- Page without shortcode loads no plugin CSS/JS/D3.
- Page with shortcode loads plugin CSS/JS/D3.

### Task 3: Shortcode HTML Template

Create `templates/geo-explorer.php`.

Acceptance:

- `[mv_geo_explorer]` outputs root wrapper, map container, panel, list container, data config.

### Task 4: Static Test Data

Create temporary test files:

```text
assets/data/geo-index-fr.json
assets/geo/europe-countries.simple.geojson
```

Acceptance:

- JS can load them.
- France/Italy/Spain or similar test countries show active.

### Task 5: D3 Map Rendering

Implement JS to render Europe GeoJSON.

Acceptance:

- SVG map appears.
- Countries render as simple outlines.
- Countries with posts have active fill colour.
- Countries without posts are muted.

### Task 6: Hover/Tap/Click Interaction

Implement:

- Hover highlight desktop.
- Click selection desktop.
- Tap selection mobile.
- Side/bottom panel update.

Acceptance:

- Selecting active country shows label, post count, top posts, hub link.
- Inactive countries do nothing or show muted state.

### Task 7: Destination List

Render list/card fallback below the map.

Acceptance:

- All active countries appear in list.
- List links to hub URLs.
- Tiny countries can be accessed via list.

### Task 8: CSS Styling

Implement warm, simple blog-style map design.

Acceptance:

- Map looks editorial, not technical.
- Outlines are clean and low-detail.
- Mobile layout works.

### Task 9: Data Builder MVP

Implement admin rebuild that generates language JSON from available explicit geo hierarchy plus Geo Mashup fallback.

Acceptance:

- JSON files generated for FR/EN/DE.
- Post counts are plausible.
- Active countries match content language.
- Geo Mashup fallback does not fatal if unavailable.

### Task 10: Admin Diagnostics

Show rebuild status and unmatched names.

Acceptance:

- Admin can see what was generated.
- Unmatched Geo Mashup names are visible for manual mapping improvements.

---

## 18. Post-MVP Tasks

### Version 2

- Add world/continent overview.
- Add map breadcrumb.
- Add regional drill-down for France.
- Add regional drill-down for UK/England.
- Add manual admin settings for drilldown-enabled countries.

### Version 3

- Add Italy/Spain regional drill-down if useful.
- Add filters using existing 0–2 recommendation table.
- Add map widget variant for homepage.
- Add integration with search-page place suggestions.
- Add server-rendered fallback list.

---

## 19. Testing Checklist

### 19.1 Asset Loading

Test page without shortcode:

- View source/network panel.
- Confirm no D3/plugin CSS/plugin JS.

Test page with shortcode:

- Confirm D3/plugin CSS/plugin JS load.
- Confirm JSON and GeoJSON fetch.

### 19.2 Languages

Test:

```text
French page with shortcode.
English page with shortcode.
German page with shortcode.
```

Verify:

- UI strings correct.
- Active countries reflect current language only.
- URLs go to correct language where available.

### 19.3 Interaction

Desktop:

- Hover active country.
- Click active country.
- Click inactive country.
- Use keyboard Tab + Enter.

Mobile:

- Tap country.
- Tap list item.
- Check panel layout.

### 19.4 Data Builder

Run rebuild:

- FR.
- EN.
- DE.
- All.

Verify:

- JSON files written.
- Admin status updated.
- No PHP warnings.
- Unmatched names shown.

### 19.5 Failure Cases

Test:

- Missing GeoJSON file.
- Missing JSON index.
- Geo Mashup inactive.
- No posts for current language.

Expected:

- No fatal errors.
- Friendly frontend/admin messages.

---

## 20. Done Criteria for MVP

The MVP is done when:

- `[mv_geo_explorer]` renders a Europe-focused D3/SVG map.
- The map uses simple beautiful outlines and blog-compatible colours.
- Only countries with posts in the current language are active.
- Hover/tap/click shows country name and post count.
- Side/bottom panel shows top posts and hub button.
- Destination list fallback is present.
- Assets load only on pages with shortcode.
- FR/EN/DE data files can be generated.
- Geo Mashup can be used as a fallback source for admin/country/region name matching.
- Plugin works if Geo Mashup is inactive, using explicit hierarchy only.
- No complex drill-down or recommendation filters are required for MVP.

---

## 21. Important Implementation Guidance

Keep the first version restrained.

Prioritize:

- Shortcode-only loading.
- Beautiful simple map outlines.
- Reliable language-specific country counts.
- Clean side panel.
- Geo Mashup-assisted matching diagnostics.

Avoid spending MVP time on:

- full world-to-region drill-down;
- city-level mapping;
- exact geographic precision;
- complex recommendations;
- live database queries from JavaScript;
- replacing destination hubs.

The guiding principle:

> This plugin is an elegant visual discovery layer for the travel archive, not a full GIS system.
