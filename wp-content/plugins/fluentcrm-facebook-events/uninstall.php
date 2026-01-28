<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = get_option('fcrm_fb_events_settings', []);
if (empty($settings['delete_data_on_uninstall']) || $settings['delete_data_on_uninstall'] !== 'yes') {
    return;
}

require_once __DIR__ . '/includes/class-logger.php';
require_once __DIR__ . '/includes/class-queue.php';

FCRM_FB_Events_Logger::delete_table();
FCRM_FB_Events_Queue::delete_table();

delete_option('fcrm_fb_events_settings');
