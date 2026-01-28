<?php
/**
 * Plugin Name: FluentCRM Facebook Events
 * Description: Send FluentCRM contact events to Facebook Conversions API.
 * Version: 1.0.0
 * Author: FluentCRM
 * Text Domain: fluentcrm-facebook-events
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FCRM_FB_EVENTS_VERSION', '1.0.0');
define('FCRM_FB_EVENTS_PATH', plugin_dir_path(__FILE__));
define('FCRM_FB_EVENTS_URL', plugin_dir_url(__FILE__));

define('FCRM_FB_EVENTS_OPTION_KEY', 'fcrm_fb_events_settings');

define('FCRM_FB_EVENTS_LOG_TABLE', 'fcrm_fb_events_log');
define('FCRM_FB_EVENTS_QUEUE_TABLE', 'fcrm_fb_events_queue');
define('FCRM_FB_EVENTS_LEAD_TABLE', 'fcrm_fb_events_leads');
define('FCRM_FB_EVENTS_LEAD_IMPORT_OPTION', 'fcrm_fb_events_lead_import_state');

define('FCRM_FB_EVENTS_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once FCRM_FB_EVENTS_PATH . 'includes/class-admin.php';
require_once FCRM_FB_EVENTS_PATH . 'includes/class-hooks.php';
require_once FCRM_FB_EVENTS_PATH . 'includes/class-facebook-capi.php';
require_once FCRM_FB_EVENTS_PATH . 'includes/class-facebook-lead-ads.php';
require_once FCRM_FB_EVENTS_PATH . 'includes/class-logger.php';
require_once FCRM_FB_EVENTS_PATH . 'includes/class-lead-store.php';
require_once FCRM_FB_EVENTS_PATH . 'includes/class-queue.php';

/**
 * Bootstrap plugin.
 */
function fcrm_fb_events_bootstrap()
{
    load_plugin_textdomain('fluentcrm-facebook-events', false, dirname(FCRM_FB_EVENTS_PLUGIN_BASENAME) . '/languages');

    $admin = new FCRM_FB_Events_Admin();
    $admin->init();

    $hooks = new FCRM_FB_Events_Hooks();
    $hooks->init();

    $queue = new FCRM_FB_Events_Queue();
    $queue->init();

    $lead_ads = fcrm_fb_events_lead_ads();
    $lead_ads->init();
}
add_action('plugins_loaded', 'fcrm_fb_events_bootstrap');

function fcrm_fb_events_lead_ads()
{
    static $instance = null;
    if (!$instance) {
        $instance = new FCRM_FB_Events_Lead_Ads();
    }

    return $instance;
}

/**
 * Activation hook.
 */
function fcrm_fb_events_activate()
{
    FCRM_FB_Events_Logger::create_table();
    FCRM_FB_Events_Queue::create_table();
    FCRM_FB_Events_Lead_Store::create_table();

    $queue = new FCRM_FB_Events_Queue();
    $queue->schedule_cron();
}
register_activation_hook(__FILE__, 'fcrm_fb_events_activate');

/**
 * Deactivation hook.
 */
function fcrm_fb_events_deactivate()
{
    $queue = new FCRM_FB_Events_Queue();
    $queue->clear_cron();
}
register_deactivation_hook(__FILE__, 'fcrm_fb_events_deactivate');
