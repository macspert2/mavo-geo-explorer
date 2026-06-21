<?php

defined('ABSPATH') || exit;

/**
 * Tools → Maman Voyage Geo Explorer: manual rebuild buttons + diagnostics
 * (Section 12.1 / 6.5 of the implementation plan).
 */
class MV_Geo_Admin {

    private const NONCE_ACTION = 'mv_geo_explorer_rebuild';
    private const PAGE_SLUG    = 'mv-geo-explorer';

    public function init(): void {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu(): void {
        $hook = add_management_page(
            'Maman Voyage Geo Explorer',
            'Geo Explorer',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
        add_action('admin_print_styles-' . $hook, [$this, 'print_styles']);
    }

    public function print_styles(): void {
        wp_register_style('mv-geo-explorer-admin', false, [], MV_GEO_EXPLORER_VERSION);
        wp_enqueue_style('mv-geo-explorer-admin');
        wp_add_inline_style('mv-geo-explorer-admin', $this->inline_css());
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'mv-geo-explorer'));
        }

        $feedback = $this->maybe_handle_rebuild();
        $options  = mv_geo_explorer_options();

        echo '<div class="wrap mv-geo-explorer-admin">';
        echo '<h1>Maman Voyage Geo Explorer</h1>';

        if (null !== $feedback) {
            $this->render_feedback_notice($feedback);
        }

        $this->render_dependency_status();
        $this->render_rebuild_form();

        foreach (MV_GEO_EXPLORER_ALLOWED_LANGS as $lang) {
            $this->render_language_status($lang, $options['last_generated'][$lang] ?? null);
        }

