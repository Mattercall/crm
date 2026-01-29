<?php

if (!defined('ABSPATH')) {
    exit;
}

class FCRM_FB_Events_Lead_Store
{
    private static $table_ready = false;

    public static function table_name()
    {
        global $wpdb;

        return $wpdb->prefix . FCRM_FB_EVENTS_LEAD_TABLE;
    }

    private static function ensure_table_exists()
    {
        global $wpdb;

        if (self::$table_ready) {
            return;
        }

        $table = self::table_name();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        if ($exists !== $table) {
            self::create_table();
            self::$table_ready = true;
            return;
        }

        $column = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'webhook_payload')
        );
        if (!$column) {
            self::create_table();
        }

        self::$table_ready = true;
    }

    public static function create_table()
    {
        global $wpdb;

        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            leadgen_id VARCHAR(64) NOT NULL,
            form_id VARCHAR(64) NULL,
            page_id VARCHAR(64) NULL,
            contact_id BIGINT UNSIGNED NULL,
            source VARCHAR(32) NOT NULL DEFAULT 'webhook',
            lead_time DATETIME NULL,
            webhook_payload LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY leadgen_id (leadgen_id),
            KEY form_id (form_id),
            KEY page_id (page_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function has_lead($leadgen_id)
    {
        global $wpdb;

        if (!$leadgen_id) {
            return false;
        }

        self::ensure_table_exists();
        $table = self::table_name();
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE leadgen_id = %s LIMIT 1", $leadgen_id));

        return !empty($existing);
    }

    public static function add_lead(array $data)
    {
        global $wpdb;

        if (empty($data['leadgen_id'])) {
            return false;
        }

        self::ensure_table_exists();
        $table = self::table_name();
        $result = $wpdb->replace(
            $table,
            [
                'leadgen_id' => sanitize_text_field($data['leadgen_id']),
                'form_id' => sanitize_text_field($data['form_id'] ?? ''),
                'page_id' => sanitize_text_field($data['page_id'] ?? ''),
                'contact_id' => !empty($data['contact_id']) ? (int) $data['contact_id'] : null,
                'source' => sanitize_text_field($data['source'] ?? 'webhook'),
                'lead_time' => !empty($data['lead_time']) ? gmdate('Y-m-d H:i:s', (int) $data['lead_time']) : null,
                'webhook_payload' => $data['webhook_payload'] ?? null,
                'created_at' => current_time('mysql'),
            ],
            [
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        return (bool) $result;
    }

    public static function get_lead($leadgen_id)
    {
        global $wpdb;

        if (!$leadgen_id) {
            return null;
        }

        self::ensure_table_exists();
        $table = self::table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE leadgen_id = %s LIMIT 1", $leadgen_id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public static function get_last_import_time($form_id)
    {
        global $wpdb;

        if (!$form_id) {
            return null;
        }

        self::ensure_table_exists();
        $table = self::table_name();
        $time = $wpdb->get_var(
            $wpdb->prepare("SELECT MAX(lead_time) FROM {$table} WHERE form_id = %s", $form_id)
        );

        return $time ?: null;
    }

    public static function delete_table()
    {
        global $wpdb;

        $table = self::table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
