<?php
/**
 * Plugin Name: Auto Ngabari
 * Plugin URI: https://samtigis.com/auto-ngabari
 * Description: Plugin untuk membuat custom post type Berita, Metabox, JSON-LD Schema, dan Shortcode Feed Berita.
 * Version: 2.3.0
 * Author: Samtigis
 * Author URI: https://samtigis.com/
 * Text Domain: auto-ngabari
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('AUTO_NGABARI_VERSION', '2.3.0');
define('AUTO_NGABARI_DIR', plugin_dir_path(__FILE__));
define('AUTO_NGABARI_URL', plugin_dir_url(__FILE__));

// Include Files
require_once AUTO_NGABARI_DIR . 'includes/license.php';
require_once AUTO_NGABARI_DIR . 'includes/cpt-news.php';
require_once AUTO_NGABARI_DIR . 'includes/metaboxes.php';
require_once AUTO_NGABARI_DIR . 'includes/schema.php';
require_once AUTO_NGABARI_DIR . 'includes/shortcodes.php';
require_once AUTO_NGABARI_DIR . 'includes/ai-provider.php'; // Multi-provider AI dispatcher (v2.2.0)
require_once AUTO_NGABARI_DIR . 'includes/cron.php';
require_once AUTO_NGABARI_DIR . 'includes/generator.php';
require_once AUTO_NGABARI_DIR . 'includes/author-generator.php';
require_once AUTO_NGABARI_DIR . 'includes/api-endpoints.php';
require_once AUTO_NGABARI_DIR . 'includes/regenerate-image.php';
require_once AUTO_NGABARI_DIR . 'includes/related-news.php';

require_once AUTO_NGABARI_DIR . 'includes/extensions-manager.php';

// Automation & Admin
if (is_admin()) {
    require_once AUTO_NGABARI_DIR . 'includes/admin-page.php';
    require_once AUTO_NGABARI_DIR . 'includes/sosmed-core.php';
}

require_once AUTO_NGABARI_DIR . 'includes/updater.php';

/**
 * Fix: WordPress REST API default JSON encoding uses JSON_HEX_TAG which converts
 * < to \u003C and > to \u003E in all REST responses. Strip those flags so
 * HTML content in feeder JSON is returned as actual HTML, not escaped unicode.
 */
add_filter('rest_json_encode_options', function ($options) {
    return $options & ~(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
});

// Redirect after activation
add_action('admin_init', 'auto_ngabari_activation_redirect');
function auto_ngabari_activation_redirect()
{
    if (get_option('auto_ngabari_do_activation_redirect', false)) {
        delete_option('auto_ngabari_do_activation_redirect');
        if (!isset($_GET['activate-multi'])) {
            wp_redirect(admin_url('admin.php?page=auto-ngabari'));
            exit;
        }
    }
}

// Activation Hook
function auto_ngabari_activate()
{
    add_option('auto_ngabari_do_activation_redirect', true);
    // This function will be defined in cpt-news.php
    if (function_exists('auto_ngabari_create_post_type')) {
        auto_ngabari_create_post_type();
    }
    flush_rewrite_rules();

    // Trigger initial cron setup
    if (function_exists('auto_ngabari_reschedule_events')) {
        auto_ngabari_reschedule_events();
    }
}
register_activation_hook(__FILE__, 'auto_ngabari_activate');

// Deactivation Hook
function auto_ngabari_deactivate()
{
    flush_rewrite_rules();

    // Clear scheduled events
    wp_clear_scheduled_hook('auto_ngabari_fetch_trends_cron');
    wp_clear_scheduled_hook('auto_ngabari_titlebase_cron');
    wp_clear_scheduled_hook('auto_ngabari_generate_articles_cron');
    wp_clear_scheduled_hook('auto_ngabari_feeder_cron');
}
register_deactivation_hook(__FILE__, 'auto_ngabari_deactivate');

// Cookie Consent Hook (v2.2.4)
function auto_ngabari_cookie_consent_footer()
{
    if (get_option('auto_ngabari_enable_cookie_consent') == 1) {
        require_once AUTO_NGABARI_DIR . 'includes/cookie-consent.php';
    }
}
add_action('wp_footer', 'auto_ngabari_cookie_consent_footer', 99);
