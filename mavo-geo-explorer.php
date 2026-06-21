<?php
/**
 * Plugin Name: Maman Voyage Geo Explorer
 * Description: Shortcode-powered D3/SVG map for discovering posts by destination. Assets load only when the [mv_geo_explorer] shortcode is used.
 * Version: 1.0.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: mv-geo-explorer
 */

defined('ABSPATH') || exit;

define('MV_GEO_EXPLORER_DIR', plugin_dir_path(__FILE__));
define('MV_GEO_EXPLORER_URL', plugin_dir_url(__FILE__));
define('MV_GEO_EXPLORER_VERSION', '1.0.1');

require_once MV_GEO_EXPLORER_DIR . 'includes/config.php';
require_once MV_GEO_EXPLORER_DIR . 'includes/helpers.php';
require_once MV_GEO_EXPLORER_DIR . 'includes/class-mv-geo-assets.php';
require_once MV_GEO_EXPLORER_DIR . 'includes/class-mv-geo-shortcode.php';
require_once MV_GEO_EXPLORER_DIR . 'includes/class-mv-geo-geomashup.php';
require_once MV_GEO_EXPLORER_DIR . 'includes/class-mv-geo-data-builder.php';
require_once MV_GEO_EXPLORER_DIR . 'includes/class-mv-geo-admin.php';
require_once MV_GEO_EXPLORER_DIR . 'includes/class-mv-geo-explorer.php';

register_activation_hook(__FILE__, ['MV_Geo_Explorer', 'activate']);

add_action('plugins_loaded', ['MV_Geo_Explorer', 'init']);
