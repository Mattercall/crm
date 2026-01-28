<?php

if (!defined('ABSPATH')) {
    exit;
}

class FCRM_FB_Events_Logger
{
    public static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . FCRM_FB_EVENTS_LOG_TABLE;
    }

    public static function create_table()
    {
        global $wpdb;

        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            trigger_name VARCHAR(190) NOT NULL,
            contact_id BIGINT UNSIGNED NULL,
            email VARCHAR(190) NULL,
            event_name VARCHAR(190) NOT NULL,
            status_code INT NOT NULL DEFAULT 0,
            response TEXT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY trigger_name (trigger_name),
            KEY contact_id (contact_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function add_log(array $entry)
    {
        global $wpdb;

        $table = self::table_name();

        $wpdb->insert(
            $table,
            [
                'created_at' => current_time('mysql'),
                'trigger_name' => sanitize_text_field($entry['trigger']),
                'contact_id' => isset($entry['contact_id']) ? (int) $entry['contact_id'] : null,
                'email' => sanitize_email($entry['email'] ?? ''),
                'event_name' => sanitize_text_field($entry['event_name']),
                'status_code' => (int) $entry['status_code'],
                'response' => sanitize_text_field($entry['response'] ?? ''),
                'success' => !empty($entry['success']) ? 1 : 0,
            ],
            [
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
            ]
        );

        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        if ($count > 100) {
            $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} ORDER BY id DESC LIMIT %d", 100));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id NOT IN ({$placeholders})", $ids));
            }
        }
    }

    public static function get_logs($limit = 100, $trigger = null)
    {
        global $wpdb;

        $table = self::table_name();
        $query = "SELECT created_at, trigger_name, contact_id, email, event_name, status_code, response FROM {$table}";
        $params = [];

        if ($trigger) {
            $query .= " WHERE trigger_name = %s";
            $params[] = $trigger;
        }

        $query .= " ORDER BY id DESC LIMIT %d";
        $params[] = (int) $limit;

        $rows = $wpdb->get_results(
            $wpdb->prepare($query, $params),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $logs = [];
        foreach ($rows as $row) {
            $identifier = $row['email'];
            if (!empty($row['contact_id'])) {
                $identifier = sprintf('#%d %s', (int) $row['contact_id'], $row['email']);
            }

            $logs[] = [
                'created_at' => $row['created_at'],
                'trigger' => $row['trigger_name'],
                'contact_identifier' => trim($identifier),
                'event_name' => $row['event_name'],
                'status_code' => $row['status_code'],
                'response' => $row['response'],
            ];
        }

        return $logs;
    }

    public static function delete_table()
    {
        global $wpdb;
        $table = self::table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
