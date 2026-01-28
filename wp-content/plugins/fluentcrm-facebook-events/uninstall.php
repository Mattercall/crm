<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!defined('FCRM_FB_EVENTS_OPTION_KEY')) {
    define('FCRM_FB_EVENTS_OPTION_KEY', 'fcrm_fb_events_settings');
}

if (!defined('FCRM_FB_EVENTS_LOG_TABLE')) {
    define('FCRM_FB_EVENTS_LOG_TABLE', 'fcrm_fb_events_log');
}

if (!defined('FCRM_FB_EVENTS_QUEUE_TABLE')) {
    define('FCRM_FB_EVENTS_QUEUE_TABLE', 'fcrm_fb_events_queue');
}

if (!defined('FCRM_FB_EVENTS_LEAD_TABLE')) {
    define('FCRM_FB_EVENTS_LEAD_TABLE', 'fcrm_fb_events_leads');
}

if (!defined('FCRM_FB_EVENTS_LEAD_IMPORT_OPTION')) {
    define('FCRM_FB_EVENTS_LEAD_IMPORT_OPTION', 'fcrm_fb_events_lead_import_state');
}

$settings = get_option(FCRM_FB_EVENTS_OPTION_KEY, []);
if (empty($settings['delete_data_on_uninstall']) || $settings['delete_data_on_uninstall'] !== 'yes') {
    return;
}

require_once __DIR__ . '/includes/class-logger.php';
require_once __DIR__ . '/includes/class-lead-store.php';
require_once __DIR__ . '/includes/class-queue.php';

FCRM_FB_Events_Logger::delete_table();
FCRM_FB_Events_Lead_Store::delete_table();
FCRM_FB_Events_Queue::delete_table();

delete_option(FCRM_FB_EVENTS_OPTION_KEY);
delete_option(FCRM_FB_EVENTS_LEAD_IMPORT_OPTION);