        echo '</div>';
    }

    // -------------------------------------------------------------------

    private function maybe_handle_rebuild(): ?array {
        if (empty($_POST['mv_geo_explorer_rebuild'])) {
            return null;
        }
        check_admin_referer(self::NONCE_ACTION);
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'mv-geo-explorer'));
        }

        $target = sanitize_key(wp_unslash($_POST['mv_geo_explorer_rebuild']));

        if ('all' === $target) {
            return MV_Geo_Data_Builder::build_all();
        }
        if (in_array($target, MV_GEO_EXPLORER_ALLOWED_LANGS, true)) {
            return [$target => MV_Geo_Data_Builder::build($target)];
        }
        return null;
    }

    private function render_feedback_notice(array $results): void {
        echo '<div class="notice notice-success"><p>';
        $parts = [];
        foreach ($results as $lang => $diagnostics) {
            if (!empty($diagnostics['error'])) {
                $parts[] = sprintf('%s: %s', esc_html(strtoupper($lang)), esc_html($diagnostics['error']));
                continue;
            }
            $parts[] = sprintf(
                '%s: %d %s, %d %s',
                esc_html(strtoupper($lang)),
                (int) $diagnostics['processed'],
                esc_html__('posts processed', 'mv-geo-explorer'),
                (int) $diagnostics['places_count'],
                esc_html__('places generated', 'mv-geo-explorer')
            );
        }
        echo esc_html__('Rebuild complete.', 'mv-geo-explorer') . ' ' . implode(' — ', $parts);
        echo '</p></div>';
    }

    private function render_dependency_status(): void {
        $rows = [
            'Polylang (languages)'                  => function_exists('pll_languages_list'),
            'Geo Mashup (location fallback)'        => MV_Geo_GeoMashup::is_available(),
            'mavo-geotag-plus (explicit hierarchy)' => mv_geo_explorer_table_exists('geo_tagger_places'),
        ];

        echo '<h2>' . esc_html__('Data sources', 'mv-geo-explorer') . '</h2><table class="widefat striped" style="max-width:560px"><tbody>';
        foreach ($rows as $label => $active) {
            printf(
                '<tr><td>%s</td><td>%s</td></tr>',
                esc_html($label),
                $active
                    ? '<span style="color:#1a7e3c">●</span> ' . esc_html__('Active', 'mv-geo-explorer')
                    : '<span style="color:#b32d2e">●</span> ' . esc_html__('Inactive — fallback source unavailable', 'mv-geo-explorer')
            );
        }
        echo '</tbody></table>';

        if (!mv_geo_explorer_table_exists('geo_tagger_places') && !MV_Geo_GeoMashup::is_available()) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__(
                'Neither the explicit hierarchy nor Geo Mashup is available. Only posts matched via the manual name map will be indexed.',
                'mv-geo-explorer'
            ) . '</p></div>';
        }
    }

    private function render_rebuild_form(): void {
        echo '<h2>' . esc_html__('Rebuild index', 'mv-geo-explorer') . '</h2>';
        echo '<form method="post" class="mv-geo-explorer-rebuild-form">';
        wp_nonce_field(self::NONCE_ACTION);

        $buttons = [
            'fr'  => __('Rebuild French index', 'mv-geo-explorer'),
            'en'  => __('Rebuild English index', 'mv-geo-explorer'),
            'de'  => __('Rebuild German index', 'mv-geo-explorer'),
            'all' => __('Rebuild all indexes', 'mv-geo-explorer'),
        ];
        foreach ($buttons as $value => $label) {
            printf(
                '<button type="submit" name="mv_geo_explorer_rebuild" value="%s" class="button %s">%s</button> ',
                esc_attr($value),
                'all' === $value ? 'button-primary' : '',
                esc_html($label)
            );
        }
        echo '</form>';
    }

    private function render_language_status(string $lang, ?array $diagnostics): void {
        echo '<h2>' . esc_html(strtoupper($lang)) . '</h2>';

        if (!$diagnostics) {
            echo '<p>' . esc_html__('Not generated yet.', 'mv-geo-explorer') . '</p>';
            return;
        }

        if (!empty($diagnostics['error'])) {
            echo '<p>' . esc_html($diagnostics['error']) . '</p>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:680px"><tbody>';
        $this->status_row(__('Last generated', 'mv-geo-explorer'), esc_html($diagnostics['generated_at']));
        $this->status_row(__('Posts processed', 'mv-geo-explorer'), (int) $diagnostics['processed']);
        $this->status_row(__('Matched via explicit hierarchy', 'mv-geo-explorer'), (int) $diagnostics['matched_hierarchy']);
        $this->status_row(__('Matched via Geo Mashup', 'mv-geo-explorer'), (int) $diagnostics['matched_geomashup']);
        $this->status_row(__('Matched via manual map', 'mv-geo-explorer'), (int) $diagnostics['matched_manual']);
        $this->status_row(__('Unmatched', 'mv-geo-explorer'), (int) $diagnostics['unmatched']);
        $this->status_row(__('Places generated', 'mv-geo-explorer'), (int) $diagnostics['places_count']);
        $this->status_row(__('Generated file', 'mv-geo-explorer'), '<code>' . esc_html($diagnostics['file_path']) . '</code>');
        echo '</tbody></table>';

        if (!empty($diagnostics['unmatched_names'])) {
            echo '<p><strong>' . esc_html__('Top unmatched names', 'mv-geo-explorer') . '</strong></p><ul>';
            foreach ($diagnostics['unmatched_names'] as $entry) {
                printf(
                    '<li>%s — %d %s</li>',
                    esc_html($entry['name']),
                    (int) $entry['count'],
                    esc_html(_n('post', 'posts', $entry['count'], 'mv-geo-explorer'))
                );
            }
            echo '</ul>';
        }
    }

    private function status_row(string $label, $value): void {
        printf('<tr><th style="text-align:left;width:240px">%s</th><td>%s</td></tr>', esc_html($label), $value);
    }

    private function inline_css(): string {
        return '.mv-geo-explorer-admin table{margin-bottom:1.5em}'
             . '.mv-geo-explorer-rebuild-form{margin-bottom:1.5em}';
    }
}
