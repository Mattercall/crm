<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
class FunctionsMigrate {

    private $pys_version;
    private static $_instance;

    public static function instance() {

        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }

        return self::$_instance;

    }
    public function __construct()
    {
        $this->pys_version = get_option( 'pys_core_version', false );

        add_action('plugins_loaded', array($this,'maybeMigrate'), 1);
    }

    public function maybeMigrate() {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $migrations = [
            '12.3.4.2' => [ $this, 'migrate_12_3_4_2' ],
            '12.2.0' => [ $this, 'migrate_12_2_0' ],
            '11.2.2.1' => [ $this, 'migrate_11_2_2_1' ],
            '11.2.2' => [ $this, 'migrate_11_2_2' ],
            '11.2.0.5' => [ $this, 'migrate_11_2_0_5' ],
            '11.1.0' => [ $this, 'migrate_11_1_0' ],
            '11.0.1' => [ $this, 'migrate_11_0_0' ],
            '10.2.2' => [ $this, 'migrate_10_2_2' ],
            '10.1.3' => [ $this, 'migrate_10_1_3' ],
            '10.1.1' => [ $this, 'migrate_10_1_0' ],
            '9.11.1.7' => [ $this, 'migrate_unify_custom_events' ],
            '9.0.0' => [ $this, 'migrate_9_0_0' ],
            '8.6.8' => [ $this, 'migrate_8_6_7' ],
            '8.3.1' => [ $this, 'migrate_8_3_1' ],
            '8.0.0' => [ $this, 'migrate_8_0_0' ],
        ];

        foreach ($migrations as $version => $migration_function) {
            if (!$this->pys_version || version_compare($this->pys_version, $version, '<')) {
                if ($version === '9.11.1.7' && get_option('pys_custom_event_migrate', false)) {
                    continue;
                }
                if (is_callable($migration_function)) {
                    try {
                        $migration_function();
                        update_option('pys_core_version', PYS_VERSION);
                        update_option('pys_updated_at', time());
                    } catch (\Throwable $e) {
                        error_log(print_r($e, true));
                    }
                }
            }
        }

    }

    protected function migrate_unify_custom_events(){
        foreach (CustomEventFactory::get() as $event) {
            $event->migrateUnifyGA();
        }
        update_option( 'pys_custom_event_migrate', true );
    }

    protected function migrate_12_3_4_2()
    {
        if (PYS()->getOption('logs_enable')) {
            Facebook()->updateOptions([
                'logs_enable' => true,
            ]);
        }
    }
    protected function migrate_12_2_0() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pys_options';

        $this->create_pys_options_table($table_name);

        $names = [
            'pys_core','pys_facebook','pys_ga','pys_google_ads','pys_gtm','pys_gatags',
            'pys_tiktok','pys_head_footer','pys_pinterest','pys_bing','pys_superpack',
            'pys_CF7','pys_ElementorForm','pys_Fluentform','pys_Formidable','pys_forminator',
            'pys_Gravity','pys_NinjaForm','pys_WPForms','pys_WSForm',
        ];

        $rows = $this->get_options_for_migration($names);

        if (empty($rows)) {
            return; // нечего переносить
        }

        $wpdb->query('START TRANSACTION');
        try {
            $inserted_names = $this->migrate_options($wpdb, $table_name, $rows);

            if (empty($inserted_names)) {
                throw new \RuntimeException('No valid options migrated.');
            }

            $count_new = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE option_name IN (" .
                    implode(',', array_fill(0, count($inserted_names), '%s')) . ")",
                    $inserted_names
                )
            );

            if ($count_new !== count($inserted_names)) {
                throw new \RuntimeException('Mismatch after migration.');
            }

            $this->cleanup_old_options($wpdb);

            $wpdb->query('COMMIT');
        } catch (\Throwable $e) {
            $wpdb->query('ROLLBACK');
            error_log('Migration 12.1.4 failed: ' . $e->getMessage());
        }
    }

    private function create_pys_options_table($table_name) {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        option_name VARCHAR(191) NOT NULL,
        option_value LONGTEXT NOT NULL,
        migrated TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY option_name (option_name)
    ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private function get_options_for_migration(array $names) {
        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($names), '%s'));
        $like = '%' . $wpdb->esc_like('"slug":"pixelyoursite') . '%';

        $sql = "SELECT option_id, option_name, option_value,
                CASE WHEN option_name IN ($placeholders) THEN 'safe' ELSE 'like' END as source_type
            FROM {$wpdb->options}
            WHERE option_value LIKE %s
               OR option_name IN ($placeholders)";

        $params = array_merge($names, [$like], $names);

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    private function migrate_options($wpdb, $table_name, array $rows): array {
        $inserted_names = [];
        foreach ($rows as $row) {
            if ($row['source_type'] === 'like' && strpos($row['option_name'], 'pys_api_request_') !== 0) {
                continue;
            }
            $result = $wpdb->replace(
                $table_name,
                [
                    'option_name' => $row['option_name'],
                    'option_value' => $row['option_value'],
                ],
                ['%s','%s']
            );
            if ($result === false) {
                throw new \RuntimeException('Failed to insert ' . $row['option_name']);
            }
            $inserted_names[] = $row['option_name'];
        }
        return $inserted_names;
    }

    private function cleanup_old_options($wpdb) {
        $options = $wpdb->get_results("
        SELECT option_id, option_value FROM {$wpdb->options}
        WHERE (option_value LIKE '%www.pixelyoursite.com%' 
            OR option_value LIKE '%License key is not valid%')
          AND option_name NOT LIKE '%_site_transient%'
    ");

        $delete_ids = [];
        foreach ($options as $opt) {
            $value = maybe_unserialize($opt->option_value);
            $value = isset($value['value']) ? json_decode($value['value']) : '';

            if (isset($value->msg) && strpos((string)$value->msg, 'License key is not valid')) {
                $delete_ids[] = $opt->option_id;
            } elseif ($value && isset($value->last_updated)
                && strtotime($value->last_updated) < strtotime('-1 month')) {
                $delete_ids[] = $opt->option_id;
            } elseif ($value && isset($value->slug) && strpos($value->slug, 'pixelyoursite')) {
                $delete_ids[] = $opt->option_id;
            }
        }

        if ($delete_ids) {
            $wpdb->query(
                "DELETE FROM {$wpdb->options} WHERE option_id IN (" . implode(',', array_map('intval', $delete_ids)) . ")"
            );
        }
    }
    protected function migrate_11_2_2_1()
    {
        if (!in_array('category_name', Facebook()->getOption('do_not_track_medical_param'))) {
            Facebook()->updateOptions([
                'do_not_track_medical_param' => array_merge(Facebook()->getOption('do_not_track_medical_param'), ['category_name']),
            ]);
        }
    }
    protected function migrate_11_2_2()
    {
        if (PYS()->getOption('woo_brand_taxonomy') == 'empty') {
            PYS()->updateOptions([
                'woo_brand_taxonomy' => 'product_brand',
            ]);
        }
    }
    protected function migrate_11_2_0_5()
    {
        foreach (CustomEventFactory::get() as $event) {
            $conditions = $event->getConditions();
            $eventData = $event->getAllData();
            if($conditions){
                $existing_values = array_map(function($condition) {
                    return $condition->getParam('condition_value');
                }, $conditions);
            }


            foreach ($event->getTriggers() as $trigger) {

                if ($trigger->getURLFilters()) {
                    foreach ($trigger->getURLFilters() as $filter) {
                        if (is_array($filter) && !empty($filter['value']) && !in_array($filter['value'], $existing_values)) {
                            $eventData[ 'conditions_enabled' ] = true;
                            $eventData[ 'conditions_logic' ] = 'OR';
                            $filter_condition = new ConditionalEvent('url_filters');
                            $filter_condition->updateParam('condition_rule', 'match');
                            $filter_condition->updateParam('condition_value', $filter['value']);
                            $filter_condition->updateParam('index', count($conditions));
                            $conditions[] = $filter_condition;
                            $existing_values[] = $filter['value'];
                        }
                    }
                }
            }

            if (!empty($conditions)) {

                update_post_meta($event->getPostId(), '_pys_event_data', $eventData);
                update_post_meta($event->getPostId(), '_pys_event_conditions', addslashes(serialize($conditions)));
            }
        }

    }
    protected function migrate_11_1_0() {

        $facebook_main_pixel = Facebook()->getOption( 'main_pixel_enabled' );
        $facebook_enabled = Facebook()->getOption( 'enabled' );
        Facebook()->updateOptions( array( 'main_pixel_enabled' => $facebook_enabled && $facebook_main_pixel ) );

        $ga_main_pixel = GA()->getOption( 'main_pixel_enabled' );
        $ga_enabled = GA()->getOption( 'enabled' );
        GA()->updateOptions( array( 'main_pixel_enabled' => $ga_enabled && $ga_main_pixel ) );

        $ads_main_pixel = Ads()->getOption( 'main_pixel_enabled' );
        $ads_enabled = Ads()->getOption( 'enabled' );
        Ads()->updateOptions( array( 'main_pixel_enabled' => $ads_enabled && $ads_main_pixel ) );

        $tiktok_main_pixel = Tiktok()->getOption( 'main_pixel_enabled' );
        $tiktok_enabled = Tiktok()->getOption( 'enabled' );
        Tiktok()->updateOptions( array( 'main_pixel_enabled' => $tiktok_enabled && $tiktok_main_pixel ) );

        $gtm_main_pixel = GTM()->getOption( 'main_pixel_enabled' );
        $gtm_enabled = GTM()->getOption( 'enabled' );
        GTM()->updateOptions( array( 'main_pixel_enabled' => $gtm_enabled && $gtm_main_pixel ) );

    }

    protected function migrate_11_0_0()
    {
        if(GTM()->getOption('gtm_dataLayer_name') === 'dataLayerPYS'){
            GTM()->updateOptions([
                "gtm_dataLayer_name" => 'dataLayer',
            ]);
        }
    }
    protected function migrate_10_2_2() {
        if(!PYS()->getOption('block_robot_enabled')){
            $globalOptions = [
                "block_robot_enabled" => true,
            ];
            PYS()->updateOptions($globalOptions);
        }
    }
    protected function migrate_10_1_3() {
        $ga_tags_woo_options = [];
        $ga_tags_edd_options = [];
        if(GA()->enabled() && Ads()->enabled()){
            $ga_tags_woo_options = [
                'woo_variable_as_simple' => GATags()->getOption('woo_variable_as_simple') ?? Ads()->getOption('woo_variable_as_simple') ?? GA()->getOption('woo_variable_as_simple'),
                'woo_variable_data_select_product' => GATags()->getOption('woo_variable_data_select_product') ?? Ads()->getOption('woo_variable_data_select_product') ?? GA()->getOption('woo_variable_data_select_product'),
                'woo_variations_use_parent_name' => GATags()->getOption('woo_variations_use_parent_name') ?? GA()->getOption('woo_variations_use_parent_name'),
                'woo_content_id' => GATags()->getOption('woo_content_id') ?? Ads()->getOption('woo_content_id') ?? GA()->getOption('woo_content_id'),
                'woo_content_id_prefix' => GATags()->getOption('woo_content_id_prefix') ?? Ads()->getOption('woo_item_id_prefix') ?? GA()->getOption('woo_content_id_prefix'),
                'woo_content_id_suffix' => GATags()->getOption('woo_content_id_suffix') ?? Ads()->getOption('woo_item_id_suffix') ?? GA()->getOption('woo_content_id_suffix'),
            ];

            $ga_tags_edd_options = [
                'edd_content_id' => GATags()->getOption('edd_content_id') ?? Ads()->getOption('edd_content_id') ?? GA()->getOption('edd_content_id'),
                'edd_content_id_prefix' => GATags()->getOption('edd_content_id_prefix') ?? Ads()->getOption('edd_content_id_prefix') ?? GA()->getOption('edd_content_id_prefix'),
                'edd_content_id_suffix' => GATags()->getOption('edd_content_id_suffix') ?? Ads()->getOption('edd_content_id_suffix') ?? GA()->getOption('edd_content_id_suffix'),
            ];
        }elseif(Ads()->enabled()){
            $ga_tags_woo_options = [
                'woo_variable_as_simple' => GATags()->getOption('woo_variable_as_simple') ?? Ads()->getOption('woo_variable_as_simple'),
                'woo_variable_data_select_product' => GATags()->getOption('woo_variable_data_select_product') ?? Ads()->getOption('woo_variable_data_select_product'),
                'woo_content_id' => GATags()->getOption('woo_content_id') ?? Ads()->getOption('woo_content_id'),
                'woo_content_id_prefix' => GATags()->getOption('woo_content_id_prefix') ?? Ads()->getOption('woo_item_id_prefix'),
                'woo_content_id_suffix' => GATags()->getOption('woo_content_id_suffix') ?? Ads()->getOption('woo_item_id_suffix'),
            ];

            $ga_tags_edd_options = [
                'edd_content_id' => GATags()->getOption('edd_content_id') ?? Ads()->getOption('edd_content_id'),
                'edd_content_id_prefix' => GATags()->getOption('edd_content_id_prefix') ?? Ads()->getOption('edd_content_id_prefix'),
                'edd_content_id_suffix' => GATags()->getOption('edd_content_id_suffix') ?? Ads()->getOption('edd_content_id_suffix'),
            ];
        }elseif(GA()->enabled()){
            $ga_tags_woo_options = [
                'woo_variable_as_simple' => GATags()->getOption('woo_variable_as_simple') ?? GA()->getOption('woo_variable_as_simple'),
                'woo_variable_data_select_product' => GATags()->getOption('woo_variable_data_select_product') ?? GA()->getOption('woo_variable_data_select_product'),
                'woo_variations_use_parent_name' => GATags()->getOption('woo_variations_use_parent_name') ?? GA()->getOption('woo_variations_use_parent_name'),
                'woo_content_id' => GATags()->getOption('woo_content_id') ?? GA()->getOption('woo_content_id'),
                'woo_content_id_prefix' => GATags()->getOption('woo_content_id_prefix') ?? GA()->getOption('woo_content_id_prefix'),
                'woo_content_id_suffix' => GATags()->getOption('woo_content_id_suffix') ?? GA()->getOption('woo_content_id_suffix'),
            ];

            $ga_tags_edd_options = [
                'edd_content_id' => GATags()->getOption('edd_content_id') ?? GA()->getOption('edd_content_id'),
                'edd_content_id_prefix' => GATags()->getOption('edd_content_id_prefix') ?? GA()->getOption('edd_content_id_prefix'),
                'edd_content_id_suffix' => GATags()->getOption('edd_content_id_suffix') ?? GA()->getOption('edd_content_id_suffix'),
            ];
        }
        else{
            return false;
        }
        GATags()->updateOptions($ga_tags_woo_options);
        GATags()->updateOptions($ga_tags_edd_options);
    }
    protected function migrate_10_1_0() {
        $globalOptions = [
            'woo_purchase_conversion_track' => 'current_event',
            'woo_initiate_checkout_conversion_track' => 'current_event',
            'woo_add_to_cart_conversion_track' => 'current_event',
            'woo_view_content_conversion_track' => 'current_event',
            'woo_view_category_conversion_track' => 'current_event',
            'edd_purchase_conversion_track' => 'current_event',
            'edd_initiate_checkout_conversion_track' => 'current_event',
            'edd_add_to_cart_conversion_track' => 'current_event',
            'edd_view_content_conversion_track' => 'current_event',
            'edd_view_category_conversion_track' => 'current_event',
        ];
        Ads()->updateOptions($globalOptions);
    }
    protected function migrate_9_0_0() {
        $globalOptions = [
            "automatic_events_enabled" => PYS()->getOption("signal_events_enabled") || PYS()->getOption("automatic_events_enabled"),
            "automatic_event_internal_link_enabled" => PYS()->getOption("signal_click_enabled"),
            "automatic_event_outbound_link_enabled" => PYS()->getOption("signal_click_enabled"),
            "automatic_event_video_enabled" => PYS()->getOption("signal_watch_video_enabled"),
            "automatic_event_tel_link_enabled" => PYS()->getOption("signal_tel_enabled"),
            "automatic_event_email_link_enabled" => PYS()->getOption("signal_email_enabled"),
            "automatic_event_form_enabled" => PYS()->getOption("signal_form_enabled"),
            "automatic_event_download_enabled" => PYS()->getOption("signal_download_enabled"),
            "automatic_event_comment_enabled" => PYS()->getOption("signal_comment_enabled"),
            "automatic_event_scroll_enabled" => PYS()->getOption("signal_page_scroll_enabled"),
            "automatic_event_time_on_page_enabled" => PYS()->getOption("signal_time_on_page_enabled"),
            "automatic_event_scroll_value" => PYS()->getOption("signal_page_scroll_value"),
            "automatic_event_time_on_page_value" => PYS()->getOption("signal_time_on_page_value"),
            "automatic_event_adsense_enabled" => PYS()->getOption("signal_adsense_enabled"),
            "automatic_event_download_extensions" => PYS()->getOption("download_event_extensions"),
        ];
        PYS()->updateOptions($globalOptions);
    }

    protected function migrate_8_6_7() {
        if(PYS()->getOption( 'woo_advance_purchase_enabled' ,true)) {
            $globalOptions = array(
                "woo_advance_purchase_fb_enabled"   => true,
                'woo_advance_purchase_ga_enabled'   => true,
            );
        } else {
            $globalOptions = array(
                "woo_advance_purchase_fb_enabled"   => false,
                'woo_advance_purchase_ga_enabled'   => false,
            );
        }



        PYS()->updateOptions($globalOptions);
    }

    protected function migrate_8_3_1() {
        $globalOptions = array(
            "enable_page_title_param"          => !PYS()->getOption( 'enable_remove_page_title_param' ,false),
            'enable_content_name_param'        => !PYS()->getOption( 'enable_remove_content_name_param' ,false),
        );

        PYS()->updateOptions($globalOptions);
    }

    protected function migrate_8_0_0() {

        $globalOptions = array(
            "signal_click_enabled"          => isEventEnabled( 'click_event_enabled' ),
            "signal_watch_video_enabled"    => isEventEnabled( 'watchvideo_event_enabled' ),
            "signal_adsense_enabled"        => isEventEnabled( 'adsense_enabled' ),
            "signal_form_enabled"           => isEventEnabled( 'form_event_enabled' ),
            "signal_user_signup_enabled"    => isEventEnabled( 'complete_registration_event_enabled' ),
            "signal_download_enabled"       => isEventEnabled( 'download_event_enabled' ),
            "signal_comment_enabled"        => isEventEnabled( 'comment_event_enabled' )
        );

        PYS()->updateOptions($globalOptions);

        $gaOptions = array(
            'woo_view_item_list_enabled' => GA()->getOption('woo_view_category_enabled')
        );
        GA()->updateOptions($gaOptions);
    }
}

function FunctionMigrate() {
    return FunctionsMigrate::instance();
}

FunctionMigrate();