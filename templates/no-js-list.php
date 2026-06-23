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
 * No heading of its own when show_list is on: the always-rendered
 * `.mv-geo-explorer__list-heading` above (see geo-explorer.php) already
 * shows the same $list_heading text, so a second one here would just
 * duplicate it for a no-JS visitor. Only rendered when show_list="0", since
 * crawler/no-JS discoverability shouldn't depend on a display toggle meant
 * for the JS-rendered UI.
 *
 * @var array  $config
 * @var array  $nojs_places
 * @var string $list_heading
 */

defined('ABSPATH') || exit;

$lang = $config['lang'] ?? 'fr';

$intro_text = [
    'fr' => 'La carte interactive nécessite JavaScript. Voici toutes nos destinations :',
    'en' => 'The interactive map requires JavaScript. Here are all our destinations:',
    'de' => 'Die interaktive Karte benötigt JavaScript. Hier sind alle unsere Reiseziele:',
];

$places = $nojs_places['places'] ?? [];
?>
<noscript>
    <div class="mv-geo-explorer__noscript">
        <?php if (!empty($places)) : ?>
            <p><?php echo esc_html($intro_text[$lang] ?? $intro_text['fr']); ?></p>
            <?php if (empty($config['showList'])) : ?>
                <h2><?php echo esc_html($list_heading); ?></h2>
            <?php endif; ?>
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
