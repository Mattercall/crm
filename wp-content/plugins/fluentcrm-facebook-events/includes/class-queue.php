<?php

if (!defined('ABSPATH')) {
    exit;
}

class FCRM_FB_Events_Queue
{
    public function init()
    {
        add_filter('cron_schedules', [$this, 'register_cron_schedule']);
        add_action('fcrm_fb_events_process_queue', [$this, 'process_queue']);
        add_action('fcrm_fb_events_process_event', [$this, 'process_action_scheduler'], 10, 1);
    }

    public static function table_name()
    {
        global $wpdb;
        return $wpdb->prefix . FCRM_FB_EVENTS_QUEUE_TABLE;
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
            payload LONGTEXT NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            last_error TEXT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY trigger_name (trigger_name)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function dispatch(array $payload, $trigger, $subscriber)
    {
        $settings = FCRM_FB_Events_Admin::get_settings();
        $mode = $settings['send_mode'] ?? 'immediate';

        if ($mode === 'immediate') {
            $sender = new FCRM_FB_Events_Facebook_CAPI();
            $sender->send_event($payload);
            return;
        }

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('fcrm_fb_events_process_event', ['payload' => $payload], 'fcrm_fb_events');
            return;
        }

        $this->enqueue_fallback($payload, $trigger, $subscriber);
    }

    private function enqueue_fallback(array $payload, $trigger, $subscriber)
    {
        global $wpdb;

        $table = self::table_name();
        $wpdb->insert(
            $table,
            [
                'created_at' => current_time('mysql'),
                'trigger_name' => sanitize_text_field($trigger),
                'contact_id' => isset($subscriber->id) ? (int) $subscriber->id : null,
                'payload' => wp_json_encode($payload),
                'attempts' => 0,
                'status' => 'pending',
                'last_error' => '',
            ],
            [
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );

        $this->schedule_cron();
    }

    public function process_action_scheduler($args)
    {
        if (empty($args['payload'])) {
            return;
        }

        $sender = new FCRM_FB_Events_Facebook_CAPI();
        $sender->send_event($args['payload']);
    }

    public function process_queue()
    {
        global $wpdb;

        $table = self::table_name();
        $rows = $wpdb->get_results("SELECT * FROM {$table} WHERE status = 'pending' ORDER BY id ASC LIMIT 10", ARRAY_A);

        if (empty($rows)) {
            return;
        }

        $sender = new FCRM_FB_Events_Facebook_CAPI();

        foreach ($rows as $row) {
            $payload = json_decode($row['payload'], true);
            if (!$payload) {
                $this->mark_failed($row['id'], 'Invalid payload');
                continue;
            }

            $wpdb->update(
                $table,
                ['status' => 'processing'],
                ['id' => (int) $row['id']],
                ['%s'],
                ['%d']
            );

            $success = $sender->send_event($payload);
            if ($success) {
                $wpdb->delete($table, ['id' => (int) $row['id']], ['%d']);
                continue;
            }

            $attempts = (int) $row['attempts'] + 1;
            if ($attempts >= 3) {
                $this->mark_failed($row['id'], 'Max attempts reached');
            } else {
                $wpdb->update(
                    $table,
                    [
                        'attempts' => $attempts,
                        'status' => 'pending',
                    ],
                    ['id' => (int) $row['id']],
                    ['%d', '%s'],
                    ['%d']
                );
            }
        }
    }

    private function mark_failed($id, $message)
    {
        global $wpdb;
        $table = self::table_name();

        $wpdb->update(
            $table,
            [
                'status' => 'failed',
                'last_error' => sanitize_text_field($message),
            ],
            ['id' => (int) $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function register_cron_schedule($schedules)
    {
        if (!isset($schedules['fcrm_fb_events_minute'])) {
            $schedules['fcrm_fb_events_minute'] = [
                'interval' => 60,
                'display' => __('Every Minute', 'fluentcrm-facebook-events'),
            ];
        }

        return $schedules;
    }

    public function schedule_cron()
    {
        if (!wp_next_scheduled('fcrm_fb_events_process_queue')) {
            wp_schedule_event(time() + 60, 'fcrm_fb_events_minute', 'fcrm_fb_events_process_queue');
        }
    }

    public function clear_cron()
    {
        $timestamp = wp_next_scheduled('fcrm_fb_events_process_queue');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'fcrm_fb_events_process_queue');
        }
    }

    public static function delete_table()
    {
        global $wpdb;
        $table = self::table_name();
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
