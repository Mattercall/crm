<?php
namespace PixelYourSite;

defined('ABSPATH') or die('Direct access not allowed');

/**
 * Queue system for server events
 * Replaces async tasks with queue system + WP Cron
 */
class EventsQueue {

    private static $_instance;
    private $table_name;
    private $max_events_per_batch;
    private $processing_interval;
    private $cached_stats = null;
    private $stats_cache_time = 0;
    private $cache_duration = 30; // Cache for 30 seconds
    private $table_exists_cache = null; // Cache table existence for current request

    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'pys_events_queue';
        
        // Default settings
        $this->max_events_per_batch = PYS()->getOption('queue_max_events_per_batch', 500);
        $this->processing_interval = PYS()->getOption('queue_processing_interval', 300); // 5 minutes
        
        add_action('init', array($this, 'init'));
        add_action('pys_process_events_queue', array($this, 'processQueue'));
        add_action('pys_cleanup_events_queue', array($this, 'runScheduledCleanup'));
        add_action('wp_ajax_pys_process_queue_manual', array($this, 'processQueueManual'));
        add_action('wp_ajax_nopriv_pys_process_queue_manual', array($this, 'processQueueManual'));
        add_action('wp_ajax_pys_cleanup_queue', array($this, 'cleanupQueueAjax'));
        add_action('wp_ajax_pys_refresh_queue_stats', array($this, 'refreshQueueStatsAjax'));
        add_action('wp_ajax_pys_reset_failed_events', array($this, 'resetFailedEventsAjax'));
    }

    public function init() {
        $this->createTable();
        $this->scheduleCron();
        $this->scheduleCleanupCron();
    }

    /**
     * Create table for events queue
     */
    private function createTable() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            pixel_type varchar(50) NOT NULL,
            event_name varchar(191) NULL,
            event_id varchar(191) NULL,
            event_data longtext NOT NULL,
            created_at datetime NULL,
            processed_at datetime NULL,
            retry_count int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            error_message text NULL,
            PRIMARY KEY (id),
            KEY pixel_type (pixel_type),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $wpdb->query($sql);
    }

    /**
     * Schedule WP Cron task for queue processing
     */
    private function scheduleCron() {
        if (!wp_next_scheduled('pys_process_events_queue')) {
            wp_schedule_event(time(), 'pys_queue_interval', 'pys_process_events_queue');
        }
    }

    /**
     * Schedule WP Cron task for automatic cleanup
     */
    private function scheduleCleanupCron() {
        if (!wp_next_scheduled('pys_cleanup_events_queue')) {
            wp_schedule_event(time(), 'daily', 'pys_cleanup_events_queue');
        }
    }

    /**
     * Run scheduled cleanup task
     */
    public function runScheduledCleanup() {
        $this->cleanupOldEvents();
        $this->cleanupStuckPendingEvents();
    }

    /**
     * Cleanup pending events that are stuck for too long
     */
    public function cleanupStuckPendingEvents() {
        global $wpdb;

        $cleanup_days = PYS()->getOption('queue_pending_cleanup_days', 1);

        if ($cleanup_days <= 0) {
            return 0;
        }

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$cleanup_days} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name}
                 WHERE status = %s
                 AND created_at < %s",
                'pending', $cutoff_date
            )
        );

        if ($deleted > 0) {
            PYS()->getLog()->debug('Cleaned up ' . $deleted . ' stuck pending events from queue (older than ' . $cleanup_days . ' days)');
            $this->clearStatsCache();
        }

        return $deleted;
    }

    /**
     * Extract event information
     */
    private function extractEventInfo($pixel_type, $event_data) {
        $event_names = array();
        $event_ids = array();

        try {
            // Process array of events
            if (is_array($event_data)) {
                foreach ($event_data as $event_item) {
                    if ($pixel_type === 'facebook' && isset($event_item['event'])) {
                        $event = $event_item['event'];
                        // For Facebook SDK use getEventName and getEventId methods
                        if (is_object($event)) {
                            if (method_exists($event, 'getEventName')) {
                                $event_name = $event->getEventName();
                                if ($event_name) {
                                    $event_names[] = $event_name;
                                }
                            }
                            if (method_exists($event, 'getEventId')) {
                                $event_id = $event->getEventId();
                                if ($event_id) {
                                    $event_ids[] = $event_id;
                                }
                            }
                        }
                    } elseif ($pixel_type === 'tiktok' && isset($event_item['event'])) {
                        $event = $event_item['event'];
                        if (isset($event->event)) {
                            $event_names[] = $event->event;
                        }
                        if (isset($event->event_id)) {
                            $event_ids[] = $event->event_id;
                        }
                    } elseif ($pixel_type === 'pinterest' && isset($event_item['event'])) {
                        $event = $event_item['event'];
                        if (isset($event->event_name)) {
                            $event_names[] = $event->event_name;
                        }
                        if (isset($event->event_id)) {
                            $event_ids[] = $event->event_id;
                        }
                    }
                }
            }
            // Process single event (for backward compatibility)
            elseif ($pixel_type === 'facebook' && isset($event_data['event'])) {
                $event = $event_data['event'];

                // For Facebook SDK use getEventName and getEventId methods
                if (is_object($event)) {
                    if (method_exists($event, 'getEventName')) {
                        $event_name = $event->getEventName();
                        if ($event_name) {
                            $event_names[] = $event_name;
                        }
                    }
                    if (method_exists($event, 'getEventId')) {
                        $event_id = $event->getEventId();
                        if ($event_id) {
                            $event_ids[] = $event_id;
                        }
                    }
                }
            } elseif ($pixel_type === 'tiktok' && isset($event_data['event'])) {
                $event = $event_data['event'];
                if (isset($event->event)) {
                    $event_names[] = $event->event;
                }
                if (isset($event->event_id)) {
                    $event_ids[] = $event->event_id;
                }
            } elseif ($pixel_type === 'pinterest' && isset($event_data['event'])) {
                $event = $event_data['event'];
                if (isset($event->event_name)) {
                    $event_names[] = $event->event_name;
                }
                if (isset($event->event_id)) {
                    $event_ids[] = $event->event_id;
                }
            }
        } catch (Exception $e) {
            PYS()->getLog()->debug('Failed to extract event info: ' . $e->getMessage());
        }
        return array(
            'event_name' => implode(', ', array_filter($event_names)),
            'event_id' => implode(', ', array_filter($event_ids))
        );
    }

    /**
     * Add event to queue
     */
    public function addEvent($pixel_type, $event_data) {
        global $wpdb;

        // Check queue limits
        if ($this->isQueueOverloaded()) {
            return false;
        }

        // Extract event information
        $event_info = $this->extractEventInfo($pixel_type, $event_data);
        // Use PHP serialize to save objects in original form
        $serialized_data = base64_encode(serialize($event_data));
        PYS()->getLog()->debug('Adding event to queue: ' . $event_info['event_name'] . ' (' . $pixel_type . ')');
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'pixel_type' => sanitize_text_field($pixel_type),
                'event_name' => sanitize_text_field($event_info['event_name']),
                'event_id' => sanitize_text_field($event_info['event_id']),
                'event_data' => $serialized_data,
                'created_at' => current_time('mysql'),
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            PYS()->getLog()->error('Failed to add event to queue: ' . $wpdb->last_error);
            return false;
        }

        // Clear cache when data changes
        $this->clearStatsCache();

        return $wpdb->insert_id;
    }


    /**
     * Batch processing of events from queue
     */
    public function processQueue() {
        global $wpdb;

        // Check if processing is already running
        $lock_key = 'pys_queue_processing_lock';
        if (get_transient($lock_key)) {
            return;
        }

        // Set lock for 10 minutes
        set_transient($lock_key, true, 600);

        try {
            $events = $this->getEventsForProcessing();

            if (empty($events)) {
                return;
            }

            $processed_count = 0;
            $failed_count = 0;

            foreach ($events as $event) {
                if ($this->processEvent($event)) {
                    $processed_count++;
                } else {
                    $failed_count++;
                }
            }
            // Clear cache when data changes
            $this->clearStatsCache();

        } catch (Exception $e) {
            PYS()->getLog()->error('Queue processing error: ' . $e->getMessage());
        } finally {
            // Remove lock
            delete_transient($lock_key);
        }
    }

    /**
     * Get events for processing
     */
    private function getEventsForProcessing() {
        global $wpdb;

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'pending' 
             ORDER BY created_at ASC 
             LIMIT %d",
            $this->max_events_per_batch
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Process single event
     */
    private function processEvent($event) {
        global $wpdb;

        try {
            // Deserialize data in original form
            $event_data = unserialize(base64_decode($event->event_data));
            
            if (!$event_data) {
                throw new Exception('Invalid event data');
            }

            // Send event to appropriate server module
            $success = $this->sendEventToServer($event->pixel_type, $event_data);

            if ($success) {
                // Mark as processed
                $wpdb->update(
                    $this->table_name,
                    array(
                        'status' => 'processed',
                        'processed_at' => current_time('mysql')
                    ),
                    array('id' => $event->id),
                    array('%s', '%s'),
                    array('%d')
                );

                return true;
            } else {
                throw new Exception('Failed to send event to server');
            }

        } catch (Exception $e) {
            // Increment retry counter
            $retry_count = $event->retry_count + 1;
            $max_retries = PYS()->getOption('queue_max_retries', 3);

            if ($retry_count >= $max_retries) {
                // Mark as failed after maximum retry attempts
                $wpdb->update(
                    $this->table_name,
                    array(
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'retry_count' => $retry_count
                    ),
                    array('id' => $event->id),
                    array('%s', '%s', '%d'),
                    array('%d')
                );
                
                PYS()->getLog()->error('Event failed after max retries', array(
                    'queue_id' => $event->id,
                    'pixel_type' => $event->pixel_type,
                    'event_name' => $event->event_name,
                    'event_id' => $event->event_id,
                    'retry_count' => $retry_count,
                    'error' => $e->getMessage()
                ));
            } else {
                // Schedule retry
                $wpdb->update(
                    $this->table_name,
                    array(
                        'retry_count' => $retry_count,
                        'error_message' => $e->getMessage()
                    ),
                    array('id' => $event->id),
                    array('%d', '%s'),
                    array('%d')
                );
            }

            PYS()->getLog()->error('Failed to process event', array(
                'event_id' => $event->id,
                'pixel_type' => $event->pixel_type,
                'error' => $e->getMessage(),
                'retry_count' => $retry_count
            ));

            return false;
        }
    }


    /**
     * Send event to appropriate server module
     */
    private function sendEventToServer($pixel_type, $event_data) {
        switch ($pixel_type) {
            case 'facebook':
                if (class_exists('PixelYourSite\FacebookServer')) {
                    foreach ($event_data as $event) {
                        FacebookServer()->sendEvent($event['pixelIds'], $event['event']);
                    }
                    return true;
                }
                break;

            case 'tiktok':
                if (class_exists('PixelYourSite\TikTokServer')) {
                    foreach ($event_data as $event) {
                        TikTokServer()->sendEvent($event['pixelIds'], $event['event']);
                    }
                    return true;
                }
                break;

            case 'pinterest':
                if (class_exists('PixelYourSite\PinterestServer')) {
                    foreach ($event_data as $event) {
                        PinterestServer()->sendEvent($event['pixelIds'], $event['event']);
                    }
                    return true;
                }
                break;

            default:
                PYS()->getLog()->error('Unknown pixel type: ' . $pixel_type);
                return false;
        }

        return false;
    }

    /**
     * Check queue overload
     */
    private function isQueueOverloaded() {
        global $wpdb;

        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'pending'"
        );

        $max_queue_size = PYS()->getOption('queue_max_size', 10000);
        return $count >= $max_queue_size;
    }

    /**
     * Cleanup old processed/failed events
     */
    public function cleanupOldEvents() {
        global $wpdb;

        $retention_days = PYS()->getOption('queue_retention_days', 7);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name}
                 WHERE status IN (%s, %s)
                 AND created_at < %s",
                'processed', 'failed', $cutoff_date
            )
        );

        if ($deleted > 0) {
            PYS()->getLog()->debug('Cleaned up ' . $deleted . ' old processed/failed events from queue (older than ' . $retention_days . ' days)');
            $this->clearStatsCache();
        }

        return $deleted;
    }

    /**
     * Get recent events
     */
    public function getRecentEvents($limit = 10) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, pixel_type, event_name, event_id, status, created_at, processed_at, retry_count, error_message 
             FROM {$this->table_name} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
        
        return $results;
    }

    /**
     * Check if queue table exists (cached for current request)
     */
    private function tableExists() {
        // Return cached result if available
        if ($this->table_exists_cache !== null) {
            return $this->table_exists_cache;
        }
        
        global $wpdb;
        $this->table_exists_cache = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'") == $this->table_name;
        
        return $this->table_exists_cache;
    }

    /**
     * Clear statistics cache
     */
    public function clearStatsCache() {
        $this->cached_stats = null;
        $this->stats_cache_time = 0;
    }

    /**
     * Get all queue statistics in one optimized call
     */
    public function getAllQueueStats() {
        // Check cache first
        if ($this->cached_stats !== null && (time() - $this->stats_cache_time) < $this->cache_duration) {
            return $this->cached_stats;
        }

        global $wpdb;

        // Check if table exists
        if (!$this->tableExists()) {
            $this->cached_stats = array(
                'pending' => 0,
                'processed' => 0,
                'failed' => 0,
                'total' => 0,
                'last_processed' => __('Never', 'pixelyoursite'),
                'queue_status' => 'idle',
                'queue_status_text' => __('Idle', 'pixelyoursite'),
                'processing_rate' => '0',
                'success_rate' => '100'
            );
            $this->stats_cache_time = time();
            return $this->cached_stats;
        }

        // Get WordPress time one hour ago
        $one_hour_ago = date( 'Y-m-d H:i:s',strtotime('-1 hour'));

        // Get all statistics in one query
        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    MAX(CASE WHEN status = 'processed' AND processed_at IS NOT NULL THEN processed_at END) as last_processed_at,
                    SUM(CASE WHEN status = 'processed' AND processed_at >= %s THEN 1 ELSE 0 END) as processed_last_hour
                 FROM {$this->table_name}",
                $one_hour_ago
            ),
            ARRAY_A
        );

        // Calculate additional metrics
        $pending = (int) ($stats['pending'] ?? 0);
        $processed = (int) ($stats['processed'] ?? 0);
        $failed = (int) ($stats['failed'] ?? 0);
        $total = (int) ($stats['total'] ?? 0);
        $processed_last_hour = (int) ($stats['processed_last_hour'] ?? 0);

        // Determine queue status
        if ($pending > 100) {
            $queue_status = 'overloaded';
            $queue_status_text = __('Overloaded', 'pixelyoursite');
        } elseif ($pending > 0) {
            $queue_status = 'processing';
            $queue_status_text = __('Processing', 'pixelyoursite');
        } else {
            $queue_status = 'idle';
            $queue_status_text = __('Idle', 'pixelyoursite');
        }

        // Calculate processing rate
        $processing_rate = ceil($processed_last_hour / 60 * 10) / 10;

        // Calculate success rate
        $total_processed = $processed + $failed;
        $success_rate = $total_processed > 0 ? round(($processed / $total_processed) * 100, 1) : 100;

        // Format last processed time
        $last_processed = __('Never', 'pixelyoursite');
        if ($stats['last_processed_at']) {
            $last_processed = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($stats['last_processed_at']));
        }

        // Cache the results
        $this->cached_stats = array(
            'pending' => $pending,
            'processed' => $processed,
            'failed' => $failed,
            'total' => $total,
            'last_processed' => $last_processed,
            'queue_status' => $queue_status,
            'queue_status_text' => $queue_status_text,
            'processing_rate' => $processing_rate,
            'success_rate' => $success_rate
        );
        $this->stats_cache_time = time();

        return $this->cached_stats;
    }

    /**
     * Manual queue processing (for AJAX)
     */
    public function processQueueManual() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $this->processQueue();
        
        wp_send_json_success(array(
            'message' => 'Queue processing completed',
            'stats' => $this->getAllQueueStats()
        ));
    }

    /**
     * AJAX handler for queue cleanup
     */
    public function cleanupQueueAjax() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $deleted = $this->cleanupOldEvents();
        
        wp_send_json_success(array(
            'message' => 'Cleanup completed',
            'deleted_count' => $deleted
        ));
    }

    /**
     * AJAX handler for queue statistics refresh
     */
    public function refreshQueueStatsAjax() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $stats = $this->getAllQueueStats();

        wp_send_json_success($stats);
    }

    /**
     * AJAX handler for resetting failed events
     */
    public function resetFailedEventsAjax() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        global $wpdb;
        
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET status = %s, retry_count = 0, error_message = NULL WHERE status = %s",
            'pending', 'failed'
        ));

        // Clear cache when data changes
        $this->clearStatsCache();

        wp_send_json_success(array(
            'message' => sprintf(__('Reset %d failed events to pending', 'pixelyoursite'), $updated),
            'reset_count' => $updated
        ));
    }

    /**
     * Get last processed time
     */
    public function getLastProcessedTime() {
        global $wpdb;
        
        if (!$this->tableExists()) {
            return __('Never', 'pixelyoursite');
        }
        
        $last_processed = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(processed_at) FROM {$this->table_name} WHERE status = %s AND processed_at IS NOT NULL",
            'processed'
        ));
        
        if ($last_processed) {
            // Use server time for formatting
            $timestamp = strtotime($last_processed);
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
        }
        
        return __('Never', 'pixelyoursite');
    }

    /**
     * Get queue status
     */
    public function getQueueStatus() {
        $stats = $this->getAllQueueStats();
        
        if ($stats['pending'] > 100) {
            return 'overloaded';
        } elseif ($stats['pending'] > 0) {
            return 'processing';
        } else {
            return 'idle';
        }
    }

    /**
     * Get queue status text
     */
    public function getQueueStatusText() {
        $status = $this->getQueueStatus();
        
        switch ($status) {
            case 'overloaded':
                return __('Overloaded', 'pixelyoursite');
            case 'processing':
                return __('Processing', 'pixelyoursite');
            case 'idle':
            default:
                return __('Idle', 'pixelyoursite');
        }
    }

    /**
     * Get processing rate (events per minute)
     */
    public function getProcessingRate() {
        global $wpdb;
        
        if (!$this->tableExists()) {
            return '0';
        }

        // Get WordPress time one hour ago
        $one_hour_ago = current_time('mysql', false, strtotime('-1 hour'));

        // Get events processed in the last hour
        $processed_last_hour = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s AND processed_at >= %s",
            'processed', $one_hour_ago
        ));

        return round($processed_last_hour / 60, 1);
    }

    /**
     * Get success rate percentage
     */
    public function getSuccessRate() {
        global $wpdb;
        
        if (!$this->tableExists()) {
            return '100';
        }
        
        $total_processed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status IN (%s, %s)",
            'processed', 'failed'
        ));
        
        if ($total_processed == 0) {
            return '100';
        }
        
        $successful = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
            'processed'
        ));
        
        return round(($successful / $total_processed) * 100, 1);
    }

    /**
     * Deactivate queue system
     */
    public function deactivate() {
        // Remove scheduled tasks
        wp_clear_scheduled_hook('pys_process_events_queue');
        wp_clear_scheduled_hook('pys_cleanup_events_queue');

        // Drop table (optional)
        if (PYS()->getOption('queue_drop_table_on_deactivate', false)) {
            global $wpdb;
            $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");
        }
    }
}

/**
 * Add interval for WP Cron
 */
add_filter('cron_schedules', function($schedules) {
    $schedules['pys_queue_interval'] = array(
        'interval' => PYS()->getOption('queue_processing_interval', 300),
        'display' => __('PixelYourSite Queue Interval', 'pixelyoursite')
    );
    return $schedules;
});

/**
 * @return EventsQueue
 */
function EventsQueue() {
    return EventsQueue::instance();
}
