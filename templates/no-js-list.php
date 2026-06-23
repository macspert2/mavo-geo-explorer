<?php
/**
 * No-JS fallback (Section 14.2 of the implementation plan). The interactive
 * map/list are rendered by JS from the geo index JSON; with JavaScript
 * unavailable there's nothing for it to render, so this renders the same
 * destinations as real, crawlable links server-side instead — scoped to
 * whichever view this shortcode instance starts in, mirroring renderList()
 * in mv-geo-explorer.js: every European country by default, every country
 * worldwide (Europe included — there's no map here to "merge" them into
 * one shape) for default_view="world", or one country's regions for
 * default_view="regions".
 *
 * @var array      $config
 * @var array|null $index
 */

defined('ABSPATH') || exit;

$lang    = $config['lang'] ?? 'fr';
$strings = $config['strings'] ?? mv_geo_explorer_ui_strings()[$lang] ?? mv_geo_explorer_ui_strings()['fr'];

$intro_text = [
    'fr' => 'La carte interactive nécessite JavaScript. Voici toutes nos destinations :',
    'en' => 'The interactive map requires JavaScript. Here are all our destinations:',
    'de' => 'Die interaktive Karte benötigt JavaScript. Hier sind alle unsere Reiseziele:',
];

$places  = [];
$heading = null;

if (is_array($index)) {
    $resolved = mv_geo_explorer_nojs_places($index, $config['defaultView'] ?? 'europe', $config['defaultRegionShapeId'] ?? null);
    $places   = $resolved['places'];
    $heading  = $resolved['heading_label'];
}

if (null === $heading) {
    $heading = ('world' === ($config['defaultView'] ?? 'europe')) ? $strings['list_heading_world'] : $strings['list_heading'];
}
?>
<noscript>
    <div class="mv-geo-explorer__noscript">
        <?php if (!empty($places)) : ?>
            <p><?php echo esc_html($intro_text[$lang] ?? $intro_text['fr']); ?></p>
            <h2><?php echo esc_html($heading); ?></h2>
            <ul>
                <?php foreach ($places as $place) : ?>
                    <li><a href="<?php echo esc_url($place['url'] ?? '#'); ?>"><?php echo esc_html($place['label'] ?? ''); ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <?php
            // No index data at all (decode failure / missing fixture) or
            // nothing matched — fall back to the original single-link
            // message rather than rendering an empty block.
            $fallback_text = [
                'fr' => ['La carte interactive nécessite JavaScript. Vous pouvez aussi explorer nos destinations depuis la page ', 'Commencez ici'],
                'en' => ['The interactive map requires JavaScript. You can also explore our destinations from the ', 'Start Here'],
                'de' => ['Die interaktive Karte benötigt JavaScript. Ihr könnt unsere Reiseziele auch über die ', 'Startseite'],
            ];
            [$fallback_intro, $fallback_link_text] = $fallback_text[$lang] ?? $fallback_text['fr'];
            $fallback_url = mv_geo_explorer_lang_fallback_url($lang);
            ?>
            <p>
                <?php echo esc_html($fallback_intro); ?><a href="<?php echo esc_url($fallback_url); ?>"><?php echo esc_html($fallback_link_text); ?></a>.
            </p>
        <?php endif; ?>
    </div>
</noscript>
