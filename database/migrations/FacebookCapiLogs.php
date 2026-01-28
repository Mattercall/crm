<?php

namespace FluentCrmMigrations;

class FacebookCapiLogs
{
    public static function migrate()
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'fc_facebook_capi_logs';
        $indexPrefix = $wpdb->prefix . 'fc_fbcapi';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `contact_id` BIGINT UNSIGNED NULL,
                `contact_email` VARCHAR(255) NULL,
                `tag_id` BIGINT UNSIGNED NULL,
                `rule_id` VARCHAR(80) NULL,
                `event_name` VARCHAR(120) NOT NULL,
                `status` VARCHAR(20) NOT NULL,
                `response_code` INT NULL,
                `response_body` TEXT NULL,
                `retry_count` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL,
                INDEX `{$indexPrefix}_contact` (`contact_id`),
                INDEX `{$indexPrefix}_tag` (`tag_id`),
                INDEX `{$indexPrefix}_status` (`status`)
            ) $charsetCollate;";

            if (!function_exists('dbDelta')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            }

            dbDelta($sql);
        }
    }
}
