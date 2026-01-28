<?php

namespace PixelYourSite;

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

?>

<div class="cards-wrapper cards-wrapper-style2 gap-24 setting-wrapper">
    <!-- Queue System Status -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Queue System Status', 'pixelyoursite'); ?></h4>
        </div>
        <div class="card-body">
            <div class="gap-24">
                <div>
                    <div class="d-flex align-items-center">
                        <?php PYS()->render_switcher_input('queue_enabled'); ?>
                        <h4 class="switcher-label secondary_heading"><?php _e('Enable Queue System', 'pixelyoursite'); ?></h4>
                    </div>
                    <p class="text-gray mt-4">
                        <?php _e('Enable the queue system to replace async tasks. This will improve performance by processing events in batches instead of individually.', 'pixelyoursite'); ?>
                    </p>
                </div>

                <div>
                    <div class="d-flex align-items-center">
                        <?php PYS()->render_switcher_input('queue_use_for_ajax'); ?>
                        <h4 class="switcher-label secondary_heading"><?php _e('Use Queue for AJAX Events', 'pixelyoursite'); ?></h4>
                    </div>
                    <p class="text-gray mt-4">
                        <?php _e('If enabled, AJAX events (like Add to Cart) will also be processed through the queue system instead of being sent immediately. This can improve page responsiveness but may slightly delay event delivery.', 'pixelyoursite'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Processing Configuration -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Processing Configuration', 'pixelyoursite'); ?></h4>
        </div>
        <div class="card-body">
            <div class="gap-24">
                <div class="d-flex align-items-center number-option-block">
                    <label class="primary_heading"><?php _e('Max Events Per Batch:', 'pixelyoursite'); ?></label>
                    <?php PYS()->render_number_input('queue_max_events_per_batch', '', false, 1000, 1); ?>
                </div>
                <p class="text-gray">
                    <?php _e('Maximum number of events to process in one batch (1-1000). Higher values may improve performance but use more memory.', 'pixelyoursite'); ?>
                </p>

                <div>
                    <h4 class="primary_heading mb-4"><?php _e('Processing Interval:', 'pixelyoursite'); ?></h4>
                    <?php PYS()->render_select_input( 'queue_processing_interval', array(
                        60 => __('1 minute', 'pixelyoursite'),
                        300 => __('5 minutes', 'pixelyoursite'),
                        600 => __('10 minutes', 'pixelyoursite'),
                        900 => __('15 minutes', 'pixelyoursite'),
                        1800 => __('30 minutes', 'pixelyoursite'),
                        3600 => __('1 hour', 'pixelyoursite')
                    ) ); ?>

                    <p class="text-gray mt-4">
                        <?php _e('How often to process the events queue. More frequent processing provides faster event delivery but uses more server resources.', 'pixelyoursite'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Queue Limits -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Queue Limits', 'pixelyoursite'); ?></h4>
        </div>
        <div class="card-body">
            <div class="gap-24">
                <div class="d-flex align-items-center number-option-block">
                    <label class="primary_heading"><?php _e('Max Queue Size:', 'pixelyoursite'); ?></label>
                    <?php PYS()->render_number_input('queue_max_size', '', false, 10000, 100); ?>
                </div>
                <p class="text-gray">
                    <?php _e('Maximum number of events in queue before dropping new events (100-100000). Prevents memory issues during high traffic periods.', 'pixelyoursite'); ?>
                </p>

                <div class="d-flex align-items-center number-option-block">
                    <label class="primary_heading"><?php _e('Max Retries:', 'pixelyoursite'); ?></label>
                    <?php PYS()->render_number_input('queue_max_retries', '', false, 3, 0); ?>
                </div>
                <p class="text-gray">
                    <?php _e('Maximum number of retry attempts for failed events (0-10). Failed events will be retried this many times before being marked as permanently failed.', 'pixelyoursite'); ?>
                </p>

            </div>
        </div>
    </div>

    <!-- Auto Cleanup Settings -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Auto Cleanup Settings', 'pixelyoursite'); ?></h4>
        </div>
        <div class="card-body">
            <div class="gap-24">
                <div class="d-flex align-items-center number-option-block">
                    <label class="primary_heading"><?php _e('Pending Events Cleanup Days:', 'pixelyoursite'); ?></label>
                    <?php PYS()->render_number_input('queue_pending_cleanup_days', '', false, 1, 0); ?>
                </div>
                <p class="text-gray">
                    <?php _e('Number of days after which pending (stuck) events will be automatically deleted (0-30). Set to 0 to disable automatic cleanup of pending events. Default: 1 day.', 'pixelyoursite'); ?>
                </p>
                <p class="text-gray mt-2">
                    <strong><?php _e('Note:', 'pixelyoursite'); ?></strong>
                    <?php _e('Automatic cleanup runs daily. Pending events older than the specified number of days will be removed to prevent queue overflow from stuck events.', 'pixelyoursite'); ?>
                </p>

                <div class="d-flex align-items-center number-option-block">
                    <label class="primary_heading"><?php _e('Retention Days:', 'pixelyoursite'); ?></label>
                    <?php PYS()->render_number_input('queue_retention_days', '', false, 1, 1); ?>
                </div>
                <p class="text-gray">
                    <?php _e('Number of days to keep processed/failed events in the database (1-30). Older events will be automatically cleaned up to save space.', 'pixelyoursite'); ?>
                </p>

                <hr class="my-4">

                <div>
                    <div class="d-flex align-items-center">
                        <?php PYS()->render_switcher_input('queue_drop_table_on_deactivate'); ?>
                        <h4 class="switcher-label secondary_heading"><?php _e('Drop Queue Table on Plugin Deactivation', 'pixelyoursite'); ?></h4>
                    </div>
                    <p class="text-gray mt-4">
                        <?php _e('If enabled, the queue database table will be completely removed when the plugin is deactivated. This will permanently delete all queued events. Use with caution.', 'pixelyoursite'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Queue Statistics -->
    <div class="card card-style6 card-static">
        <div class="card-header card-header-style2 d-flex justify-content-between align-items-center">
            <h4 class="secondary_heading_type2"><?php _e('Queue Statistics', 'pixelyoursite'); ?></h4>
            <div class="queue-actions">
                <button type="button" id="refresh-stats" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-refresh"></i> <?php _e('Refresh', 'pixelyoursite'); ?>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="gap-24">
                <?php 
                // Get all queue statistics in one optimized call
                $all_stats = EventsQueue()->getAllQueueStats();
                ?>
                <div class="queue-stats-grid" id="queue-stats-container">
                    <div class="stat-card stat-pending">
                        <h3 class="stat-number" id="pending-count"><?php echo $all_stats['pending'] ?? 0; ?></h3>
                        <p class="stat-label"><?php _e('Pending Events', 'pixelyoursite'); ?></p>
                    </div>
                    <div class="stat-card stat-processed">
                        <h3 class="stat-number" id="processed-count"><?php echo $all_stats['processed'] ?? 0; ?></h3>
                        <p class="stat-label"><?php _e('Processed Today', 'pixelyoursite'); ?></p>
                    </div>
                    <div class="stat-card stat-failed">
                        <h3 class="stat-number" id="failed-count"><?php echo $all_stats['failed'] ?? 0; ?></h3>
                        <p class="stat-label"><?php _e('Failed Events', 'pixelyoursite'); ?></p>
                    </div>
                    <div class="stat-card stat-total">
                        <h3 class="stat-number" id="total-count"><?php echo $all_stats['total'] ?? 0; ?></h3>
                        <p class="stat-label"><?php _e('Total Events', 'pixelyoursite'); ?></p>
                    </div>
                </div>
                
                <div class="row queue-status-info">
                    <div class="col-md-6">
                        <p class="text-gray mb-12">
                            <strong><?php _e('Last Processed:', 'pixelyoursite'); ?></strong>
                            <span id="last-processed"><?php echo $all_stats['last_processed']; ?></span>
                        </p>
                        <p class="text-gray mb-2">
                            <strong><?php _e('Queue Status:', 'pixelyoursite'); ?></strong>
                            <span id="queue-status" class="queue-status <?php echo $all_stats['queue_status']; ?>">
                                <?php echo $all_stats['queue_status_text']; ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="text-gray mb-12">
                            <strong><?php _e('Processing Rate:', 'pixelyoursite'); ?></strong>
                            <span id="processing-rate"><?php echo $all_stats['processing_rate']; ?></span> <?php _e('events/min (last 1 hour)', 'pixelyoursite'); ?>
                        </p>
                        <p class="text-gray mb-2">
                            <strong><?php _e('Success Rate:', 'pixelyoursite'); ?></strong>
                            <span id="success-rate"><?php echo $all_stats['success_rate']; ?>%</span>
                        </p>
                    </div>
                </div>
                
                <div class="row queue-management-actions">
                    <div class="d-flex gap-3 flex-wrap">
                        <button type="button" id="manual-process-queue" class="btn btn-primary">
                            <i class="fa fa-play"></i> <?php _e('Process Queue Now', 'pixelyoursite'); ?>
                        </button>
                        <button type="button" id="clear-old-records" class="btn btn-warning">
                            <i class="fa fa-trash"></i> <?php _e('Clear Old Records', 'pixelyoursite'); ?>
                        </button>
                        <button type="button" id="reset-failed-events" class="btn btn-secondary">
                            <i class="fa fa-refresh"></i> <?php _e('Reset Failed Events', 'pixelyoursite'); ?>
                        </button>
                    </div>
                    <p class="text-gray mt-3">
                        <?php _e('Use these actions to manually manage your event queue. Process Queue Now will immediately start processing pending events. Clear Old Records will remove processed events older than the retention period.', 'pixelyoursite'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
