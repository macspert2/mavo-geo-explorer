<?php
/**
 * No-JS fallback message (Section 14.2 of the implementation plan). The
 * destination list itself is rendered by JS from the geo index JSON; if
 * JavaScript is unavailable there is nothing for it to render, so this
 * points readers at a real page instead of leaving an empty map.
 *
 * @var array $config
 */

defined('ABSPATH') || exit;

$noscript_text = [
    'fr' => ['La carte interactive nécessite JavaScript. Vous pouvez aussi explorer nos destinations depuis la page ', 'Commencez ici'],
    'en' => ['The interactive map requires JavaScript. You can also explore our destinations from the ', 'Start Here'],
    'de' => ['Die interaktive Karte benötigt JavaScript. Ihr könnt unsere Reiseziele auch über die ', 'Startseite'],
];
$lang              = $config['lang'] ?? 'fr';
[$intro, $link_text] = $noscript_text[$lang] ?? $noscript_text['fr'];
$url               = mv_geo_explorer_lang_fallback_url($lang);
?>
<noscript>
    <p class="mv-geo-explorer__noscript">
        <?php echo esc_html($intro); ?><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($link_text); ?></a>.
    </p>
</noscript>
