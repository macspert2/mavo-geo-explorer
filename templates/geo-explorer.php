<?php
/**
 * Shortcode root markup. The map/panel/list are populated by JS from the
 * geo index JSON — this only renders the empty shell plus a no-JS fallback.
 *
 * @var array  $config
 * @var string $instance_id
 * @var string $theme
 * @var array  $strings
 */

defined('ABSPATH') || exit;
?>
<div
    id="<?php echo esc_attr($instance_id); ?>"
    class="mv-geo-explorer mv-geo-explorer--theme-<?php echo esc_attr($theme); ?>"
    data-mv-geo-explorer
    data-config="<?php echo esc_attr(wp_json_encode($config)); ?>"
>
    <div class="mv-geo-explorer__layout">
        <div class="mv-geo-explorer__map-wrap">
            <div class="mv-geo-explorer__loading"><?php echo esc_html($strings['loading']); ?></div>
            <div class="mv-geo-explorer__breadcrumb" hidden></div>
            <svg class="mv-geo-explorer__svg" aria-label="<?php echo esc_attr($strings['map_aria_label']); ?>" role="img"></svg>
        </div>
        <aside class="mv-geo-explorer__panel" aria-live="polite">
            <h2 class="mv-geo-explorer__panel-title"><?php echo esc_html($strings['default_title']); ?></h2>
            <p class="mv-geo-explorer__panel-text"><?php echo esc_html($strings['default_text']); ?></p>
        </aside>
    </div>
    <?php if (!empty($config['showList'])) : ?>
        <div class="mv-geo-explorer__list">
            <h2 class="mv-geo-explorer__list-heading"><?php echo esc_html($strings['list_heading']); ?></h2>
            <ul class="mv-geo-explorer__list-items"></ul>
        </div>
    <?php endif; ?>
    <?php include MV_GEO_EXPLORER_DIR . 'templates/no-js-list.php'; ?>
</div>
